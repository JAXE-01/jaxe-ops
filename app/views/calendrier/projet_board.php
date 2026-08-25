<?php
$project = $calendar['project'];
$plans = $calendar['plans'];
$selectedMonth = $calendar['selectedMonth'] ?? null;
$selectedPlanId = (int) ($selectedPlanId ?? 0);
$readyDeliverablesForPublicValidation = is_array($readyDeliverablesForPublicValidation ?? null) ? $readyDeliverablesForPublicValidation : [];
$publicValidationLinks = is_array($publicValidationLinks ?? null) ? $publicValidationLinks : [];
$calendarStatsByPlan = is_array($calendarStatsByPlan ?? null) ? $calendarStatsByPlan : [];
$showAllPipelineStages = !empty($showAllPipelineStages);
$boardReassignmentOptions = is_array($boardReassignmentOptions ?? null) ? $boardReassignmentOptions : [];
$boardCanManageAsCC = !empty($boardCanManageAsCC);
$boardCurrentUserId = (int) ($boardCurrentUserId ?? 0);
$focusedDeliverableId = (int) ($_GET['focus_deliverable'] ?? 0);
$currentReturn = route_url('/calendrier/projet/' . $project['id']) . ($selectedMonth ? '?month=' . urlencode($selectedMonth) : '');

if (!function_exists('calendrier_stage_columns')) {
    function calendrier_stage_columns($type) {
        if ($type === 'Video') {
            return ['Script', 'Tournage', 'Montage', 'Validation interne', 'Validation client', 'Publication', 'Collecte KPI'];
        }

        return ['Brief', 'Production', 'Validation interne', 'Validation client', 'Publication', 'Collecte KPI'];
    }
}

if (!function_exists('calendrier_visible_stage_columns')) {
    function calendrier_visible_stage_columns($type, array $deliverables, $showAllPipelineStages) {
        $allColumns = calendrier_stage_columns($type);
        if ($showAllPipelineStages) {
            return $allColumns;
        }

        $visible = [];
        foreach ($deliverables as $deliverable) {
            foreach ((array) ($deliverable['tasks'] ?? []) as $task) {
                $taskType = (string) ($task['type_tache'] ?? '');
                if (in_array($taskType, $allColumns, true) && !in_array($taskType, $visible, true)) {
                    $visible[] = $taskType;
                }
            }
        }

        return $visible;
    }
}

if (!function_exists('calendrier_group_deliverables')) {
    function calendrier_group_deliverables(array $deliverables) {
        $grouped = ['Video' => [], 'Visuel' => []];
        foreach ($deliverables as $deliverable) {
            $grouped[$deliverable['type_livrable']][] = $deliverable;
        }
        return $grouped;
    }
}

if (!function_exists('calendrier_preview_label')) {
    function calendrier_preview_label($file) {
        $extension = strtoupper((string) ($file['extension'] ?? ''));
        return $extension !== '' ? $extension : 'FICHIER';
    }
}

if (!function_exists('calendrier_preview_role_label')) {
    function calendrier_preview_role_label($file) {
        return (string) ($file['role_label'] ?? 'EXPORT');
    }
}

if (!function_exists('calendrier_task_status_class')) {
    function calendrier_task_status_class(array $task) {
        $status = (string) ($task['statut'] ?? '');
        return strtolower(str_replace(' ', '-', $status));
    }
}

if (!function_exists('calendrier_task_status_label')) {
    function calendrier_task_status_label(array $task) {
        $status = (string) ($task['statut'] ?? '');
        if (in_array((string) ($task['type_tache'] ?? ''), ['Montage', 'Production', 'Brief', 'Script', 'Calendrier'], true)
            && $status === 'Annulee') {
            return 'Non valide';
        }
        return $status;
    }
}

if (!function_exists('calendrier_reassignment_options_for_task')) {
    function calendrier_reassignment_options_for_task($taskType, array $optionsByGroup) {
        $taskType = (string) $taskType;
        if (in_array($taskType, ['Brief', 'Script', 'Production'], true)) {
            return (array) ($optionsByGroup['creative'] ?? []);
        }
        if (in_array($taskType, ['Tournage', 'Montage'], true)) {
            return (array) ($optionsByGroup['video'] ?? []);
        }
        if ($taskType === 'Validation client') {
            return (array) ($optionsByGroup['validation_client'] ?? []);
        }
        if ($taskType === 'Validation interne') {
            return (array) ($optionsByGroup['validation_interne'] ?? []);
        }
        if (in_array($taskType, ['Publication', 'Interactions', 'Collecte KPI'], true)) {
            return (array) ($optionsByGroup['publication'] ?? []);
        }

        return (array) ($optionsByGroup['generic'] ?? []);
    }
}

