<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/project-calendar-compact.css')) ?>">
<?php
$taskType = (string) ($task['type_tache'] ?? '');
$rawNetworks = $task['publication_reseaux'] ?? '';
$currentNetworks = is_array($rawNetworks) ? $rawNetworks : json_decode((string) $rawNetworks, true);
$currentNetworks = is_array($currentNetworks) ? $currentNetworks : [];
$workflowNetworks = ['Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'YouTube', 'WhatsApp'];
$deliverable = $task['deliverable'] ?? null;
$brief = $task['brief'] ?? [];
$publicationEntries = $task['publication_entries'] ?? [];
$latestPublication = $task['latest_publication'] ?? null;
$resultEntries = $task['result_entries'] ?? [];
$isBriefTask = in_array($taskType, ['Brief', 'Script'], true);
$isValidationTask = in_array($taskType, ['Validation interne', 'Validation client'], true);
$isProductionTask = in_array($taskType, ['Production', 'Tournage', 'Montage'], true);
$isTournageTask = $taskType === 'Tournage';
$isPublicationTask = $taskType === 'Publication';
$isResultTask = $taskType === 'Collecte KPI';
$phpUploadLimitLabel = (string) ($phpUploadLimitLabel ?? '');
$canManagerInvalidate = !empty($canManagerInvalidate);
$taskStatusDisplay = (string) ($task['statut'] ?? '');
if (in_array($taskType, ['Montage', 'Production', 'Brief', 'Script', 'Calendrier'], true) && $taskStatusDisplay === 'Annulee') {
    $taskStatusDisplay = 'Non valide';
}
$canViewFutureContentInfo = !empty($canViewFutureContentInfo);
$briefFiles = is_array($brief['pieces_jointes'] ?? null) ? $brief['pieces_jointes'] : [];
$taskFiles = json_decode((string) ($task['fichiers_livres'] ?? ''), true);
$taskFiles = is_array($taskFiles) ? $taskFiles : [];
$deliverableFiles = [];
if ($deliverable && !empty($deliverable['pieces_jointes'])) {
    $deliverableFiles = json_decode((string) $deliverable['pieces_jointes'], true);
    $deliverableFiles = is_array($deliverableFiles) ? $deliverableFiles : [];
}
$downloadBaseName = (string) ($task['livrable_titre'] ?? $task['titre'] ?? 'contenu');

$taskMap = $deliverable['taskMap'] ?? [];
$validationInterneTask = $taskMap['Validation interne'] ?? null;
$validationClientTask = $taskMap['Validation client'] ?? null;
$tournageTask = $taskMap['Tournage'] ?? null;
$tournageStorage = $tournageTask ? task_extract_tournage_storage((string) ($tournageTask['notes'] ?? '')) : ['disque' => '', 'dossier' => ''];
$deliverableTaskFilesForValidation = [];
if (is_array($deliverable['tasks'] ?? null)) {
    foreach ((array) $deliverable['tasks'] as $deliverableTask) {
        $deliverableTaskId = (int) ($deliverableTask['id'] ?? 0);
        $currentTaskId = (int) ($task['id'] ?? 0);
        if ($deliverableTaskId <= 0 || $deliverableTaskId === $currentTaskId) {
            continue;
        }

        $rawPipelineFiles = json_decode((string) ($deliverableTask['fichiers_livres'] ?? ''), true);
        $pipelineFiles = is_array($rawPipelineFiles) ? $rawPipelineFiles : [];
        if (empty($pipelineFiles)) {
            continue;
        }

        $taskTypeLabel = trim((string) ($deliverableTask['type_tache'] ?? 'Pipeline'));
        foreach ($pipelineFiles as $file) {
            if (!is_array($file)) {
                continue;
            }
            $file['origin'] = 'Pipeline · ' . ($taskTypeLabel !== '' ? $taskTypeLabel : 'Etape');
            $deliverableTaskFilesForValidation[] = $file;
        }
    }
}
$latestResult = !empty($resultEntries) ? $resultEntries[0] : null;
$kpiNetworkConfig = is_array($kpiNetworkConfig ?? null) ? $kpiNetworkConfig : [];
$kpiNetworkConfig = is_array($kpiNetworkConfig) ? $kpiNetworkConfig : [];
$kpiDefaultNetwork = (string) (array_key_first($kpiNetworkConfig) ?: 'facebook');
$postedKpiNetworks = array_values(array_unique(array_filter(array_map(static function ($value) {
    return strtolower(trim((string) $value));
}, (array) ($_POST['kpi_networks'] ?? [])))));
if (empty($postedKpiNetworks) && !empty($_POST['kpi_network'])) {
    $postedKpiNetworks[] = strtolower(trim((string) $_POST['kpi_network']));
}
if (empty($postedKpiNetworks) && $kpiDefaultNetwork !== '') {
    $postedKpiNetworks[] = strtolower($kpiDefaultNetwork);
}

$kpiPostedRawValues = is_array($_POST['kpi_values'] ?? null) ? $_POST['kpi_values'] : [];
$kpiDraftValuesByNetwork = [];
foreach ($kpiPostedRawValues as $networkKey => $networkValues) {
    if (is_array($networkValues)) {
        $kpiDraftValuesByNetwork[strtolower((string) $networkKey)] = $networkValues;
    }
}
if (empty($kpiDraftValuesByNetwork) && !empty($kpiPostedRawValues) && !empty($postedKpiNetworks)) {
    $kpiDraftValuesByNetwork[$postedKpiNetworks[0]] = $kpiPostedRawValues;
}
$contentStageRank = [
    'Brief' => 1,
    'Script' => 1,
    'Production' => 2,
    'Tournage' => 2,
    'Montage' => 2,
    'Validation interne' => 3,
    'Validation client' => 4,
    'Publication' => 5,
    'Collecte KPI' => 6,
];
$currentRank = $contentStageRank[$taskType] ?? 0;
$showBriefExisting = $canViewFutureContentInfo || $currentRank >= 2;
$showDeliverableExisting = $canViewFutureContentInfo || $currentRank >= 2;
$showValidationExisting = $canViewFutureContentInfo || $currentRank >= 4;
$showPublicationExisting = $canViewFutureContentInfo || $currentRank >= 6;
$showResultExisting = $canViewFutureContentInfo || $currentRank >= 6;
$selectedSocialAccountPreview = is_array($selectedSocialAccountPreview ?? null) ? $selectedSocialAccountPreview : [];
$canReassignTask = !empty($canReassignTask);
$canManageTaskPlanningDate = !empty($canManageTaskPlanningDate);
$reassignmentOptions = is_array($reassignmentOptions ?? null) ? $reassignmentOptions : [];
$inlineErrors = is_array($inlineErrors ?? null) ? $inlineErrors : [];
$canGeneratePublicValidationLink = !empty($canGeneratePublicValidationLink);
$taskPublicValidationLinks = is_array($taskPublicValidationLinks ?? null) ? $taskPublicValidationLinks : [];
$taskReadyDeliverablesForPublicValidation = is_array($taskReadyDeliverablesForPublicValidation ?? null) ? $taskReadyDeliverablesForPublicValidation : [];
$taskIsBlocked = (string) ($task['statut'] ?? '') === 'Bloquee';
$requireSecondMontageVideo = !empty($requireSecondMontageVideo);
$currentTaskUrl = route_url('/calendrier/task/' . (int) ($task['id'] ?? 0));

function task_field_error(array $inlineErrors, $field) {
    $field = (string) $field;
    return isset($inlineErrors[$field]) ? (string) $inlineErrors[$field] : '';
}

function task_field_has_error(array $inlineErrors, $field) {
    return task_field_error($inlineErrors, $field) !== '';
}

function task_preview_kind(array $file) {
    $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
        return 'image';
    }
    if (in_array($extension, ['mp4', 'mov', 'webm'], true)) {
        return 'video';
    }
    if ($extension === 'pdf') {
        return 'pdf';
    }
    return 'file';
}

