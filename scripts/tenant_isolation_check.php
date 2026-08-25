<?php
require __DIR__ . '/../config/config.php';
$pdo = Database::getConnection();
$tenantId = (int) $pdo->query("SELECT id FROM tenants WHERE slug='jaxe-ops' LIMIT 1")->fetchColumn();
$user = $pdo->query('SELECT id,nom,email,role,secondary_roles FROM users ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$_SESSION['user'] = $user;
$_SESSION['tenant_id'] = $tenantId;
$checks = [];
$checks['tenant_resolved'] = TenantGuard::tenantId() === $tenantId;
$ownClient = $pdo->query('SELECT * FROM clients WHERE tenant_id=' . $tenantId . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$checks['own_client_allowed'] = $ownClient ? TenantGuard::canAccessRecord('clients', $ownClient) : true;
$foreign = $ownClient ?: ['id' => 999999];
$foreign['tenant_id'] = $tenantId + 999999;
$checks['foreign_client_denied'] = !TenantGuard::canAccessRecord('clients', $foreign);
$checks['cross_user_denied'] = !TenantGuard::canAccessRecord('users', ['id' => 999999]);
$checks['all_clients_backfilled'] = (int) $pdo->query('SELECT COUNT(*) FROM clients WHERE tenant_id IS NULL OR organization_id IS NULL')->fetchColumn() === 0;
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
echo json_encode(['checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed ? 1 : 0);
