<?php
require __DIR__ . '/../config/config.php';

$pdo = Database::getConnection();

$fix1 = $pdo->exec("UPDATE taches_pipeline child
    JOIN taches_pipeline parent ON parent.id = child.parent_task_id
    SET child.statut = 'A faire'
    WHERE parent.statut = 'Terminee'
      AND child.statut IN ('Bloquee', 'Annulee')");

$fix2 = $pdo->exec("UPDATE taches_pipeline validation
    JOIN taches_pipeline publication ON publication.parent_task_id = validation.id
    SET publication.statut = 'A faire'
    WHERE validation.type_tache = 'Validation client'
      AND validation.statut = 'Terminee'
      AND publication.type_tache = 'Publication'
      AND publication.statut IN ('Bloquee', 'Annulee')");

echo json_encode([
    'children_reopened' => (int) $fix1,
    'publication_reopened' => (int) $fix2,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
