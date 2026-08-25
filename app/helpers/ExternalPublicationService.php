<?php
class ExternalPublicationService {
    public static function pushPublication(array $payload) {
        // Les connecteurs actuels sont globaux : seul le tenant plateforme peut les utiliser.
        // Les autres organisations seront ouvertes lorsque les secrets seront scopes par tenant.
        if (TenantGuard::tenantId() !== 1) {
            return ['sent' => false, 'reason' => 'tenant_webhook_not_configured'];
        }
        $url = trim((string) PUBLICATION_API_WEBHOOK);
        if ($url === '') {
            $url = self::resolveWebhookFromSettings('publication');
        }
        return self::postJson($url, $payload);
    }

    public static function pushKpiCollection(array $payload) {
        if (TenantGuard::tenantId() !== 1) {
            return ['sent' => false, 'reason' => 'tenant_webhook_not_configured'];
        }
        $url = trim((string) KPI_COLLECTION_API_WEBHOOK);
        if ($url === '') {
            $url = self::resolveWebhookFromSettings('kpi');
        }
        return self::postJson($url, $payload);
    }

    private static function resolveWebhookFromSettings($key) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
            $stmt->execute(['setting_key' => 'api_integrations_config']);
            $value = $stmt->fetchColumn();
            if ($value === false || $value === null || $value === '') {
                return '';
            }
            $decoded = json_decode((string) $value, true);
            return trim((string) ($decoded['webhooks'][$key] ?? ''));
        } catch (Throwable $exception) {
            return '';
        }
    }

    private static function postJson($url, array $payload) {
        $url = trim((string) $url);
        if ($url === '') {
            return ['sent' => false, 'reason' => 'webhook_not_configured'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['sent' => false, 'reason' => 'json_encode_failed'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['sent' => false, 'reason' => $error !== '' ? $error : 'curl_exec_failed'];
        }

        return [
            'sent' => $status >= 200 && $status < 300,
            'status' => $status,
            'response' => $response,
        ];
    }
}
