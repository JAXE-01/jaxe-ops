<?php
class PermissionModel extends Model {
    private static $definitions = [
        'dashboard.view' => ['label' => 'Voir le dashboard', 'group' => 'Cockpit'],
        'calendar.view' => ['label' => 'Voir le pilotage client', 'group' => 'Cockpit'],
        'clients.view' => ['label' => 'Voir les clients', 'group' => 'References'],
        'clients.manage' => ['label' => 'Modifier les clients', 'group' => 'References'],
        'projects.view' => ['label' => 'Voir les projets', 'group' => 'Operations'],
        'projects.manage' => ['label' => 'Modifier les projets et le pipeline', 'group' => 'Operations'],
        'content.view' => ['label' => 'Voir les contenus', 'group' => 'Production'],
        'content.manage' => ['label' => 'Modifier les contenus', 'group' => 'Production'],
        'strategy.view' => ['label' => 'Voir les modules marketing', 'group' => 'Strategie'],
        'strategy.manage' => ['label' => 'Modifier les modules marketing', 'group' => 'Strategie'],
        'settings.view' => ['label' => 'Voir les parametres', 'group' => 'Administration'],
        'settings.manage' => ['label' => 'Modifier les parametres', 'group' => 'Administration'],
        'users.view' => ['label' => 'Voir les utilisateurs', 'group' => 'Administration'],
        'users.manage' => ['label' => 'Modifier les utilisateurs', 'group' => 'Administration'],
        'subscriptions.view' => ['label' => 'Voir les abonnements', 'group' => 'Administration'],
        'subscriptions.manage' => ['label' => 'Modifier les abonnements', 'group' => 'Administration'],
        'reporting.view' => ['label' => 'Voir les modules de reporting', 'group' => 'Administration'],
        'reporting.manage' => ['label' => 'Modifier les modules de reporting', 'group' => 'Administration'],
        'integrations.view' => ['label' => 'Voir les integrations', 'group' => 'Integrations'],
        'integrations.manage' => ['label' => 'Configurer et synchroniser les integrations', 'group' => 'Integrations'],
        'publishing.view' => ['label' => 'Voir le studio de publication', 'group' => 'Publication sociale'],
        'publishing.manage' => ['label' => 'Composer et programmer des publications', 'group' => 'Publication sociale'],
        'publishing.approve' => ['label' => 'Approuver les publications', 'group' => 'Publication sociale']
    ];

    private static $moduleMap = [
        'client' => 'clients',
        'projet' => 'projects',
        'plan-mensuel' => 'projects',
        'livrable-item' => 'projects',
        'tache-pipeline' => 'projects',
        'contenu' => 'content',
        'brief' => 'content',
        'calendrier-contenu' => 'content',
        'offre' => 'strategy',
        'persona' => 'strategy',
        'message-marketing' => 'strategy',
        'campagne' => 'strategy',
        'tunnel-conversion' => 'strategy',
        'user' => 'users',
        'abonnement' => 'subscriptions',
        'reporting' => 'reporting',
        'reporting-metric' => 'reporting',
        'social-publishing' => 'publishing'
    ];

    public function getPermissionDefinitions() {
        return self::$definitions;
    }

    public function getGroupedDefinitions() {
        $grouped = [];
        foreach (self::$definitions as $key => $meta) {
            $group = $meta['group'];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$key] = $meta;
        }

