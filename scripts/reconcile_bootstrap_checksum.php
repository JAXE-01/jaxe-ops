<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$filename = '20260416_001_bootstrap.sql';
$migrationPath = rtrim((string) MIGRATIONS_PATH, '/\\') . DIRECTORY_SEPARATOR . $filename;

if (!is_file($migrationPath)) {
    fwrite(STDERR, "Migration introuvable: {$filename}\n");
    exit(2);
}

$checksum = sha1_file($migrationPath);
if (!is_string($checksum) || $checksum === '') {
    fwrite(STDERR, "Impossible de calculer l'empreinte de la migration.\n");
    exit(3);
}

$pdo = Database::getConnection();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(190) PRIMARY KEY,
    checksum CHAR(40) NOT NULL,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$select = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE filename = :filename LIMIT 1');
$select->execute(['filename' => $filename]);
$recorded = $select->fetchColumn();

if ($recorded === false) {
    fwrite(STDERR, "La migration bootstrap n'est pas enregistrée comme appliquée. Réconciliation refusée.\n");
    exit(4);
}

if (hash_equals((string) $recorded, $checksum)) {
    echo "Empreinte bootstrap déjà conforme.\n";
    exit(0);
}

echo "Migration : {$filename}\n";
echo "Empreinte enregistrée : {$recorded}\n";
echo "Empreinte déployée    : {$checksum}\n";

if (!in_array('--apply', $argv, true)) {
    echo "Aucune modification. Relancez avec --apply pour réconcilier uniquement cette empreinte.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE schema_migrations
        SET checksum = :checksum
        WHERE filename = :filename AND checksum = :recorded');
    $update->execute([
        'checksum' => $checksum,
        'filename' => $filename,
        'recorded' => (string) $recorded,
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('La ligne de migration a changé pendant la réconciliation.');
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Empreinte bootstrap réconciliée. Aucun SQL de migration n'a été rejoué.\n";
