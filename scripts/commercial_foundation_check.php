<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$pdo = Database::getConnection();
$checks = [];
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$checks['team_invitation_table'] = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='team_invitation_tokens'"
)->fetchColumn();
$checks['registered_clients_have_profile'] = ((int) $pdo->query(
    "SELECT COUNT(*) FROM organizations o LEFT JOIN clients c ON c.organization_id=o.id WHERE o.account_type='ClientCompany' AND o.registration_state='Registered' AND c.id IS NULL"
)->fetchColumn()) === 0;
$checks['production_schema_autosync_disabled'] = APP_ENV !== 'production' || empty($GLOBALS['config']['auto_sync_schema']);

$failed = array_keys(array_filter($checks, static fn ($value) => !$value));
echo json_encode([
    'database' => $database,
    'checks' => $checks,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed ? 1 : 0);
