<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$limit = isset($argv[1]) ? max(1, min(100, (int) $argv[1])) : 25;
$db = Database::getConnection();
$tenantIds = $db->query('SELECT DISTINCT tenant_id FROM social_publications WHERE tenant_id IS NOT NULL')
    ->fetchAll(PDO::FETCH_COLUMN);

$collector = new SocialMetricsCollectorService();
$summary = ['tenants' => 0, 'collected' => 0, 'failed' => 0, 'errors' => []];

foreach ($tenantIds as $tenantId) {
    $result = $collector->collectPublished((int) $tenantId, null, $limit);
    $summary['tenants']++;
    $summary['collected'] += (int) ($result['collected'] ?? 0);
    $summary['failed'] += (int) ($result['failed'] ?? 0);
    foreach ((array) ($result['errors'] ?? []) as $error) {
        $summary['errors'][] = ['tenant_id' => (int) $tenantId, 'message' => (string) $error];
    }
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
