<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$apply = in_array('--apply', $argv, true);
$sourceArgument = '';
foreach (array_slice($argv, 1) as $argument) {
    if ($argument !== '--apply') {
        $sourceArgument = (string) $argument;
        break;
    }
}

$home = dirname(dirname(__DIR__));
$candidates = array_filter([
    $sourceArgument,
    $home . '/eva-marketing/public/uploads',
    $home . '/eva-marketing/uploads',
    $home . '/eva-marketing/storage/uploads',
]);
$sourceRoot = null;
foreach ($candidates as $candidate) {
    $resolved = realpath($candidate);
    if ($resolved !== false && is_dir($resolved)) {
        $sourceRoot = $resolved;
        break;
    }
}
if ($sourceRoot === null) {
    fwrite(STDERR, "Dossier source introuvable. Indiquez le chemin des anciens uploads.\n");
    fwrite(STDERR, "Usage: php scripts/restore_legacy_uploads.php /home/.../eva-marketing/public/uploads [--apply]\n");
    exit(2);
}

$targetRoot = realpath((string) UPLOADS_PATH);
if ($targetRoot === false || !is_dir($targetRoot)) {
    throw new RuntimeException('Dossier cible uploads introuvable : ' . UPLOADS_PATH);
}

$pdo = Database::getConnection();
$references = [];
$collect = static function ($value) use (&$references, &$collect): void {
    if (!is_array($value)) return;
    if (isset($value['path']) && is_string($value['path'])) {
        $path = ltrim(str_replace('\\', '/', trim($value['path'])), '/');
        if ($path !== '' && !preg_match('#(^|/)\.\.(/|$)#', $path)) $references[$path] = true;
    }
    foreach ($value as $child) if (is_array($child)) $collect($child);
};

foreach ([['livrable_items','pieces_jointes'],['taches_pipeline','fichiers_livres'],['briefs','pieces_jointes']] as [$table,$column]) {
    $sql = "SELECT `$column` payload FROM `$table` WHERE `$column` IS NOT NULL AND `$column` NOT IN ('', '[]', 'null')";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $payload) {
        $decoded = json_decode((string) $payload, true);
        if (is_array($decoded)) $collect($decoded);
    }
}
foreach ($pdo->query("SELECT fichier_path FROM documentation_files WHERE fichier_path IS NOT NULL AND fichier_path <> ''")->fetchAll(PDO::FETCH_COLUMN) as $path) {
    $normalized = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
    if ($normalized !== '' && !preg_match('#(^|/)\.\.(/|$)#', $normalized)) $references[$normalized] = true;
}

$copied = $available = $alreadyPresent = $notFound = 0;
foreach (array_keys($references) as $relativePath) {
    $target = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($target)) {
        $alreadyPresent++;
        continue;
    }
    $source = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($source)) {
        $notFound++;
        echo "ABSENT  $relativePath\n";
        continue;
    }
    $available++;
    echo ($apply ? 'COPIE   ' : 'PRET    ') . $relativePath . PHP_EOL;
    if (!$apply) continue;
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de créer : ' . $directory);
    }
    if (!copy($source, $target)) throw new RuntimeException('Copie impossible : ' . $relativePath);
    if (filesize($source) !== filesize($target)) {
        @unlink($target);
        throw new RuntimeException('Copie incomplète annulée : ' . $relativePath);
    }
    $copied++;
}

echo PHP_EOL . 'Source : ' . $sourceRoot . PHP_EOL;
echo 'Cible : ' . $targetRoot . PHP_EOL;
echo 'Déjà présents : ' . $alreadyPresent . PHP_EOL;
echo 'Disponibles dans la source : ' . $available . PHP_EOL;
echo 'Introuvables dans la source : ' . $notFound . PHP_EOL;
echo $apply ? ('Fichiers copiés sans écrasement : ' . $copied . PHP_EOL) : "Simulation uniquement. Relancez avec --apply pour copier.\n";