        return $grouped;
    }

    public function getRoles() {
        $options = ModuleRegistry::get('user')['formFields']['role']['options'] ?? [];
        return array_keys($options);
    }

    public function getRolePermissionMatrix() {
        $matrix = $this->getDefaultRoleMatrix();
        $stmt = $this->db->query('SELECT role, permission_key, allowed FROM role_permissions');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($matrix[$row['role']]) || !isset(self::$definitions[$row['permission_key']])) {
                continue;
            }
            $matrix[$row['role']][$row['permission_key']] = (bool) $row['allowed'];
        }

        return $matrix;
    }

    public function saveRolePermissionMatrix(array $submittedMatrix) {
        $roles = $this->getRoles();
        $permissions = array_keys(self::$definitions);

        $this->db->beginTransaction();
        try {
            $this->db->exec('DELETE FROM role_permissions');
            $stmt = $this->db->prepare('INSERT INTO role_permissions (role, permission_key, allowed) VALUES (:role, :permission_key, :allowed)');
            foreach ($roles as $role) {
                foreach ($permissions as $permissionKey) {
                    $stmt->execute([
                        'role' => $role,
                        'permission_key' => $permissionKey,
                        'allowed' => !empty($submittedMatrix[$role][$permissionKey]) ? 1 : 0
                    ]);
                }
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function getUserOverrideMatrix($userId) {
        $userId = (int) $userId;
        $overrides = [];
        foreach (array_keys(self::$definitions) as $permissionKey) {
            $overrides[$permissionKey] = 'inherit';
        }

        if ($userId <= 0) {
            return $overrides;
        }

        $stmt = $this->db->prepare('SELECT permission_key, allowed FROM user_permissions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($overrides[$row['permission_key']])) {
                continue;
            }
            $overrides[$row['permission_key']] = (int) $row['allowed'] === 1 ? 'allow' : 'deny';
        }

        return $overrides;
    }

    public function saveUserOverrideMatrix($userId, array $submittedOverrides) {
        $userId = (int) $userId;
        if ($userId <= 0) {
            throw new RuntimeException('Selectionne un utilisateur pour enregistrer une surcharge.');
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Utilisateur introuvable pour la surcharge.');
        }

        $this->db->beginTransaction();
        try {
            $deleteStmt = $this->db->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
            $deleteStmt->execute(['user_id' => $userId]);

            $insertStmt = $this->db->prepare('INSERT INTO user_permissions (user_id, permission_key, allowed) VALUES (:user_id, :permission_key, :allowed)');
            foreach (array_keys(self::$definitions) as $permissionKey) {
                $mode = $submittedOverrides[$permissionKey] ?? 'inherit';
                if ($mode === 'inherit') {
                    continue;
                }
                $insertStmt->execute([
                    'user_id' => $userId,
                    'permission_key' => $permissionKey,
                    'allowed' => $mode === 'allow' ? 1 : 0
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function userHasPermission(array $user = null, $permissionKey = '') {
        if ($user === null || empty($user['id'])) {
            return false;
        }

        if (!isset(self::$definitions[$permissionKey])) {
            return false;
        }

        $userOverride = $this->getUserOverride($user['id'], $permissionKey);
        if ($userOverride !== null) {
            return $userOverride;
        }

        $matrix = $this->getRolePermissionMatrix();
        foreach (UserRoles::extractRoles($user) as $role) {
            if (!empty($matrix[$role][$permissionKey])) {
                return true;
            }
        }

        return false;
    }

    public static function resolveModulePermission($moduleKey, $action = 'view') {
        $base = self::$moduleMap[$moduleKey] ?? null;
        if ($base === null) {
            return null;
        }

        return $base . '.' . ($action === 'manage' ? 'manage' : 'view');
    }

    private function getUserOverride($userId, $permissionKey) {
        $stmt = $this->db->prepare('SELECT allowed FROM user_permissions WHERE user_id = :user_id AND permission_key = :permission_key LIMIT 1');
        $stmt->execute([
            'user_id' => (int) $userId,
            'permission_key' => $permissionKey
        ]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }

        return (int) $value === 1;
    }

    private function getDefaultRoleMatrix() {
        $permissions = array_fill_keys(array_keys(self::$definitions), false);
        $matrix = [];
        foreach ($this->getRoles() as $role) {
            $matrix[$role] = $permissions;
        }

        $defaults = [
            'Admin' => array_keys(self::$definitions),
            'CC' => ['dashboard.view', 'calendar.view', 'clients.view', 'clients.manage', 'projects.view', 'projects.manage', 'content.view', 'strategy.view', 'strategy.manage', 'settings.view', 'subscriptions.view', 'reporting.view', 'integrations.view', 'publishing.view', 'publishing.approve'],
            'Clientele' => ['dashboard.view', 'calendar.view', 'clients.view', 'projects.view', 'content.view'],
            'CM' => ['dashboard.view', 'calendar.view', 'clients.view', 'projects.view', 'content.view', 'content.manage', 'strategy.view', 'reporting.view', 'publishing.view', 'publishing.manage'],
            'Createur' => ['dashboard.view', 'calendar.view'],
            'Cadreur' => ['dashboard.view', 'calendar.view'],
            'Designer' => ['dashboard.view', 'calendar.view'],
            'Videaste' => ['dashboard.view', 'calendar.view']
        ];

        foreach ($defaults as $role => $allowedPermissions) {
            if (!isset($matrix[$role])) {
                continue;
            }
            foreach ($allowedPermissions as $permissionKey) {
                if (isset($matrix[$role][$permissionKey])) {
                    $matrix[$role][$permissionKey] = true;
                }
            }
        }

        return $matrix;
    }
}