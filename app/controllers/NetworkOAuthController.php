<?php
class NetworkOAuthController extends Controller {
    public function __construct() { parent::__construct(); $this->requireAuth(); $this->requirePermission('publishing.manage'); }
    public function connect($connectionId): void {
        try {
            $connection=$this->connection((int)$connectionId);
            $provider=(string)$connection['provider'];
            $redirect=NetworkOAuthService::callbackUrl($provider);
            NetworkOAuthService::encrypt('encryption-readiness-check');
            $state=bin2hex(random_bytes(32));
            $url=(new NetworkOAuthService())->authorize($provider,$state,$redirect);
            foreach ((array)($_SESSION['network_oauth']??[]) as $key=>$item) if ((int)($item['created']??0)<time()-900) unset($_SESSION['network_oauth'][$key]);
            if (count($_SESSION['network_oauth']??[])>=10) throw new RuntimeException('Trop de connexions en cours. Réessayez dans 15 minutes.');
            $_SESSION['network_oauth'][$state]=['provider'=>$provider,'connection_id'=>(int)$connection['id'],'client_id'=>(int)$connection['client_id'],'tenant_id'=>TenantGuard::tenantId(),'user_id'=>(int)$this->currentUser()['id'],'created'=>time(),'redirect'=>$redirect];
            header('Location: '.$url); exit;
        } catch (Throwable $e) { $this->failure($e); }
        $this->redirect('/social-connection');
    }
    public function callback($provider): void {
        header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
        $state=is_string($_GET['state']??null)?$_GET['state']:'';
        $saved=$_SESSION['network_oauth'][$state]??null;
        unset($_SESSION['network_oauth'][$state]);
        try {
            if (!NetworkOAuthState::valid($saved,(string)$provider,TenantGuard::tenantId(),(int)$this->currentUser()['id'],time())) throw new RuntimeException('Session OAuth invalide ou expirée. Recommencez la connexion.');
            if (!empty($_GET['error'])) throw new RuntimeException('Autorisation annulée ou refusée par le fournisseur.');
            $code=is_string($_GET['code']??null)?trim($_GET['code']):'';
            if ($code==='') throw new RuntimeException('Code OAuth manquant.');
            $connection=$this->connection((int)$saved['connection_id']);
            if ($connection['provider']!==$provider || (int)$connection['client_id']!==(int)$saved['client_id']) throw new RuntimeException('Le rattachement de la connexion a changé. Recommencez.');
            $service=new NetworkOAuthService();
            $tokens=$service->exchange((string)$provider,$code,$saved['redirect']);
            $account=$service->account((string)$provider,$tokens['access_token']);
            $this->save($connection,$account,$tokens);
            $scopes=isset($tokens['scope'])?preg_split('/[ ,]+/',trim((string)$tokens['scope']),-1,PREG_SPLIT_NO_EMPTY):NetworkOAuthService::scopes((string)$provider);
            $capabilities=[];
            if($provider==='linkedin'&&in_array('w_member_social',$scopes,true))$capabilities[]='publication texte';
            if($provider==='youtube')$capabilities[]='publication vidéo et collecte';
            if($provider==='tiktok'&&in_array('video.list',$scopes,true))$capabilities[]='collecte';
            $this->flash('success','Compte '.$account['name'].' connecté'.($capabilities?' : '.implode(' et ',$capabilities).' activée(s).':'. Les produits complémentaires restent soumis à l’approbation du réseau.'));
        } catch (Throwable $e) { $this->failure($e); }
        $this->redirect('/social-connection');
    }
    public function renew($connectionId): void {
        if (!$this->isPost()) { http_response_code(405); header('Allow: POST'); return; }
        try {
            $connection=$this->connection((int)$connectionId);
            $service=new NetworkOAuthService();
            $refresh=CryptoService::decrypt((string)($connection['refresh_token_encrypted']??''));
            $tokens=$service->refresh($connection['provider'],$refresh);
            $account=$service->account($connection['provider'],$tokens['access_token']);
            $this->save($connection,$account,$tokens);
            $this->flash('success','Accès renouvelé et compte vérifié.');
        } catch (Throwable $e) { $this->failure($e); }
        $this->redirect('/social-connection');
    }
    private function connection(int $id): array {
        $stmt=Database::getConnection()->prepare('SELECT * FROM social_connections WHERE id=:id AND tenant_id=:tenant');
        $stmt->execute(['id'=>$id,'tenant'=>TenantGuard::tenantId()]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Connexion inaccessible.');
        TenantGuard::assertClient((int)$row['client_id']);
        return $row;
    }
    private function save(array $connection,array $account,array $tokens): void {
        $access=NetworkOAuthService::encrypt($tokens['access_token']);
        $refresh=!empty($tokens['refresh_token'])?NetworkOAuthService::encrypt((string)$tokens['refresh_token']):null;
        $expires=isset($tokens['expires_in']) && (int)$tokens['expires_in']>0?date('Y-m-d H:i:s',time()+(int)$tokens['expires_in']):null;
        $scopes=isset($tokens['scope'])?preg_split('/[ ,]+/',trim((string)$tokens['scope']),-1,PREG_SPLIT_NO_EMPTY):NetworkOAuthService::scopes($connection['provider']);
        $pdo=Database::getConnection();$pdo->beginTransaction();
        try {
            $lock=$pdo->prepare('SELECT * FROM social_connections WHERE id=:id AND tenant_id=:tenant FOR UPDATE');
            $lock->execute(['id'=>$connection['id'],'tenant'=>TenantGuard::tenantId()]);$current=$lock->fetch(PDO::FETCH_ASSOC);
            if (!$current || (int)$current['client_id']!==(int)$connection['client_id'] || $current['provider']!==$connection['provider']) throw new RuntimeException('Connexion modifiée pendant l autorisation.');
            if (!empty($current['external_account_id']) && $current['external_account_id']!==$account['id']) throw new RuntimeException('Ce compte ne correspond pas à la destination existante. Créez une nouvelle connexion.');
            $duplicate=$pdo->prepare('SELECT id FROM social_connections WHERE tenant_id=:tenant AND provider=:provider AND external_account_id=:external AND id<>:id');
            $duplicate->execute(['tenant'=>TenantGuard::tenantId(),'provider'=>$connection['provider'],'external'=>$account['id'],'id'=>$connection['id']]);
            if ($duplicate->fetchColumn()) throw new RuntimeException('Compte déjà rattaché. Actualisez les droits de sa connexion existante.');
            $stmt=$pdo->prepare("UPDATE social_connections SET external_account_id=:external,account_label=:label,account_type=:type,access_token_encrypted=:access,refresh_token_encrypted=:refresh,token_expires_at=:expires,scopes_json=:scopes,metadata_json=:metadata,status='Connected',last_validated_at=NOW(),connected_by=:user WHERE id=:id AND tenant_id=:tenant");
            $publishing=($connection['provider']==='linkedin'&&in_array('w_member_social',$scopes,true))||($connection['provider']==='youtube'&&in_array('https://www.googleapis.com/auth/youtube.upload',$scopes,true));$analytics=$connection['provider']==='youtube'||($connection['provider']==='tiktok'&&in_array('video.list',$scopes,true))||($connection['provider']==='linkedin'&&in_array('r_member_social',$scopes,true));
            $stmt->execute(['external'=>$account['id'],'label'=>$account['name'],'type'=>$account['type'],'access'=>$access,'refresh'=>$refresh??($current['refresh_token_encrypted']??null),'expires'=>$expires,'scopes'=>json_encode($scopes),'metadata'=>json_encode(['oauth_connection_only'=>false,'analytics_enabled'=>$analytics,'publishing_enabled'=>$publishing]),'user'=>(int)$this->currentUser()['id'],'id'=>$connection['id'],'tenant'=>TenantGuard::tenantId()]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    private function failure(Throwable $error): void {
        $message=$error instanceof PDOException?'Enregistrement impossible. Vérifiez le schéma de la base et réessayez.':($error instanceof RuntimeException?$error->getMessage():'Connexion impossible. Vérifiez la configuration serveur.');
        $this->flash('error',$message);
    }
}
