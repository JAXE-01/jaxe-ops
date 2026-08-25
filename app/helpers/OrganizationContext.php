<?php
class OrganizationContext {
    private static array $cache=[];
    public static function forUser(array $user = null) {
        $userId=(int)($user['id']??0);$tenantId=TenantGuard::tenantId();$key=$tenantId.':'.$userId;
        if($userId<=0||$tenantId<=0)return null;if(array_key_exists($key,self::$cache))return self::$cache[$key];
        $stmt=Database::getConnection()->prepare("SELECT o.id,o.name,o.slug,o.account_type,o.project_mode,tm.membership_role,tm.status AS membership_status FROM tenant_memberships tm LEFT JOIN organizations o ON o.id=tm.organization_id WHERE tm.user_id=:user_id AND tm.tenant_id=:tenant_id AND tm.status='Actif' LIMIT 1");
        $stmt->execute(['user_id'=>$userId,'tenant_id'=>$tenantId]);return self::$cache[$key]=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public static function accountType(array $user=null): string {return(string)(self::forUser($user)['account_type']??'');}
    public static function isClientCompany(array $user=null): bool {return self::accountType($user)==='ClientCompany';}
    public static function isAgency(array $user=null): bool {return self::accountType($user)==='Agency';}
    public static function isPlatformAdmin(array $user=null): bool {
        $context=self::forUser($user);if(!$context||!UserRoles::hasRole($user,'Admin'))return false;
        if(($context['account_type']??'')==='Platform')return true;
        return TenantGuard::tenantId()===1&&($context['account_type']??'')==='Agency'&&in_array($context['membership_role']??'',['Owner','Admin'],true);
    }
    public static function canManageOrganizations(array $user=null): bool {
        $context=self::forUser($user);return(bool)($context&&in_array($context['account_type']??'',['Platform','Agency'],true)&&in_array($context['membership_role']??'',['Owner','Admin'],true));
    }
}
