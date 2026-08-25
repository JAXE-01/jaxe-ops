<?php
class AgencyConnectionService {
    private PDO $pdo;
    private array $user;
    private array $organization;

    public function __construct(array $user) {
        $this->pdo = Database::getConnection();
        $this->user = $user;
        $this->organization = OrganizationContext::forUser($user) ?: [];
        if (empty($this->organization['id'])) { throw new RuntimeException('Aucune organisation active.'); }
    }

    public function organization(): array { return $this->organization; }

    public function assertCanAdminister(): void {
        $role = (string) ($this->organization['membership_role'] ?? '');
        if (!OrganizationContext::isPlatformAdmin($this->user) && !in_array($role, ['Owner','Admin'], true)) {
            throw new RuntimeException('Seul un proprietaire ou administrateur peut gerer les connexions agence.');
        }
    }

    public function createSyncCode(): string {
        $this->assertCanAdminister();
        if (($this->organization['account_type'] ?? '') !== 'ClientCompany') { throw new RuntimeException('Les codes sont generes depuis un compte client.'); }
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 4).'-'.substr(bin2hex(random_bytes(6)), 0, 4));
        $hash = $this->codeHash($code);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE organization_sync_codes SET revoked_at=NOW() WHERE client_organization_id=:org AND revoked_at IS NULL')->execute(['org'=>$this->organization['id']]);
            $stmt=$this->pdo->prepare('INSERT INTO organization_sync_codes(tenant_id,client_organization_id,code_hash,expires_at,max_uses,created_by) VALUES (:tenant,:org,:hash,DATE_ADD(NOW(),INTERVAL 30 MINUTE),1,:user)');
            $stmt->execute(['tenant'=>TenantGuard::tenantId(),'org'=>$this->organization['id'],'hash'=>$hash,'user'=>$this->user['id']]);
            $this->audit('sync_code.created', (int)$this->organization['id'], null, ['expires_in_minutes'=>30]);
            $this->pdo->commit();
        } catch(Throwable $e) { $this->pdo->rollBack(); throw $e; }
        return $code;
    }

    public function requestWithCode(string $code, array $scope): void {
        $this->assertCanAdminister();
        if (($this->organization['account_type'] ?? '') !== 'Agency') { throw new RuntimeException('Le code client doit etre utilise depuis un compte agence.'); }
        $hash=$this->codeHash($code);
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT sc.*,o.tenant_id AS client_tenant_id,o.name AS client_name FROM organization_sync_codes sc JOIN organizations o ON o.id=sc.client_organization_id WHERE sc.code_hash=:hash AND sc.revoked_at IS NULL AND sc.expires_at>NOW() AND sc.use_count<sc.max_uses FOR UPDATE');
            $stmt->execute(['hash'=>$hash]); $sync=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$sync) throw new RuntimeException('Code invalide, expire ou deja utilise.');
            if((int)$sync['client_organization_id']===(int)$this->organization['id']) throw new RuntimeException('Une organisation ne peut pas se connecter a elle-meme.');
            $grant=$this->upsertGrant((int)$sync['client_tenant_id'],(int)$sync['client_organization_id'],(int)$this->organization['id'],'AgencyCode',$scope,false);
            $this->pdo->prepare('UPDATE organization_sync_codes SET use_count=use_count+1 WHERE id=:id')->execute(['id'=>$sync['id']]);
            $this->audit('agency_access.requested_with_code',(int)$sync['client_organization_id'],$grant,['scope'=>$scope]);
            $this->pdo->commit();
        } catch(Throwable $e){$this->pdo->rollBack();throw$e;}
    }

    public function inviteAgency(int $agencyId, array $scope): void {
        $this->assertCanAdminister();
        if (($this->organization['account_type'] ?? '') !== 'ClientCompany') throw new RuntimeException('L invitation doit partir du compte client.');
        $stmt=$this->pdo->prepare("SELECT id FROM organizations WHERE id=:id AND account_type='Agency' AND registration_state='Registered' AND status='Actif'");$stmt->execute(['id'=>$agencyId]);
        if(!$stmt->fetchColumn()) throw new RuntimeException('Agence inscrite introuvable.');
        $this->pdo->beginTransaction();
        try{$grant=$this->upsertGrant(TenantGuard::tenantId(),(int)$this->organization['id'],$agencyId,'ClientInvitation',$scope,true);$this->audit('agency_access.invited',(int)$this->organization['id'],$grant,['scope'=>$scope,'agency_id'=>$agencyId]);$this->pdo->commit();}catch(Throwable$e){$this->pdo->rollBack();throw$e;}
    }

    private function upsertGrant(int $tenantId,int $clientId,int $agencyId,string $origin,array $scope,bool $clientConfirmed): int {
        $sql="INSERT INTO organization_agency_grants(tenant_id,client_organization_id,agency_organization_id,status,connection_purpose,request_origin,permission_scope,requested_by,client_confirmed_by,client_confirmed_at) VALUES (:tenant,:client,:agency,'Pending','DataLink',:origin,:scope,:user,:confirmed_by,:confirmed_at) ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id),status='Pending',request_origin=VALUES(request_origin),permission_scope=VALUES(permission_scope),requested_by=VALUES(requested_by),requested_at=NOW(),approved_by=NULL,approved_at=NULL,client_confirmed_by=VALUES(client_confirmed_by),client_confirmed_at=VALUES(client_confirmed_at),revoked_at=NULL";
        $stmt=$this->pdo->prepare($sql);$stmt->execute(['tenant'=>$tenantId,'client'=>$clientId,'agency'=>$agencyId,'origin'=>$origin,'scope'=>json_encode($scope,JSON_UNESCAPED_UNICODE),'user'=>$this->user['id'],'confirmed_by'=>$clientConfirmed?$this->user['id']:null,'confirmed_at'=>$clientConfirmed?date('Y-m-d H:i:s'):null]);
        $q=$this->pdo->prepare('SELECT id FROM organization_agency_grants WHERE client_organization_id=:client AND agency_organization_id=:agency');$q->execute(['client'=>$clientId,'agency'=>$agencyId]);return(int)$q->fetchColumn();
    }

    public function decide(int $grantId, bool $approve): void {
        $this->assertCanAdminister(); $grant=$this->grant($grantId,true); $orgId=(int)$this->organization['id'];
        $isClient=$orgId===(int)$grant['client_organization_id'];$isAgency=$orgId===(int)$grant['agency_organization_id'];
        if(!$isClient&&!$isAgency)throw new RuntimeException('Relation inaccessible.');
        $expected=$grant['request_origin']==='AgencyCode'?$isClient:$isAgency;
        if(!$expected&&!OrganizationContext::isPlatformAdmin($this->user))throw new RuntimeException('Cette validation appartient a l autre organisation.');
        $status=$approve?'Active':'Revoked';
        $sql="UPDATE organization_agency_grants SET status=:status,approved_by=:user,approved_at=IF(:active='Active',NOW(),approved_at),client_confirmed_by=IF(:is_client=1,:user,client_confirmed_by),client_confirmed_at=IF(:is_client=1,NOW(),client_confirmed_at),revoked_at=IF(:revoked='Revoked',NOW(),NULL) WHERE id=:id";
        $this->pdo->prepare($sql)->execute(['status'=>$status,'user'=>$this->user['id'],'active'=>$status,'is_client'=>$isClient?1:0,'revoked'=>$status,'id'=>$grantId]);
        $this->audit($approve?'agency_access.approved':'agency_access.declined',(int)$grant['client_organization_id'],$grantId,[]);
    }

    public function updatePermissions(int $grantId,array $scope): void {
        $this->assertCanAdminister();$grant=$this->grant($grantId,true);
        if((int)$grant['client_organization_id']!==(int)$this->organization['id']&&!OrganizationContext::isPlatformAdmin($this->user))throw new RuntimeException('Seul le client proprietaire peut modifier les droits.');
        $this->pdo->prepare('UPDATE organization_agency_grants SET permission_scope=:scope WHERE id=:id')->execute(['scope'=>json_encode($scope,JSON_UNESCAPED_UNICODE),'id'=>$grantId]);
        $this->audit('agency_access.permissions_updated',(int)$grant['client_organization_id'],$grantId,['scope'=>$scope]);
    }

    public function revoke(int $grantId): void {
        $this->assertCanAdminister();$grant=$this->grant($grantId,true);
        $this->pdo->prepare("UPDATE organization_agency_grants SET status='Revoked',revoked_at=NOW() WHERE id=:id")->execute(['id'=>$grantId]);
        $this->audit('agency_access.revoked',(int)$grant['client_organization_id'],$grantId,[]);
    }

    public function grants(): array {
        $org=(int)$this->organization['id'];$sql="SELECT g.*,client.name client_name,agency.name agency_name FROM organization_agency_grants g JOIN organizations client ON client.id=g.client_organization_id JOIN organizations agency ON agency.id=g.agency_organization_id WHERE g.client_organization_id=:org OR g.agency_organization_id=:org ORDER BY FIELD(g.status,'Pending','Active','Revoked'),g.updated_at DESC";$stmt=$this->pdo->prepare($sql);$stmt->execute(['org'=>$org]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);foreach($rows as&$row)$row['scope']=json_decode((string)$row['permission_scope'],true)?:[];return$rows;
    }

    public function agencies(): array { return $this->pdo->query("SELECT id,name FROM organizations WHERE account_type='Agency' AND registration_state='Registered' AND status='Actif' AND id<>".(int)$this->organization['id']." ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); }
    public function activity(): array {$stmt=$this->pdo->prepare('SELECT l.*,u.nom actor_name FROM organization_activity_logs l LEFT JOIN users u ON u.id=l.actor_user_id WHERE l.organization_id=:org ORDER BY l.created_at DESC LIMIT 80');$stmt->execute(['org'=>$this->organization['id']]);return$stmt->fetchAll(PDO::FETCH_ASSOC);}
    public static function sanitizeScope(array $input): array {$keys=['projects','content','validation','publishing','analytics','history','users'];$scope=[];foreach($keys as$key)$scope[$key]=!empty($input[$key]);$scope['projects']=true;return$scope;}

    private function grant(int $id,bool $lock=false): array {$sql='SELECT * FROM organization_agency_grants WHERE id=:id'.($lock?' FOR UPDATE':'');$stmt=$this->pdo->prepare($sql);$stmt->execute(['id'=>$id]);$grant=$stmt->fetch(PDO::FETCH_ASSOC);if(!$grant)throw new RuntimeException('Connexion introuvable.');$org=(int)$this->organization['id'];if($org!==(int)$grant['client_organization_id']&&$org!==(int)$grant['agency_organization_id']&&!OrganizationContext::isPlatformAdmin($this->user))throw new RuntimeException('Connexion inaccessible.');return$grant;}
    private function codeHash(string $code): string {return hash_hmac('sha256',strtoupper(preg_replace('/\s+/','',trim($code))),APP_ENCRYPTION_KEY);}
    private function audit(string $action,int $orgId,?int $grantId,array $metadata): void {$ip=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),APP_ENCRYPTION_KEY);$stmt=$this->pdo->prepare('INSERT INTO organization_activity_logs(tenant_id,organization_id,agency_grant_id,actor_user_id,action,target_type,target_id,metadata,ip_hash) VALUES (:tenant,:org,:grant,:user,:action,\'agency_connection\',:target,:metadata,:ip)');$stmt->execute(['tenant'=>TenantGuard::tenantId(),'org'=>$orgId,'grant'=>$grantId,'user'=>$this->user['id'],'action'=>$action,'target'=>$grantId,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE),'ip'=>$ip]);}
}