if (!function_exists('calendrier_can_manage_task')) {
    function calendrier_can_manage_task(array $task, $currentUserId, $canManageAsCC) {
        return !empty($canManageAsCC);
    }
}

if (!function_exists('calendrier_can_manage_deliverable_date')) {
    function calendrier_can_manage_deliverable_date(array $deliverable, $currentUserId, $canManageAsCC) {
        return !empty($canManageAsCC);
    }
}
?>
<section class="page-intro-card project-board-intro"><div><span class="page-eyebrow">Espace projet</span><h2><?= htmlspecialchars($project['nom'] ?? 'Projet') ?></h2><p><?= htmlspecialchars($project['entreprise'] ?? $project['client_nom'] ?? '') ?> · Suivi mensuel, production, validations et publication.</p></div><span class="context-pill"><?= count($plans) ?> mois planifié<?= count($plans) > 1 ? 's' : '' ?></span></section>
<?php if ($selectedPlanId > 0): ?>
    <section class="panel public-validation-panel">
        <div class="panel-head">
            <div>
                <h2>Lien public client</h2>
                <p class="panel-subtitle">Selectionne les livrables prets a valider et genere un lien externe partageable.</p>
            </div>
        </div>

        <form method="post" action="<?= htmlspecialchars(route_url('/calendrier/createPublicValidationLink')) ?>" class="form-grid">
            <input type="hidden" name="plan_id" value="<?= htmlspecialchars((string) $selectedPlanId) ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentReturn) ?>">

            <label class="field">
                <span>Expiration du lien (jours)</span>
                <input type="number" min="1" max="365" name="expiry_days" value="45">
            </label>

            <div class="field">
                <span>Livrables prets a afficher</span>
                <?php if (!empty($readyDeliverablesForPublicValidation)): ?>
                    <div class="checkbox-grid">
                        <?php foreach ($readyDeliverablesForPublicValidation as $item): ?>
                            <label class="checkbox-pill">
                                <input type="checkbox" name="deliverable_ids[]" value="<?= htmlspecialchars((string) ($item['deliverable_id'] ?? 0)) ?>" checked>
                                <span><?= htmlspecialchars((string) ($item['titre'] ?? 'Livrable')) ?> · <?= htmlspecialchars((string) ($item['date_prevue'] ?? '')) ?> · <?= htmlspecialchars((string) ($item['canal'] ?? '')) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Aucun livrable pret a valider pour ce mois.</p>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="button" type="submit">Generer le lien public</button>
            </div>
        </form>

        <?php if (!empty($publicValidationLinks)): ?>
            <div class="table-wrap compact-table" style="margin-top:16px;">
                <table>
                    <thead>
                        <tr>
                            <th>Lien</th>
                            <th>Selection</th>
                            <th>Expiration</th>
                            <th>Etat</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publicValidationLinks as $link): ?>
                            <?php
                            $publicUrl = route_url('/public-validation/index/' . (string) ($link['token'] ?? ''));
                            $isRevoked = !empty($link['revoked_at']);
                            $isExpired = !empty($link['expires_at']) && strtotime((string) $link['expires_at']) < time();
                            ?>
                            <tr>
                                <td><a class="link" target="_blank" rel="noopener noreferrer" href="<?= htmlspecialchars($publicUrl) ?>"><?= htmlspecialchars($publicUrl) ?></a></td>
                                <td><?= htmlspecialchars((string) ($link['selected_deliverables'] ?? 0)) ?> livrable(s)</td>
                                <td><?= htmlspecialchars((string) ($link['expires_at'] ?? '')) ?></td>
                                <td>
                                    <?php if ($isRevoked): ?>
                                        <span class="status-badge status-annulee">Revoque</span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="status-badge status-annulee">Expire</span>
                                    <?php else: ?>
                                        <span class="status-badge status-terminee">Actif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$isRevoked): ?>
                                        <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier/revokePublicValidationLink/' . (int) ($link['id'] ?? 0)) . '?return_to=' . urlencode($currentReturn)) ?>" onclick="return confirm('Revoquer ce lien public ?');">Revoquer</a>
                                        <button type="button" class="button secondary" data-copy-link="<?= htmlspecialchars($publicUrl) ?>">Copier le lien</button>
                                    <?php else: ?>
                                        <span class="mini-text">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php endif; ?>

