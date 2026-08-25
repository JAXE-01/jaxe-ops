<?php
require __DIR__ . '/../config/config.php';

function findUserByRole(PDO $pdo, string $role): ?array {
    $stmt = $pdo->prepare("SELECT id, nom, role, secondary_roles
        FROM users
        WHERE role = :role OR secondary_roles LIKE :secondary
        ORDER BY id ASC
        LIMIT 1");
    $stmt->execute([
        'role' => $role,
        'secondary' => '%' . $role . '%',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function findTaskByAuthorAndTypes(PDO $pdo, int $userId, array $types): ?array {
    if (empty($types)) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $sql = "SELECT id, type_tache, statut, auteur_id
        FROM taches_pipeline
        WHERE auteur_id = ?
          AND type_tache IN ($placeholders)
          AND statut <> 'Bloquee'
        ORDER BY id DESC
        LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $params = array_merge([$userId], $types);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function findTaskByType(PDO $pdo, string $type): ?array {
    $stmt = $pdo->prepare("SELECT id, type_tache, statut, auteur_id
        FROM taches_pipeline
        WHERE type_tache = :type
        ORDER BY id DESC
        LIMIT 1");
    $stmt->execute(['type' => $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function checkTransitionCount(PDO $pdo, string $parentTypeCsv, string $childTypeCsv): int {
    $parentTypes = array_map('trim', explode(',', $parentTypeCsv));
    $childTypes = array_map('trim', explode(',', $childTypeCsv));
    $parentPH = implode(',', array_fill(0, count($parentTypes), '?'));
    $childPH = implode(',', array_fill(0, count($childTypes), '?'));

    $sql = "SELECT COUNT(*) AS total
        FROM taches_pipeline parent
        JOIN taches_pipeline child ON child.parent_task_id = parent.id
        WHERE parent.type_tache IN ($parentPH)
          AND parent.statut = 'Terminee'
          AND child.type_tache IN ($childPH)
          AND child.statut IN ('Bloquee', 'Annulee')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($parentTypes, $childTypes));
    return (int) ($stmt->fetchColumn() ?: 0);
}

$pdo = Database::getConnection();
$cal = new CalendrierModel();

$roles = ['Clientele', 'Cadreur', 'Videaste', 'CM', 'CC'];
$reportRows = [];

foreach ($roles as $role) {
    $user = findUserByRole($pdo, $role);
    if (!$user) {
        $reportRows[] = [
            'role' => $role,
            'user' => 'N/A',
            'check' => 'User account available',
            'status' => 'SKIP',
            'details' => 'No user with this role in dataset',
        ];
        continue;
    }

    $scopeUser = [
        'id' => (int) $user['id'],
        'nom' => (string) ($user['nom'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'secondary_roles' => (string) ($user['secondary_roles'] ?? ''),
    ];

    $allowed = UserScope::allowedTaskTypes($scopeUser);
    $sampleAllowedTask = findTaskByAuthorAndTypes($pdo, (int) $scopeUser['id'], $allowed);

    if ($sampleAllowedTask) {
        $workspace = $cal->getTaskWorkspace((int) $sampleAllowedTask['id'], $scopeUser);
        $reportRows[] = [
            'role' => $role,
            'user' => (string) $scopeUser['nom'],
            'check' => 'Can open own allowed task',
            'status' => $workspace ? 'PASS' : 'FAIL',
            'details' => 'task_id=' . (int) $sampleAllowedTask['id'] . '; type=' . (string) $sampleAllowedTask['type_tache'],
        ];
    } else {
        $reportRows[] = [
            'role' => $role,
            'user' => (string) $scopeUser['nom'],
            'check' => 'Can open own allowed task',
            'status' => 'SKIP',
            'details' => 'No non-blocked task found authored by this user for allowed types',
        ];
    }

    if (UserScope::isScopedOperationalUser($scopeUser)) {
        $blockedType = null;
        foreach (['Validation client', 'Montage', 'Tournage', 'Publication', 'Brief', 'Production'] as $candidate) {
            if (!in_array($candidate, $allowed, true)) {
                $blockedType = $candidate;
                break;
            }
        }

        if ($blockedType !== null) {
            $sampleBlockedTask = findTaskByType($pdo, $blockedType);
            if ($sampleBlockedTask) {
                $workspace = $cal->getTaskWorkspace((int) $sampleBlockedTask['id'], $scopeUser);
                $reportRows[] = [
                    'role' => $role,
                    'user' => (string) $scopeUser['nom'],
                    'check' => 'Cannot open disallowed task type',
                    'status' => $workspace ? 'FAIL' : 'PASS',
                    'details' => 'task_id=' . (int) $sampleBlockedTask['id'] . '; blocked_type=' . $blockedType,
                ];
            } else {
                $reportRows[] = [
                    'role' => $role,
                    'user' => (string) $scopeUser['nom'],
                    'check' => 'Cannot open disallowed task type',
                    'status' => 'SKIP',
                    'details' => 'No sample task found for blocked_type=' . $blockedType,
                ];
            }
        }
    } else {
        $sampleAnyTask = findTaskByType($pdo, 'Montage');
        if ($sampleAnyTask) {
            $workspace = $cal->getTaskWorkspace((int) $sampleAnyTask['id'], $scopeUser);
            $reportRows[] = [
                'role' => $role,
                'user' => (string) $scopeUser['nom'],
                'check' => 'Privileged role can open cross-type task',
                'status' => $workspace ? 'PASS' : 'FAIL',
                'details' => 'task_id=' . (int) $sampleAnyTask['id'] . '; type=' . (string) $sampleAnyTask['type_tache'],
            ];
        }
    }
}

$transitionChecks = [
    ['name' => 'Brief/Script -> Production/Tournage', 'parent' => 'Brief,Script', 'child' => 'Production,Tournage'],
    ['name' => 'Tournage -> Montage', 'parent' => 'Tournage', 'child' => 'Montage'],
    ['name' => 'Validation client -> Publication', 'parent' => 'Validation client', 'child' => 'Publication'],
];

foreach ($transitionChecks as $check) {
    $blockedCount = checkTransitionCount($pdo, $check['parent'], $check['child']);
    $reportRows[] = [
        'role' => 'GLOBAL',
        'user' => '-',
        'check' => $check['name'],
        'status' => $blockedCount === 0 ? 'PASS' : 'FAIL',
        'details' => 'blocked_children=' . $blockedCount,
    ];
}

$summary = [
    'total' => count($reportRows),
    'pass' => count(array_filter($reportRows, static fn($r) => $r['status'] === 'PASS')),
    'fail' => count(array_filter($reportRows, static fn($r) => $r['status'] === 'FAIL')),
    'skip' => count(array_filter($reportRows, static fn($r) => $r['status'] === 'SKIP')),
];

$output = [
    'timestamp' => date('c'),
    'summary' => $summary,
    'rows' => $reportRows,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($summary['fail'] > 0 ? 1 : 0);
