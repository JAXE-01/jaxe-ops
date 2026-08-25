<?php
class UserScope {
    private const TASK_TYPES_BY_ROLE = [
        'Clientele' => ['Validation client'],
        'CM' => ['Interactions', 'Publication', 'Collecte KPI'],
        'Createur' => ['Brief', 'Script', 'Production'],
        'Designer' => ['Brief', 'Production'],
        'Cadreur' => ['Tournage'],
        'Videaste' => ['Montage'],
    ];

    public static function privilegedRoles() {
        return ['Admin', 'CC'];
    }

    public static function isScopedOperationalUser(array $user = null) {
        if ($user === null) {
            return false;
        }

        return !UserRoles::hasAnyRole($user, self::privilegedRoles());
    }

    public static function userId(array $user = null) {
        $userId = (int) ($user['id'] ?? 0);
        return $userId > 0 ? $userId : null;
    }

    public static function allowedTaskTypes(array $user = null) {
        if (!self::isScopedOperationalUser($user)) {
            return [];
        }

        $allowed = [];
        foreach (UserRoles::extractRoles($user) as $role) {
            foreach (self::TASK_TYPES_BY_ROLE[$role] ?? [] as $taskType) {
                if (!in_array($taskType, $allowed, true)) {
                    $allowed[] = $taskType;
                }
            }
        }

        return $allowed;
    }

    public static function canAccessTaskType(array $user = null, $taskType = null) {
        if (!self::isScopedOperationalUser($user)) {
            return true;
        }

        $taskType = trim((string) $taskType);
        if ($taskType === '') {
            return false;
        }

        return in_array($taskType, self::allowedTaskTypes($user), true);
    }
}