<?php foreach ($plans as $plan): ?>
    <details class="panel month-panel collapsible-panel" <?= $selectedMonth ? 'open' : '' ?>>
        <summary class="collapsible-summary">
            <span>
                <strong>Mois <?= htmlspecialchars((string) $plan['index_mois']) ?> · <?= htmlspecialchars(date('F Y', strtotime($plan['periode_mois']))) ?></strong>
                <small><?= htmlspecialchars((string) $plan['livrables_prevus']) ?> livrable(s) prevu(s)</small>
            </span>
            <span class="collapsible-indicator">Afficher / masquer</span>
        </summary>
        <div class="panel-head">
            <div>
                <h2>Mois <?= htmlspecialchars((string) $plan['index_mois']) ?> · <?= htmlspecialchars(date('F Y', strtotime($plan['periode_mois']))) ?></h2>
                <p class="panel-subtitle">Prevu: <?= htmlspecialchars((string) $plan['livrables_prevus']) ?> livrable(s) · Livre: <?= htmlspecialchars((string) $plan['livrables_livres']) ?> · <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $plan['statut']))) ?>"><?= htmlspecialchars($plan['statut']) ?></span></p>
            </div>
            <span class="workflow-note">Les livrables du mois sont generes automatiquement depuis les quotas du projet.</span>
        </div>

        <?php $planStats = (array) ($calendarStatsByPlan[(int) ($plan['id'] ?? 0)] ?? []); ?>
        <div class="stats-grid">
            <article class="stat-card">
                <span class="stat-label">Retard moyen</span>
                <span class="stat-value"><?= htmlspecialchars((string) ($planStats['avg_delay_days'] ?? 0)) ?> j</span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Validation client au 1er passage</span>
                <span class="stat-value"><?= htmlspecialchars((string) ($planStats['first_pass_validation_rate'] ?? 0)) ?>%</span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Ratio invalidations</span>
                <span class="stat-value"><?= htmlspecialchars((string) ($planStats['invalidation_ratio'] ?? 0)) ?>%</span>
            </article>
        </div>

        <div class="workflow-strip">
            <?php foreach ($plan['month_tasks'] as $task): ?>
                <?php $taskStatusClass = calendrier_task_status_class($task); ?>
                <?php $taskStatusLabel = calendrier_task_status_label($task); ?>
                <article class="workflow-card status-<?= htmlspecialchars($taskStatusClass) ?>">
                    <strong><?= htmlspecialchars($task['titre']) ?></strong>
                    <span><?= htmlspecialchars($task['type_tache']) ?></span>
                    <span><span class="status-badge status-<?= htmlspecialchars($taskStatusClass) ?>"><?= htmlspecialchars($taskStatusLabel) ?></span> · <?= htmlspecialchars($task['deadline'] ?: 'Sans deadline') ?></span>
                    <?php $canManageTask = calendrier_can_manage_task($task, $boardCurrentUserId, $boardCanManageAsCC); ?>
                    <?php if ($canManageTask): ?>
                        <?php $taskReassignmentOptions = calendrier_reassignment_options_for_task((string) ($task['type_tache'] ?? ''), $boardReassignmentOptions); ?>
                        <?php if (!empty($taskReassignmentOptions)): ?>
                            <details class="mini-reassign-panel">
                                <summary class="mini-reassign-trigger">Responsable: <?= htmlspecialchars($task['auteur_nom'] ?: 'Non assigne') ?></summary>
                                <form method="post" class="mini-inline-form mini-reassign-ajax" data-ajax-url="<?= htmlspecialchars(route_url('/calendrier/reassignTask/' . (int) ($task['id'] ?? 0))) ?>">
                                    <select name="auteur_id" class="mini-select" aria-label="Reattribuer la tache">
                                        <?php foreach ($taskReassignmentOptions as $optionValue => $optionLabel): ?>
                                            <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= ((int) ($task['auteur_id'] ?? 0) === (int) $optionValue) ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button secondary mini-submit" type="submit">OK</button>
                                </form>
                            </details>
                        <?php else: ?>
                            <span>Responsable: <?= htmlspecialchars($task['auteur_nom'] ?: 'Non assigne') ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span>Responsable: <?= htmlspecialchars($task['auteur_nom'] ?: 'Non assigne') ?></span>
                    <?php endif; ?>
                    <a class="icon-link" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id'])) ?>" aria-label="Ouvrir" title="Ouvrir">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M13 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <?php $deliverableGroups = calendrier_group_deliverables($plan['deliverables']); ?>
        <?php foreach ($deliverableGroups as $deliverableType => $deliverables): ?>
            <?php $visibleStageColumns = calendrier_visible_stage_columns($deliverableType, $deliverables, $showAllPipelineStages); ?>
            <div class="list-group">
                <div class="list-group-head">
                    <div>
                        <h3><?= htmlspecialchars($deliverableType === 'Video' ? 'Livrables video' : 'Livrables visuels') ?></h3>
                        <p><?= count($deliverables) ?> element<?= count($deliverables) > 1 ? 's' : '' ?></p>
                    </div>
                </div>
                <div class="table-wrap calendar-table-wrap">
                    <table class="calendar-table">
                        <thead>
                            <tr>
                                <th>Livrable</th>
                                <th>Type</th>
                                <th>Date prevue</th>
                                <th>Fiche contenu</th>
                                <th>Progression</th>
                                <th>Apercus</th>
                                <?php foreach ($visibleStageColumns as $column): ?>
                                    <th><?= htmlspecialchars($column) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliverables as $deliverable): ?>
                                <?php
                                $taskMap = [];
                                foreach ($deliverable['tasks'] as $task) {
                                    $taskMap[$task['type_tache']] = $task;
                                }
                                ?>
                                <tr id="deliverable-<?= htmlspecialchars((string) ($deliverable['id'] ?? 0)) ?>" class="<?= $focusedDeliverableId === (int) ($deliverable['id'] ?? 0) ? 'focus-deliverable-row' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($deliverable['titre']) ?></strong>
                                        <div class="mini-text">Statut: <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $deliverable['statut']))) ?>"><?= htmlspecialchars($deliverable['statut']) ?></span></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($deliverable['type_livrable']) ?><?= !empty($deliverable['sous_type']) ? ' · ' . htmlspecialchars($deliverable['sous_type']) : '' ?>
                                    </td>
                                    <td>
                                        <?php $plannedDate = (string) ($deliverable['date_prevue'] ?? ''); ?>
                                        <?php $canManageDate = calendrier_can_manage_deliverable_date($deliverable, $boardCurrentUserId, $boardCanManageAsCC); ?>
                                        <span><?= htmlspecialchars($plannedDate !== '' ? $plannedDate : 'N/A') ?></span>
                                        <?php if ($canManageDate): ?>
                                            <details class="mini-reassign-panel" style="margin-top:6px;">
                                                <summary class="mini-reassign-trigger">Modifier</summary>
                                                <form method="post" action="<?= htmlspecialchars($currentReturn) ?>" class="mini-inline-form">
                                                    <input type="hidden" name="manager_action" value="move_publication_date">
                                                    <input type="hidden" name="deliverable_id" value="<?= htmlspecialchars((string) ($deliverable['id'] ?? 0)) ?>">
                                                    <input type="date" name="new_date_prevue" class="mini-select" value="<?= htmlspecialchars($plannedDate) ?>" required>
                                                    <button class="button secondary mini-submit" type="submit">OK</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="task-cell status-<?= !empty($deliverable['content_ready']) ? 'terminee' : 'bloquee' ?>">
                                            <strong><span class="status-badge status-<?= !empty($deliverable['content_ready']) ? 'terminee' : 'bloquee' ?>"><?= !empty($deliverable['content_ready']) ? 'Pret' : 'A completer' ?></span></strong>
                                            <span><?= htmlspecialchars($deliverable['contenu_statut'] ?? 'Strategique defini') ?></span>
                                            <a class="icon-link" href="<?= htmlspecialchars(route_url('/calendrier/contenu/' . $deliverable['id'])) ?>" aria-label="Ouvrir la fiche contenu" title="Ouvrir la fiche contenu">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h10l6 6v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm10 0v6h6M8 13h8M8 17h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $missingAssets = $deliverable['missing_assets'] ?? ['count' => 0, 'items' => [], 'complete' => true]; ?>
                                        <?php $progress = $deliverable['progress'] ?? ['percent' => 0, 'done' => 0, 'total' => 0, 'current_stage' => null, 'blocked' => 0]; ?>
                                        <div class="deliverable-progress-card">
                                            <div class="deliverable-progress-head">
                                                <strong><?= htmlspecialchars((string) $progress['percent']) ?>%</strong>
                                                <span><?= htmlspecialchars((string) $progress['done']) ?>/<?= htmlspecialchars((string) $progress['total']) ?> etapes</span>
                                            </div>
                                            <div class="progress-track"><span class="progress-fill" style="width: <?= max(0, min(100, (int) $progress['percent'])) ?>%"></span></div>
                                            <div class="mini-text">
                                                <?= !empty($progress['current_stage']) ? 'En cours: ' . htmlspecialchars($progress['current_stage']) : 'Workflow termine ou en attente' ?>
                                                <?= !empty($progress['blocked']) ? ' · ' . htmlspecialchars((string) $progress['blocked']) . ' bloquee(s)' : '' ?>
                                            </div>
                                            <div class="mini-text <?= $missingAssets['complete'] ? 'asset-complete' : 'asset-missing' ?>" title="<?= htmlspecialchars(!empty($missingAssets['items']) ? implode(', ', $missingAssets['items']) : 'Pack complet') ?>">
                                                <?= $missingAssets['complete'] ? 'Pack complet' : htmlspecialchars((string) $missingAssets['count']) . ' fichier(s) manquant(s)' ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $previewFiles = $deliverable['preview_files'] ?? []; ?>
                                        <?php if (!empty($previewFiles)): ?>
                                            <div class="preview-grid">
                                                <?php foreach ($previewFiles as $preview): ?>
                                                    <a class="preview-card preview-<?= htmlspecialchars($preview['kind']) ?> preview-role-<?= htmlspecialchars($preview['role'] ?? 'export') ?>" href="<?= htmlspecialchars(upload_url($preview['path'])) ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars($preview['name']) ?>" <?= in_array($preview['kind'], ['image', 'video'], true) ? 'data-preview-kind="' . htmlspecialchars($preview['kind']) . '" data-preview-src="' . htmlspecialchars(upload_url($preview['path'])) . '" data-preview-name="' . htmlspecialchars($preview['name']) . '"' : '' ?>>
                                                        <?php if ($preview['kind'] === 'image'): ?>
                                                            <img src="<?= htmlspecialchars(upload_url($preview['path'])) ?>" alt="<?= htmlspecialchars($preview['name']) ?>">
                                                        <?php elseif ($preview['kind'] === 'video'): ?>
                                                            <video preload="metadata" muted playsinline>
                                                                <source src="<?= htmlspecialchars(upload_url($preview['path'])) ?>">
                                                            </video>
                                                            <span class="preview-pill">VIDEO</span>
                                                        <?php else: ?>
                                                            <span class="preview-pill"><?= htmlspecialchars(calendrier_preview_label($preview)) ?></span>
                                                        <?php endif; ?>
                                                        <span class="preview-role-badge"><?= htmlspecialchars(calendrier_preview_role_label($preview)) ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="mini-text">Aucun fichier</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($visibleStageColumns as $column): ?>
                                        <?php $task = $taskMap[$column] ?? null; ?>
                                        <td>
                                            <?php if ($task): ?>
                                                <?php $taskStatusClass = calendrier_task_status_class($task); ?>
                                                <?php $taskStatusLabel = calendrier_task_status_label($task); ?>
                                                <div class="task-cell status-<?= htmlspecialchars($taskStatusClass) ?>">
                                                    <strong><span class="status-badge status-<?= htmlspecialchars($taskStatusClass) ?>"><?= htmlspecialchars($taskStatusLabel) ?></span></strong>
                                                    <span><?= htmlspecialchars($task['deadline'] ?: 'N/A') ?></span>
                                                    <?php $canManageTask = calendrier_can_manage_task($task, $boardCurrentUserId, $boardCanManageAsCC); ?>
                                                    <?php if ($canManageTask): ?>
                                                        <?php $taskReassignmentOptions = calendrier_reassignment_options_for_task((string) ($task['type_tache'] ?? ''), $boardReassignmentOptions); ?>
                                                        <?php if (!empty($taskReassignmentOptions)): ?>
                                                            <details class="mini-reassign-panel">
                                                                <summary class="mini-reassign-trigger">Responsable: <?= htmlspecialchars($task['auteur_nom'] ?: 'Non assigne') ?></summary>
                                                                <form method="post" class="mini-inline-form mini-reassign-ajax" data-ajax-url="<?= htmlspecialchars(route_url('/calendrier/reassignTask/' . (int) ($task['id'] ?? 0))) ?>">
                                                                    <select name="auteur_id" class="mini-select" aria-label="Reattribuer la tache">
                                                                        <?php foreach ($taskReassignmentOptions as $optionValue => $optionLabel): ?>
                                                                            <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= ((int) ($task['auteur_id'] ?? 0) === (int) $optionValue) ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <button class="button secondary mini-submit" type="submit">OK</button>
                                                                </form>
                                                            </details>
                                                        <?php else: ?>
                                                            <span>Responsable: <?= htmlspecialchars($task['auteur_nom'] ?: 'Non assigne') ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span>Responsable: <?= htmlspecialchars($task['auteur_nom'] ?: 'Non assigne') ?></span>
                                                    <?php endif; ?>
                                                    <?php
                                                    $targetHref = route_url('/calendrier/task/' . $task['id']);
                                                    ?>
                                                    <a class="icon-link" href="<?= htmlspecialchars($targetHref) ?>" aria-label="Voir" title="Voir">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <span class="mini-text">Non genere</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($deliverables)): ?>
                                <tr><td colspan="<?= 6 + count($visibleStageColumns) ?>">Aucun livrable <?= htmlspecialchars(strtolower($deliverableType)) ?> pour ce mois.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </details>
