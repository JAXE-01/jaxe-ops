<?php
// Presentation only: the two tasks retain their independent permissions and prerequisites.
$productionTasks = array_values(array_filter([$taskMap['Tournage'] ?? null, $taskMap['Montage'] ?? null]));
$remainingProduction = array_values(array_filter($productionTasks, static fn($item) => ($item['statut'] ?? '') !== 'Terminee'));
$productionComplete = $productionTasks && !$remainingProduction;
$activeProduction = $remainingProduction[0] ?? ($productionTasks[0] ?? null);
$productionPeople = [];
foreach ($productionTasks as $item) {
    $key = (string) ($item['auteur_id'] ?? 0);
    $productionPeople[$key] = $item['auteur_nom'] ?: 'Non assigné';
}
?>
<?php if ($activeProduction): ?>
<?php if ($productionComplete): ?><details class="pipeline-completed"><summary>✓ Production terminée · détails</summary><?php endif; ?>
<div class="task-cell production-card">
    <strong><span class="status-badge status-<?= htmlspecialchars(calendrier_task_status_class($activeProduction)) ?>"><?= htmlspecialchars(calendrier_task_status_label($activeProduction)) ?></span></strong>
    <span title="Échéance de l’étape courante"><?= htmlspecialchars($activeProduction['deadline'] ?: '—') ?></span>
    <span title="<?= htmlspecialchars(implode(' / ', $productionPeople)) ?>">👤 <?= htmlspecialchars(implode(' / ', array_map(static fn($name) => explode(' ', trim($name))[0], $productionPeople))) ?></span>
    <a class="icon-link" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $activeProduction['id'])) ?>" title="Ouvrir la production : <?= htmlspecialchars($activeProduction['type_tache']) ?>" aria-label="Ouvrir la production vidéo">↗</a>
    <div class="production-people">
    <?php foreach ($productionTasks as $item): ?>
        <a href="<?= htmlspecialchars(route_url('/calendrier/task/' . $item['id'])) ?>" title="<?= htmlspecialchars($item['type_tache'] . ' · ' . ($item['auteur_nom'] ?: 'Non assigné')) ?>"><?= htmlspecialchars($item['type_tache']) ?> <?= ($item['statut'] ?? '') === 'Terminee' ? '✓' : '' ?></a>
    <?php endforeach; ?>
    </div>
</div>
<?php if ($productionComplete): ?></details><?php endif; ?>
<?php else: ?><span class="mini-text">Non générée</span><?php endif; ?>