function task_preview_label(array $file) {
    $extension = strtoupper((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    return $extension !== '' ? $extension : 'FICHIER';
}

function task_download_url(array $file, $downloadBaseName) {
    return route_url('/asset/download')
        . '?path=' . urlencode((string) ($file['path'] ?? ''))
        . '&name=' . urlencode((string) $downloadBaseName);
}

function task_view_url(array $file, $downloadBaseName) {
    $path = trim((string) ($file['path'] ?? ''));
    if ($path !== '') {
        return upload_url($path);
    }
    return task_download_url($file, $downloadBaseName);
}

function task_extract_tournage_storage($rawNotes) {
    $rawNotes = (string) $rawNotes;
    $pattern = '/^\[TOURNAGE_STORAGE\]\Rdisque=(.*)\Rdossier=(.*)\R\[\/TOURNAGE_STORAGE\](?:\R\R)?/';
    if (!preg_match($pattern, $rawNotes, $matches)) {
        return ['disque' => '', 'dossier' => ''];
    }

    return [
        'disque' => trim((string) ($matches[1] ?? '')),
        'dossier' => trim((string) ($matches[2] ?? '')),
    ];
}

function task_guided_requirements($taskType, array $task, array $brief, array $taskFiles, array $currentNetworks, $latestPublication) {
    $requirements = [];
    $latestPublication = is_array($latestPublication) ? $latestPublication : [];

    if (in_array($taskType, ['Brief', 'Script'], true)) {
        $requirements[] = ['key' => 'brief_title', 'label' => 'Titre de consigne renseigne', 'done' => trim((string) ($brief['titre_brief'] ?? '')) !== ''];
        $requirements[] = ['key' => 'brief_message', 'label' => $taskType === 'Brief' ? 'Message detaille complete' : 'Plan de script complete', 'done' => trim((string) ($taskType === 'Brief' ? ($brief['details_message'] ?? '') : ($brief['plan_script'] ?? ''))) !== ''];
        $requirements[] = ['key' => 'brief_status', 'label' => 'Statut de consigne sur Valide', 'done' => (string) ($brief['statut'] ?? '') === 'Valide'];
        return $requirements;
    }

    if ($taskType === 'Tournage') {
        $requirements[] = ['key' => 'tournage_disk', 'label' => 'Disque de copie renseigne', 'done' => trim((string) ($task['tournage_disque'] ?? '')) !== ''];
        $requirements[] = ['key' => 'tournage_folder', 'label' => 'Dossier de copie renseigne', 'done' => trim((string) ($task['tournage_dossier'] ?? '')) !== ''];
        return $requirements;
    }

    if (in_array($taskType, ['Production', 'Montage'], true)) {
        $requirements[] = ['key' => 'production_files', 'label' => 'Au moins un fichier de contribution ajoute', 'done' => !empty($taskFiles)];
        return $requirements;
    }

    if (in_array($taskType, ['Validation interne', 'Validation client'], true)) {
        $decision = trim((string) ($task['validation_decision'] ?? ''));
        $requirements[] = ['key' => 'validation_decision', 'label' => 'Decision renseignee', 'done' => in_array($decision, ['Valide', 'Non valide'], true)];
        $requirements[] = ['key' => 'validation_comment', 'label' => 'Commentaire si decision Non valide', 'done' => $decision !== 'Non valide' || trim((string) ($task['validation_commentaire'] ?? '')) !== ''];
        return $requirements;
    }

    if ($taskType === 'Publication') {
        $requirements[] = ['key' => 'publication_date', 'label' => 'Date de publication renseignee', 'done' => trim((string) ($latestPublication['date_publication'] ?? $_POST['date_publication'] ?? '')) !== ''];
        $requirements[] = ['key' => 'publication_canal', 'label' => 'Canal final renseigne', 'done' => trim((string) ($latestPublication['canal'] ?? $_POST['canal'] ?? '')) !== ''];
        $requirements[] = ['key' => 'publication_networks', 'label' => 'Au moins un reseau coche', 'done' => !empty($currentNetworks)];
        return $requirements;
    }

    if ($taskType === 'Collecte KPI') {
        $postedNetworks = array_values(array_filter(array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, (array) ($_POST['kpi_networks'] ?? []))));
        if (empty($postedNetworks) && !empty($_POST['kpi_network'])) {
            $postedNetworks[] = strtolower(trim((string) $_POST['kpi_network']));
        }

        $rawKpiValues = is_array($_POST['kpi_values'] ?? null) ? $_POST['kpi_values'] : [];
        $hasKpiValue = false;
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($rawKpiValues));
        foreach ($iterator as $kpiValue) {
            if (trim((string) $kpiValue) !== '') {
                $hasKpiValue = true;
                break;
            }
        }

        $requirements[] = ['key' => 'kpi_date', 'label' => 'Date de collecte renseignee', 'done' => trim((string) ($_POST['date_collecte'] ?? '')) !== ''];
        $requirements[] = ['key' => 'kpi_value', 'label' => 'Reseaux et mesures KPI renseignes', 'done' => !empty($postedNetworks) && $hasKpiValue];
        return $requirements;
    }

    if ($taskType === 'Strategie') {
        $requirements[] = ['key' => 'strategie_notes', 'label' => 'Notes de strategie renseignees', 'done' => trim((string) ($task['notes'] ?? '')) !== ''];
        return $requirements;
    }

    return $requirements;
}

function task_extract_invalidation_history($notes) {
    $history = [];
    foreach (preg_split('/\R/', (string) $notes) as $line) {
        $line = trim((string) $line);
        if (strpos($line, '[[INVALIDATION]]') !== 0) {
            continue;
        }

        $payload = substr($line, strlen('[[INVALIDATION]]'));
        $parts = explode('|', $payload, 3);
        $history[] = [
            'date' => trim((string) ($parts[0] ?? '')),
            'source' => trim((string) ($parts[1] ?? 'Validation')),
            'comment' => trim((string) ($parts[2] ?? '')),
        ];
    }

    return $history;
}

$invalidationHistory = [];
foreach ([$validationInterneTask, $validationClientTask] as $validationTask) {
    if (!$validationTask) {
        continue;
    }

    $invalidationHistory = array_merge($invalidationHistory, task_extract_invalidation_history($validationTask['notes'] ?? ''));
    if (($validationTask['validation_decision'] ?? '') === 'Non valide' && trim((string) ($validationTask['validation_commentaire'] ?? '')) !== '') {
        $invalidationHistory[] = [
            'date' => '',
            'source' => (string) ($validationTask['type_tache'] ?? 'Validation'),
            'comment' => trim((string) $validationTask['validation_commentaire']),
        ];
    }
}

function task_action_title($taskType) {
    if ($taskType === 'Brief') {
        return 'Action a effectuer: rediger le brief';
    }
    if ($taskType === 'Script') {
        return 'Action a effectuer: rediger le script';
    }
    if ($taskType === 'Production') {
        return 'Action a effectuer: produire le visuel';
    }
    if ($taskType === 'Tournage') {
        return 'Action a effectuer: renseigner le stockage des rush';
    }
    if ($taskType === 'Montage') {
        return 'Action a effectuer: monter la video';
    }
    if ($taskType === 'Validation interne') {
        return 'Action a effectuer: valider en interne';
    }
    if ($taskType === 'Validation client') {
        return 'Action a effectuer: valider avec le client';
    }
    if ($taskType === 'Publication') {
        return 'Action a effectuer: planifier ou publier';
    }
    if ($taskType === 'Collecte KPI') {
        return 'Action a effectuer: enregistrer les resultats';
    }
    return 'Action a effectuer';
}

function task_action_subtitle($taskType) {
    if ($taskType === 'Brief' || $taskType === 'Script') {
        return 'Tu modifies uniquement la consigne du contenu. Les sections ci-dessus restent la base de travail commune.';
    }
    if (in_array($taskType, ['Production', 'Tournage', 'Montage'], true)) {
        if ($taskType === 'Tournage') {
            return 'Tu consultes l amont valide puis tu indiques ou les fichiers ont ete copies (disque + dossier), sans televersement dans la plateforme.';
        }
        return 'Tu consultes l amont deja valide et tu ajoutes uniquement ta contribution de production.';
    }
    if (in_array($taskType, ['Validation interne', 'Validation client'], true)) {
        return 'Tu ne modifies pas les etapes precedentes. Tu statues sur ce qui a ete livre et ajoutes ton retour.';
    }
    if ($taskType === 'Publication') {
        return 'Tu mets a jour l etat de diffusion de ce contenu sans exposer les resultats a ceux qui n en ont pas besoin.';
    }
    if ($taskType === 'Collecte KPI') {
        return 'Tu ajoutes un point de mesure sur le contenu deja publie.';
    }
    return 'Cette zone sert uniquement a l action attendue sur la tache en cours.';
}

function task_completion_note($taskType, $task) {
    if (in_array($taskType, ['Brief', 'Script'], true)) {
        return 'Tu peux enregistrer la consigne en brouillon. Elle ne sera reconnue comme finie que lorsque la consigne est complete et son statut passe a Valide.';
    }
    if (in_array($taskType, ['Production', 'Tournage', 'Montage'], true)) {
        if ($taskType === 'Tournage') {
            return 'Tu peux sauvegarder l avancement. La tache est reconnue comme terminee uniquement quand le disque et le dossier de copie sont renseignes.';
        }
        return 'Tu peux sauvegarder l avancement. La tache est reconnue comme terminee uniquement quand les fichiers attendus sont ajoutes et que tu valides la fin de travail.';
    }
    if (in_array($taskType, ['Validation interne', 'Validation client'], true)) {
        return 'La sauvegarde conserve ton analyse. La tache n est consideree finie que lorsque la decision est clairement posee sur Valide.';
    }
    if ($taskType === 'Publication') {
        return 'Tu peux enregistrer la preparation, mais la tache n est reconnue comme terminee que lorsque les informations de diffusion sont completees.';
    }
    if ($taskType === 'Collecte KPI') {
        return 'Tu peux enregistrer une note en cours, mais la collecte est reconnue quand la mesure est vraiment saisie.';
    }
    if ($taskType === 'Strategie' || $taskType === 'Calendrier') {
        return 'Tu peux sauvegarder en cours. Utilise la validation finale seulement quand le travail attendu est reellement termine.';
    }

    return 'La sauvegarde conserve ton avancement. Le statut final doit rester reserve au travail complet.';
}
?>
<section class="panel task-workspace-hero">
    <div class="panel-head">
        <div>
            <h2><?= htmlspecialchars($task['titre']) ?></h2>
            <p class="panel-subtitle task-context-title"><strong><?= htmlspecialchars($task['client_nom']) ?></strong><span><?= htmlspecialchars($task['projet_nom']) ?><?= !empty($task['periode_mois']) ? ' · ' . htmlspecialchars(date('F Y', strtotime($task['periode_mois']))) : '' ?></span></p>
        </div>
        <div class="toolbar-actions">
            <?php if (!empty($previousTaskUrl)): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $previousTaskUrl) ?>" data-shortcut-prev title="Étape précédente" aria-label="Étape précédente">←</a>
            <?php endif; ?>
            <?php if (!empty($nextTaskUrl)): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $nextTaskUrl) ?>" data-shortcut-next title="Étape suivante" aria-label="Étape suivante">→</a>
            <?php endif; ?>
            <button class="button secondary" type="button" data-compact-toggle>Mode compact</button>
            <a class="button secondary" href="<?= htmlspecialchars($returnTo) ?>" title="Retour au projet" aria-label="Retour au projet">↩</a>
            <?php if (($taskType === 'Publication' && in_array((string) ($task['statut'] ?? ''), ['Terminee', 'Validée', 'Validee'], true)) || !empty($latestPublication)): ?>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/reporting-metric') . '?project_id=' . (int) ($task['projet_id'] ?? 0)) ?>" title="Statistiques et rapports" aria-label="Statistiques et rapports">⌁</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-grid">
        <article class="detail-card"><span class="detail-label">Type</span><div class="detail-value"><?= htmlspecialchars($taskType) ?></div></article>
        <article class="detail-card"><span class="detail-label">Statut</span><div class="detail-value"><?= htmlspecialchars($taskStatusDisplay) ?></div></article>
        <article class="detail-card"><span class="detail-label">Deadline</span><div class="detail-value"><?= htmlspecialchars((string) ($task['deadline'] ?: 'N/A')) ?></div></article>
        <article class="detail-card"><span class="detail-label">Responsable</span><div class="detail-value"><?php if ($canReassignTask && !empty($reassignmentOptions)): ?><button type="button" class="detail-value-button" data-open-reassign-panel="1"><?= htmlspecialchars((string) ($task['auteur_nom'] ?: 'Non assigne')) ?></button><?php else: ?><?= htmlspecialchars((string) ($task['auteur_nom'] ?: 'Non assigne')) ?><?php endif; ?></div></article>
    </div>
