<?php
/** OAuth account linking only. Analytics collectors and publishers are separate. */
class NetworkOAuthService {
    private $transport;
    public function __construct(?callable $transport = null) { $this->transport = $transport; }
    public static function definition(string $provider): array {
        $definitions = [
            'tiktok' => ['key'=>'TIKTOK_CLIENT_KEY','secret'=>'TIKTOK_CLIENT_SECRET','authorize'=>'https://www.tiktok.com/v2/auth/authorize/','token'=>'https://open.tiktokapis.com/v2/oauth/token/','scopes'=>['user.info.basic']],
            'linkedin' => ['key'=>'LINKEDIN_CLIENT_ID','secret'=>'LINKEDIN_CLIENT_SECRET','authorize'=>'https://www.linkedin.com/oauth/v2/authorization','token'=>'https://www.linkedin.com/oauth/v2/accessToken','scopes'=>['openid','profile','w_member_social']],
            'youtube' => ['key'=>'YOUTUBE_CLIENT_ID','secret'=>'YOUTUBE_CLIENT_SECRET','authorize'=>'https://accounts.google.com/o/oauth2/v2/auth','token'=>'https://oauth2.googleapis.com/token','scopes'=>['https://www.googleapis.com/auth/youtube.readonly','https://www.googleapis.com/auth/yt-analytics.readonly','https://www.googleapis.com/auth/youtube.upload']],
        ];
        if (!isset($definitions[$provider])) throw new RuntimeException('Réseau OAuth non pris en charge.');
        return $definitions[$provider];
    }
    public static function scopes(string $provider): array {
        $defaults=self::definition($provider)['scopes'];
        if($provider==='youtube')return$defaults;
        $envKey=$provider==='tiktok'?'TIKTOK_OAUTH_SCOPES':'LINKEDIN_OAUTH_SCOPES';
        $raw=trim((string)config_env_value($envKey,implode(',',$defaults)));
        $requested=array_values(array_unique(array_filter(preg_split('/[\s,]+/',$raw,-1,PREG_SPLIT_NO_EMPTY))));
        $allowed=$provider==='tiktok'?['user.info.basic','user.info.profile','user.info.stats','video.list']:['openid','profile','email','w_member_social','r_member_social'];
        $required=$provider==='tiktok'?['user.info.basic']:['openid','profile'];
        if(!$requested||array_diff($requested,$allowed)||array_diff($required,$requested))throw new RuntimeException($envKey.' contient des permissions invalides ou omet les permissions de base.');
        return$requested;
    }
    public static function callbackUrl(string $provider): string {
        self::definition($provider);
        $url = trim((string) config_env_value(strtoupper($provider).'_REDIRECT_URI', 'https://strax.jaxecommunication.com/index.php/network-oauth/callback/'.$provider));
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme']??'') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) throw new RuntimeException('URI OAuth HTTPS invalide.');
        return $url;
    }
    private function credentials(string $provider): array {
        $d = self::definition($provider);
        $id = trim((string) config_env_value($d['key'], ''));
        $secret = trim((string) config_env_value($d['secret'], ''));
        if ($id === '' || $secret === '') throw new RuntimeException('Configurer '.$d['key'].' et '.$d['secret'].' sur le serveur.');
        return [$id, $secret];
    }
    public function authorize(string $provider, string $state, string $redirect): string {
        $d = self::definition($provider); [$id] = $this->credentials($provider);
        $params = [$provider === 'tiktok' ? 'client_key' : 'client_id' => $id, 'redirect_uri'=>$redirect, 'response_type'=>'code', 'state'=>$state, 'scope'=>implode($provider==='tiktok'?',':' ', self::scopes($provider))];
        if ($provider === 'youtube') $params += ['access_type'=>'offline','prompt'=>'consent'];
        return $d['authorize'].'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
    public function exchange(string $provider, string $code, string $redirect): array {
        [$id,$secret] = $this->credentials($provider);
        $body = [$provider==='tiktok'?'client_key':'client_id'=>$id,'client_secret'=>$secret,'grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$redirect];
        return $this->validateToken($this->request('POST', self::definition($provider)['token'], $body), $provider);
    }
    public function refresh(string $provider, string $refreshToken): array {
        if ($refreshToken === '') throw new RuntimeException('Reconnectez le compte : aucun jeton de renouvellement.');
        [$id,$secret] = $this->credentials($provider);
        return $this->validateToken($this->request('POST', self::definition($provider)['token'], [$provider==='tiktok'?'client_key':'client_id'=>$id,'client_secret'=>$secret,'grant_type'=>'refresh_token','refresh_token'=>$refreshToken]), $provider);
    }
    private function validateToken(array $data, string $provider): array {
        if (!is_string($data['access_token']??null) || $data['access_token']==='') throw new RuntimeException('Le fournisseur ne retourne aucun jeton utilisable.');
        if (isset($data['token_type']) && strcasecmp((string)$data['token_type'],'Bearer')!==0) throw new RuntimeException('Type de jeton non pris en charge.');
        if (isset($data['scope'])) {
            $scopes = preg_split('/[ ,]+/', trim((string)$data['scope']), -1, PREG_SPLIT_NO_EMPTY);
            if (array_diff(self::scopes($provider), $scopes)) throw new RuntimeException('Permissions requises refusées. Reconnectez et autorisez les accès demandés.');
        }
        return $data;
    }
    public function account(string $provider, string $accessToken): array {
        self::definition($provider);
        if ($provider === 'tiktok') {
            $data=$this->request('GET','https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name',[],$accessToken);
            $user=$data['data']['user']??[];
            $account=['id'=>$user['open_id']??'','name'=>$user['display_name']??'TikTok','type'=>'Profile'];
        } elseif ($provider === 'linkedin') {
            // Use authenticated userinfo, never trust an unverified ID-token payload.
            $user=$this->request('GET','https://api.linkedin.com/v2/userinfo',[],$accessToken);
            $account=['id'=>$user['sub']??'','name'=>$user['name']??'LinkedIn','type'=>'Profile'];
        } else {
            $data=$this->request('GET','https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true&maxResults=50',[],$accessToken);
            $items=$data['items']??[];
            if (count($items)!==1 || !empty($data['nextPageToken'])) throw new RuntimeException('Sélectionnez une seule chaîne YouTube lors du consentement et reconnectez-la.');
            $account=['id'=>$items[0]['id']??'','name'=>$items[0]['snippet']['title']??'YouTube','type'=>'Channel'];
        }
        if (!is_string($account['id']) || $account['id']==='' || strlen($account['id'])>190) throw new RuntimeException('Identifiant de compte manquant ou invalide.');
        $account['name']=mb_substr((string)$account['name'],0,190);
        return $account;
    }
    private function request(string $method,string $url,array $body=[],string $token=''): array {
        if ($this->transport) return ($this->transport)($method,$url,$body,$token);
        $headers=['Accept: application/json'];
        if ($token!=='') $headers[]='Authorization: Bearer '.$token;
        $ch=curl_init($url);
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS];
        if ($method==='POST') { $headers[]='Content-Type: application/x-www-form-urlencoded'; $options[CURLOPT_POST]=true; $options[CURLOPT_POSTFIELDS]=http_build_query($body); }
        $options[CURLOPT_HTTPHEADER]=$headers; curl_setopt_array($ch,$options);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode((string)$raw,true);
        // Never echo remote error bodies: they may contain codes or credentials.
        if ($raw===false || $status<200 || $status>=300 || !is_array($data)) throw new RuntimeException('Service OAuth indisponible ou accès refusé (HTTP '.$status.'). Vérifiez les produits, les permissions et l URI de redirection.');
        if (!empty($data['error']) && (!is_array($data['error']) || ($data['error']['code']??'ok')!=='ok')) throw new RuntimeException('Le fournisseur a refusé la requête OAuth. Vérifiez les autorisations.');
        return $data;
    }
    public static function encrypt(string $token): string {
        if ($token==='') return '';
        $encrypted=CryptoService::encrypt($token);
        if ($encrypted===$token || !str_starts_with($encrypted,'ENCv1:')) throw new RuntimeException('Configurez APP_ENCRYPTION_KEY avant de connecter les comptes.');
        return $encrypted;
    }
}
