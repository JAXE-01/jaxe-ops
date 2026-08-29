<?php
$pairedTaskMap = [
    'Validation interne' => ['Validation client', 'Validation'],
    'Validation client' => ['Validation interne', 'Validation'],
    'Publication' => ['Collecte KPI', 'Publication et performances'],
    'Collecte KPI' => ['Publication', 'Publication et performances'],
];
$pairDefinition = $pairedTaskMap[$taskType] ?? null;
$pairedTask = $pairDefinition ? ($taskMap[$pairDefinition[0]] ?? null) : null;
?>
<?php if ($pairedTask): ?>
<section class="panel task-workspace-compact paired-task-inline" data-paired-task="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) $pairedTask['id'])) ?>" data-paired-type="<?= htmlspecialchars((string) $pairDefinition[0]) ?>">
    <div class="panel-head">
        <div><h2><?= htmlspecialchars((string) $pairDefinition[1]) ?> · <?= htmlspecialchars((string) $pairDefinition[0]) ?></h2><p class="panel-subtitle">Même étape métier, enregistrement et droits conservés séparément.</p></div>
        <a class="icon-link" href="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) $pairedTask['id'])) ?>" title="Ouvrir la vue détaillée" aria-label="Ouvrir la vue détaillée">↗</a>
    </div>
    <p class="mini-text" data-paired-status role="status">Chargement du formulaire associé…</p>
    <div data-paired-host></div>
</section>
<script src="<?= htmlspecialchars(app_url('/public/assets/inline-paired-task.js')) ?>" defer></script>
<?php endif; ?>
