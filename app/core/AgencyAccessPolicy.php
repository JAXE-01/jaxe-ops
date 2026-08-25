<?php
class AgencyAccessPolicy {
    private static array $organizationCache = [];
    private static array $grantCache = [];

    public static function defaultCapability(string $table): string {
        $map = [
            'clients'=>'projects','projets'=>'projects','plans_mensuels'=>'projects','taches_pipeline'=>'projects',
            'livrable_items'=>'content','contenus'=>'content','content_matrices'=>'content','matrix_ideas'=>'content',
            'campagnes'=>'content','personas'=>'content','offres'=>'content','messages_marketing'=>'content',
            'reporting_metrics'=>'analytics','users'=>'users','tenant_memberships'=>'users',
        ];
        return $map[$table] ?? 'projects';
    }

    public static function canAccessRecord(string $table, ?array $row, ?string $capability=null): bool {
        if (!$row) return false;
        $tenantId=TenantGuard::tenantId();
        if($tenantId<=0)return false;
        if(isset($row['tenant_id'])&&(int)$row['tenant_id']===$tenantId)return true;
        $organizationId=self::resolveOrganizationId($table,$row);
        if($organizationId<=0)return false;
        $current=self::currentOrganization();
        if(!$current)return false;
        if((int)$current['id']===$organizationId)return true;
        if(OrganizationContext::isPlatformAdmin($_SESSION['user']??null)&&isset($row['tenant_id'])&&(int)$row['tenant_id']===$tenantId)return true;
        return self::hasGrant($organizationId,$capability?:self::defaultCapability($table));
    }

    public static function assertRecordCapability(string $table, ?array $row, string $capability, bool $write=false): void {
        if(!self::canAccessRecord($table,$row,$capability))throw new RuntimeException('Cette organisation ne vous a pas accorde l acces requis.');
        if($write&&self::isSharedRecord($table,$row)){
            $scope=self::grantScopeForRecord($table,$row);
            if(empty($scope[$capability]))throw new RuntimeException('Droit de modification non accorde pour cette ressource.');
        }
    }

    public static function hasGrant(int $clientOrganizationId,string $capability): bool {
        $current=self::currentOrganization();if(!$current||($current['account_type']??'')!=='Agency')return false;
        $key=$clientOrganizationId.':'.(int)$current['id'];
        if(!array_key_exists($key,self::$grantCache)){
            $stmt=Database::getConnection()->prepare("SELECT id,permission_scope FROM organization_agency_grants WHERE client_organization_id=:client AND agency_organization_id=:agency AND status='Active' LIMIT 1");
            $stmt->execute(['client'=>$clientOrganizationId,'agency'=>$current['id']]);$grant=$stmt->fetch(PDO::FETCH_ASSOC);
            if($grant){$grant['scope']=json_decode((string)$grant['permission_scope'],true)?:[];}self::$grantCache[$key]=$grant?:null;
        }
        $grant=self::$grantCache[$key];return(bool)($grant&&!empty($grant['scope'][$capability]));
    }

    public static function accessibleClientOrganizationIds(string $capability): array {
        $current=self::currentOrganization();if(!$current)return[];
        $ids=[(int)$current['id']];
        if(($current['account_type']??'')==='Agency'){
            $stmt=Database::getConnection()->prepare("SELECT client_organization_id,permission_scope FROM organization_agency_grants WHERE agency_organization_id=:agency AND status='Active'");$stmt->execute(['agency'=>$current['id']]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as$row){$scope=json_decode((string)$row['permission_scope'],true)?:[];if(!empty($scope[$capability]))$ids[]=(int)$row['client_organization_id'];}
        }
        return array_values(array_unique(array_filter($ids)));
    }

