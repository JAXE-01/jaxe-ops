<?php
require __DIR__ . '/../config/config.php';

function fetchOne(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: [];
}

function fetchAll(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function boolResult(string $name, bool $ok, string $details): array {
    return [
        'name' => $name,
        'ok' => $ok,
        'details' => $details,
    ];
}

$pdo = Database::getConnection();
$results = [];

$orphanBlocked = (int) (fetchOne(
    $pdo,
    "SELECT COUNT(*) AS total
     FROM taches_pipeline child
     JOIN taches_pipeline parent ON parent.id = child.parent_task_id
     WHERE parent.statut = 'Terminee'
       AND child.statut IN ('Bloquee', 'Annulee')"
)['total'] ?? 0);
$results[] = boolResult(
    'Children unlocked when parent is Terminee',
    $orphanBlocked === 0,
    'blocked_or_invalid_children=' . $orphanBlocked
);

$briefProdBlocked = (int) (fetchOne(
    $pdo,
    "SELECT COUNT(*) AS total
     FROM taches_pipeline brief
     JOIN taches_pipeline prod ON prod.parent_task_id = brief.id
     WHERE brief.type_tache IN ('Brief', 'Script')
       AND brief.statut = 'Terminee'
       AND prod.type_tache IN ('Production', 'Tournage')
       AND prod.statut IN ('Bloquee', 'Annulee')"
)['total'] ?? 0);
$results[] = boolResult(
    'Transition Brief/Script -> Production/Tournage',
    $briefProdBlocked === 0,
    'blocked_or_invalid_children=' . $briefProdBlocked
);

$tournageMontageBlocked = (int) (fetchOne(
    $pdo,
    "SELECT COUNT(*) AS total
     FROM taches_pipeline shoot
     JOIN taches_pipeline montage ON montage.parent_task_id = shoot.id
     WHERE shoot.type_tache = 'Tournage'
       AND shoot.statut = 'Terminee'
       AND montage.type_tache = 'Montage'
       AND montage.statut IN ('Bloquee', 'Annulee')"
)['total'] ?? 0);
$results[] = boolResult(
    'Transition Tournage -> Montage',
    $tournageMontageBlocked === 0,
    'blocked_or_invalid_children=' . $tournageMontageBlocked
);

$validationPublicationBlocked = (int) (fetchOne(
    $pdo,
    "SELECT COUNT(*) AS total
     FROM taches_pipeline validation
     JOIN taches_pipeline publication ON publication.parent_task_id = validation.id
     WHERE validation.type_tache = 'Validation client'
       AND validation.statut = 'Terminee'
       AND publication.type_tache = 'Publication'
       AND publication.statut IN ('Bloquee', 'Annulee')"
)['total'] ?? 0);
$results[] = boolResult(
    'Transition Validation client -> Publication',
    $validationPublicationBlocked === 0,
    'blocked_or_invalid_children=' . $validationPublicationBlocked
);

$publicationNoCampaignTask = (int) (fetchOne(
    $pdo,
    "SELECT tp.id
     FROM taches_pipeline tp
     JOIN projets p ON p.id = tp.projet_id
     WHERE tp.type_tache = 'Publication'
       AND (p.campagne_id IS NULL OR p.campagne_id = 0)
     ORDER BY tp.id DESC
     LIMIT 1"
)['id'] ?? 0);

if ($publicationNoCampaignTask > 0) {
    $model = new CalendrierModel();
    $ok = true;
    $details = 'task_id=' . $publicationNoCampaignTask;
    try {
        $ret = $model->savePublicationEntry($publicationNoCampaignTask, [
            'date_publication' => date('Y-m-d'),
            'heure_publication' => '09:00:00',
            'canal' => 'Test',
            'statut' => 'Planifie',
            'note' => 'Regression check',
        ], null);
        $details .= '; return=' . (string) $ret;
    } catch (Throwable $e) {
        $ok = false;
        $details .= '; error=' . $e->getMessage();
    }
    $results[] = boolResult('Publication without campagne does not crash', $ok, $details);
} else {
    $results[] = boolResult('Publication without campagne does not crash', true, 'no matching task in dataset');
}

$videaste = fetchOne(
    $pdo,
    "SELECT id, role, secondary_roles
     FROM users
     WHERE role = 'Videaste' OR secondary_roles LIKE '%Videaste%'
     ORDER BY id ASC
     LIMIT 1"
);

if (!empty($videaste['id'])) {
    $user = [
        'id' => (int) $videaste['id'],
        'role' => (string) ($videaste['role'] ?? ''),
        'secondary_roles' => (string) ($videaste['secondary_roles'] ?? ''),
    ];

    $invalidMontages = fetchAll(
        $pdo,
        "SELECT id
         FROM taches_pipeline
         WHERE auteur_id = :uid
           AND type_tache = 'Montage'
           AND statut = 'Annulee'",
        ['uid' => (int) $user['id']]
    );

    $dashboard = new DashboardModel();
    $corrections = $dashboard->getPhilsFocus($user);
    $correctionIds = array_map(static function ($row) { return (int) ($row['id'] ?? 0); }, $corrections);

    $expectedVisible = count($invalidMontages);
    $visible = 0;
    foreach ($invalidMontages as $row) {
        if (in_array((int) ($row['id'] ?? 0), $correctionIds, true)) {
            $visible++;
        }
    }

    $ok = $expectedVisible === 0 ? true : $visible > 0;
    $results[] = boolResult(
        'Invalid Montage visible in Corrections for Videaste',
        $ok,
        'invalid_montages=' . $expectedVisible . '; visible_in_corrections=' . $visible
    );

    if ($expectedVisible > 0) {
        $cal = new CalendrierModel();
        $taskId = (int) ($invalidMontages[0]['id'] ?? 0);
        $task = $taskId > 0 ? $cal->getTaskWorkspace($taskId, $user) : null;
        $results[] = boolResult(
            'Invalid Montage openable in workspace for Videaste',
            !empty($task),
            'task_id=' . $taskId . '; workspace=' . (!empty($task) ? 'ok' : 'null')
        );
    }
} else {
    $results[] = boolResult('Invalid Montage visibility checks (Videaste)', true, 'no videaste user in dataset');
}

$failed = array_values(array_filter($results, static function ($r) {
    return empty($r['ok']);
}));

$report = [
    'timestamp' => date('c'),
    'summary' => [
        'total' => count($results),
        'passed' => count($results) - count($failed),
        'failed' => count($failed),
    ],
    'results' => $results,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(empty($failed) ? 0 : 1);
