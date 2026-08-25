<?php
class DolibarrModel extends Model {
    public function getConfig() {
        $keys = ['dolibarr_enabled', 'dolibarr_base_url', 'dolibarr_api_key', 'dolibarr_entity'];
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (' . $placeholders . ')');
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'enabled' => !empty($rows['dolibarr_enabled']) && $rows['dolibarr_enabled'] === '1',
            'base_url' => trim((string) ($rows['dolibarr_base_url'] ?? '')),
            'api_key' => trim((string) ($rows['dolibarr_api_key'] ?? '')),
            'entity' => trim((string) ($rows['dolibarr_entity'] ?? ''))
        ];
    }

    public function saveConfig(array $values) {
        $mapping = [
            'dolibarr_enabled' => !empty($values['enabled']) ? '1' : '0',
            'dolibarr_base_url' => trim((string) ($values['base_url'] ?? '')),
            'dolibarr_api_key' => trim((string) ($values['api_key'] ?? '')),
            'dolibarr_entity' => trim((string) ($values['entity'] ?? ''))
        ];

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($mapping as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
    }

    public function getUserMappingStats() {
        $stmt = $this->db->query('SELECT COUNT(*) AS total, SUM(CASE WHEN local_user_id IS NOT NULL THEN 1 ELSE 0 END) AS mapped FROM dolibarr_user_mappings');
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'mapped' => 0];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'mapped' => (int) ($row['mapped'] ?? 0),
            'unmapped' => max(0, (int) ($row['total'] ?? 0) - (int) ($row['mapped'] ?? 0))
        ];
    }

    public function getProjectMappingStats() {
        $stmt = $this->db->query('SELECT COUNT(*) AS total, SUM(CASE WHEN local_project_id IS NOT NULL THEN 1 ELSE 0 END) AS mapped FROM dolibarr_project_mappings');
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'mapped' => 0];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'mapped' => (int) ($row['mapped'] ?? 0),
            'unmapped' => max(0, (int) ($row['total'] ?? 0) - (int) ($row['mapped'] ?? 0))
        ];
    }

    public function getUserMappings($limit = 10) {
        $stmt = $this->db->prepare('SELECT m.*, u.nom AS local_user_name FROM dolibarr_user_mappings m LEFT JOIN users u ON u.id = m.local_user_id ORDER BY m.last_synced_at DESC, m.remote_name ASC LIMIT :limit');
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectMappings($limit = 10) {
        $stmt = $this->db->prepare('SELECT m.*, p.nom AS local_project_name FROM dolibarr_project_mappings m LEFT JOIN projets p ON p.id = m.local_project_id ORDER BY m.last_synced_at DESC, m.remote_title ASC LIMIT :limit');
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncUsers(array $remoteUsers) {
        $count = 0;
        $stmt = $this->db->prepare('INSERT INTO dolibarr_user_mappings (dolibarr_user_id, local_user_id, remote_login, remote_email, remote_name, remote_payload, last_synced_at) VALUES (:dolibarr_user_id, :local_user_id, :remote_login, :remote_email, :remote_name, :remote_payload, NOW()) ON DUPLICATE KEY UPDATE local_user_id = VALUES(local_user_id), remote_login = VALUES(remote_login), remote_email = VALUES(remote_email), remote_name = VALUES(remote_name), remote_payload = VALUES(remote_payload), last_synced_at = VALUES(last_synced_at)');

        foreach ($remoteUsers as $remoteUser) {
            $remoteId = (int) ($remoteUser['id'] ?? $remoteUser['rowid'] ?? 0);
            if ($remoteId <= 0) {
                continue;
            }

            $remoteEmail = trim((string) ($remoteUser['email'] ?? ''));
            $remoteLogin = trim((string) ($remoteUser['login'] ?? $remoteUser['ref'] ?? ''));
            $remoteName = trim((string) ($remoteUser['fullname'] ?? trim((string) (($remoteUser['firstname'] ?? '') . ' ' . ($remoteUser['lastname'] ?? '')))));
            if ($remoteName === '') {
                $remoteName = $remoteLogin !== '' ? $remoteLogin : ('Utilisateur #' . $remoteId);
            }

            $localUserId = $this->ensureLocalUserId($remoteEmail, $remoteLogin, $remoteName);

            $stmt->execute([
                'dolibarr_user_id' => $remoteId,
                'local_user_id' => $localUserId,
                'remote_login' => $remoteLogin !== '' ? $remoteLogin : null,
                'remote_email' => $remoteEmail !== '' ? $remoteEmail : null,
                'remote_name' => $remoteName,
                'remote_payload' => json_encode($remoteUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);
            $count++;
        }

        return $count;
    }

    public function syncProjects(array $remoteProjects) {
        $count = 0;
        $stmt = $this->db->prepare('INSERT INTO dolibarr_project_mappings (dolibarr_project_id, local_project_id, remote_ref, remote_title, remote_thirdparty, remote_status, remote_payload, last_synced_at) VALUES (:dolibarr_project_id, :local_project_id, :remote_ref, :remote_title, :remote_thirdparty, :remote_status, :remote_payload, NOW()) ON DUPLICATE KEY UPDATE local_project_id = VALUES(local_project_id), remote_ref = VALUES(remote_ref), remote_title = VALUES(remote_title), remote_thirdparty = VALUES(remote_thirdparty), remote_status = VALUES(remote_status), remote_payload = VALUES(remote_payload), last_synced_at = VALUES(last_synced_at)');

        foreach ($remoteProjects as $remoteProject) {
            $remoteId = (int) ($remoteProject['id'] ?? $remoteProject['rowid'] ?? 0);
            if ($remoteId <= 0) {
                continue;
            }

            $remoteTitle = trim((string) ($remoteProject['title'] ?? $remoteProject['label'] ?? $remoteProject['ref'] ?? ''));
            if ($remoteTitle === '') {
                $remoteTitle = 'Projet #' . $remoteId;
            }

            $stmt->execute([
                'dolibarr_project_id' => $remoteId,
                'local_project_id' => $this->findLocalProjectId($remoteTitle),
                'remote_ref' => trim((string) ($remoteProject['ref'] ?? '')) ?: null,
                'remote_title' => $remoteTitle,
                'remote_thirdparty' => trim((string) ($remoteProject['thirdparty']['name'] ?? $remoteProject['thirdparty_name'] ?? '')) ?: null,
                'remote_status' => trim((string) ($remoteProject['status_label'] ?? $remoteProject['status'] ?? '')) ?: null,
                'remote_payload' => json_encode($remoteProject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);
            $count++;
        }

        return $count;
    }

    private function findLocalUserId($email, $login) {
        if ($email !== '') {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                return (int) $userId;
            }
        }

        if ($login !== '') {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email LIKE :login OR nom = :name LIMIT 1');
            $stmt->execute([
                'login' => '%' . $login . '%',
                'name' => $login
            ]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                return (int) $userId;
            }
        }

        return null;
    }

    private function ensureLocalUserId($email, $login, $name) {
        $existingUserId = $this->findLocalUserId($email, $login);
        if ($existingUserId !== null) {
            return $existingUserId;
        }

        $resolvedEmail = $this->resolveImportedEmail($email, $login);
        $passwordSeed = function_exists('random_bytes') ? bin2hex(random_bytes(16)) : uniqid('dolibarr_', true);
        $passwordHash = password_hash($passwordSeed, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare('INSERT INTO users (nom, email, password, role, statut) VALUES (:nom, :email, :password, :role, :statut)');
        $stmt->execute([
            'nom' => $name,
            'email' => $resolvedEmail,
            'password' => $passwordHash,
            'role' => 'Clientele',
            'statut' => 'Inactif'
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function resolveImportedEmail($email, $login) {
        $baseEmail = trim((string) $email);
        if ($baseEmail === '' || strpos($baseEmail, '@') === false) {
            $slugSource = $login !== '' ? $login : 'user';
            $slug = preg_replace('/[^a-z0-9]+/i', '.', strtolower($slugSource));
            $slug = trim((string) $slug, '.');
            if ($slug === '') {
                $slug = 'user';
            }
            $baseEmail = 'dolibarr.' . $slug . '@import.local';
        }

        $candidate = $baseEmail;
        $counter = 1;
        while ($this->emailExists($candidate)) {
            $parts = explode('@', $baseEmail, 2);
            $localPart = $parts[0] ?? 'dolibarr.user';
            $domainPart = $parts[1] ?? 'import.local';
            $candidate = $localPart . '+' . $counter . '@' . $domainPart;
            $counter++;
        }

        return $candidate;
    }

    private function emailExists($email) {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    private function findLocalProjectId($title) {
        $stmt = $this->db->prepare('SELECT id FROM projets WHERE nom = :nom LIMIT 1');
        $stmt->execute(['nom' => $title]);
        $projectId = $stmt->fetchColumn();
        return $projectId ? (int) $projectId : null;
    }
}