<?php if(!empty($briefEditUrl)): ?>
<details id="inline-content-brief" class="panel inline-content-brief" data-inline-brief="<?= htmlspecialchars($briefEditUrl) ?>">
<summary><strong><?= ($deliverable['type_livrable']??'')==='Video'?'Script vidéo':'Brief créatif' ?></strong> · travailler ici</summary>
<p class="mini-text">La fiche et le brief ont chacun leur sauvegarde. Les validations et droits restent inchangés.</p>
<p role="status" data-brief-status></p>
<div data-brief-host></div>
<a href="<?= htmlspecialchars($briefEditUrl) ?>" class="mini-text">Ouvrir la vue détaillée du brief</a>
</details>
<script src="<?= htmlspecialchars(app_url('/public/assets/inline-brief.js')) ?>" defer></script>
<?php endif ?>
