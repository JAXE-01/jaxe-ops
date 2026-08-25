<?php
$listFields = $module['listFields'];
$formFields = $module['formFields'];
$groups = !empty($groupedRows) ? $groupedRows : [['id' => null, 'label' => null, 'rows' => $rows]];
$filters = $filters ?? ['client_id' => '', 'q' => ''];
$clientOptions = $clientOptions ?? [];
$showClientPicker = $showClientPicker ?? false;
$requiresClientSelection = $requiresClientSelection ?? false;
$isUserModule = ($module['route'] ?? '') === 'user';
$isProjectModule = ($module['route'] ?? '') === 'projet';
$crudRowCount = array_sum(array_map(static fn($group) => count((array) ($group['rows'] ?? [])), $groups));

function crud_display_value(array $row, string $field, array $formFields, array $options) {
    $value = $row[$field] ?? '';
    $fieldType = $formFields[$field]['type'] ?? null;

    if ($fieldType === 'relation') {
        return $options[$field][$value] ?? $value;
    }

    if ($fieldType === 'select') {
        return $formFields[$field]['options'][$value] ?? $value;
    }

    if ($fieldType === 'multiselect') {
        $selectedValues = UserRoles::normalizeList($value);
        if (empty($selectedValues)) {
            return '—';
        }

        $labels = [];
        foreach ($selectedValues as $selectedValue) {
            $labels[] = $formFields[$field]['options'][$selectedValue] ?? $selectedValue;
        }
        return implode(', ', $labels);
    }

    if ($value === null || $value === '') {
        return '—';
    }

    return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
<?php if ($isProjectModule): ?>
<section class="page-intro-card project-list-intro"><div><span class="page-eyebrow">Production éditoriale</span><h2>Projets</h2><p>Créez, filtrez et pilotez les projets rattachés à chaque client depuis un répertoire unique.</p></div><a class="button primary" href="<?= htmlspecialchars(route_url('/projet/create')) ?>">+ Nouveau projet</a></section>
<div class="entity-stats-grid project-list-stats"><article class="entity-stat"><span>Projets affichés</span><strong><?= $crudRowCount ?></strong><small>Selon les filtres actifs</small></article><article class="entity-stat"><span>Clients représentés</span><strong><?= count($clientOptions) ?></strong><small>Portefeuille disponible</small></article><article class="entity-stat"><span>Vue</span><strong><?= $showClientPicker ? 'Clients' : 'Liste' ?></strong><small>Mode de navigation actuel</small></article></div>
<?php endif; ?><section class="panel">
    <div class="panel-head">
        <div>
            <p>Gestion de <?= htmlspecialchars(strtolower($module['label'])) ?></p>
            <?php if ($requiresClientSelection): ?>
                <p class="panel-subtitle">Selectionne d'abord un client pour afficher cette liste.</p>
            <?php endif; ?>
        </div>
        <div class="toolbar-actions">
            <?php if ($isUserModule): ?>
                <button class="button secondary" type="button" id="bulk-select-all">Tout selectionner</button>
                <button class="button secondary" type="button" id="bulk-select-none">Tout deselectionner</button>
            <?php endif; ?>
            <a class="button" href="<?= htmlspecialchars(route_url('/' . $module['route'] . '/create')) ?>">Ajouter</a>
        </div>
    </div>

    <form method="get" action="<?= htmlspecialchars(route_url('/' . $module['route'])) ?>" class="list-toolbar">
        <?php if (!empty($clientOptions)): ?>
            <label class="field toolbar-field">
                <span>Client</span>
                <select name="client_id">
                    <option value="">Tous les clients</option>
                    <?php foreach ($clientOptions as $clientOption): ?>
                        <option value="<?= htmlspecialchars($clientOption['id']) ?>" <?= $filters['client_id'] === $clientOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($clientOption['label']) ?> (<?= $clientOption['count'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <label class="field toolbar-field toolbar-search">
            <span>Recherche</span>
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Rechercher dans la liste">
        </label>

        <?php if (($module['route'] ?? '') === 'user'): ?>
            <label class="field toolbar-field">
                <span>Role</span>
                <select name="role">
                    <option value="">Tous</option>
                    <?php foreach (ModuleRegistry::roleOptions() as $roleValue => $roleLabel): ?>
                        <option value="<?= htmlspecialchars((string) $roleValue) ?>" <?= (($filters['role'] ?? '') === (string) $roleValue) ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field toolbar-field">
                <span>Role secondaire</span>
                <select name="secondary_role">
                    <option value="">Tous</option>
                    <?php foreach (ModuleRegistry::roleOptions() as $roleValue => $roleLabel): ?>
                        <option value="<?= htmlspecialchars((string) $roleValue) ?>" <?= (($filters['secondary_role'] ?? '') === (string) $roleValue) ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field toolbar-field">
                <span>Statut</span>
                <select name="statut">
                    <option value="">Tous</option>
                    <option value="Actif" <?= (($filters['statut'] ?? '') === 'Actif') ? 'selected' : '' ?>>Actif</option>
                    <option value="Inactif" <?= (($filters['statut'] ?? '') === 'Inactif') ? 'selected' : '' ?>>Inactif</option>
                </select>
            </label>
        <?php endif; ?>

        <div class="toolbar-actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/' . $module['route'])) ?>">Reinitialiser</a>
        </div>
    </form>

    <?php if ($isUserModule): ?>
        <form method="post" action="<?= htmlspecialchars(route_url('/' . $module['route'] . '/bulk')) ?>" id="user-bulk-form" class="list-toolbar" style="margin-top: 10px;">
            <label class="field toolbar-field">
                <span>Action de masse</span>
                <select name="bulk_action" id="bulk-action-select">
                    <option value="set_status">Changer statut</option>
                    <option value="set_role">Changer role</option>
                    <option value="delete">Supprimer</option>
                </select>
            </label>
            <label class="field toolbar-field" id="bulk-status-field">
                <span>Nouveau statut</span>
                <select name="bulk_status">
                    <option value="Actif">Actif</option>
                    <option value="Inactif">Inactif</option>
                </select>
            </label>
            <label class="field toolbar-field" id="bulk-role-field" style="display:none;">
                <span>Nouveau role</span>
                <select name="bulk_role">
                    <?php foreach (ModuleRegistry::roleOptions() as $roleValue => $roleLabel): ?>
                        <option value="<?= htmlspecialchars((string) $roleValue) ?>"><?= htmlspecialchars($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="toolbar-actions">
                <button class="button" type="submit" onclick="return confirm('Appliquer cette action aux utilisateurs selectionnes ?');">Appliquer</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($showClientPicker && !empty($clientOptions)): ?>
        <div class="client-picker-head">
            <h3>Choisir un client</h3>
            <p>Les <?= htmlspecialchars(strtolower($module['label'])) ?> s'affichent apres selection du client.</p>
        </div>
        <div class="client-picker-grid">
            <?php foreach ($clientOptions as $clientOption): ?>
                <a class="client-picker-card" href="<?= htmlspecialchars(route_url('/' . $module['route']) . '?client_id=' . urlencode($clientOption['id'])) ?>">
                    <strong><?= htmlspecialchars($clientOption['label']) ?></strong>
                    <span><?= $clientOption['count'] ?> element<?= $clientOption['count'] > 1 ? 's' : '' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$showClientPicker && empty($rows)): ?>
        <div class="empty-state">Aucune donnee disponible.</div>
    <?php endif; ?>

    <?php foreach ($showClientPicker ? [] : $groups as $group): ?>
        <?php if (!empty($group['label'])): ?>
            <div class="list-group">
                <div class="list-group-head">
                    <div>
                        <h3><?= htmlspecialchars($group['label']) ?></h3>
                        <p><?= count($group['rows']) ?> element<?= count($group['rows']) > 1 ? 's' : '' ?></p>
                    </div>
                </div>
        <?php endif; ?>

        <?php if (!empty($group['rows'])): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($isUserModule): ?>
                                <th>Sel.</th>
                            <?php endif; ?>
                            <?php foreach ($listFields as $field): ?>
                                <th><?= htmlspecialchars($formFields[$field]['label'] ?? strtoupper($field)) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['rows'] as $row): ?>
                            <tr>
                                <?php if ($isUserModule): ?>
                                    <td>
                                        <input type="checkbox" class="bulk-user-checkbox" name="selected_ids[]" value="<?= htmlspecialchars((string) $row[$module['primaryKey']]) ?>" form="user-bulk-form">
                                    </td>
                                <?php endif; ?>
                                <?php foreach ($listFields as $field): ?>
                                    <?php $displayValue = crud_display_value($row, $field, $formFields, $options); ?>
                                    <td>
                                        <span class="cell-content"><?= nl2br(htmlspecialchars($displayValue)) ?></span>
                                    </td>
                                <?php endforeach; ?>
                                <td class="actions-cell">
                                    <div class="icon-actions">
                                        <a class="icon-link" href="<?= htmlspecialchars(route_url('/' . $module['route'] . '/show/' . $row[$module['primaryKey']])) ?>" aria-label="Voir" title="Voir">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>
                                        </a>
                                        <a class="icon-link" href="<?= htmlspecialchars(route_url('/' . $module['route'] . '/edit/' . $row[$module['primaryKey']])) ?>" aria-label="Modifier" title="Modifier">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4Zm11-13 4 4M13 6l4 4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                        <a class="icon-link danger" href="<?= htmlspecialchars(route_url('/' . $module['route'] . '/delete/' . $row[$module['primaryKey']])) ?>" aria-label="Supprimer" title="Supprimer" onclick="return confirm('Supprimer cet element ?');">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-7 0 1 12h6l1-12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($group['label'])): ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<?php if ($isUserModule): ?>
<script>
(function () {
    var allBtn = document.getElementById('bulk-select-all');
    var noneBtn = document.getElementById('bulk-select-none');
    var checkboxes = document.querySelectorAll('.bulk-user-checkbox');
    var actionSelect = document.getElementById('bulk-action-select');
    var statusField = document.getElementById('bulk-status-field');
    var roleField = document.getElementById('bulk-role-field');

    function refreshBulkFields() {
        var action = actionSelect ? actionSelect.value : '';
        if (statusField) {
            statusField.style.display = action === 'set_status' ? '' : 'none';
        }
        if (roleField) {
            roleField.style.display = action === 'set_role' ? '' : 'none';
        }
    }

    if (allBtn) {
        allBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) { checkbox.checked = true; });
        });
    }
    if (noneBtn) {
        noneBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) { checkbox.checked = false; });
        });
    }
    if (actionSelect) {
        actionSelect.addEventListener('change', refreshBulkFields);
    }

    refreshBulkFields();
})();
</script>
<?php endif; ?>
