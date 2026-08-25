<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$filename = (string) ($argv[1] ?? '');
if ($filename === '' || $filename !== basename($filename) || !preg_match('/^[0-9]{8}_[0-9]{3}_[a-z0-9_]+\.sql$/', $filename)) {
    fwrite(STDERR, "Usage: php scripts/reconcile_migration_checksum.php <migration.sql> [--apply]\n");
    exit(2);
}

$migrationPath = rtrim((string) MIGRATIONS_PATH, '/\\') . DIRECTORY_SEPARATOR . $filename;
if (!is_file($migrationPath)) {
    fwrite(STDERR, "Migration introuvable dans le dépôt : {$filename}\n");
    exit(3);
}

$deployedChecksum = sha1_file($migrationPath);
if (!is_string($deployedChecksum) || $deployedChecksum === '') {
    fwrite(STDERR, "Impossible de calculer l'empreinte déployée.\n");
    exit(4);
}

$pdo = Database::getConnection();
$select = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE filename = :filename LIMIT 1');
$select->execute(['filename' => $filename]);
$recordedChecksum = $select->fetchColumn();

if ($recordedChecksum === false) {
    fwrite(STDERR, "Cette migration n'est pas enregistrée comme appliquée. Réconciliation refusée.\n");
    exit(5);
}

if (hash_equals((string) $recordedChecksum, $deployedChecksum)) {
    echo "Empreinte déjà conforme : {$filename}\n";
    exit(0);
}

echo "Migration              : {$filename}\n";
echo "Empreinte enregistrée  : {$recordedChecksum}\n";
echo "Empreinte déployée     : {$deployedChecksum}\n";

if (!in_array('--apply', $argv, true)) {
    echo "Aucune modification. Ajoutez --apply pour réconcilier uniquement cette migration.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE schema_migrations
        SET checksum = :deployed
        WHERE filename = :filename AND checksum = :recorded');
    $update->execute([
        'deployed' => $deployedChecksum,
        'filename' => $filename,
        'recorded' => (string) $recordedChecksum,
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('La ligne a changé pendant la réconciliation.');
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Empreinte réconciliée. Aucun SQL de migration n'a été rejoué.\n";
