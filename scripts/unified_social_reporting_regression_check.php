<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/config.php';

$db = Database::getConnection();
$columns = $db->query('SHOW COLUMNS FROM reporting_metrics')->fetchAll(PDO::FETCH_COLUMN);
$required = ['tenant_id', 'project_id', 'social_publication_id', 'social_target_id', 'source', 'collected_at'];
$missing = array_values(array_diff($required, $columns));
if ($missing) {
    fwrite(STDERR, 'Missing reporting columns: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$db->query('SELECT rm.id,
        COALESCE(ct.sujet, sp.master_title, "Publication non rattachee") publication
    FROM reporting_metrics rm
    LEFT JOIN contenus ct ON ct.id=rm.contenu_id
    LEFT JOIN social_publications sp ON sp.id=rm.social_publication_id
    LIMIT 1')->fetch(PDO::FETCH_ASSOC);

echo "OK: unified social reporting schema and publication labels.\n";
