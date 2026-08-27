<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require dirname(__DIR__) . '/config/config.php';

$pdo = Database::getConnection();
$connections = $pdo->query(
    'SELECT provider,status,COUNT(*) total FROM social_connections GROUP BY provider,status ORDER BY provider,status'
)->fetchAll(PDO::FETCH_ASSOC);
$result = [
    'configuration' => [
        'client_id' => trim((string) config_env_value('META_CLIENT_ID', '')) !== '',
        'client_secret' => trim((string) config_env_value('META_CLIENT_SECRET', '')) !== '',
        'encryption_key' => defined('APP_ENCRYPTION_KEY') && strlen((string) APP_ENCRYPTION_KEY) >= 32,
        'graph_version' => (string) config_env_value('META_GRAPH_VERSION', ''),
        'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) config_env_value('META_OAUTH_SCOPES', ''))))),
    ],
    'connections' => $connections,
    'publications' => (int) $pdo->query('SELECT COUNT(*) FROM social_publications')->fetchColumn(),
    'targets' => (int) $pdo->query('SELECT COUNT(*) FROM social_publication_targets')->fetchColumn(),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
