<?php $completed=count(array_filter($contentRequirements,static fn($item)=>$item['done'])); ?>
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/content-completion.css')) ?>">
<section class="panel content-overview" aria-label="Avancement de la fiche">
    <div class="panel-head"><div><h2>Votre contenu, étape par étape</h2><p class="panel-subtitle">Renseignez la fiche à votre rythme. La complétion ne remplace pas les validations.</p></div>
    <label>Affichage <select data-content-view><option value="focused">Essentiel</option><option value="expanded">Tout afficher</option></select></label></div>
    <div data-content-completion aria-live="polite"><strong><?= $completed ?> / <?= count($contentRequirements) ?> étapes renseignées</strong><progress max="<?= count($contentRequirements) ?>" value="<?= $completed ?>" aria-label="Complétion de la fiche"></progress></div>
    <?php if(!empty($briefEditUrl)): ?><a class="button secondary" href="#inline-content-brief" data-open-inline-brief>Continuer vers le <?= ($deliverable['type_livrable']??'')==='Video'?'script':'brief' ?> →</a><?php endif ?>
</section>
<script type="application/json" data-completion-initial><?= json_encode($contentRequirements,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="<?= htmlspecialchars(app_url('/public/assets/content-completion.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(app_url('/public/assets/content-view.js')) ?>" defer></script>