</section>

<?php if ($canReassignTask && !empty($reassignmentOptions)): ?>
    <section class="panel inset-panel" id="task-reassign-panel" hidden>
        <div class="panel-head">
            <div>
                <h3>Reattribuer cette tache</h3>
                <p class="panel-subtitle">En cas d indisponibilite, transfere rapidement la tache a un autre profil metier.</p>
            </div>
        </div>
        <form method="post" action="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) ($task['id'] ?? 0))) ?>" class="form-grid">
            <label class="field">
                <span>Nouveau responsable</span>
                <select name="auteur_id" required>
                    <option value="">Selectionner...</option>
                    <?php foreach ($reassignmentOptions as $optionValue => $optionLabel): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= ((int) ($task['auteur_id'] ?? 0) === (int) $optionValue) ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-actions">
                <button class="button secondary" type="submit" name="manager_action" value="reassign_task">Reattribuer la tache</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php
$guidedRequirements = task_guided_requirements($taskType, $task, $brief, $taskFiles, $currentNetworks, $latestPublication);
$guidedTotal = count($guidedRequirements);
$guidedDone = count(array_filter($guidedRequirements, static function ($item) {
    return !empty($item['done']);
}));
$guidedPercent = $guidedTotal > 0 ? (int) round(($guidedDone / $guidedTotal) * 100) : 100;
?>
<?php if ($guidedTotal > 0): ?>
    <section class="panel inset-panel task-guided-panel">
        <div class="panel-head">
            <div>
                <h2>Progression guidee</h2>
                <p class="panel-subtitle">Checklist metier pour finaliser cette etape sans oubli.</p>
            </div>
            <span class="status-badge status-<?= $guidedPercent >= 100 ? 'terminee' : 'en-cours' ?>"><?= htmlspecialchars((string) $guidedDone) ?>/<?= htmlspecialchars((string) $guidedTotal) ?></span>
        </div>
        <div class="deliverable-progress-card" style="max-width: 460px;">
            <div class="deliverable-progress-head">
                <strong><?= htmlspecialchars((string) $guidedPercent) ?>%</strong>
                <span>Prerequis couverts</span>
            </div>
            <div class="progress-track"><span class="progress-fill" style="width: <?= max(0, min(100, $guidedPercent)) ?>%"></span></div>
        </div>
        <ul class="requirement-list">
            <?php foreach ($guidedRequirements as $requirement): ?>
                <?php
                $guidedState = !empty($requirement['done']) ? 'done' : ($taskIsBlocked ? 'awaiting' : 'todo');
                $guidedStateClass = $guidedState === 'done' ? 'is-done' : ($guidedState === 'awaiting' ? 'is-awaiting' : 'is-todo');
                $guidedStateLabel = $guidedState === 'done' ? 'Fait' : ($guidedState === 'awaiting' ? 'En attente' : 'A faire');
                ?>
                <li class="requirement-item <?= $guidedStateClass ?>" data-guided-item data-guided-key="<?= htmlspecialchars((string) ($requirement['key'] ?? '')) ?>" data-guided-done="<?= !empty($requirement['done']) ? '1' : '0' ?>">
                    <span class="requirement-state"><?= htmlspecialchars($guidedStateLabel) ?></span>
                    <span><?= htmlspecialchars((string) ($requirement['label'] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if (!empty($task['livrable_item_id'])): ?>
    <details class="panel inset-panel collapsible-panel task-existing-context" open>
        <summary class="collapsible-summary">
            <span>
                <strong>Informations existantes</strong>
                <small>Contexte utile a cette etape</small>
            </span>
            <span class="collapsible-indicator">Afficher / masquer</span>
        </summary>
        <div class="panel-head">
            <div>

                <p class="panel-subtitle">Cette zone est en lecture seule pour la tache actuelle. Elle montre uniquement l amont utile a ton intervention.</p>
            </div>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier/contenu/' . $task['livrable_item_id'])) ?>">Fiche contenu</a>
        </div>
        <div class="panel stack-panel">
            <div class="panel-head">
                <div>
                    <h3>Objet contenu</h3>
                    <p class="panel-subtitle">Base generale et specifique du contenu.</p>
                </div>
            </div>
            <div class="detail-grid">
                <article class="detail-card"><span class="detail-label">Objectif du mois</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['objectif_mois'] ?? '—'))) ?></div></article>
                <article class="detail-card"><span class="detail-label">Temps forts</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['temps_forts_mois'] ?? '—'))) ?></div></article>
                <article class="detail-card"><span class="detail-label">Sujet</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['contenu_sujet'] ?? '—'))) ?></div></article>
                <article class="detail-card"><span class="detail-label">Objectif publication</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['objectif_publication'] ?? '—'))) ?></div></article>
                <article class="detail-card"><span class="detail-label">Message</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['contenu_message'] ?? '—'))) ?></div></article>
                <article class="detail-card"><span class="detail-label">Cible</span><div class="detail-value"><?= htmlspecialchars((string) ($task['persona_nom'] ?? $task['cible_libre'] ?? '—')) ?></div></article>
                <article class="detail-card"><span class="detail-label">Reseau cible</span><div class="detail-value"><?= htmlspecialchars((string) ($task['reseau_cible'] ?? $task['canal_principal'] ?? '—')) ?></div></article>
                <article class="detail-card"><span class="detail-label">Etat du contenu</span><div class="detail-value"><?= htmlspecialchars((string) ($task['contenu_statut'] ?? 'Strategique defini')) ?></div></article>
            </div>
        </div>

        <?php if ($showBriefExisting): ?>
            <div class="panel stack-panel">
                <div class="panel-head">
                    <div>
                        <h3><?= (($deliverable['type_livrable'] ?? '') === 'Video') ? 'Script / brief' : 'Brief / script' ?></h3>
                        <p class="panel-subtitle">Consigne redigee en amont pour guider la production.</p>
                    </div>
                </div>
                <div class="detail-grid">
                    <article class="detail-card"><span class="detail-label">Titre</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($brief['titre_brief'] ?? '—'))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Message detaille</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($brief['details_message'] ?? $brief['plan_script'] ?? '—'))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Instructions</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($brief['instructions_visuelles'] ?? $brief['recommandation_design'] ?? '—'))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">CTA</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($brief['cta'] ?? '—'))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Hook</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($brief['hook_video'] ?? '—'))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Texte / descriptif</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($brief['texte_script'] ?? $brief['description_publication'] ?? '—'))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Statut consigne</span><div class="detail-value"><?= htmlspecialchars((string) ($brief['statut'] ?? 'A faire')) ?></div></article>
                </div>

                <?php if (!empty($task['persona_nom'])): ?>
                    <div class="detail-grid">
                        <article class="detail-card"><span class="detail-label">Persona</span><div class="detail-value"><?= htmlspecialchars((string) ($task['persona_nom'] ?? '')) ?></div></article>
                        <article class="detail-card"><span class="detail-label">Profession</span><div class="detail-value"><?= htmlspecialchars((string) ($task['persona_profession'] ?? '—')) ?></div></article>
                        <article class="detail-card"><span class="detail-label">Objectif persona</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['persona_objectif'] ?? '—'))) ?></div></article>
                        <article class="detail-card"><span class="detail-label">Probleme principal</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['persona_probleme'] ?? '—'))) ?></div></article>
                        <article class="detail-card"><span class="detail-label">Desirs</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['persona_desirs'] ?? '—'))) ?></div></article>
                        <article class="detail-card"><span class="detail-label">Canaux favoris</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($task['persona_canaux'] ?? '—'))) ?></div></article>
                    </div>
                <?php endif; ?>

                <?php if (!empty($briefFiles)): ?>
                    <div class="file-list">
                        <?php foreach ($briefFiles as $file): ?>
                            <div class="file-item-row">
                                <a class="file-link" href="<?= htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode((string) ($file['path'] ?? '')) . '&name=' . urlencode($downloadBaseName)) ?>"><?= htmlspecialchars($file['name'] ?? 'Fichier') ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($showDeliverableExisting && $deliverable): ?>
            <div class="panel stack-panel">
                <div class="panel-head">
                    <div>
                        <h3>Livrable associe</h3>
                        <p class="panel-subtitle">Support attendu pour cette chaine de production.</p>
                    </div>
                </div>
                <div class="detail-grid">
                    <?php if (($deliverable['titre'] ?? '') !== ($brief['titre_brief'] ?? '') && ($deliverable['titre'] ?? '') !== ($task['livrable_titre'] ?? '')): ?><article class="detail-card"><span class="detail-label">Titre</span><div class="detail-value"><?= htmlspecialchars((string) ($deliverable['titre'] ?? '—')) ?></div></article><?php endif; ?>
                    <article class="detail-card"><span class="detail-label">Format</span><div class="detail-value"><?= htmlspecialchars((string) (($deliverable['type_livrable'] ?? '') . (!empty($deliverable['sous_type']) ? ' · ' . $deliverable['sous_type'] : ''))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Pages</span><div class="detail-value"><?= htmlspecialchars((string) ($deliverable['nombre_pages'] ?? $brief['nombre_pages_carrousel'] ?? 1)) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Statut livrable</span><div class="detail-value"><?= htmlspecialchars((string) ($deliverable['statut'] ?? 'Planifie')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Stockage tournage · disque</span><div class="detail-value"><?= htmlspecialchars((string) ($tournageStorage['disque'] !== '' ? $tournageStorage['disque'] : 'En attente')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Stockage tournage · dossier</span><div class="detail-value"><?= htmlspecialchars((string) ($tournageStorage['dossier'] !== '' ? $tournageStorage['dossier'] : 'En attente')) ?></div></article>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showValidationExisting): ?>
            <div class="panel stack-panel">
                <div class="panel-head">
                    <div>
                        <h3>Statuts de validation</h3>
                        <p class="panel-subtitle">Lecture seule des validations deja passees.</p>
                    </div>
                </div>
                <div class="detail-grid">
                    <article class="detail-card"><span class="detail-label">Validation interne</span><div class="detail-value"><?= htmlspecialchars((string) ($validationInterneTask['statut'] ?? 'Non lancee')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Decision interne</span><div class="detail-value"><?= htmlspecialchars((string) ($validationInterneTask['validation_decision'] ?? 'En attente')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Note interne /10</span><div class="detail-value"><?= htmlspecialchars((string) (($validationInterneTask['note_sur_10'] ?? '') === '' ? 'N/A' : $validationInterneTask['note_sur_10'])) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Validation client</span><div class="detail-value"><?= htmlspecialchars((string) ($validationClientTask['statut'] ?? 'Non lancee')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Decision client</span><div class="detail-value"><?= htmlspecialchars((string) ($validationClientTask['validation_decision'] ?? 'En attente')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Note client /10</span><div class="detail-value"><?= htmlspecialchars((string) (($validationClientTask['note_sur_10'] ?? '') === '' ? 'N/A' : $validationClientTask['note_sur_10'])) ?></div></article>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showPublicationExisting): ?>
            <div class="panel stack-panel">
                <div class="panel-head">
                    <div>
                        <h3>Etat de publication</h3>
                        <p class="panel-subtitle">Contenu publie, programme ou en attente selon la derniere diffusion connue.</p>
                    </div>
                </div>
                <div class="detail-grid">
                    <article class="detail-card"><span class="detail-label">Etat</span><div class="detail-value"><?= htmlspecialchars((string) ($latestPublication['statut'] ?? 'En attente')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Canal</span><div class="detail-value"><?= htmlspecialchars((string) ($latestPublication['canal'] ?? $task['reseau_cible'] ?? '—')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Date</span><div class="detail-value"><?= htmlspecialchars((string) (($latestPublication['date_publication'] ?? '') . (!empty($latestPublication['heure_publication']) ? ' ' . $latestPublication['heure_publication'] : ''))) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Note</span><div class="detail-value"><?= nl2br(htmlspecialchars((string) ($latestPublication['note'] ?? '—'))) ?></div></article>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showResultExisting): ?>
            <div class="panel stack-panel">
                <div class="panel-head">
                    <div>
                        <h3>Stat du contenu</h3>
                        <p class="panel-subtitle">Dernieres mesures connues deja enregistrees.</p>
                    </div>
                </div>
                <div class="detail-grid">
                    <article class="detail-card"><span class="detail-label">Periode</span><div class="detail-value"><?= htmlspecialchars((string) ($latestResult['periode_label'] ?? 'Aucune')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Valeur cle</span><div class="detail-value"><?= htmlspecialchars((string) ($latestResult['valeur_cle'] ?? '—')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Date collecte</span><div class="detail-value"><?= htmlspecialchars((string) ($latestResult['date_collecte'] ?? '—')) ?></div></article>
                    <article class="detail-card"><span class="detail-label">Mesures</span><div class="detail-value"><?php
                        $snapshotRaw = (string) ($latestResult['metric_snapshot'] ?? '');
                        $snapshotDecoded = json_decode($snapshotRaw, true);
                        if (is_array($snapshotDecoded) && !empty($snapshotDecoded['kpis']) && is_array($snapshotDecoded['kpis'])) {
                            $parts = [];
                            foreach ($snapshotDecoded['kpis'] as $kpiEntry) {
                                if (!is_array($kpiEntry)) {
                                    continue;
                                }
                                $parts[] = (string) ($kpiEntry['label'] ?? $kpiEntry['name'] ?? 'KPI') . ': ' . (string) ($kpiEntry['value'] ?? 0);
                            }
                            echo nl2br(htmlspecialchars(implode("\n", $parts)));
                        } else {
                            echo nl2br(htmlspecialchars($snapshotRaw !== '' ? $snapshotRaw : '—'));
                        }
                    ?></div></article>
                </div>
            </div>
        <?php endif; ?>
    </details>

    <?php if ($isBriefTask): ?>
        <section class="panel task-workspace-compact">
            <div class="panel-head">
                <div>
                    <h2><?= htmlspecialchars(task_action_title($taskType)) ?></h2>
                    <p class="panel-subtitle"><?= htmlspecialchars(task_action_subtitle($taskType)) ?></p>
                </div>
            </div>
            <div class="info-banner"><?= htmlspecialchars(task_completion_note($taskType, $task)) ?></div>
            <form data-brief-editor="true" method="post" class="form-grid task-brief-grid" enctype="multipart/form-data" data-autosave-form="true" data-autosave-endpoint="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) ($task['id'] ?? 0))) ?>">
                <div class="autosave-status" data-autosave-status>Modifications locales</div>
                <label class="field">
                    <span>Titre</span>
                    <input type="text" name="titre_brief" value="<?= htmlspecialchars((string) ($brief['titre_brief'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>Format</span>
                    <input type="text" name="sous_type" value="<?= htmlspecialchars((string) ($_POST['sous_type'] ?? $deliverable['sous_type'] ?? '')) ?>">
                </label>

                <?php if ($taskType === 'Brief'): ?>
                    <label class="field">
                        <span>Details du message</span>
                        <textarea name="details_message"><?= htmlspecialchars((string) ($brief['details_message'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>CTA</span>
                        <input type="text" name="cta" value="<?= htmlspecialchars((string) ($brief['cta'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span>Recommandations design</span>
                        <textarea name="recommandation_design"><?= htmlspecialchars((string) ($brief['recommandation_design'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>Infos complementaires</span>
                        <textarea name="informations_complementaires"><?= htmlspecialchars((string) ($brief['informations_complementaires'] ?? '')) ?></textarea>
                    </label>
                    <div class="field caption-editor brief-caption-editor">
                        <label for="brief-description-publication">Descriptif publication</label>
                        <div class="text-toolbar" role="toolbar" aria-label="Mise en forme du descriptif"><button type="button" data-text-style="bold" title="Gras"><b>B</b></button><button type="button" data-text-style="italic" title="Italique"><i>I</i></button><button type="button" data-text-style="boldItalic" title="Gras italique"><b><i>BI</i></b></button><button type="button" data-text-style="mono" title="Monospace">M</button><span data-char-count>0 caractères</span></div>
                        <textarea id="brief-description-publication" name="description_publication" rows="7" placeholder="Rédigez la légende, les mentions et les hashtags…"><?= htmlspecialchars((string) ($brief['description_publication'] ?? '')) ?></textarea>
                        <div class="hashtag-row"><input type="text" data-hashtags placeholder="marketing, conseil, produit"><button class="button secondary" type="button" data-add-hashtags>Ajouter les hashtags</button></div>
                        <div class="caption-preview"><span>Aperçu publication</span><p data-caption-preview></p></div>
                    </div>
                    <label class="field">
                        <span>Nombre de pages</span>
                        <input type="number" min="1" name="nombre_pages_carrousel" value="<?= htmlspecialchars((string) ($brief['nombre_pages_carrousel'] ?? $deliverable['nombre_pages'] ?? 1)) ?>">
                    </label>
                    <?php $pageCount = max(1, (int) ($brief['nombre_pages_carrousel'] ?? $deliverable['nombre_pages'] ?? 1)); ?>
                    <?php for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++): ?>
                        <label class="field">
                            <span>Message slide <?= htmlspecialchars((string) ($pageIndex + 1)) ?></span>
                            <textarea name="page_messages[]"><?= htmlspecialchars((string) ($brief['pages_carrousel'][$pageIndex]['message'] ?? '')) ?></textarea>
                        </label>
                    <?php endfor; ?>
                <?php else: ?>
                    <label class="field">
                        <span>CTA</span>
                        <input type="text" name="cta" value="<?= htmlspecialchars((string) ($brief['cta'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span>Hook video</span>
                        <input type="text" name="hook_video" value="<?= htmlspecialchars((string) ($brief['hook_video'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span>Plan du script</span>
                        <textarea name="plan_script"><?= htmlspecialchars((string) ($brief['plan_script'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>Texte du script</span>
                        <textarea name="texte_script"><?= htmlspecialchars((string) ($brief['texte_script'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>Instructions visuelles</span>
                        <textarea name="instructions_visuelles"><?= htmlspecialchars((string) ($brief['instructions_visuelles'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>Format de tournage</span>
                        <input type="text" name="format" value="<?= htmlspecialchars((string) ($brief['format'] ?? '')) ?>">
                    </label>
                <?php endif; ?>

                <label class="field">
                    <span>Fichiers de consigne</span>
                    <input type="file" name="pieces_jointes[]" multiple data-accumulate-files="true" accept=".png,.jpg,.jpeg,.pdf,.psd,.psb,.doc,.docx,.txt">
                </label>

                <?php if (!empty($briefFiles)): ?>
                    <div class="file-list">
                        <?php foreach ($briefFiles as $index => $file): ?>
                            <div class="file-item-row">
                                <a class="file-link" href="<?= htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode((string) ($file['path'] ?? '')) . '&name=' . urlencode($downloadBaseName)) ?>"><?= htmlspecialchars($file['name'] ?? 'Fichier') ?></a>
                                <a class="icon-link danger" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id']) . '?remove_brief_file=' . $index) ?>" aria-label="Supprimer le fichier" title="Supprimer le fichier" onclick="return confirm('Supprimer ce fichier ?');">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-7 0 1 12h6l1-12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label class="field">
                    <span>Statut</span>
                    <select name="statut">
                        <?php foreach (['A faire', 'En cours', 'Valide'] as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>" <?= (($brief['statut'] ?? 'A faire') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="form-actions">
                    <button class="button" type="submit">Enregistrer la consigne</button>
                    <?php if ($canManagerInvalidate): ?>
                        <button class="button secondary" type="submit" name="manager_action" value="invalidate_brief" onclick="return confirm('Marquer ce brief comme non valide ?');">Invalider le brief</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if ($taskType === 'Strategie'): ?>
    <section class="panel task-workspace-compact">
        <div class="panel-head">
            <div>
                <h2>Workspace strategie</h2>
                <p class="panel-subtitle">La strategie correspond a la mise en place des offres, personas, messages marketing et campagnes du client.</p>
            </div>
        </div>
        <div class="info-banner"><?= htmlspecialchars(task_completion_note($taskType, $task)) ?></div>
        <div class="stats-grid">
            <a class="stat-card" href="<?= htmlspecialchars(route_url('/offre') . '?client_id=' . urlencode((string) $task['client_id'])) ?>"><span class="stat-label">Offres</span><span class="stat-value"><?= htmlspecialchars((string) ($task['strategyStats']['offres_total'] ?? 0)) ?></span><span class="stat-link">Ouvrir les offres</span></a>
            <a class="stat-card" href="<?= htmlspecialchars(route_url('/persona') . '?client_id=' . urlencode((string) $task['client_id'])) ?>"><span class="stat-label">Personas</span><span class="stat-value"><?= htmlspecialchars((string) ($task['strategyStats']['personas_total'] ?? 0)) ?></span><span class="stat-link">Ouvrir les personas</span></a>
            <a class="stat-card" href="<?= htmlspecialchars(route_url('/messages-marketing') . '?client_id=' . urlencode((string) $task['client_id'])) ?>"><span class="stat-label">Messages marketing</span><span class="stat-value"><?= htmlspecialchars((string) ($task['strategyStats']['messages_total'] ?? 0)) ?></span><span class="stat-link">Ouvrir les messages</span></a>
            <a class="stat-card" href="<?= htmlspecialchars(route_url('/campagne') . '?client_id=' . urlencode((string) $task['client_id'])) ?>"><span class="stat-label">Campagnes</span><span class="stat-value"><?= htmlspecialchars((string) ($task['strategyStats']['campagnes_total'] ?? 0)) ?></span><span class="stat-link">Ouvrir les campagnes</span></a>
        </div>
        <form method="post" class="form-grid">
            <label class="field">
                <span>Notes de strategie</span>
                <textarea name="notes"><?= htmlspecialchars((string) ($task['notes'] ?? '')) ?></textarea>
            </label>
            <div class="form-actions">
                <button class="button" type="submit" name="statut" value="Terminee">Marquer comme terminee</button>
                <button class="button secondary" type="submit" name="statut" value="En cours">Sauvegarder en cours</button>
            </div>
        </form>
    </section>
<?php elseif (!$isBriefTask): ?>
    <?php if ($taskType === 'Validation client' && $canGeneratePublicValidationLink): ?>
        <section class="panel inset-panel">
            <div class="panel-head">
                <div>
                    <h2>Generation de lien client</h2>
                    <p class="panel-subtitle">Genere un lien public de validation directement depuis cette etape.</p>
                </div>
            </div>

            <form method="post" action="<?= htmlspecialchars($currentTaskUrl) ?>" class="form-grid">
                <label class="field">
                    <span>Expiration du lien (jours)</span>
                    <input type="number" min="1" max="365" name="expiry_days" value="45">
                </label>
                <div class="field">
                    <span>Livrables a exposer</span>
                    <?php if (!empty($taskReadyDeliverablesForPublicValidation)): ?>
                        <div class="checkbox-grid">
                            <?php foreach ($taskReadyDeliverablesForPublicValidation as $item): ?>
                                <label class="checkbox-pill">
                                    <input type="checkbox" name="deliverable_ids[]" value="<?= htmlspecialchars((string) ($item['deliverable_id'] ?? 0)) ?>" <?= ((int) ($item['deliverable_id'] ?? 0) === (int) ($task['livrable_item_id'] ?? 0)) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars((string) ($item['titre'] ?? 'Livrable')) ?> · <?= htmlspecialchars((string) ($item['date_prevue'] ?? '')) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">Aucun livrable pret a valider pour ce mois.</p>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button class="button secondary" type="submit" name="manager_action" value="create_public_validation_link">Generer le lien public</button>
                </div>
            </form>

            <?php if (!empty($taskPublicValidationLinks)): ?>
                <div class="file-list" style="margin-top: 12px;">
                    <?php foreach ($taskPublicValidationLinks as $link): ?>
                        <?php $publicUrl = route_url('/public-validation/index/' . (string) ($link['token'] ?? '')); ?>
                        <div class="file-item-row">
                            <a class="file-link" target="_blank" rel="noopener noreferrer" href="<?= htmlspecialchars($publicUrl) ?>"><?= htmlspecialchars($publicUrl) ?></a>
                            <button type="button" class="button secondary" data-copy-link="<?= htmlspecialchars($publicUrl) ?>">Copier</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel task-workspace-compact">
        <div class="panel-head">
            <div>
                <h2><?= htmlspecialchars(task_action_title($taskType)) ?></h2>
                <p class="panel-subtitle"><?= htmlspecialchars(task_action_subtitle($taskType)) ?></p>
            </div>
        </div>

        <div class="info-banner"><?= htmlspecialchars(task_completion_note($taskType, $task)) ?></div>

        <form method="post" class="form-grid" enctype="multipart/form-data" data-autosave-form="true" data-autosave-endpoint="<?= htmlspecialchars(route_url('/calendrier/task/' . (int) ($task['id'] ?? 0))) ?>" data-task-type="<?= htmlspecialchars($taskType) ?>" data-task-blocked="<?= $taskIsBlocked ? '1' : '0' ?>">
            <div class="autosave-status" data-autosave-status>Modifications locales</div>
            <?php if ($isValidationTask): ?>
                <div class="info-banner">La note mesure l appreciation qualitative. La decision Valide/Non valide confirme la conformite finale. Ces deux champs sont independants.</div>
                <label class="field">
                    <span>Decision</span>
                    <select name="validation_decision" class="<?= task_field_has_error($inlineErrors, 'validation_decision') ? 'has-error' : '' ?>">
                        <?php foreach (['En attente', 'Valide', 'Non valide'] as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>" <?= (($task['validation_decision'] ?? 'En attente') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-error" data-field-error-for="validation_decision"><?= htmlspecialchars(task_field_error($inlineErrors, 'validation_decision')) ?></small>
                </label>
                <label class="field">
                    <span>Note sur 10</span>
                    <input type="number" name="note_sur_10" min="0" max="10" step="1" value="<?= htmlspecialchars((string) ($task['note_sur_10'] ?? '')) ?>" placeholder="Ex: 8" class="<?= task_field_has_error($inlineErrors, 'note_sur_10') ? 'has-error' : '' ?>">
                    <small class="field-error" data-field-error-for="note_sur_10"><?= htmlspecialchars(task_field_error($inlineErrors, 'note_sur_10')) ?></small>
                </label>
                <label class="field">
                    <span>Commentaire</span>
                    <textarea name="validation_commentaire"><?= htmlspecialchars((string) ($task['validation_commentaire'] ?? '')) ?></textarea>
                </label>
            <?php endif; ?>

            <?php if ($isPublicationTask): ?>
                <input type="hidden" name="publication_entry_id" value="<?= htmlspecialchars((string) ($latestPublication['id'] ?? '')) ?>">
                <?php if ($canManageTaskPlanningDate): ?>
                    <label class="field">
                        <span>Date prevue (planning)</span>
                        <input type="date" name="date_prevue" value="<?= htmlspecialchars((string) ($_POST['date_prevue'] ?? $task['date_prevue'] ?? $task['deadline'] ?? '')) ?>">
                    </label>
                <?php endif; ?>
                <label class="field">
                    <span>Date de publication</span>
                    <input type="date" name="date_publication" value="<?= htmlspecialchars((string) ($_POST['date_publication'] ?? $latestPublication['date_publication'] ?? $task['deadline'] ?? '')) ?>" class="<?= task_field_has_error($inlineErrors, 'date_publication') ? 'has-error' : '' ?>">
                    <small class="field-error" data-field-error-for="date_publication"><?= htmlspecialchars(task_field_error($inlineErrors, 'date_publication')) ?></small>
                </label>
                <label class="field">
                    <span>Heure</span>
                    <input type="time" name="heure_publication" value="<?= htmlspecialchars((string) ($_POST['heure_publication'] ?? $latestPublication['heure_publication'] ?? $task['default_publication_time'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>Canal final</span>
                    <input type="text" name="canal" value="<?= htmlspecialchars((string) ($_POST['canal'] ?? $latestPublication['canal'] ?? $task['reseau_cible'] ?? $task['canal_principal'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>Note de publication</span>
                    <textarea name="publication_note"><?= htmlspecialchars((string) ($_POST['publication_note'] ?? $latestPublication['note'] ?? '')) ?></textarea>
                </label>
                <label class="field">
                    <span>Reseaux</span>
                    <div class="checkbox-grid">
                        <?php foreach ($workflowNetworks as $network): ?>
                            <label class="checkbox-pill"><input type="checkbox" name="publication_reseaux[]" value="<?= htmlspecialchars($network) ?>" <?= in_array($network, $currentNetworks, true) ? 'checked' : '' ?>> <span><?= htmlspecialchars($network) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </label>
                <article class="detail-card readonly-selector-card">
                    <span class="detail-label">Compte social choisi (auto)</span>
                    <div class="detail-value">
                        <?php if (!empty($selectedSocialAccountPreview)): ?>
                            <?= htmlspecialchars((string) ($selectedSocialAccountPreview['reseau_label'] ?? 'Reseau')) ?> ·
                            <?= htmlspecialchars((string) ($selectedSocialAccountPreview['compte_label'] ?? 'Compte')) ?>
                            <?php if (!empty($selectedSocialAccountPreview['page_nom'])): ?>
                                · <?= htmlspecialchars((string) $selectedSocialAccountPreview['page_nom']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            Aucun compte mappe pour ce client/reseau.
                        <?php endif; ?>
                    </div>
                    <div class="mini-text">Lecture seule. Le mapping se configure dans la fiche client.</div>
                </article>
            <?php endif; ?>

            <?php if ($isResultTask): ?>
                <label class="field">
                    <span>Date de collecte</span>
                    <input type="date" name="date_collecte" value="<?= htmlspecialchars((string) ($_POST['date_collecte'] ?? date('Y-m-d'))) ?>">
                </label>
                <div class="field" style="grid-column: 1 / -1;">
                    <span>Reseaux de collecte (multi-selection)</span>
                    <div class="checkbox-grid" id="kpi_network_picker">
                        <?php foreach ($kpiNetworkConfig as $networkKey => $networkMeta): ?>
                            <?php $networkKeyLower = strtolower((string) $networkKey); ?>
                            <label class="checkbox-pill">
                                <input type="checkbox" name="kpi_networks[]" value="<?= htmlspecialchars((string) $networkKeyLower) ?>" <?= in_array($networkKeyLower, $postedKpiNetworks, true) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars((string) ($networkMeta['label'] ?? ucfirst((string) $networkKeyLower))) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <label class="field">
                    <span>Periode analysee (auto)</span>
                    <input type="text" value="Calcule automatiquement a partir de la date de collecte" readonly>
                </label>
                <div class="field" style="grid-column: 1 / -1;">
                    <span>Indicateurs KPI par reseau</span>
                    <div id="kpi_network_sections" class="detail-grid"></div>
                </div>
                <label class="field">
                    <span>Interpretation</span>
                    <textarea name="result_note"><?= htmlspecialchars((string) ($_POST['result_note'] ?? '')) ?></textarea>
                </label>
            <?php endif; ?>

            <?php if ($isProductionTask): ?>
                <?php if ($isTournageTask): ?>
                    <label class="field">
                        <span>Disque de copie</span>
                        <input type="text" name="tournage_disque" value="<?= htmlspecialchars((string) ($task['tournage_disque'] ?? '')) ?>" placeholder="Ex: NAS-VIDEO-01 / D:\" class="<?= task_field_has_error($inlineErrors, 'tournage_disque') ? 'has-error' : '' ?>">
                        <small class="field-error" data-field-error-for="tournage_disque"><?= htmlspecialchars(task_field_error($inlineErrors, 'tournage_disque')) ?></small>
                    </label>
                    <label class="field">
                        <span>Dossier de copie</span>
                        <input type="text" name="tournage_dossier" value="<?= htmlspecialchars((string) ($task['tournage_dossier'] ?? '')) ?>" placeholder="Ex: Clients/Acme/2026-04-Tournage" class="<?= task_field_has_error($inlineErrors, 'tournage_dossier') ? 'has-error' : '' ?>">
                        <small class="field-error" data-field-error-for="tournage_dossier"><?= htmlspecialchars(task_field_error($inlineErrors, 'tournage_dossier')) ?></small>
                    </label>
                <?php else: ?>
                    <label class="field">
                        <span>Fichiers de contribution</span>
                        <input type="file" name="fichiers_livres[]" multiple data-accumulate-files="true" data-existing-count="<?= htmlspecialchars((string) count($taskFiles)) ?>" accept=".png,.jpg,.jpeg,.pdf,.psd,.psb,.mp4,.mov,.zip" class="<?= task_field_has_error($inlineErrors, 'fichiers_livres') ? 'has-error' : '' ?>">
                        <small class="field-error" data-field-error-for="fichiers_livres"><?= htmlspecialchars(task_field_error($inlineErrors, 'fichiers_livres')) ?></small>
                        <small class="field-help"><?php if (($task['type_livrable'] ?? '') === 'Video' && $taskType === 'Montage'): ?><?php if ($requireSecondMontageVideo): ?>Pour terminer le montage, charge les 2 exports finaux: version avec musique et version sans musique.<?php else: ?>Pour terminer le montage, charge au moins un export video final (la 2e version est optionnelle pour le moment).<?php endif; ?><?php elseif (($task['type_livrable'] ?? '') === 'Video'): ?>Charge les rush pour le tournage puis les exports video pour le montage.<?php elseif (strcasecmp((string) ($task['sous_type'] ?? ''), 'Carrousel') === 0): ?>Charge les exports par page, le PDF et le PSD ou PSB du carrousel.<?php else: ?>Charge l export visuel et le fichier source.<?php endif; ?><?php if ($phpUploadLimitLabel !== ''): ?> Limite serveur actuelle: <?= htmlspecialchars($phpUploadLimitLabel) ?>.<?php endif; ?></small>
                    </label>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isValidationTask): ?>
                <div class="panel inset-panel">
                    <div class="panel-head">
                        <div>
                            <h3>Apercu des fichiers a valider</h3>
                            <p class="panel-subtitle">Tu peux previsualiser et telecharger les fichiers avant de statuer.</p>
                        </div>
                    </div>
                    <?php
                    $validationFiles = [];
                    foreach ($taskFiles as $file) {
                        $file['origin'] = 'Tache';
                        $validationFiles[] = $file;
                    }
                    foreach ($deliverableFiles as $file) {
                        $file['origin'] = 'Livrable';
                        $validationFiles[] = $file;
                    }
                    foreach ($deliverableTaskFilesForValidation as $file) {
                        $validationFiles[] = $file;
                    }
                    foreach ($briefFiles as $file) {
                        $file['origin'] = 'Brief';
                        $validationFiles[] = $file;
                    }

                    $dedupedValidationFiles = [];
                    $seenValidationFiles = [];
                    foreach ($validationFiles as $file) {
                        $filePath = trim((string) ($file['path'] ?? ''));
                        $fileName = trim((string) ($file['name'] ?? ''));
                        $fileKey = strtolower($filePath . '|' . $fileName);
                        if ($fileKey !== '|' && isset($seenValidationFiles[$fileKey])) {
                            continue;
                        }
                        if ($fileKey !== '|') {
                            $seenValidationFiles[$fileKey] = true;
                        }
                        $dedupedValidationFiles[] = $file;
                    }
                    $validationFiles = $dedupedValidationFiles;
                    ?>
                    <?php if (!empty($validationFiles)): ?>
                        <div class="preview-grid" style="margin-bottom: 12px;">
                            <?php foreach ($validationFiles as $file): ?>
                                <?php
                                $kind = task_preview_kind($file);
                                $viewUrl = task_view_url($file, $downloadBaseName);
                                ?>
                                <a class="preview-card preview-<?= htmlspecialchars($kind) ?>" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars((string) ($file['name'] ?? 'Fichier')) ?>" <?= in_array($kind, ['image', 'video'], true) ? 'data-preview-kind="' . htmlspecialchars($kind) . '" data-preview-src="' . htmlspecialchars($viewUrl) . '" data-preview-name="' . htmlspecialchars((string) ($file['name'] ?? 'Fichier')) . '"' : '' ?>>
                                    <?php if ($kind === 'image'): ?>
                                        <img src="<?= htmlspecialchars($viewUrl) ?>" alt="<?= htmlspecialchars((string) ($file['name'] ?? 'Fichier')) ?>">
                                    <?php elseif ($kind === 'video'): ?>
                                        <video preload="metadata" muted playsinline>
                                            <source src="<?= htmlspecialchars($viewUrl) ?>">
                                        </video>
                                        <span class="preview-pill">VIDEO</span>
                                    <?php else: ?>
                                        <span class="preview-pill"><?= htmlspecialchars(task_preview_label($file)) ?></span>
                                    <?php endif; ?>
                                    <span class="preview-role-badge"><?= htmlspecialchars((string) ($file['origin'] ?? 'Fichier')) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="file-list">
                            <?php foreach ($validationFiles as $file): ?>
                                <div class="file-item-row"><a class="file-link" href="<?= htmlspecialchars(task_download_url($file, $downloadBaseName)) ?>"><?= htmlspecialchars((string) (($file['origin'] ?? 'Fichier') . ' · ' . ($file['name'] ?? 'Fichier'))) ?></a></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">Aucun fichier disponible pour cette validation.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($invalidationHistory)): ?>
                <div class="panel inset-panel">
                    <div class="panel-head">
                        <div>
                            <h3>Historique des invalidations</h3>
                            <p class="panel-subtitle">Commentaires conserves pour corriger la tache concernee.</p>
                        </div>
                    </div>
                    <ul class="requirement-list">
                        <?php foreach ($invalidationHistory as $entry): ?>
                            <li class="requirement-item is-awaiting">
                                <span class="requirement-state"><?= htmlspecialchars((string) ($entry['source'] ?? 'Validation')) ?></span>
                                <span><?= htmlspecialchars(trim((string) (($entry['date'] !== '' ? $entry['date'] . ' · ' : '') . ($entry['comment'] ?? '')))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <label class="field">
                <span>Notes</span>
                <textarea name="notes"><?= htmlspecialchars((string) ($task['notes'] ?? '')) ?></textarea>
            </label>

            <?php if (!empty($taskFiles)): ?>
                <div class="file-list">
                    <?php foreach ($taskFiles as $index => $file): ?>
                        <div class="file-item-row">
                            <a class="file-link" href="<?= htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode((string) ($file['path'] ?? '')) . '&name=' . urlencode($downloadBaseName)) ?>"><?= htmlspecialchars($file['name'] ?? 'Fichier') ?></a>
                            <a class="icon-link danger" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id']) . '?remove_file=' . $index) ?>" aria-label="Supprimer le fichier" title="Supprimer le fichier" onclick="return confirm('Supprimer ce fichier ?');">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-7 0 1 12h6l1-12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button class="button" type="submit" name="statut" value="Terminee" data-gated-complete>Terminer le travail</button>
                <button class="button secondary" type="submit" name="statut" value="En cours">Enregistrer en cours</button>
            </div>
        </form>
    </section>

    <?php if ($isPublicationTask && $canViewFutureContentInfo): ?>
        <section class="panel inset-panel">
            <div class="panel-head">
                <div>
                    <h2>Historique de publication</h2>
                    <p class="panel-subtitle">Le contenu peut etre diffuse, repousse ou republie sans perdre son historique.</p>
                </div>
            </div>
            <?php if (!empty($publicationEntries)): ?>
                <div class="detail-grid">
                    <?php foreach ($publicationEntries as $entry): ?>
                        <article class="detail-card">
                            <span class="detail-label"><?= htmlspecialchars((string) ($entry['canal'] ?? 'Canal')) ?></span>
                            <div class="detail-value"><?= htmlspecialchars((string) (($entry['date_publication'] ?? '') . (!empty($entry['heure_publication']) ? ' ' . $entry['heure_publication'] : ''))) ?></div>
                            <div class="mini-text"><?= htmlspecialchars((string) ($entry['statut'] ?? 'Planifie')) ?></div>
                            <div class="mini-text"><?= nl2br(htmlspecialchars((string) ($entry['note'] ?? ''))) ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Aucune publication enregistree pour ce contenu.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($isResultTask): ?>
        <section class="panel inset-panel result-history-panel">
            <div class="panel-head">
                <div>
                    <h2>Historique des resultats</h2>
                    <p class="panel-subtitle">Chaque collecte ajoute un point de mesure supplementaire sur le meme contenu.</p>
                </div>
            </div>
            <?php if (!empty($resultEntries)): ?>
                <div class="detail-grid">
                    <?php foreach ($resultEntries as $entry): ?>
                        <article class="detail-card result-history-card">
                            <span class="detail-label"><?= htmlspecialchars((string) ($entry['periode_label'] ?? 'Periode')) ?></span>
                            <div class="detail-value"><?= htmlspecialchars((string) ($entry['valeur_cle'] ?? '—')) ?></div>
                            <div class="mini-text"><?= htmlspecialchars((string) ($entry['date_collecte'] ?? '')) ?></div>
                            <div class="mini-text"><?php
                                $entrySnapshotRaw = (string) ($entry['metric_snapshot'] ?? '');
                                $entrySnapshot = json_decode($entrySnapshotRaw, true);
                                if (is_array($entrySnapshot) && !empty($entrySnapshot['kpis']) && is_array($entrySnapshot['kpis'])) {
                                    $entryLines = [];
                                    $entryNetworkLabel = trim((string) ($entrySnapshot['reseau_label'] ?? $entrySnapshot['reseau'] ?? ''));
                                    if ($entryNetworkLabel !== '') {
                                        $entryLines[] = 'Reseau: ' . $entryNetworkLabel;
                                    }
                                    foreach ($entrySnapshot['kpis'] as $entryKpi) {
                                        if (!is_array($entryKpi)) {
                                            continue;
                                        }
                                        $entryLines[] = (string) ($entryKpi['label'] ?? $entryKpi['name'] ?? 'KPI') . ': ' . (string) ($entryKpi['value'] ?? 0);
                                    }
                                    echo nl2br(htmlspecialchars(implode("\n", $entryLines)));
                                } else {
                                    echo nl2br(htmlspecialchars($entrySnapshotRaw));
                                }
                            ?></div>
                            <div class="mini-text"><?= nl2br(htmlspecialchars((string) ($entry['note'] ?? ''))) ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Aucun resultat historise pour ce contenu.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($deliverableFiles) && ($canViewFutureContentInfo || $currentRank >= 4)): ?>
        <section class="panel inset-panel">
            <div class="panel-head">
                <div>
                    <h2>Fichiers du livrable</h2>
                    <p class="panel-subtitle">Vue d ensemble des fichiers deja rattaches au livrable final.</p>
                </div>
            </div>
            <div class="file-list">
                <?php foreach ($deliverableFiles as $file): ?>
                    <div class="file-item-row">
                        <a class="file-link" href="<?= htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode((string) ($file['path'] ?? '')) . '&name=' . urlencode($downloadBaseName)) ?>"><?= htmlspecialchars($file['name'] ?? 'Fichier') ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>


<script>
(function () {
    var openBtn = document.querySelector('[data-open-reassign-panel="1"]');
    var panel = document.getElementById('task-reassign-panel');
    if (openBtn && panel) {
        openBtn.addEventListener('click', function () {
            panel.hidden = false;
            var select = panel.querySelector('select[name="auteur_id"]');
            if (select) {
                select.focus();
            }
        });
    }
})();

(function () {
    var forms = document.querySelectorAll('form[data-autosave-form="true"]');
    forms.forEach(function (form) {
        var statusNode = form.querySelector('[data-autosave-status]');
        var timer = null;
        var inFlight = false;

        function setStatus(text, state) {
            if (!statusNode) { return; }
            statusNode.textContent = text;
            statusNode.setAttribute('data-state', state || 'idle');
        }

        function hasSelectedFiles() {
            var fileInputs = form.querySelectorAll('input[type="file"]');
            for (var i = 0; i < fileInputs.length; i++) {
                if (fileInputs[i].files && fileInputs[i].files.length > 0) {
                    return true;
                }
            }
            return false;
        }

        function queueAutosave() {
            setStatus('Modification detectee...', 'pending');
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                if (inFlight || hasSelectedFiles()) {
                    return;
                }

                inFlight = true;
                setStatus('Sauvegarde en cours...', 'saving');
                var payload = new FormData(form);
                payload.set('autosave_mode', '1');

                fetch(form.getAttribute('data-autosave-endpoint') || window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: payload,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, json: json };
                    });
                }).then(function (result) {
                    inFlight = false;
                    clearInlineErrors();
                    if (!result.ok || !result.json || result.json.ok !== true) {
                        applyInlineErrors((result.json && result.json.errors) ? result.json.errors : {});
                        setStatus((result.json && result.json.message) ? result.json.message : 'Erreur de sauvegarde', 'error');
                        if (window.AppUI && typeof window.AppUI.toast === 'function') {
                            window.AppUI.toast('error', (result.json && result.json.message) ? result.json.message : 'Erreur de sauvegarde auto');
                        }
                        return;
                    }
                    setStatus('Sauvegarde auto: ' + (result.json.at || ''), 'saved');
                    if (window.AppUI && typeof window.AppUI.toast === 'function') {
                        window.AppUI.toast('success', 'Sauvegarde auto reussie');
                    }
                }).catch(function () {
                    inFlight = false;
                    setStatus('Sauvegarde impossible (reseau)', 'error');
                    if (window.AppUI && typeof window.AppUI.toast === 'function') {
                        window.AppUI.toast('error', 'Sauvegarde auto impossible (reseau)');
                    }
                });
            }, 380);
        }

        function clearInlineErrors() {
            form.querySelectorAll('.field-error[data-field-error-for]').forEach(function (node) {
                node.textContent = '';
            });
            form.querySelectorAll('.has-error').forEach(function (node) {
                node.classList.remove('has-error');
            });
        }

        function applyInlineErrors(errors) {
            Object.keys(errors || {}).forEach(function (name) {
                var message = (errors[name] || '').toString();
                if (!message) {
                    return;
                }

                var safeName = name.replace(/"/g, '\\"');
                var field = form.querySelector('[name="' + safeName + '"]') || form.querySelector('[name="' + safeName + '[]"]');
                if (field) {
                    field.classList.add('has-error');
                }

                var errorNode = form.querySelector('[data-field-error-for="' + safeName + '"]');
                if (errorNode) {
                    errorNode.textContent = message;
                }
            });
        }

        form.querySelectorAll('textarea, select, input[type="text"], input[type="date"], input[type="number"], input[type="time"], input[type="checkbox"]').forEach(function (input) {
            input.addEventListener('blur', queueAutosave);
            input.addEventListener('change', queueAutosave);
        });
    });
})();

(function () {
    var form = document.querySelector('form[data-task-type]');
    var completeButton = form ? form.querySelector('[data-gated-complete]') : null;
    var guidedItems = Array.prototype.slice.call(document.querySelectorAll('[data-guided-item][data-guided-key]'));
    var progressPanel = document.querySelector('.task-guided-panel');
    if (!form || !completeButton || guidedItems.length === 0 || !progressPanel) {
        return;
    }

    function bool(v) {
        return !!v;
    }

    function updateItemState(key, done) {
        var item = guidedItems.find(function (candidate) {
            return candidate.getAttribute('data-guided-key') === key;
        });
        if (!item) {
            return;
        }
        item.setAttribute('data-guided-done', done ? '1' : '0');
        var blocked = form.getAttribute('data-task-blocked') === '1';
        item.classList.toggle('is-done', done);
        item.classList.toggle('is-awaiting', !done && blocked);
        item.classList.toggle('is-todo', !done && !blocked);
        var state = item.querySelector('.requirement-state');
        if (state) {
            state.textContent = done ? 'Fait' : (blocked ? 'En attente' : 'A faire');
        }
    }

    function refreshProgress() {
        var all = guidedItems.length;
        var done = guidedItems.filter(function (item) {
            return item.getAttribute('data-guided-done') === '1';
        }).length;
        var percent = all > 0 ? Math.round((done / all) * 100) : 100;

        var badge = progressPanel.querySelector('.status-badge');
        if (badge) {
            badge.textContent = done + '/' + all;
            badge.classList.toggle('status-terminee', percent >= 100);
            badge.classList.toggle('status-en-cours', percent < 100);
        }

        var percentNode = progressPanel.querySelector('.deliverable-progress-head strong');
        if (percentNode) {
            percentNode.textContent = percent + '%';
        }

        var fill = progressPanel.querySelector('.progress-fill');
        if (fill) {
            fill.style.width = percent + '%';
        }

        var ready = done === all;
        completeButton.disabled = !ready;
        completeButton.title = ready ? '' : 'Complete d abord tous les prerequis de la progression guidee.';
        return ready;
    }

    function value(name) {
        var node = form.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
        return node ? String(node.value || '').trim() : '';
    }

    function isChecked(name) {
        var nodes = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
        if (!nodes.length) {
            return false;
        }
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].checked) {
                return true;
            }
        }
        return false;
    }

    function evaluate() {
        var taskType = (form.getAttribute('data-task-type') || '').toLowerCase();

        if (taskType === 'tournage') {
            updateItemState('tournage_disk', bool(value('tournage_disque')));
            updateItemState('tournage_folder', bool(value('tournage_dossier')));
        }

        if (taskType === 'production' || taskType === 'montage') {
            var fileInput = form.querySelector('input[type="file"][name="fichiers_livres[]"]');
            var selected = fileInput && fileInput.files ? fileInput.files.length : 0;
            var existing = fileInput ? parseInt(fileInput.getAttribute('data-existing-count') || '0', 10) : 0;
            updateItemState('production_files', (selected + existing) > 0);
        }

        if (taskType === 'validation interne' || taskType === 'validation client') {
            var decision = value('validation_decision');
            updateItemState('validation_decision', decision === 'Valide' || decision === 'Non valide');
            updateItemState('validation_comment', decision !== 'Non valide' || bool(value('validation_commentaire')));
        }

        if (taskType === 'publication') {
            updateItemState('publication_date', bool(value('date_publication')));
            updateItemState('publication_canal', bool(value('canal')));
            updateItemState('publication_networks', isChecked('publication_reseaux[]'));
        }

        if (taskType === 'collecte kpi') {
            updateItemState('kpi_date', bool(value('date_collecte')));
            var networkOk = isChecked('kpi_networks[]') || bool(value('kpi_network'));
            var kpiInputs = form.querySelectorAll('[name^="kpi_values["]');
            var hasKpiValue = false;
            for (var idx = 0; idx < kpiInputs.length; idx++) {
                if (String(kpiInputs[idx].value || '').trim() !== '') {
                    hasKpiValue = true;
                    break;
                }
            }
            updateItemState('kpi_value', networkOk && hasKpiValue);
        }

        return refreshProgress();
    }

    form.addEventListener('input', evaluate);
    form.addEventListener('change', evaluate);
    completeButton.addEventListener('click', function (event) {
        if (evaluate()) {
            return;
        }
        event.preventDefault();
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast('error', 'Terminaison bloquee: complete d abord tous les prerequis de cette etape.');
        }
    });

    evaluate();
})();

(function () {
    var form = document.querySelector('form[data-task-type="Collecte KPI"], form[data-task-type="collecte kpi"]');
    var networkContainer = document.getElementById('kpi_network_sections');
    if (!form || !networkContainer) {
        return;
    }

    var config = <?= json_encode($kpiNetworkConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var postedValuesByNetwork = <?= json_encode((array) ($kpiDraftValuesByNetwork ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function selectedNetworks() {
        var nodes = form.querySelectorAll('input[name="kpi_networks[]"]:checked');
        var values = [];
        nodes.forEach(function (node) {
            values.push(String(node.value || '').toLowerCase());
        });
        return values;
    }

    function createKpiInput(network, kpi) {
        var label = document.createElement('label');
        label.className = 'field';

        var span = document.createElement('span');
        span.textContent = String(kpi.label || kpi.name || 'KPI');
        label.appendChild(span);

        var input = document.createElement('input');
        input.type = 'number';
        input.name = 'kpi_values[' + network + '][' + String(kpi.name || '') + ']';
        input.min = '0';
        input.step = String(kpi.type || 'integer') === 'float' ? '0.01' : '1';
        input.placeholder = String(kpi.placeholder || '0');
        var networkValues = postedValuesByNetwork[network] || {};
        if (Object.prototype.hasOwnProperty.call(networkValues, String(kpi.name || ''))) {
            input.value = String(networkValues[String(kpi.name || '')] || '');
        }
        label.appendChild(input);
        return label;
    }

    function renderKpiSections() {
        networkContainer.innerHTML = '';

        selectedNetworks().forEach(function (network) {
            var meta = config[network] || null;
            if (!meta || !Array.isArray(meta.kpis)) {
                return;
            }

            var card = document.createElement('article');
            card.className = 'detail-card';

            var title = document.createElement('span');
            title.className = 'detail-label';
            title.textContent = String(meta.label || network);
            card.appendChild(title);

            var grid = document.createElement('div');
            grid.className = 'form-grid';
            meta.kpis.forEach(function (kpi) {
                grid.appendChild(createKpiInput(network, kpi));
            });

            card.appendChild(grid);
            networkContainer.appendChild(card);
        });

        if (!networkContainer.children.length) {
            var empty = document.createElement('p');
            empty.className = 'mini-text';
            empty.textContent = 'Selectionne au moins un reseau pour afficher les indicateurs.';
            networkContainer.appendChild(empty);
        }
    }

    form.querySelectorAll('input[name="kpi_networks[]"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', renderKpiSections);
    });

    renderKpiSections();
})();

(function () {
    var modal = document.getElementById('task-preview-modal');
    if (!modal) {
        return;
    }

    var title = modal.querySelector('#task-preview-title');
    var body = modal.querySelector('#task-preview-body');

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('preview-open');
        if (body) {
            body.innerHTML = '';
        }
    }

    function openPreview(link) {
        var kind = link.getAttribute('data-preview-kind');
        var src = link.getAttribute('data-preview-src');
        var name = link.getAttribute('data-preview-name') || 'Apercu';
        if (!kind || !src || !body) {
            return;
        }

        body.innerHTML = '';
        if (title) {
            title.textContent = name;
        }

        if (kind === 'image') {
            var image = document.createElement('img');
            image.className = 'preview-modal-image';
            image.src = src;
            image.alt = name;
            body.appendChild(image);
        } else if (kind === 'video') {
            var video = document.createElement('video');
            video.className = 'preview-modal-video';
            video.controls = true;
            video.preload = 'metadata';
            video.src = src;
            body.appendChild(video);
        }

        modal.hidden = false;
        document.body.classList.add('preview-open');
    }

    document.addEventListener('click', function (event) {
        var previewLink = event.target.closest('[data-preview-kind]');
        if (previewLink) {
            event.preventDefault();
            openPreview(previewLink);
            return;
        }

        if (!modal.hidden && event.target.closest('[data-preview-close]')) {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();

(function () {
    document.addEventListener('click', function (event) {
        var copyButton = event.target.closest('[data-copy-link]');
        if (!copyButton) {
            return;
        }
        var url = copyButton.getAttribute('data-copy-link') || '';
        if (!url || !navigator.clipboard || !navigator.clipboard.writeText) {
            return;
        }
        navigator.clipboard.writeText(url).then(function () {
            if (window.AppUI && typeof window.AppUI.toast === 'function') {
                window.AppUI.toast('success', 'Lien copie dans le presse-papiers');
            }
        });
    });
})();
</script>

<div class="preview-modal" id="task-preview-modal" hidden>
    <div class="preview-modal-backdrop" data-preview-close></div>
    <div class="preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="task-preview-title">
        <button class="preview-modal-close" type="button" aria-label="Fermer" data-preview-close>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="preview-modal-head">
            <h3 id="task-preview-title">Apercu</h3>
        </div>
        <div class="preview-modal-body" id="task-preview-body"></div>
    </div>
</div>
<?php if (in_array($taskType, ['Tournage', 'Montage'], true)) { require __DIR__ . '/production-inline.php'; } ?>
<?php if (in_array($taskType, ['Validation interne', 'Validation client', 'Publication', 'Collecte KPI'], true)) { require __DIR__ . '/paired-task-inline.php'; } ?>
<?php if ($isBriefTask): ?><script src="<?= htmlspecialchars(app_url('/public/assets/social-composer.js?v=1')) ?>"></script><?php endif; ?>
