<?php
class SocialInboxService {
    private PDO $db;
    public function __construct(){ $this->db=Database::getConnection(); }

    public function connections(int $tenantId): array {
        $stmt=$this->db->prepare("SELECT id,account_label,external_account_id,scopes_json FROM social_connections WHERE tenant_id=:tenant AND provider='facebook' AND status='Connected' ORDER BY account_label");
        $stmt->execute(['tenant'=>$tenantId]);return$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function inbox(int $connectionId,int $tenantId,string $type='comments'): array {
        $connection=$this->connection($connectionId,$tenantId);$token=$this->token($connection);$page=(string)$connection['external_account_id'];
        $posts=[];if($type==='comments'){$response=$this->graph('GET','/'.$page.'/feed',['fields'=>'id,message,created_time,permalink_url,comments.limit(50){id,message,created_time,from,parent}','limit'=>30,'access_token'=>$token]);$posts=(array)($response['data']??[]);}
        $conversations=[];$messageError=null;
        if($type==='messages')try{$response=$this->graph('GET','/'.$page.'/conversations',['fields'=>'id,updated_time,participants,messages.limit(25){id,message,from,to,created_time}','limit'=>30,'access_token'=>$token]);$conversations=(array)($response['data']??[]);}catch(Throwable $e){$messageError=$e->getMessage();}
        $scopes=(array)json_decode((string)($connection['scopes_json']??'[]'),true);$metadata=(array)json_decode((string)($connection['metadata_json']??'{}'),true);$tasks=(array)($metadata['tasks']??[]);
        return['connection'=>$connection,'posts'=>$posts,'conversations'=>$conversations,'message_error'=>$messageError,'message_access'=>[
            'scope_granted'=>in_array('pages_messaging',$scopes,true),'task_granted'=>in_array('MESSAGING',$tasks,true),'tasks'=>$tasks,
        ]];
    }
    public function replyComment(int $connectionId,int $tenantId,string $commentId,string $message): void {
        $connection=$this->connection($connectionId,$tenantId);$message=trim($message);if($message==='')throw new RuntimeException('Réponse vide.');
        $this->requireScope($connection,'pages_manage_engagement');$this->graph('POST','/'.rawurlencode($commentId).'/comments',['message'=>$message,'access_token'=>$this->token($connection)]);
    }
    public function replyMessage(int $connectionId,int $tenantId,string $recipientId,string $message): void {
        $connection=$this->connection($connectionId,$tenantId);$message=trim($message);if($message===''||$recipientId==='')throw new RuntimeException('Destinataire ou réponse manquant.');
        $this->requireScope($connection,'pages_messaging');$this->graph('POST','/me/messages',['recipient'=>json_encode(['id'=>$recipientId]),'message'=>json_encode(['text'=>$message]),'messaging_type'=>'RESPONSE','access_token'=>$this->token($connection)]);
    }
    private function connection(int$id,int$tenant): array{$stmt=$this->db->prepare("SELECT * FROM social_connections WHERE id=:id AND tenant_id=:tenant AND provider='facebook' AND status='Connected' LIMIT 1");$stmt->execute(['id'=>$id,'tenant'=>$tenant]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Page Facebook inaccessible.');return$row;}
    private function token(array$c): string{$token=CryptoService::decrypt((string)$c['access_token_encrypted']);if($token==='')throw new RuntimeException('Jeton Page absent.');return$token;}
    private function requireScope(array$c,string$scope): void{$scopes=(array)json_decode((string)($c['scopes_json']??'[]'),true);if(!in_array($scope,$scopes,true))throw new RuntimeException('Permission Meta manquante : '.$scope.'. Reconnectez la Page après validation de cette permission.');}
    private function graph(string$method,string$path,array$params): array{$version=trim((string)config_env_value('META_GRAPH_VERSION','v23.0'));$url='https://graph.facebook.com/'.$version.$path;$ch=curl_init();if($method==='GET')$url.='?'.http_build_query($params);curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>['Accept: application/json']]);if($method==='POST')curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params)]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);$data=json_decode((string)$body,true);if($body===false||$status<200||$status>=300)throw new RuntimeException((string)($data['error']['message']??$error?:'Meta HTTP '.$status));return is_array($data)?$data:[];}
}