<?php endforeach; ?>

<script>
(function () {
    document.addEventListener('submit', function (evt) {
        var form = evt.target;
        if (!form || !form.classList.contains('mini-reassign-ajax')) {
            return;
        }
        evt.preventDefault();

        var url = form.getAttribute('data-ajax-url');
        if (!url) { return; }

        var select = form.querySelector('select[name="auteur_id"]');
        var btn = form.querySelector('button[type="submit"]');
        var details = form.closest('details.mini-reassign-panel');
        var summary = details ? details.querySelector('.mini-reassign-trigger') : null;

        var authorLabel = '';
        if (select && select.selectedIndex >= 0) {
            authorLabel = (select.options[select.selectedIndex] || {}).text || '';
        }

        var originalBtnText = btn ? btn.textContent : 'OK';
        if (btn) { btn.disabled = true; btn.textContent = '...'; }

        var fd = new FormData(form);
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var settled = false;
        var timeoutId = setTimeout(function () {
            if (settled) {
                return;
            }
            if (controller) {
                controller.abort();
                return;
            }
            settled = true;
            if (btn) { btn.disabled = false; btn.textContent = originalBtnText; }
            if (window.AppUI && window.AppUI.toast) {
                window.AppUI.toast('error', 'La reattribution prend trop de temps. Veuillez reessayer.');
            }
        }, 15000);

        fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: fd,
            signal: controller ? controller.signal : undefined
        }).then(function (r) {
            return r.json().then(function (json) { return { ok: r.ok, json: json }; });
        }).then(function (result) {
            settled = true;
            clearTimeout(timeoutId);
            if (btn) { btn.disabled = false; btn.textContent = originalBtnText; }
            if (result.ok && result.json && result.json.ok) {
                var name = authorLabel.split(' \u00b7 ')[0] || authorLabel;
                if (summary) { summary.textContent = 'Responsable\u00a0: ' + (name || 'Inconnu'); }
                if (details) { details.open = false; }
                if (window.AppUI && window.AppUI.toast) {
                    window.AppUI.toast('success', 'Responsable mis a jour.');
                }
            } else {
                var msg = (result.json && result.json.message) || 'Erreur de reattribution.';
                if (window.AppUI && window.AppUI.toast) {
                    window.AppUI.toast('error', msg);
                }
            }
        }).catch(function () {
            settled = true;
            clearTimeout(timeoutId);
            if (btn) { btn.disabled = false; btn.textContent = originalBtnText; }
            if (window.AppUI && window.AppUI.toast) {
                window.AppUI.toast('error', 'Erreur reseau. Veuillez reessayer.');
            }
        });
    });
})();
</script>