<?php
$companionType = $taskType === 'Tournage' ? 'Montage' : 'Tournage';
$companionTask = $taskMap[$companionType] ?? null;
?>
<?php if ($companionTask): ?>
<section class="panel task-workspace-compact" data-inline-production="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) $companionTask['id'])) ?>" data-production-type="<?= htmlspecialchars($companionType) ?>">
    <div class="panel-head">
        <h2>Production vidéo · <?= htmlspecialchars($companionType) ?></h2>
        <a class="icon-link" href="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) $companionTask['id'])) ?>" title="Ouvrir la vue détaillée" aria-label="Ouvrir la vue détaillée">↗</a>
    </div>
    <p class="mini-text" data-production-status role="status">Chargement de l’autre étape de production…</p>
    <div data-production-host></div>
</section>
<script src="<?= htmlspecialchars(app_url('/public/assets/inline-production.js')) ?>" defer></script>
<?php endif; ?>
