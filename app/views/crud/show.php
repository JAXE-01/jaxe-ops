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
            <p class="panel-subtitle">Comptes récupérés directement depuis le module Comptes sociaux.</p>
        </div>
        <a class="button secondary" href="<?= htmlspecialchars(route_url('/social-connection')) ?>">Gérer les comptes</a>
    </div>
    <div class="table-wrap compact-table">
        <table>
            <thead>
                <tr>
                    <th>Reseau</th>
                    <th>Libelle</th>
                    <th>Type</th>
                    <th>Identifiant réseau</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientSocialAccounts as $account): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ucfirst((string) ($account['provider'] ?? ''))) ?></td>
                        <td><strong><?= htmlspecialchars((string) ($account['account_label'] ?? '')) ?></strong></td>
                        <td><?= htmlspecialchars((string) ($account['account_type'] ?? 'Compte')) ?></td>
                        <td><?= htmlspecialchars((string) ($account['external_account_id'] ?? '—')) ?></td>
                        <td><span class="status-badge"><?= htmlspecialchars((string) ($account['status'] ?? '')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($clientSocialAccounts)): ?>
                    <tr><td colspan="5">Aucun compte social relié à ce client dans le module Comptes sociaux.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
