<?php
$items = $workspace['items'] ?? [];
$flash = $this->getFlash();
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Validation client</h2>
            <p class="panel-subtitle"><?= htmlspecialchars((string) ($workspace['client_nom'] ?? 'Client')) ?> · <?= htmlspecialchars((string) ($workspace['projet_nom'] ?? 'Projet')) ?> · <?= htmlspecialchars(date('F Y', strtotime((string) ($workspace['periode_mois'] ?? date('Y-m-01'))))) ?></p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= htmlspecialchars((string) ($flash['type'] ?? 'success')) ?>"><?= htmlspecialchars((string) ($flash['message'] ?? '')) ?></div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p class="empty-state">Aucun livrable disponible sur ce mois.</p>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
        <div class="panel inset-panel">
            <div class="panel-head">
                <div>
                    <h3><?= htmlspecialchars((string) ($item['titre'] ?? 'Livrable')) ?></h3>
                    <p class="panel-subtitle"><?= htmlspecialchars((string) ($item['type_livrable'] ?? '')) ?> · <?= htmlspecialchars((string) ($item['date_prevue'] ?? '')) ?></p>
                </div>
            </div>

            <?php if (!empty($item['files'])): ?>
                <div class="file-list">
                    <?php foreach ($item['files'] as $file): ?>
                        <div class="file-item-row">
                            <a class="file-link" href="<?= htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode((string) ($file['path'] ?? '')) . '&name=' . urlencode((string) ($item['titre'] ?? 'contenu'))) ?>"><?= htmlspecialchars((string) ($file['name'] ?? 'Fichier')) ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($item['task_id'])): ?>
                <form method="post" class="form-grid">
                    <input type="hidden" name="task_id" value="<?= htmlspecialchars((string) ($item['task_id'] ?? '')) ?>">
                    <div class="info-banner">La note sur 10 et la decision de validation sont independantes.</div>
                    <label class="field">
                        <span>Email client (optionnel)</span>
                        <input type="email" name="client_email" value="<?= htmlspecialchars((string) ($_POST['client_email'] ?? ($workspace['client_email'] ?? ''))) ?>" placeholder="contact@client.com">
                        <small class="hint">Si renseigne, un email de confirmation est envoye au client apres validation.</small>
                    </label>
                    <label class="field">
                        <span>Decision</span>
                        <select name="decision">
                            <option value="Valide" <?= (($item['validation_decision'] ?? '') === 'Valide') ? 'selected' : '' ?>>Valide</option>
                            <option value="Non valide" <?= (($item['validation_decision'] ?? '') === 'Non valide') ? 'selected' : '' ?>>Non valide</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Note sur 10</span>
                        <input type="number" name="note_sur_10" min="0" max="10" step="1" value="<?= htmlspecialchars((string) ($item['note_sur_10'] ?? '')) ?>" placeholder="Ex: 8">
                    </label>
                    <label class="field">
                        <span>Commentaire</span>
                        <textarea name="comment"><?= htmlspecialchars((string) ($item['validation_commentaire'] ?? '')) ?></textarea>
                    </label>
                    <div class="form-actions">
                        <button class="button" type="submit">Envoyer la validation</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="empty-state">Tache de validation client introuvable pour ce livrable.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
