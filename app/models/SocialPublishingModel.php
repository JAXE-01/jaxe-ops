<?php
class SocialPublishingModel extends Model {
    public const PROVIDERS=['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','tiktok'=>'TikTok','youtube'=>'YouTube','x'=>'X'];

    public function dashboardData(): array {
        $tenant=TenantGuard::tenantId();
        $connections=$this->db->prepare('SELECT sc.*,c.entreprise client_name FROM social_connections sc LEFT JOIN clients c ON c.id=sc.client_id WHERE sc.tenant_id=:tenant ORDER BY sc.status="Connected" DESC,sc.provider,sc.account_label');
        $connections->execute(['tenant'=>$tenant]);
        $publications=$this->db->prepare('SELECT sp.*,c.entreprise client_name,COUNT(t.id) target_count,SUM(t.status="Published") published_count,SUM(t.status="Failed") failed_count FROM social_publications sp JOIN clients c ON c.id=sp.client_id LEFT JOIN social_publication_targets t ON t.publication_id=sp.id WHERE sp.tenant_id=:tenant GROUP BY sp.id ORDER BY sp.created_at DESC LIMIT 30');
        $publications->execute(['tenant'=>$tenant]);
        $stats=$this->db->prepare('SELECT COUNT(*) total,SUM(status="Queued") queued,SUM(status="Published") published,SUM(status="Failed") failed FROM social_publication_targets WHERE publication_id IN (SELECT id FROM social_publications WHERE tenant_id=:tenant)');
        $stats->execute(['tenant'=>$tenant]);
        return ['connections'=>$connections->fetchAll(PDO::FETCH_ASSOC),'publications'=>$publications->fetchAll(PDO::FETCH_ASSOC),'stats'=>$stats->fetch(PDO::FETCH_ASSOC)?:[]];
    }

