<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/create_platform_admin.php adresse@email.tld\n");
    exit(2);
}

function read_hidden_password(string $prompt): string {
    if (DIRECTORY_SEPARATOR !== '/' || !function_exists('system')) {
        throw new RuntimeException('Saisie masquée indisponible sur ce terminal.');
    }

    fwrite(STDOUT, $prompt);
    system('stty -echo');
    try {
        $value = fgets(STDIN);
    } finally {
        system('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return rtrim((string) $value, "\r\n");
}

$password = read_hidden_password('Nouveau mot de passe : ');
$confirmation = read_hidden_password('Confirmez le mot de passe : ');

if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Les mots de passe ne correspondent pas.\n");
    exit(3);
}
if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "Utilisez au moins 12 caractères avec majuscule, minuscule et chiffre.\n");
    exit(4);
}

$pdo = Database::getConnection();
$pdo->beginTransaction();
try {
    $select = $pdo->prepare('SELECT id, nom FROM users WHERE LOWER(email) = :email LIMIT 1');
    $select->execute(['email' => $email]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($existing) {
        $userId = (int) $existing['id'];
        $update = $pdo->prepare("UPDATE users
            SET password = :password,
                role = 'Admin',
                secondary_roles = NULL,
                statut = 'Actif',
                email_verified_at = COALESCE(email_verified_at, NOW())
            WHERE id = :id");
        $update->execute(['password' => $passwordHash, 'id' => $userId]);
    } else {
        $name = trim((string) strtok($email, '@'));
        $name = $name !== '' ? ucwords(str_replace(['.', '_', '-'], ' ', $name)) : 'Administrateur Strax';
        $insert = $pdo->prepare("INSERT INTO users
            (nom, email, password, role, secondary_roles, statut, email_verified_at)
            VALUES (:nom, :email, :password, 'Admin', NULL, 'Actif', NOW())");
        $insert->execute(['nom' => $name, 'email' => $email, 'password' => $passwordHash]);
        $userId = (int) $pdo->lastInsertId();
    }

    $pdo->exec("INSERT INTO tenants(name, slug, status, plan_code)
        SELECT 'Jaxe Ops', 'jaxe-ops', 'Actif', 'legacy'
        WHERE NOT EXISTS (SELECT 1 FROM tenants WHERE slug = 'jaxe-ops')");
    $tenantId = (int) $pdo->query("SELECT id FROM tenants WHERE slug = 'jaxe-ops' LIMIT 1")->fetchColumn();
    if ($tenantId <= 0) {
        throw new RuntimeException('Tenant principal introuvable.');
    }

    $membership = $pdo->prepare("INSERT INTO tenant_memberships
        (tenant_id, organization_id, user_id, membership_role, status, joined_at)
        VALUES (:tenant, NULL, :user, 'Owner', 'Actif', NOW())
        ON DUPLICATE KEY UPDATE organization_id = NULL, membership_role = 'Owner', status = 'Actif', joined_at = COALESCE(joined_at, NOW())");
    $membership->execute(['tenant' => $tenantId, 'user' => $userId]);

    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user AND used_at IS NULL')
        ->execute(['user' => $userId]);
    $identityHash = hash_hmac('sha256', $email, defined('APP_ENCRYPTION_KEY') ? APP_ENCRYPTION_KEY : 'local-auth-key');
    $pdo->prepare('DELETE FROM auth_login_attempts WHERE identity_hash = :identity')
        ->execute(['identity' => $identityHash]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Compte administrateur prêt : {$email}\n";
echo "Tenant : jaxe-ops | rôle SaaS : Owner | statut : Actif\n";
