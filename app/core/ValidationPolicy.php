<?php
/** Tenant-scoped defaults, project overrides and immutable per-content policy. */
class ValidationPolicy {
    private static function read(PDO $db, string $key): ?array {
        $q=$db->prepare('SELECT setting_value FROM app_settings WHERE setting_key=?');$q->execute([$key]);
        $value=json_decode((string)$q->fetchColumn(),true);return is_array($value)?$value:null;
    }
    private static function write(PDO $db,string $key,array $value): void {
        $q=$db->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $q->execute([$key,json_encode($value,JSON_UNESCAPED_UNICODE)]);
    }
    public static function defaults(int $tenant): array {
        return self::read(Database::getConnection(),'validation_tenant_'.$tenant)??['internal'=>true,'client'=>true];
    }
    public static function saveDefaults(array $data): void {
        $tenant=TenantGuard::tenantId();if(!$tenant)throw new RuntimeException('Entreprise requise.');
        self::write(Database::getConnection(),'validation_tenant_'.$tenant,['internal'=>!empty($data['validation_internal']),'client'=>!empty($data['validation_client'])]);
    }
    public static function project(int $id,int $tenant): array {
        $value=self::read(Database::getConnection(),'validation_project_'.$tenant.'_'.$id);
        return $value??['inherit'=>true]+self::defaults($tenant);
    }
    public static function saveProject(int $id,array $data): void {
        if(empty($data['validation_policy_present']))return;
        $db=Database::getConnection();$tenant=TenantGuard::tenantId();
        $q=$db->prepare('SELECT p.id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=? AND c.tenant_id=?');$q->execute([$id,$tenant]);
        if(!$q->fetchColumn())throw new RuntimeException('Projet inaccessible pour les validations.');
        self::write($db,'validation_project_'.$tenant.'_'.$id,['inherit'=>($data['validation_mode']??'inherit')==='inherit','internal'=>!empty($data['validation_internal']),'client'=>!empty($data['validation_client'])]);
    }
    public static function forContent(PDO $db,int $project,int $item): array {
        $q=$db->prepare('SELECT c.tenant_id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=?');$q->execute([$project]);$tenant=(int)$q->fetchColumn();
        if(!$tenant)throw new RuntimeException('Entreprise du projet introuvable.');
        $key='validation_content_'.$tenant.'_'.$item;
        $saved=self::read($db,$key);if($saved!==null)return $saved;
        // Legacy content must keep its historical gates, even after changing defaults.
        $q=$db->prepare('SELECT COUNT(*) FROM taches_pipeline WHERE livrable_item_id=?');$q->execute([$item]);
        if((int)$q->fetchColumn()>0)$policy=['internal'=>true,'client'=>true];
        else {$policy=self::project($project,$tenant);if(!empty($policy['inherit']))$policy=self::defaults($tenant);}
        $policy=['internal'=>!empty($policy['internal']),'client'=>!empty($policy['client'])];
        self::write($db,$key,$policy);return $policy;
    }
    public static function contentRequires(PDO $db,int $item,string $stage): bool {
        $q=$db->prepare('SELECT c.tenant_id FROM livrable_items li JOIN projets p ON p.id=li.projet_id JOIN clients c ON c.id=p.client_id WHERE li.id=?');$q->execute([$item]);$tenant=(int)$q->fetchColumn();
        $policy=self::read($db,'validation_content_'.$tenant.'_'.$item);
        if($policy===null)return true;
        return !empty($policy[$stage==='Validation interne'?'internal':'client']);
    }
}
