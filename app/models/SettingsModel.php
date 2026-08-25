<?php
class SettingsModel extends Model {
    public function getKpiNetworksConfig() {
        $default = $this->getDefaultKpiNetworksConfig();

        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => 'kpi_networks_config']);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        $normalized = $this->normalizeKpiNetworksConfig($decoded);
        return !empty($normalized) ? $normalized : $default;
    }

    public function saveKpiNetworksConfigFromJson($rawJson) {
        $decoded = json_decode((string) $rawJson, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Le JSON KPI est invalide.');
        }

        $normalized = $this->normalizeKpiNetworksConfig($decoded);
        if (empty($normalized)) {
            throw new RuntimeException('Aucun reseau KPI valide trouve dans la configuration.');
        }

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'setting_key' => 'kpi_networks_config',
            'setting_value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function getDefaultKpiNetworksConfig() {
        $path = __DIR__ . '/../../config/kpi_networks.php';
        if (!file_exists($path)) {
            return [];
        }

        $config = require $path;
        return is_array($config) ? $config : [];
    }

    public function getBrandingConfig() {
        $default = [
            'app_name' => 'Strax',
            'logo_url' => '',
            'brand_caption' => 'Operations editoriales et pilotage client',
        ];

        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => 'branding_config']);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return [
            'app_name' => $this->sanitizeBrandingText($decoded['app_name'] ?? $default['app_name'], $default['app_name'], 80),
            'logo_url' => $this->sanitizeBrandingText($decoded['logo_url'] ?? $default['logo_url'], $default['logo_url'], 255),
            'brand_caption' => $this->sanitizeBrandingText($decoded['brand_caption'] ?? $default['brand_caption'], $default['brand_caption'], 160),
        ];
    }

    public function saveBrandingConfig(array $config) {
        $payload = [
            'app_name' => $this->sanitizeBrandingText($config['app_name'] ?? '', 'Strax', 80),
            'logo_url' => $this->sanitizeBrandingText($config['logo_url'] ?? '', '', 255),
            'brand_caption' => $this->sanitizeBrandingText($config['brand_caption'] ?? '', 'Operations editoriales et pilotage client', 160),
        ];

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'setting_key' => 'branding_config',
            'setting_value' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function getCalendarColorScheme() {
        $default = $this->getDefaultCalendarColorScheme();

        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => 'calendar_color_scheme']);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        $merged = [];
        foreach ($default as $stateKey => $palette) {
            $rawState = is_array($decoded[$stateKey] ?? null) ? $decoded[$stateKey] : [];
            $merged[$stateKey] = [
                'label' => (string) ($palette['label'] ?? $stateKey),
                'bg' => $this->sanitizeHexColor($rawState['bg'] ?? $palette['bg'] ?? '#ffffff', $palette['bg'] ?? '#ffffff'),
                'border' => $this->sanitizeHexColor($rawState['border'] ?? $palette['border'] ?? '#dddddd', $palette['border'] ?? '#dddddd'),
                'text' => $this->sanitizeHexColor($rawState['text'] ?? $palette['text'] ?? '#111111', $palette['text'] ?? '#111111'),
            ];
        }

        return $merged;
    }

    public function saveCalendarColorScheme(array $rawScheme) {
        $default = $this->getDefaultCalendarColorScheme();
        $normalized = [];

        foreach ($default as $stateKey => $palette) {
            $stateInput = is_array($rawScheme[$stateKey] ?? null) ? $rawScheme[$stateKey] : [];
            $normalized[$stateKey] = [
                'label' => (string) ($palette['label'] ?? $stateKey),
                'bg' => $this->sanitizeHexColor($stateInput['bg'] ?? $palette['bg'] ?? '#ffffff', $palette['bg'] ?? '#ffffff'),
                'border' => $this->sanitizeHexColor($stateInput['border'] ?? $palette['border'] ?? '#dddddd', $palette['border'] ?? '#dddddd'),
                'text' => $this->sanitizeHexColor($stateInput['text'] ?? $palette['text'] ?? '#111111', $palette['text'] ?? '#111111'),
            ];
        }

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'setting_key' => 'calendar_color_scheme',
            'setting_value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function resetCalendarColorScheme() {
        $stmt = $this->db->prepare('DELETE FROM app_settings WHERE setting_key = :setting_key');
        $stmt->execute(['setting_key' => 'calendar_color_scheme']);
    }

    public function getDefaultCalendarColorScheme() {
        return [
            'retard' => ['label' => 'Contenu en retard', 'bg' => '#F3E4E6', 'border' => '#CC7A82', 'text' => '#8B3A41'],
            'non_rempli' => ['label' => 'Fiche non remplie', 'bg' => '#E9EFF5', 'border' => '#AAB7C5', 'text' => '#5D6E80'],
            'brief_attente' => ['label' => 'Brief / Script en attente', 'bg' => '#FFF0D9', 'border' => '#E1A64F', 'text' => '#A96815'],
            'tournage_attente' => ['label' => 'Tournage en attente', 'bg' => '#FFE7C7', 'border' => '#E89A3C', 'text' => '#B86612'],
            'montage_attente' => ['label' => 'Montage en attente', 'bg' => '#FFF6C8', 'border' => '#D9BE4E', 'text' => '#8F7800'],
            'production_attente' => ['label' => 'Production visuel en attente', 'bg' => '#FFF9DA', 'border' => '#E1C96A', 'text' => '#847100'],
            'validation_attente' => ['label' => 'Validation en attente', 'bg' => '#E5F3FF', 'border' => '#8BC4F7', 'text' => '#0D6FB8'],
            'publication_attente' => ['label' => 'Publication en attente', 'bg' => '#DCEBFA', 'border' => '#7BA7D4', 'text' => '#1C5D8F'],
            'publie' => ['label' => 'Publie', 'bg' => '#E4F6EC', 'border' => '#8DCFAF', 'text' => '#2D9B6E'],
            'premiere_collecte' => ['label' => 'Premiere collecte effectuee', 'bg' => '#E5F1E9', 'border' => '#8FB8A8', 'text' => '#3E7F67'],
        ];
    }

    public function getApiIntegrationsConfig() {
        $default = [
            'facebook' => ['mode' => 'oauth', 'app_id' => '', 'app_secret' => '', 'access_token' => ''],
            'linkedin' => ['mode' => 'oauth', 'client_id' => '', 'client_secret' => '', 'access_token' => ''],
            'instagram' => ['mode' => 'oauth', 'access_token' => ''],
            'tiktok' => ['mode' => 'direct', 'username' => '', 'password' => ''],
            'youtube' => ['mode' => 'direct', 'username' => '', 'password' => ''],
            'whatsapp' => ['mode' => 'direct', 'username' => '', 'password' => ''],
            'webhooks' => ['publication' => '', 'kpi' => ''],
        ];

        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => 'api_integrations_config']);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return array_replace_recursive($default, $decoded);
    }

    public function saveApiIntegrationsConfig(array $config) {
        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'setting_key' => 'api_integrations_config',
            'setting_value' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function getProjectTypeOptions() {
        return $this->getListSetting('project_type_options', ['SEA ponctuel', 'Abonnement mensuel', 'Abonnement mixte']);
    }

    public function getSubscriptionTypeOptions() {
        return $this->getListSetting('subscription_type_options', ['Abonnement mensuel', 'Abonnement mixte']);
    }

    public function getContentObjectiveOptions() {
        return $this->getListSetting('content_objective_options', ['Attirer', 'Eduquer', 'Donner confiance', 'Pousser a l achat']);
    }

    public function saveProjectTypeOptions($rawValue) {
        $this->saveListSetting('project_type_options', $rawValue, ['SEA ponctuel']);
    }

    public function saveSubscriptionTypeOptions($rawValue) {
        $this->saveListSetting('subscription_type_options', $rawValue, ['Abonnement mensuel']);
    }

    public function saveContentObjectiveOptions($rawValue) {
        $this->saveListSetting('content_objective_options', $rawValue);
    }

    public function getWorkflowRulesConfig() {
        $default = [
            'require_second_montage_video' => false,
        ];

        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => 'workflow_rules_config']);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return [
            'require_second_montage_video' => !empty($decoded['require_second_montage_video']),
        ];
    }

    public function saveWorkflowRulesConfig(array $config) {
        $payload = [
            'require_second_montage_video' => !empty($config['require_second_montage_video']),
        ];

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'setting_key' => 'workflow_rules_config',
            'setting_value' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function getUserOptionsByRoles(array $roles) {
        if (empty($roles)) {
            return [];
        }

        $roleLabels = ModuleRegistry::roleOptions();

        $placeholders = [];
        $params = [];
        foreach (array_values($roles) as $index => $role) {
            $key = 'role_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $role;
        }

        $roleConditions = ['role IN (' . implode(', ', $placeholders) . ')'];
        foreach (array_keys($params) as $key) {
            $roleConditions[] = 'FIND_IN_SET(:' . $key . ', REPLACE(COALESCE(secondary_roles, \'\'), \' \' , \'\')) > 0';
        }

        $sql = sprintf(
            'SELECT id, nom, role, secondary_roles FROM users WHERE statut = :statut AND (%s) ORDER BY nom ASC',
            implode(' OR ', $roleConditions)
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(['statut' => 'Actif'], $params));

        $options = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $labels = UserRoles::labels($row);
            $options[(string) $row['id']] = $row['nom'] . ' · ' . implode(' / ', $labels);
        }

        return $options;
    }

    public function getProjectDefaults() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('default_charge_compte_id', 'default_charge_clientele_id', 'default_cm_id', 'default_createur_id', 'default_cadreur_id', 'default_videaste_id')");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'charge_compte_id' => $this->normalizeId($rows['default_charge_compte_id'] ?? null),
            'charge_clientele_id' => $this->normalizeId($rows['default_charge_clientele_id'] ?? null),
            'cm_id' => $this->normalizeId($rows['default_cm_id'] ?? null),
            'createur_id' => $this->normalizeId($rows['default_createur_id'] ?? null),
            'cadreur_id' => $this->normalizeId($rows['default_cadreur_id'] ?? null),
            'videaste_id' => $this->normalizeId($rows['default_videaste_id'] ?? null)
        ];
    }

    public function saveProjectDefaults(array $values) {
        $mapping = [
            'default_charge_compte_id' => $this->normalizeId($values['charge_compte_id'] ?? null),
            'default_charge_clientele_id' => $this->normalizeId($values['charge_clientele_id'] ?? null),
            'default_cm_id' => $this->normalizeId($values['cm_id'] ?? null),
            'default_createur_id' => $this->normalizeId($values['createur_id'] ?? null),
            'default_cadreur_id' => $this->normalizeId($values['cadreur_id'] ?? null),
            'default_videaste_id' => $this->normalizeId($values['videaste_id'] ?? null)
        ];

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($mapping as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => $value === null ? null : (string) $value
            ]);
        }
    }

    public function resetProjectDefaults() {
        $stmt = $this->db->prepare("UPDATE app_settings SET setting_value = NULL WHERE setting_key IN ('default_charge_compte_id', 'default_charge_clientele_id', 'default_cm_id', 'default_createur_id', 'default_cadreur_id', 'default_videaste_id')");
        $stmt->execute();
    }

    public function getProjectRoleFieldMap() {
        return [
            'charge_compte_id' => ['Admin', 'CC'],
            'charge_clientele_id' => ['Admin', 'Clientele'],
            'cm_id' => ['Admin', 'CM'],
            'createur_id' => ['Admin', 'Createur', 'Designer'],
            'cadreur_id' => ['Admin', 'Cadreur', 'Videaste'],
            'videaste_id' => ['Admin', 'Videaste'],
            'designer_id' => ['Admin', 'Designer', 'Createur']
        ];
    }

    private function normalizeId($value) {
        $normalized = (int) $value;
        return $normalized > 0 ? $normalized : null;
    }

    private function getListSetting($key, array $fallback) {
        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $fallback;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $fallback;
        }

        $cleaned = [];
        foreach ($decoded as $item) {
            $label = trim((string) $item);
            if ($label !== '' && !in_array($label, $cleaned, true)) {
                $cleaned[] = $label;
            }
        }

        return !empty($cleaned) ? $cleaned : $fallback;
    }

    private function saveListSetting($key, $rawValue, array $requiredValues = []) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $rawValue);
        $cleaned = [];
        foreach ($lines as $line) {
            $label = trim((string) $line);
            if ($label !== '' && !in_array($label, $cleaned, true)) {
                $cleaned[] = $label;
            }
        }

        foreach ($requiredValues as $required) {
            if (!in_array($required, $cleaned, true)) {
                $cleaned[] = $required;
            }
        }

        if (empty($cleaned)) {
            throw new RuntimeException('La liste ne peut pas etre vide.');
        }

        $stmt = $this->db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => json_encode(array_values($cleaned), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
    }

    private function sanitizeHexColor($value, $fallback) {
        $hex = strtoupper(trim((string) $value));
        if (preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return $hex;
        }
        return strtoupper((string) $fallback);
    }

    private function sanitizeBrandingText($value, $fallback, $maxLength) {
        $text = trim((string) $value);
        if ($text === '') {
            $text = (string) $fallback;
        }
        return mb_substr($text, 0, (int) $maxLength);
    }

    private function normalizeKpiNetworksConfig(array $config) {
        $normalized = [];

        foreach ($config as $networkKey => $meta) {
            $key = strtolower(trim((string) $networkKey));
            $key = preg_replace('/[^a-z0-9_-]+/', '', $key);
            if ($key === '' || !is_array($meta)) {
                continue;
            }

            $label = trim((string) ($meta['label'] ?? ucfirst($key)));
            if ($label === '') {
                $label = ucfirst($key);
            }

            $kpis = [];
            foreach ((array) ($meta['kpis'] ?? []) as $kpi) {
                if (!is_array($kpi)) {
                    continue;
                }

                $name = strtolower(trim((string) ($kpi['name'] ?? '')));
                $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
                if ($name === '') {
                    continue;
                }

                $type = strtolower(trim((string) ($kpi['type'] ?? 'integer')));
                if (!in_array($type, ['integer', 'float'], true)) {
                    $type = 'integer';
                }

                $entry = [
                    'name' => $name,
                    'label' => trim((string) ($kpi['label'] ?? $name)),
                    'type' => $type,
                ];

                $column = trim((string) ($kpi['column'] ?? ''));
                if ($column !== '') {
                    $entry['column'] = $column;
                }

                $placeholder = trim((string) ($kpi['placeholder'] ?? ''));
                if ($placeholder !== '') {
                    $entry['placeholder'] = $placeholder;
                }

                $kpis[] = $entry;
            }

            if (empty($kpis)) {
                continue;
            }

            $allowedNames = array_values(array_map(static function ($kpi) {
                return (string) ($kpi['name'] ?? '');
            }, $kpis));
            $weights = [];
            foreach ((array) ($meta['weights'] ?? []) as $metricName => $weight) {
                $metricKey = strtolower(trim((string) $metricName));
                $metricKey = preg_replace('/[^a-z0-9_]+/', '_', $metricKey);
                if ($metricKey === '' || !in_array($metricKey, $allowedNames, true)) {
                    continue;
                }

                $numericWeight = (float) $weight;
                if ($numericWeight <= 0) {
                    continue;
                }
                $weights[$metricKey] = round($numericWeight, 6);
            }

            $normalized[$key] = [
                'label' => $label,
                'kpis' => $kpis,
            ];

            if (!empty($weights)) {
                $normalized[$key]['weights'] = $weights;
            }
        }

        return $normalized;
    }
}