<?php
class SocialPublisherService {
    private PDO $db;
    public function __construct(){ $this->db=Database::getConnection(); }

    public function processDue(int $limit=20,?int $tenantId=null): array {
        $limit=max(1,min(100,$limit));$processed=0;$published=0;$failed=0;
        for($i=0;$i<$limit;$i++){
            $target=$this->claimNext($tenantId);if(!$target)break;$processed++;
            try{$result=$this->publish($target);$this->complete($target,$result);$published++;}
            catch(Throwable$e){$this->fail($target,$e->getMessage());$failed++;}
        }
        return compact('processed','published','failed');
    }

    private function claimNext(?int$tenantId): ?array {
        $this->db->beginTransaction();
        try{
            $sql="SELECT t.*,p.tenant_id,p.master_title,p.master_caption,p.media_url,p.scheduled_at,c.external_account_id,c.access_token_encrypted,c.status connection_status FROM social_publication_targets t JOIN social_publications p ON p.id=t.publication_id JOIN social_connections c ON c.id=t.connection_id WHERE t.status IN ('Queued','Retrying') AND t.next_attempt_at<=NOW() AND p.approval_status='Approved'";
            $params=[];if($tenantId!==null){$sql.=' AND p.tenant_id=:tenant';$params['tenant']=$tenantId;}$sql.=' ORDER BY t.next_attempt_at,t.id LIMIT 1 FOR UPDATE';
            $stmt=$this->db->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row){$this->db->commit();return null;}
            $update=$this->db->prepare("UPDATE social_publication_targets SET status='Processing',attempts=attempts+1,updated_at=NOW() WHERE id=:id AND status IN ('Queued','Retrying')");$update->execute(['id'=>$row['id']]);
            if(!$update->rowCount()){$this->db->rollBack();return null;}$row['attempts']=(int)$row['attempts']+1;$this->db->commit();return$row;
        }catch(Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    private function publish(array$row): array {
        if($row['connection_status']!=='Connected')throw new RuntimeException('Compte social non connecte.');
        $token=CryptoService::decrypt((string)$row['access_token_encrypted']);if($token==='')throw new RuntimeException('Jeton social absent ou illisible.');
        $provider=(string)$row['provider'];
        if($provider==='facebook')return$this->publishFacebook($row,$token);
        if($provider==='instagram')return$this->publishInstagram($row,$token);
        throw new RuntimeException('Adaptateur '.$provider.' non encore active.');
    }

    private function publishFacebook(array$row,string$token): array {
        $page=(string)$row['external_account_id'];if($page==='')throw new RuntimeException('Identifiant Page Facebook manquant.');
        $caption=(string)$row['adapted_caption'];$media=trim((string)$row['media_url']);
        if($media!==''){$data=$this->postGraph('/'.$page.'/photos',['url'=>$media,'caption'=>$caption,'access_token'=>$token]);}
        else{$data=$this->postGraph('/'.$page.'/feed',['message'=>$caption,'access_token'=>$token]);}
        $id=(string)($data['post_id']??$data['id']??'');if($id==='')throw new RuntimeException('Meta n a retourne aucun identifiant de publication.');
        return['external_post_id'=>$id,'external_post_url'=>'https://www.facebook.com/'.$id];
    }

    private function publishInstagram(array$row,string$token): array {
        $account=(string)$row['external_account_id'];$media=trim((string)$row['media_url']);if($account==='')throw new RuntimeException('Identifiant Instagram Business manquant.');if($media==='')throw new RuntimeException('Instagram exige une URL de media publique.');
        $container=$this->postGraph('/'.$account.'/media',['image_url'=>$media,'caption'=>(string)$row['adapted_caption'],'access_token'=>$token]);$creation=(string)($container['id']??'');if($creation==='')throw new RuntimeException('Conteneur Instagram non cree.');
        $result=$this->postGraph('/'.$account.'/media_publish',['creation_id'=>$creation,'access_token'=>$token]);$id=(string)($result['id']??'');if($id==='')throw new RuntimeException('Publication Instagram non confirmee.');return['external_post_id'=>$id,'external_post_url'=>''];
    }

    private function postGraph(string$path,array$params): array {$version=trim((string)config_env_value('META_GRAPH_VERSION','v23.0'));if(!preg_match('/^v\d+\.\d+$/',$version))$version='v23.0';$ch=curl_init('https://graph.facebook.com/'.$version.$path);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Accept: application/json']]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);$data=json_decode((string)$body,true);if($body===false||$status<200||$status>=300)throw new RuntimeException((string)($data['error']['message']??$error?:('Meta HTTP '.$status)));return is_array($data)?$data:[];}
    private function complete(array$row,array$result): void {$stmt=$this->db->prepare("UPDATE social_publication_targets SET status='Published',published_at=NOW(),external_post_id=:post,external_post_url=:url,last_error=NULL WHERE id=:id AND status='Processing'");$stmt->execute(['post'=>$result['external_post_id']??null,'url'=>$result['external_post_url']??null,'id'=>$row['id']]);$this->event($row,'published','Published','Publication envoyee avec succes',$result);}
    private function fail(array$row,string$error): void {$retry=(int)$row['attempts']<(int)$row['max_attempts'];$minutes=min(240,5*(2**max(0,(int)$row['attempts']-1)));$stmt=$this->db->prepare("UPDATE social_publication_targets SET status=:status,next_attempt_at=IF(:retry=1,DATE_ADD(NOW(),INTERVAL :minutes MINUTE),next_attempt_at),last_error=:error WHERE id=:id AND status='Processing'");$stmt->execute(['status'=>$retry?'Retrying':'Failed','retry'=>$retry?1:0,'minutes'=>$minutes,'error'=>mb_strimwidth($error,0,1000),'id'=>$row['id']]);$this->event($row,$retry?'retry_scheduled':'failed',$retry?'Retrying':'Failed',$error,['attempt'=>$row['attempts'],'retry_minutes'=>$retry?$minutes:null]);}
    private function event(array$row,string$type,string$status,string$message,array$payload=[]): void {$stmt=$this->db->prepare('INSERT INTO social_publication_events(tenant_id,publication_id,target_id,event_type,status,message,payload_json) VALUES(:tenant,:publication,:target,:type,:status,:message,:payload)');$stmt->execute(['tenant'=>$row['tenant_id'],'publication'=>$row['publication_id'],'target'=>$row['id'],'type'=>$type,'status'=>$status,'message'=>mb_strimwidth($message,0,1000),'payload'=>json_encode($payload,JSON_UNESCAPED_SLASHES)]);}
}