    public function clients(): array {
        $stmt=$this->db->prepare('SELECT id,COALESCE(NULLIF(entreprise,""),nom) name FROM clients WHERE tenant_id=:tenant AND statut="Actif" ORDER BY name');
        $stmt->execute(['tenant'=>TenantGuard::tenantId()]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveConnection(array $input,int $userId): int {
        $client=(int)($input['client_id']??0); TenantGuard::assertClient($client);
        $provider=strtolower(trim((string)($input['provider']??'')));
        if(!isset(self::PROVIDERS[$provider]))throw new RuntimeException('Reseau non pris en charge.');
        $label=trim((string)($input['account_label']??'')); if($label==='')throw new RuntimeException('Nom du compte obligatoire.');
        $stmt=$this->db->prepare("INSERT INTO social_connections(tenant_id,organization_id,client_id,provider,account_label,external_account_id,account_type,status,connected_by) VALUES(:tenant,:organization,:client,:provider,:label,:external_id,:account_type,'Pending',:user)");
        $ctx=OrganizationContext::forUser($_SESSION['user']??[]);
        $stmt->execute(['tenant'=>TenantGuard::tenantId(),'organization'=>(int)($ctx['organization_id']??0)?:null,'client'=>$client,'provider'=>$provider,'label'=>$label,'external_id'=>trim((string)($input['external_account_id']??''))?:null,'account_type'=>trim((string)($input['account_type']??'Page')),'user'=>$userId]);
        return (int)$this->db->lastInsertId();
    }

    public function createPublication(array $input,int $userId): int {
        $client=(int)($input['client_id']??0); TenantGuard::assertClient($client);
        $targets=array_values(array_unique(array_map('intval',(array)($input['connection_ids']??[])))); if(!$targets)throw new RuntimeException('Selectionnez au moins une destination.');
        $title=trim((string)($input['master_title']??'')); $caption=trim((string)($input['master_caption']??'')); if($title===''||$caption==='')throw new RuntimeException('Titre et contenu maitre obligatoires.');
        $mode=($input['publish_mode']??'Scheduled')==='Now'?'Now':'Scheduled'; $scheduled=trim((string)($input['scheduled_at']??''));
        if($mode==='Scheduled'&&$scheduled==='')throw new RuntimeException('Date de programmation obligatoire.');
        $approval=!empty($input['submit_approval'])?'Pending':'Draft';
        $this->db->beginTransaction();
        try{
            $ctx=OrganizationContext::forUser($_SESSION['user']??[]);
            $stmt=$this->db->prepare('INSERT INTO social_publications(tenant_id,organization_id,client_id,project_id,content_id,master_title,master_caption,media_url,publish_mode,scheduled_at,approval_status,created_by) VALUES(:tenant,:organization,:client,:project,:content,:title,:caption,:media,:mode,:scheduled,:approval,:user)');
            $stmt->execute(['tenant'=>TenantGuard::tenantId(),'organization'=>(int)($ctx['organization_id']??0)?:null,'client'=>$client,'project'=>(int)($input['project_id']??0)?:null,'content'=>(int)($input['content_id']??0)?:null,'title'=>$title,'caption'=>$caption,'media'=>trim((string)($input['media_url']??''))?:null,'mode'=>$mode,'scheduled'=>$mode==='Scheduled'?date('Y-m-d H:i:s',strtotime($scheduled)):date('Y-m-d H:i:s'),'approval'=>$approval,'user'=>$userId]);
            $id=(int)$this->db->lastInsertId();
            $check=$this->db->prepare('SELECT id,provider FROM social_connections WHERE id=:id AND tenant_id=:tenant AND client_id=:client AND status IN ("Pending","Connected")');
            $insert=$this->db->prepare('INSERT INTO social_publication_targets(publication_id,connection_id,provider,adapted_caption,status,next_attempt_at) VALUES(:publication,:connection,:provider,:caption,"WaitingApproval",:next)');
            foreach($targets as $connection){$check->execute(['id'=>$connection,'tenant'=>TenantGuard::tenantId(),'client'=>$client]);$row=$check->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Destination inaccessible.');$variant=trim((string)($input['variant'][$row['provider']]??''))?:$caption;$insert->execute(['publication'=>$id,'connection'=>$connection,'provider'=>$row['provider'],'caption'=>$variant,'next'=>$mode==='Now'?date('Y-m-d H:i:s'):date('Y-m-d H:i:s',strtotime($scheduled))]);}
            $this->event($id,null,'created',$approval,'Publication creee',$userId); $this->db->commit(); return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function approve(int $id,int $userId): void {
        $stmt=$this->db->prepare('UPDATE social_publications SET approval_status="Approved",approved_by=:user,approved_at=NOW() WHERE id=:id AND tenant_id=:tenant AND approval_status="Pending"');
        $stmt->execute(['user'=>$userId,'id'=>$id,'tenant'=>TenantGuard::tenantId()]); if(!$stmt->rowCount())throw new RuntimeException('Publication non disponible pour validation.');
        $this->db->prepare('UPDATE social_publication_targets SET status="Queued" WHERE publication_id=:id AND status="WaitingApproval"')->execute(['id'=>$id]); $this->event($id,null,'approved','Queued','Publication approuvee et placee en file',$userId);
    }

    public function retry(int $targetId,int $userId): void {
        $stmt=$this->db->prepare('UPDATE social_publication_targets t JOIN social_publications p ON p.id=t.publication_id SET t.status="Queued",t.next_attempt_at=NOW(),t.last_error=NULL WHERE t.id=:id AND p.tenant_id=:tenant AND t.status="Failed"');
        $stmt->execute(['id'=>$targetId,'tenant'=>TenantGuard::tenantId()]); if(!$stmt->rowCount())throw new RuntimeException('Echec introuvable ou deja relance.');
    }

    private function event(int $publication,?int $target,string $type,string $status,string $message,int $user): void {
        $stmt=$this->db->prepare('INSERT INTO social_publication_events(tenant_id,publication_id,target_id,event_type,status,message,created_by) VALUES(:tenant,:publication,:target,:type,:status,:message,:user)');
        $stmt->execute(['tenant'=>TenantGuard::tenantId(),'publication'=>$publication,'target'=>$target,'type'=>$type,'status'=>$status,'message'=>$message,'user'=>$user]);
    }
}
