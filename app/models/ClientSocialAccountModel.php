<?php
class ClientSocialAccountModel extends Model {
    public function getByClientId($clientId) {
        TenantGuard::assertClient((int) $clientId);
        $stmt = $this->db->prepare("SELECT * FROM client_social_accounts WHERE client_id = :client_id ORDER BY FIELD(statut, 'Actif', 'Inactif'), is_default DESC, reseau ASC, compte_label ASC, id DESC");
        $stmt->execute(['client_id' => (int) $clientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['access_token'] = CryptoService::decrypt((string) ($row['access_token'] ?? ''));
            $row['refresh_token'] = CryptoService::decrypt((string) ($row['refresh_token'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    public function getById($id, $clientId = null) {
        if ($clientId !== null) { TenantGuard::assertClient((int) $clientId); }
        $sql = 'SELECT * FROM client_social_accounts WHERE id = :id';
        $params = ['id' => (int) $id];
        if ($clientId !== null) {
            $sql .= ' AND client_id = :client_id';
            $params['client_id'] = (int) $clientId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        $row['access_token'] = CryptoService::decrypt((string) ($row['access_token'] ?? ''));
        $row['refresh_token'] = CryptoService::decrypt((string) ($row['refresh_token'] ?? ''));
        return $row;
    }

    public function save(array $payload) {
        $id = (int) ($payload['id'] ?? 0);
        $clientId = (int) ($payload['client_id'] ?? 0);
        if ($clientId <= 0) {
            throw new RuntimeException('Client invalide pour le compte social.');
        }
        TenantGuard::assertClient($clientId);

        $data = [
            'client_id' => $clientId,
            'reseau' => trim((string) ($payload['reseau'] ?? '')),
            'compte_label' => trim((string) ($payload['compte_label'] ?? '')),
            'identifiant_compte' => trim((string) ($payload['identifiant_compte'] ?? '')),
            'page_id' => trim((string) ($payload['page_id'] ?? '')),
            'page_nom' => trim((string) ($payload['page_nom'] ?? '')),
            'access_token' => CryptoService::encrypt(trim((string) ($payload['access_token'] ?? ''))),
            'refresh_token' => CryptoService::encrypt(trim((string) ($payload['refresh_token'] ?? ''))),
            'statut' => trim((string) ($payload['statut'] ?? 'Actif')),
            'is_default' => !empty($payload['is_default']) ? 1 : 0,
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];

        if ($data['reseau'] === '') {
            throw new RuntimeException('Reseau obligatoire.');
        }

        if ($data['compte_label'] === '') {
            $data['compte_label'] = ucfirst($data['reseau']) . ' compte';
        }

        if (!in_array($data['statut'], ['Actif', 'Inactif'], true)) {
            $data['statut'] = 'Actif';
        }

        if ($data['is_default'] === 1) {
            $this->clearDefaultForClientNetwork($clientId, $data['reseau'], $id);
        }

        if ($id > 0) {
            $stmt = $this->db->prepare("UPDATE client_social_accounts
                SET reseau = :reseau,
                    compte_label = :compte_label,
                    identifiant_compte = :identifiant_compte,
                    page_id = :page_id,
                    page_nom = :page_nom,
                    access_token = :access_token,
                    refresh_token = :refresh_token,
                    statut = :statut,
                    is_default = :is_default,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id AND client_id = :client_id");
            $stmt->execute([
                'reseau' => $data['reseau'],
                'compte_label' => $data['compte_label'],
                'identifiant_compte' => $data['identifiant_compte'] !== '' ? $data['identifiant_compte'] : null,
                'page_id' => $data['page_id'] !== '' ? $data['page_id'] : null,
                'page_nom' => $data['page_nom'] !== '' ? $data['page_nom'] : null,
                'access_token' => $data['access_token'] !== '' ? $data['access_token'] : null,
                'refresh_token' => $data['refresh_token'] !== '' ? $data['refresh_token'] : null,
                'statut' => $data['statut'],
                'is_default' => $data['is_default'],
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'id' => $id,
                'client_id' => $clientId,
            ]);
            return $id;
        }

        $stmt = $this->db->prepare("INSERT INTO client_social_accounts
            (client_id, reseau, compte_label, identifiant_compte, page_id, page_nom, access_token, refresh_token, statut, is_default, notes)
            VALUES
            (:client_id, :reseau, :compte_label, :identifiant_compte, :page_id, :page_nom, :access_token, :refresh_token, :statut, :is_default, :notes)");
        $stmt->execute([
            'client_id' => $clientId,
            'reseau' => $data['reseau'],
            'compte_label' => $data['compte_label'],
            'identifiant_compte' => $data['identifiant_compte'] !== '' ? $data['identifiant_compte'] : null,
            'page_id' => $data['page_id'] !== '' ? $data['page_id'] : null,
            'page_nom' => $data['page_nom'] !== '' ? $data['page_nom'] : null,
            'access_token' => $data['access_token'] !== '' ? $data['access_token'] : null,
            'refresh_token' => $data['refresh_token'] !== '' ? $data['refresh_token'] : null,
            'statut' => $data['statut'],
            'is_default' => $data['is_default'],
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function delete($id, $clientId) {
        TenantGuard::assertClient((int) $clientId);
        $stmt = $this->db->prepare('DELETE FROM client_social_accounts WHERE id = :id AND client_id = :client_id');
        $stmt->execute(['id' => (int) $id, 'client_id' => (int) $clientId]);
        return (int) $stmt->rowCount();
    }

    private function clearDefaultForClientNetwork($clientId, $network, $excludeId = 0) {
        $sql = 'UPDATE client_social_accounts SET is_default = 0 WHERE client_id = :client_id AND reseau = :reseau';
        $params = ['client_id' => (int) $clientId, 'reseau' => (string) $network];
        if ((int) $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = (int) $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}
