<?php
$submission = (array) (($confirmation['submission'] ?? null) ?: []);
$mail = (array) (($confirmation['mail'] ?? null) ?: []);
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Merci, votre reponse a ete enregistree</h2>
            <p class="panel-subtitle"><?= htmlspecialchars((string) ($workspace['client_nom'] ?? 'Client')) ?> · <?= htmlspecialchars((string) ($workspace['projet_nom'] ?? 'Projet')) ?></p>
        </div>
    </div>

    <?php if (!empty($submission)): ?>
        <div class="stats-grid">
            <article class="stat-card">
                <span class="stat-label">Livrable</span>
                <span class="stat-value"><?= htmlspecialchars((string) ($submission['deliverable_title'] ?? 'N/A')) ?></span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Decision</span>
                <span class="stat-value"><?= htmlspecialchars((string) ($submission['decision'] ?? 'N/A')) ?></span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Note /10</span>
                <span class="stat-value\"><?= htmlspecialchars((string) (($submission['score'] ?? '') === '' ? 'N/A' : $submission['score'])) ?></span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Email client</span>
                <span class="stat-value"><?= !empty($mail['client_sent']) ? 'Envoye' : 'Non envoye' ?></span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Email equipe</span>
                <span class="stat-value"><?= !empty($mail['internal_sent']) ? 'Envoye' : 'Non envoye' ?></span>
            </article>
        </div>

        <?php if (!empty($submission['comment'])): ?>
            <div class="panel inset-panel" style="margin-top: 16px;">
                <h3>Votre commentaire</h3>
                <p><?= nl2br(htmlspecialchars((string) $submission['comment'])) ?></p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="empty-state">Votre validation a bien ete enregistree.</p>
    <?php endif; ?>

</section>
