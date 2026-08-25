<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$pdo = Database::getConnection();
$sources = [
    ['table' => 'livrable_items', 'column' => 'pieces_jointes'],
    ['table' => 'taches_pipeline', 'column' => 'fichiers_livres'],
    ['table' => 'briefs', 'column' => 'pieces_jointes'],
];
$paths = [];

$collect = static function ($value) use (&$paths, &$collect): void {
    if (is_array($value)) {
        if (isset($value['path']) && is_string($value['path']) && trim($value['path']) !== '') {
            $paths[] = ltrim(str_replace('\\', '/', trim($value['path'])), '/');
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $collect($child);
            }
        }
    }
};

foreach ($sources as $source) {
    $query = 'SELECT `' . $source['column'] . '` AS payload FROM `' . $source['table'] . '` WHERE `' . $source['column'] . '` IS NOT NULL';
    foreach ($pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $decoded = json_decode((string) ($row['payload'] ?? ''), true);
        if (is_array($decoded)) {
            $collect($decoded);
        }
    }
}

foreach ($pdo->query("SELECT fichier_path FROM documentation_files WHERE fichier_path IS NOT NULL AND fichier_path <> ''")->fetchAll(PDO::FETCH_COLUMN) as $path) {
    $paths[] = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
}

$paths = array_values(array_unique(array_filter($paths)));
$missing = [];
$present = [];
foreach ($paths as $path) {
    if (is_file(UPLOADS_PATH . '/' . $path)) {
        $present[] = $path;
    } else {
        $missing[] = $path;
    }
}

echo 'Dossier uploads : ' . UPLOADS_PATH . PHP_EOL;
echo 'Dossier présent : ' . (is_dir(UPLOADS_PATH) ? 'oui' : 'non') . PHP_EOL;
echo 'Dossier inscriptible : ' . (is_writable(UPLOADS_PATH) ? 'oui' : 'non') . PHP_EOL;
echo 'Références uniques : ' . count($paths) . PHP_EOL;
echo 'Fichiers présents : ' . count($present) . PHP_EOL;
echo 'Fichiers manquants : ' . count($missing) . PHP_EOL;

if ($missing) {
    echo "\nPremiers fichiers manquants :\n";
    foreach (array_slice($missing, 0, 30) as $path) {
        echo ' - ' . $path . PHP_EOL;
    }
}
