<?php
class UserRoles {
    public static function normalizeList($roles) {
        $values = is_array($roles) ? $roles : preg_split('/\s*,\s*/', (string) $roles);
        $allowedRoles = array_keys(ModuleRegistry::roleOptions());
        $normalized = [];

        foreach ((array) $values as $role) {
            $role = trim((string) $role);
            if ($role === '' || !in_array($role, $allowedRoles, true) || in_array($role, $normalized, true)) {
                continue;
            }
            $normalized[] = $role;
        }

        return $normalized;
    }

    public static function serialize($roles) {
        return implode(',', self::normalizeList($roles));
    }

    public static function extractRoles(array $user = null) {
        if ($user === null) {
            return [];
        }

        if (!empty($user['roles']) && is_array($user['roles'])) {
            return self::normalizeList($user['roles']);
        }

        $roles = [];
        if (!empty($user['role'])) {
            $roles[] = (string) $user['role'];
        }

        if (array_key_exists('secondary_roles', $user)) {
            $roles = array_merge($roles, self::normalizeList($user['secondary_roles']));
        }

        return self::normalizeList($roles);
    }

    public static function hasRole(array $user = null, $role) {
        return in_array((string) $role, self::extractRoles($user), true);
    }

    public static function hasAnyRole(array $user = null, array $roles) {
        $userRoles = self::extractRoles($user);
        foreach ($roles as $role) {
            if (in_array((string) $role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    public static function labels(array $user = null) {
        $options = ModuleRegistry::roleOptions();
        return array_map(static function ($role) use ($options) {
            return $options[$role] ?? $role;
        }, self::extractRoles($user));
    }
}