    public static function clientSqlScope(string $clientAlias, string $capability, string $prefix='agency_scope'): array {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$clientAlias)||!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$prefix))throw new InvalidArgumentException('Alias SQL invalide.');
        $tenantId=TenantGuard::tenantId();
        $current=self::currentOrganization();
        $tenantParam=$prefix.'_tenant';
        $agencyParam=$prefix.'_agency';
        $capability=strtolower(trim($capability));
        if(!in_array($capability,['projects','content','validation','publishing','analytics','history','users'],true))throw new InvalidArgumentException('Permission SaaS invalide.');
        $sql='('.$clientAlias.'.tenant_id = :'.$tenantParam;
        $params=[$tenantParam=>$tenantId];
        if($current&&($current['account_type']??'')==='Agency'){
            $sql.=' OR EXISTS (SELECT 1 FROM organization_agency_grants agency_grant'
                .' WHERE agency_grant.client_organization_id = '.$clientAlias.'.organization_id'
                .' AND agency_grant.agency_organization_id = :'.$agencyParam
                ." AND agency_grant.status = 'Active'"
                ." AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(agency_grant.permission_scope, '$.".$capability."')), 'false') = 'true')";
            $params[$agencyParam]=(int)$current['id'];
        }
        $sql.=')';
        return ['sql'=>$sql,'params'=>$params];
    }
    public static function auditAccess(string $table,int $recordId,string $capability,array $row=[]): void {
        if(!self::isSharedRecord($table,$row))return;
        $key=$table.':'.$recordId.':'.$capability;if(!empty($_SESSION['_audited_shared_access'][$key]))return;$_SESSION['_audited_shared_access'][$key]=time();
        $orgId=self::resolveOrganizationId($table,$row);$current=self::currentOrganization();if(!$current||$orgId<=0)return;
        $stmt=Database::getConnection()->prepare("SELECT id,tenant_id FROM organization_agency_grants WHERE client_organization_id=:client AND agency_organization_id=:agency AND status='Active' LIMIT 1");$stmt->execute(['client'=>$orgId,'agency'=>$current['id']]);$grant=$stmt->fetch(PDO::FETCH_ASSOC);if(!$grant)return;
        $ip=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),APP_ENCRYPTION_KEY);
        Database::getConnection()->prepare("INSERT INTO organization_activity_logs(tenant_id,organization_id,agency_grant_id,actor_user_id,action,target_type,target_id,metadata,ip_hash) VALUES (:tenant,:org,:grant,:user,'shared_data.accessed',:type,:target,:metadata,:ip)")->execute(['tenant'=>$grant['tenant_id'],'org'=>$orgId,'grant'=>$grant['id'],'user'=>(int)($_SESSION['user']['id']??0)?:null,'type'=>$table,'target'=>(string)$recordId,'metadata'=>json_encode(['capability'=>$capability],JSON_UNESCAPED_UNICODE),'ip'=>$ip]);
    }

    private static function currentOrganization(): ?array {$tenant=TenantGuard::tenantId();$user=(int)($_SESSION['user']['id']??0);$key=$tenant.':'.$user;if(!array_key_exists($key,self::$organizationCache))self::$organizationCache[$key]=OrganizationContext::forUser($_SESSION['user']??null)?:null;return self::$organizationCache[$key];}
    private static function isSharedRecord(string $table,?array $row): bool {if(!$row)return false;if(isset($row['tenant_id']))return(int)$row['tenant_id']!==TenantGuard::tenantId();$org=self::resolveOrganizationId($table,$row);$current=self::currentOrganization();return$org>0&&$current&&(int)$current['id']!==$org;}
    private static function grantScopeForRecord(string $table,array $row): array {$org=self::resolveOrganizationId($table,$row);$current=self::currentOrganization();if(!$current||$org<=0)return[];$key=$org.':'.(int)$current['id'];self::hasGrant($org,self::defaultCapability($table));return(self::$grantCache[$key]['scope']??[]);}
    private static function resolveOrganizationId(string $table,array $row): int {
        if($table==='organizations')return(int)($row['id']??0);
        if($table==='clients'&&!empty($row['organization_id']))return(int)$row['organization_id'];
        if(in_array($table,['projets'],true)){if(!empty($row['beneficiary_organization_id']))return(int)$row['beneficiary_organization_id'];if(!empty($row['client_id']))return self::organizationFromClient((int)$row['client_id']);}
        if(!empty($row['client_id']))return self::organizationFromClient((int)$row['client_id']);
        if(!empty($row['projet_id']))return self::organizationFromProject((int)$row['projet_id']);
        if(!empty($row['plan_mensuel_id'])){$stmt=Database::getConnection()->prepare('SELECT projet_id FROM plans_mensuels WHERE id=:id');$stmt->execute(['id'=>$row['plan_mensuel_id']]);return self::organizationFromProject((int)$stmt->fetchColumn());}
        if(!empty($row['livrable_item_id'])){$stmt=Database::getConnection()->prepare('SELECT projet_id FROM livrable_items WHERE id=:id');$stmt->execute(['id'=>$row['livrable_item_id']]);return self::organizationFromProject((int)$stmt->fetchColumn());}
        if(!empty($row['campagne_id'])){$stmt=Database::getConnection()->prepare('SELECT client_id FROM campagnes WHERE id=:id');$stmt->execute(['id'=>$row['campagne_id']]);return self::organizationFromClient((int)$stmt->fetchColumn());}
        return 0;
    }
    private static function organizationFromClient(int$id): int {$stmt=Database::getConnection()->prepare('SELECT organization_id FROM clients WHERE id=:id');$stmt->execute(['id'=>$id]);return(int)$stmt->fetchColumn();}
    private static function organizationFromProject(int$id): int {$stmt=Database::getConnection()->prepare('SELECT COALESCE(beneficiary_organization_id,c.organization_id) FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=:id');$stmt->execute(['id'=>$id]);return(int)$stmt->fetchColumn();}
}
