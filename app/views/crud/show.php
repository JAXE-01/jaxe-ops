<?php
 $clientSocialAccounts = is_array($clientSocialAccounts ?? null) ? $clientSocialAccounts : [];
 $editingSocialAccount = is_array($editingSocialAccount ?? null) ? $editingSocialAccount : null;

function crud_show_value(array $record, string $field, array $module, array $options) {
    $meta = $module['formFields'][$field] ?? [];
    $value = $record[$field] ?? null;

    if (($meta['type'] ?? null) === 'relation') {
        return $options[$field][$value] ?? $value ?? '—';
    }

    if (($meta['type'] ?? null) === 'select') {
        return $meta['options'][$value] ?? $value ?? '—';
    }

    if (($meta['type'] ?? null) === 'multiselect') {
        $selectedValues = UserRoles::normalizeList($value);
        if (empty($selectedValues)) {
            return '—';
        }

        $labels = [];
        foreach ($selectedValues as $selectedValue) {
            $labels[] = $meta['options'][$selectedValue] ?? $selectedValue;
        }
        return implode(', ', $labels);
    }

    if (($meta['type'] ?? null) === 'checkbox') {
        return !empty($value) ? 'Oui' : 'Non';
    }

    if (in_array(($meta['type'] ?? null), ['file', 'files'], true)) {
        return $value;
    }

    if ($value === null || $value === '') {
        return '—';
    }

    return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function crud_decode_files($value) {
    if (empty($value)) {
        return [];
    }

    if (is_array($value)) {
        return $value;
    }

    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    return [];
}
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2><?= htmlspecialchars($module['label']) ?></h2>
            <p class="panel-subtitle">Detail de l'element selectionne.</p>
        </div>
        <div class="form-actions">
            <a class="button secondary" href="<?= htmlspecialchars($returnTo ?? route_url('/' . $module['route'])) ?>"><?= htmlspecialchars($backLabel ?? 'Retour a la liste') ?></a>
            <a class="button" href="<?= htmlspecialchars(route_url('/' . $module['route'] . '/edit/' . $record[$module['primaryKey']]) . (!empty($returnTo) ? '?return_to=' . urlencode($returnTo) : '')) ?>">Modifier</a>
        </div>
    </div>

    <div class="detail-grid">
        <?php foreach (($module['detailFields'] ?? array_keys($module['formFields'])) as $field): ?>
            <?php $meta = $module['formFields'][$field] ?? null; ?>
            <?php if ($meta === null || ($meta['type'] ?? null) === 'password') { continue; } ?>
            <?php if (($meta['type'] ?? null) === 'password') { continue; } ?>
            <article class="detail-card">
                <span class="detail-label"><?= htmlspecialchars($meta['label']) ?></span>
                <?php if (in_array(($meta['type'] ?? null), ['file', 'files'], true)): ?>
                    <?php $files = crud_decode_files(crud_show_value($record, $field, $module, $options)); ?>
                    <?php if (!empty($files)): ?>
                        <div class="file-list">
                            <?php foreach ($files as $file): ?>
                                <a class="file-link" href="<?= htmlspecialchars(upload_url($file['path'] ?? '')) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($file['name'] ?? 'Fichier') ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="detail-value">—</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="detail-value"><?= nl2br(htmlspecialchars(crud_show_value($record, $field, $module, $options))) ?></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if (($module['route'] ?? '') === 'client'): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Comptes sociaux du client</h2>
            <p class="panel-subtitle">Configuration par client des comptes TikTok/YouTube/Instagram/WhatsApp, et mapping pages Facebook/LinkedIn.</p>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars(route_url('/client/saveSocialAccount/' . (int) ($record['id'] ?? 0))) ?>" class="form-grid">
        <input type="hidden" name="social_account_id" value="<?= htmlspecialchars((string) ($editingSocialAccount['id'] ?? 0)) ?>">
        <label class="field">
            <span>Reseau</span>
            <select name="reseau" required>
                <?php $selectedNetwork = (string) ($editingSocialAccount['reseau'] ?? ''); ?>
                <option value="">Selectionner</option>
                <?php foreach (['facebook', 'linkedin', 'instagram', 'tiktok', 'youtube', 'whatsapp'] as $network): ?>
                    <option value="<?= htmlspecialchars($network) ?>" <?= $selectedNetwork === $network ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($network)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field"><span>Libelle compte</span><input type="text" name="compte_label" value="<?= htmlspecialchars((string) ($editingSocialAccount['compte_label'] ?? '')) ?>" required></label>
        <label class="field"><span>Identifiant compte (username, id, urn...)</span><input type="text" name="identifiant_compte" value="<?= htmlspecialchars((string) ($editingSocialAccount['identifiant_compte'] ?? '')) ?>"></label>
        <label class="field"><span>Page ID / Organization ID</span><input type="text" name="page_id" value="<?= htmlspecialchars((string) ($editingSocialAccount['page_id'] ?? '')) ?>"></label>
        <label class="field"><span>Page nom</span><input type="text" name="page_nom" value="<?= htmlspecialchars((string) ($editingSocialAccount['page_nom'] ?? '')) ?>"></label>
        <label class="field"><span>Access token</span><input type="text" name="access_token" value="<?= htmlspecialchars((string) ($editingSocialAccount['access_token'] ?? '')) ?>"></label>
        <label class="field"><span>Refresh token</span><input type="text" name="refresh_token" value="<?= htmlspecialchars((string) ($editingSocialAccount['refresh_token'] ?? '')) ?>"></label>
        <label class="field">
            <span>Statut</span>
            <?php $selectedStatus = (string) ($editingSocialAccount['statut'] ?? 'Actif'); ?>
            <select name="statut">
                <option value="Actif" <?= $selectedStatus === 'Actif' ? 'selected' : '' ?>>Actif</option>
                <option value="Inactif" <?= $selectedStatus === 'Inactif' ? 'selected' : '' ?>>Inactif</option>
            </select>
        </label>
        <label class="field"><span>Notes</span><textarea name="notes"><?= htmlspecialchars((string) ($editingSocialAccount['notes'] ?? '')) ?></textarea></label>
        <label class="field"><span>Compte par defaut</span><label class="checkbox-pill"><input type="checkbox" name="is_default" value="1" <?= !empty($editingSocialAccount['is_default']) ? 'checked' : '' ?>> <span>Oui</span></label></label>
        <div class="form-actions">
            <button class="button" type="submit"><?= !empty($editingSocialAccount) ? 'Mettre a jour le compte' : 'Ajouter le compte' ?></button>
            <?php if (!empty($editingSocialAccount)): ?>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/client/show/' . (int) ($record['id'] ?? 0))) ?>">Annuler edition</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-wrap compact-table" style="margin-top: 12px;">
        <table>
            <thead>
                <tr>
                    <th>Reseau</th>
                    <th>Libelle</th>
                    <th>Identifiant</th>
                    <th>Page/Org</th>
                    <th>Defaut</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientSocialAccounts as $account): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ucfirst((string) ($account['reseau'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars((string) ($account['compte_label'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($account['identifiant_compte'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($account['page_nom'] ?? $account['page_id'] ?? '')) ?></td>
                        <td><?= !empty($account['is_default']) ? 'Oui' : 'Non' ?></td>
                        <td><?= htmlspecialchars((string) ($account['statut'] ?? '')) ?></td>
                        <td class="actions-cell">
                            <div class="icon-actions">
                                <a class="icon-link" href="<?= htmlspecialchars(route_url('/client/show/' . (int) ($record['id'] ?? 0) . '?social_edit_id=' . (int) ($account['id'] ?? 0))) ?>" title="Modifier" aria-label="Modifier">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4Zm11-13 4 4M13 6l4 4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                                <a class="icon-link danger" href="<?= htmlspecialchars(route_url('/client/deleteSocialAccount/' . (int) ($record['id'] ?? 0) . '/' . (int) ($account['id'] ?? 0))) ?>" title="Supprimer" aria-label="Supprimer" onclick="return confirm('Supprimer ce compte social ?');">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-7 0 1 12h6l1-12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($clientSocialAccounts)): ?>
                    <tr><td colspan="7">Aucun compte social configure pour ce client.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>