<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/diagnose_matrix.php adresse@email.tld\n");
    exit(2);
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id, nom, email, role, secondary_roles, statut FROM users WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('Utilisateur introuvable.');
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'nom' => (string) $user['nom'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
        'secondary_roles' => (string) ($user['secondary_roles'] ?? ''),
        'roles' => UserRoles::extractRoles($user),
    ];
    TenantContext::clear();
    $tenant = TenantContext::resolveForUser($_SESSION['user']);
    echo 'Utilisateur : ' . $user['email'] . ' | ' . $user['statut'] . PHP_EOL;
    echo 'Tenant : ' . json_encode($tenant, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo 'Organisation : ' . json_encode(OrganizationContext::forUser($_SESSION['user']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/index.php/matrix';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    ob_start();
    (new MatrixController())->index();
    $html = ob_get_clean();
    echo 'Matrice : OK (' . strlen((string) $html) . ' octets rendus)' . PHP_EOL;
} catch (Throwable $exception) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $exception->getTraceAsString() . PHP_EOL);
    exit(1);
}

