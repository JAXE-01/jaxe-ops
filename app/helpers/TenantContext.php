<?php
class TenantContext {
    public static function resolveForUser(array $user = null) {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $sessionTenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        $params = ['user_id' => $userId];
        $sql = "SELECT t.id, t.name, t.slug, t.status, t.plan_code,
                       tm.membership_role, tm.organization_id
                FROM tenant_memberships tm
                JOIN tenants t ON t.id = tm.tenant_id
                WHERE tm.user_id = :user_id
                  AND tm.status = 'Actif'
                  AND t.status = 'Actif'";

        if ($sessionTenantId > 0) {
            $sql .= ' AND t.id = :tenant_id';
            $params['tenant_id'] = $sessionTenantId;
        }

        $sql .= ' ORDER BY tm.id ASC LIMIT 1';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($tenant) {
            $_SESSION['tenant_id'] = (int) $tenant['id'];
        } else {
            unset($_SESSION['tenant_id']);
        }

        return $tenant;
    }

    public static function tenantId(array $user = null) {
        $tenant = self::resolveForUser($user);
        return $tenant ? (int) $tenant['id'] : null;
    }

    public static function clear() {
        unset($_SESSION['tenant_id']);
    }
}
