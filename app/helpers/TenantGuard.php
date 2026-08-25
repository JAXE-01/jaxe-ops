<?php
class TenantGuard {
    public static function tenantId() {
        $id=(int)($_SESSION['tenant_id']??0);
        if($id>0) return $id;
        $user=$_SESSION['user']??null;
        return $user?(int)(TenantContext::tenantId($user)??0):0;
    }
    public static function filterRows($table,array $rows){return array_values(array_filter($rows,static fn($row)=>self::canAccessRecord($table,$row)));}
    public static function canAccessRecord($table,array $row=null){
        if(!$row||self::tenantId()<=0)return false;
        $tenantId=self::tenantId();
        if($table==='clients'){if((int)($row['tenant_id']??0)===$tenantId)return true;return AgencyAccessPolicy::canAccessRecord($table,$row,'projects');}
        if($table==='users')return self::userBelongsToTenant((int)($row['id']??0),$tenantId);
        if(isset($row['tenant_id'])&&(int)$row['tenant_id']===$tenantId)return true;
        if(!empty($row['client_id'])&&self::clientBelongsToTenant((int)$row['client_id'],$tenantId))return true;
        if($table==='projets'&&!empty($row['id'])&&self::projectBelongsDirectlyToTenant((int)$row['id'],$tenantId))return true;
        return AgencyAccessPolicy::canAccessRecord($table,$row,AgencyAccessPolicy::defaultCapability($table));
    }
    public static function prepareCreate($table,array $payload,array $config=[]){
        $tenantId=self::requireTenantId();$pdo=Database::getConnection();$orgId=self::currentOrganizationId($tenantId);
        if($table==='clients'){
            $payload['tenant_id']=$tenantId;
            $payload['managed_by_organization_id']=$orgId?:null;
            $payload['relationship_mode']='External';
            if(!empty($payload['organization_id']))self::assertOrganization((int)$payload['organization_id']);
        }
        self::assertRelations($payload,$config);
        if($table==='projets'&&!empty($payload['client_id'])){
            self::assertClient((int)$payload['client_id']);
            $stmt=$pdo->prepare('SELECT organization_id,managed_by_organization_id,relationship_mode FROM clients WHERE id=:client_id LIMIT 1');
            $stmt->execute(['client_id'=>(int)$payload['client_id']]);$client=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$client)throw new RuntimeException('Client inaccessible dans cette entreprise.');
            $external=($client['relationship_mode']??'External')==='External';
            $payload['beneficiary_organization_id']=(int)($client['organization_id']??0)?:null;
            $payload['managed_by_organization_id']=(int)($client['managed_by_organization_id']??0)?:($orgId?:null);
            $payload['owner_organization_id']=$external?$payload['managed_by_organization_id']:$payload['beneficiary_organization_id'];
            $payload['workspace_owner_type']=$external?'Agency':'ClientCompany';
        }
        return $payload;
    }
    public static function prepareUpdate($table,array $payload,array $config=[]){if($table==='clients')unset($payload['tenant_id']);self::assertRelations($payload,$config);return $payload;}
    public static function afterCreate($table,$id){
        if((int)$id<=0)return;$tenantId=self::requireTenantId();$pdo=Database::getConnection();
        if($table==='clients'){
            $stmt=$pdo->prepare('SELECT nom,entreprise,statut FROM clients WHERE id=:id AND tenant_id=:tenant_id LIMIT 1');$stmt->execute(['id'=>(int)$id,'tenant_id'=>$tenantId]);$client=$stmt->fetch(PDO::FETCH_ASSOC);if(!$client)return;
            $name=trim((string)($client['entreprise']?:$client['nom']?:('Client '.$id)));
            $stmt=$pdo->prepare("INSERT INTO organizations (tenant_id,legacy_client_id,name,slug,account_type,project_mode,registration_state,status) VALUES (:tenant_id,:client_id,:name,:slug,'ClientCompany','Single','ExternalProfile',:status) ON DUPLICATE KEY UPDATE name=VALUES(name),status=VALUES(status)");
            $stmt->execute(['tenant_id'=>$tenantId,'client_id'=>(int)$id,'name'=>$name,'slug'=>'client-'.(int)$id,'status'=>$client['statut']==='Inactif'?'Inactif':'Actif']);
            $stmt=$pdo->prepare('SELECT id FROM organizations WHERE legacy_client_id=:client_id LIMIT 1');$stmt->execute(['client_id'=>(int)$id]);$organizationId=(int)$stmt->fetchColumn();
            $pdo->prepare('UPDATE clients SET organization_id=:organization_id WHERE id=:id AND tenant_id=:tenant_id')->execute(['organization_id'=>$organizationId,'id'=>(int)$id,'tenant_id'=>$tenantId]);return;
        }
        if($table==='users'){
            $stmt=$pdo->prepare("INSERT INTO tenant_memberships (tenant_id,organization_id,user_id,membership_role,status,joined_at) VALUES (:tenant_id,:organization_id,:user_id,'Member','Actif',NOW()) ON DUPLICATE KEY UPDATE status='Actif'");
            $stmt->execute(['tenant_id'=>$tenantId,'organization_id'=>self::currentOrganizationId($tenantId)?:null,'user_id'=>(int)$id]);
        }
    }
    public static function assertRecord($table,array $row=null){if(!self::canAccessRecord($table,$row))throw new RuntimeException('Ressource introuvable ou inaccessible dans cette entreprise.');}
    public static function assertClient($id){$stmt=Database::getConnection()->prepare('SELECT * FROM clients WHERE id=:id LIMIT 1');$stmt->execute(['id'=>(int)$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);AgencyAccessPolicy::assertRecordCapability('clients',$row,'projects');}
    public static function assertOrganization($id){$stmt=Database::getConnection()->prepare('SELECT 1 FROM organizations WHERE id=:id AND tenant_id=:tenant_id LIMIT 1');$stmt->execute(['id'=>(int)$id,'tenant_id'=>self::requireTenantId()]);if(!$stmt->fetchColumn())throw new RuntimeException('Organisation inaccessible dans cette entreprise.');}
    public static function assertUser($id){if((int)$id>0&&!self::userBelongsToTenant((int)$id,self::requireTenantId()))throw new RuntimeException('Utilisateur inaccessible dans cette entreprise.');}
    public static function assertProject($id){$stmt=Database::getConnection()->prepare('SELECT p.*,c.tenant_id,c.organization_id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=:id LIMIT 1');$stmt->execute(['id'=>(int)$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);AgencyAccessPolicy::assertRecordCapability('projets',$row,'projects');}
    private static function assertRelations(array $payload,array $config){foreach(($config['formFields']??[])as$field=>$meta){if(($meta['type']??'')!=='relation'||empty($payload[$field]))continue;$module=(string)($meta['module']??'');if($module==='client')self::assertClient((int)$payload[$field]);if($module==='projet')self::assertProject((int)$payload[$field]);if($module==='user')self::assertUser((int)$payload[$field]);}}
    private static function requireTenantId(){$id=self::tenantId();if($id<=0)throw new RuntimeException('Aucune entreprise active dans la session.');return $id;}
    private static function currentOrganizationId($tenantId){$stmt=Database::getConnection()->prepare("SELECT organization_id FROM tenant_memberships WHERE tenant_id=:tenant_id AND user_id=:user_id AND status='Actif' LIMIT 1");$stmt->execute(['tenant_id'=>$tenantId,'user_id'=>(int)($_SESSION['user']['id']??0)]);return (int)($stmt->fetchColumn()?:0);}
    private static function clientBelongsToTenant($id,$tenantId){$stmt=Database::getConnection()->prepare('SELECT 1 FROM clients WHERE id=:id AND tenant_id=:tenant_id LIMIT 1');$stmt->execute(['id'=>$id,'tenant_id'=>$tenantId]);return(bool)$stmt->fetchColumn();}
    private static function userBelongsToTenant($id,$tenantId){$stmt=Database::getConnection()->prepare("SELECT 1 FROM tenant_memberships WHERE user_id=:user_id AND tenant_id=:tenant_id AND status='Actif' LIMIT 1");$stmt->execute(['user_id'=>$id,'tenant_id'=>$tenantId]);return(bool)$stmt->fetchColumn();}
    private static function projectBelongsToTenant($id,$tenantId){if(self::projectBelongsDirectlyToTenant($id,$tenantId))return true;$stmt=Database::getConnection()->prepare('SELECT p.*,c.tenant_id,c.organization_id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=:id LIMIT 1');$stmt->execute(['id'=>$id]);return AgencyAccessPolicy::canAccessRecord('projets',$stmt->fetch(PDO::FETCH_ASSOC)?:null,'projects');} private static function projectBelongsDirectlyToTenant($id,$tenantId){$stmt=Database::getConnection()->prepare('SELECT 1 FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=:id AND c.tenant_id=:tenant_id LIMIT 1');$stmt->execute(['id'=>$id,'tenant_id'=>$tenantId]);return(bool)$stmt->fetchColumn();}
}
