<?php
require __DIR__ . '/../config/config.php';
$pdo = Database::getConnection();
$checks = [];
$pdo->beginTransaction();
try {
    $primaryTenantId = (int) $pdo->query("SELECT id FROM tenants WHERE slug='jaxe-ops' LIMIT 1")->fetchColumn();
    $primaryUser = $pdo->query('SELECT u.id,u.nom,u.email,u.role,u.secondary_roles FROM users u JOIN tenant_memberships tm ON tm.user_id=u.id WHERE tm.tenant_id=' . $primaryTenantId . ' ORDER BY u.id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user'] = $primaryUser;
    $_SESSION['tenant_id'] = $primaryTenantId;

    $slug = 'isolation-test-' . bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT INTO tenants (name,slug,status,plan_code) VALUES ('Isolation Test',:slug,'Actif','test')");
    $stmt->execute(['slug' => $slug]);
    $foreignTenantId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO clients (tenant_id,nom,entreprise,statut) VALUES (:tenant_id,'Foreign Client','Foreign Co','Actif')");
    $stmt->execute(['tenant_id' => $foreignTenantId]);
    $foreignClientId = (int) $pdo->lastInsertId();

    $foreignEmail = 'foreign-' . bin2hex(random_bytes(4)) . '@isolation.test';
    $stmt = $pdo->prepare("INSERT INTO users (nom,email,password,role,statut) VALUES ('Foreign User',:email,:password,'Clientele','Actif')");
    $stmt->execute(['email' => $foreignEmail, 'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT)]);
    $foreignUserId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,membership_role,status) VALUES (:tenant_id,:user_id,'Member','Actif')");
    $stmt->execute(['tenant_id' => $foreignTenantId, 'user_id' => $foreignUserId]);

    $stmt = $pdo->prepare("INSERT INTO projets (client_id,nom,type_projet,date_debut,date_fin,statut) VALUES (:client_id,'Foreign Project','Test','2026-01-01','2026-01-31','Brouillon')");
    $stmt->execute(['client_id' => $foreignClientId]);
    $foreignProjectId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO client_social_accounts (client_id,reseau,compte_label,statut) VALUES (:client_id,'facebook','Foreign Social','Actif')");
    $stmt->execute(['client_id' => $foreignClientId]);
    $foreignSocialId = (int) $pdo->lastInsertId();

    $checks['foreign_client_get_denied'] = (new CrudModel(ModuleRegistry::get('client')))->getById($foreignClientId) === null;
    $checks['foreign_user_get_denied'] = (new CrudModel(ModuleRegistry::get('user')))->getById($foreignUserId) === null;
    $checks['foreign_project_get_denied'] = (new ProjectModel())->getById($foreignProjectId) === null;
    $checks['foreign_clients_filtered_from_list'] = !in_array($foreignClientId, array_map('intval', array_column((new CrudModel(ModuleRegistry::get('client')))->getAll(), 'id')), true);
    try {
        (new ClientSocialAccountModel())->getById($foreignSocialId, $foreignClientId);
        $checks['foreign_social_account_denied'] = false;
    } catch (RuntimeException $e) {
        $checks['foreign_social_account_denied'] = true;
    }

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
echo json_encode(['checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed ? 1 : 0);
