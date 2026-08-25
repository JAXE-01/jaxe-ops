<?php
class SocialOAuthController extends Controller {
    public function __construct(){parent::__construct();$this->requireAuth();$this->requirePermission('publishing.manage');}

    public function connect($connectionId){
        $clientId=trim((string)config_env_value('META_CLIENT_ID',''));
        $secret=trim((string)config_env_value('META_CLIENT_SECRET',''));
        if($clientId===''||$secret===''){$this->flash('error','Ajoutez META_CLIENT_ID et META_CLIENT_SECRET dans le fichier .env.');$this->redirect('/social-publishing');}
        $connection=$this->connection((int)$connectionId);
        if(!in_array($connection['provider'],['facebook','instagram'],true)){$this->flash('error','Ce connecteur OAuth sera disponible dans un prochain adaptateur.');$this->redirect('/social-publishing');}
        $state=bin2hex(random_bytes(24));
        $_SESSION['social_oauth'][$state]=['connection_id'=>(int)$connection['id'],'tenant_id'=>TenantGuard::tenantId(),'created_at'=>time()];
        $redirect=route_url('/social-oauth/callback');
        // Les permissions de publication ne deviennent demandables qu apres activation chez Meta.
        $scopes=trim((string)config_env_value('META_OAUTH_SCOPES','pages_show_list,pages_read_engagement,instagram_basic'));
        $params=['client_id'=>$clientId,'redirect_uri'=>$this->absoluteUrl($redirect),'state'=>$state,'response_type'=>'code','scope'=>$scopes];
        header('Location: https://www.facebook.com/'.rawurlencode($this->version()).'/dialog/oauth?'.http_build_query($params));exit;
    }

    public function callback(){
        $state=(string)($_GET['state']??'');$saved=$_SESSION['social_oauth'][$state]??null;unset($_SESSION['social_oauth'][$state]);
        if(!$saved||!hash_equals((string)$saved['tenant_id'],(string)TenantGuard::tenantId())||time()-(int)$saved['created_at']>900){$this->flash('error','Session OAuth invalide ou expiree.');$this->redirect('/social-publishing');}
        if(!empty($_GET['error'])){$this->flash('error','Connexion Meta annulee ou refusee.');$this->redirect('/social-publishing');}
        $code=trim((string)($_GET['code']??''));if($code===''){$this->flash('error','Meta n a retourne aucun code OAuth.');$this->redirect('/social-publishing');}
        try{
            $token=$this->request('https://graph.facebook.com/'.$this->version().'/oauth/access_token',['client_id'=>config_env_value('META_CLIENT_ID',''),'client_secret'=>config_env_value('META_CLIENT_SECRET',''),'redirect_uri'=>$this->absoluteUrl(route_url('/social-oauth/callback')),'code'=>$code]);
            if(empty($token['access_token']))throw new RuntimeException('Jeton Meta manquant.');
            $pages=$this->request('https://graph.facebook.com/'.$this->version().'/me/accounts',['fields'=>'id,name,access_token,instagram_business_account','access_token'=>$token['access_token']]);
            $connection=$this->connection((int)$saved['connection_id']);$count=0;
            foreach((array)($pages['data']??[]) as $page){$this->savePage($connection,$page);$count++;}
            if(!$count)throw new RuntimeException('Aucune Page Facebook administrable retournee par Meta.');
            $this->flash('success',$count.' destination(s) Meta connectee(s).');
        }catch(Throwable $e){$this->markError((int)$saved['connection_id'],$e->getMessage());$this->flash('error','Connexion Meta impossible : '.$e->getMessage());}
        $this->redirect('/social-publishing');
    }

    private function connection(int $id): array {$stmt=Database::getConnection()->prepare('SELECT * FROM social_connections WHERE id=:id AND tenant_id=:tenant LIMIT 1');$stmt->execute(['id'=>$id,'tenant'=>TenantGuard::tenantId()]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Connexion sociale inaccessible.');return$row;}
    private function savePage(array $base,array $page): void {$pdo=Database::getConnection();$token=CryptoService::encrypt((string)($page['access_token']??''));$meta=json_encode(['instagram_business_account'=>$page['instagram_business_account']['id']??null],JSON_UNESCAPED_SLASHES);$stmt=$pdo->prepare("INSERT INTO social_connections(tenant_id,organization_id,client_id,provider,account_label,external_account_id,account_type,access_token_encrypted,status,scopes_json,metadata_json,connected_by) VALUES(:tenant,:org,:client,'facebook',:label,:external,'Page',:token,'Connected',:scopes,:meta,:user) ON DUPLICATE KEY UPDATE account_label=VALUES(account_label),access_token_encrypted=VALUES(access_token_encrypted),status='Connected',scopes_json=VALUES(scopes_json),metadata_json=VALUES(metadata_json),connected_by=VALUES(connected_by)");$stmt->execute(['tenant'=>TenantGuard::tenantId(),'org'=>$base['organization_id'],'client'=>$base['client_id'],'label'=>$page['name']??$base['account_label'],'external'=>$page['id'],'token'=>$token,'scopes'=>json_encode(array_values(array_filter(array_map('trim',explode(',',(string)config_env_value('META_OAUTH_SCOPES','pages_show_list,pages_read_engagement,instagram_basic')))))),'meta'=>$meta,'user'=>(int)$this->currentUser()['id']]);if((string)$base['external_account_id']===''||$base['status']!=='Connected')$pdo->prepare("UPDATE social_connections SET status='Revoked' WHERE id=:id")->execute(['id'=>$base['id']]);}
    private function markError(int$id,string$message): void {$stmt=Database::getConnection()->prepare("UPDATE social_connections SET status='Error',metadata_json=:meta WHERE id=:id AND tenant_id=:tenant");$stmt->execute(['meta'=>json_encode(['last_error'=>mb_strimwidth($message,0,500)]),'id'=>$id,'tenant'=>TenantGuard::tenantId()]);}
    private function request(string$url,array$params): array {$ch=curl_init($url.'?'.http_build_query($params));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Accept: application/json']]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);$data=json_decode((string)$body,true);if($body===false||$status<200||$status>=300)throw new RuntimeException((string)($data['error']['message']??$error?:('HTTP '.$status)));return is_array($data)?$data:[];}
    private function version(): string {$v=trim((string)config_env_value('META_GRAPH_VERSION','v23.0'));return preg_match('/^v\d+\.\d+$/',$v)?$v:'v23.0';}
    private function absoluteUrl(string$path): string {$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'localhost');return$scheme.'://'.$host.$path;}
}
