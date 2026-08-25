<?php
$globalStats = is_array($globalStats ?? null) ? $globalStats : [];
$projects = is_array($projects ?? null) ? $projects : [];
$clients = is_array($clients ?? null) ? $clients : [];
$filters = is_array($filters ?? null) ? $filters : [];
$globalMonthCalendar = is_array($globalMonthCalendar ?? null) ? $globalMonthCalendar : [];
$openGlobalCalendar = !empty($openGlobalCalendar);
$showGlobalCalendar = array_key_exists('showGlobalCalendar', get_defined_vars()) ? (bool) $showGlobalCalendar : true;
$showProjectsPilotage = array_key_exists('showProjectsPilotage', get_defined_vars()) ? (bool) $showProjectsPilotage : true;
$calendarColorDefaults = [
    'retard' => ['label' => 'Contenu retard', 'bg' => '#F3E4E6', 'border' => '#CC7A82', 'text' => '#8B3A41'],
    'non_rempli' => ['label' => 'Fiche non remplie', 'bg' => '#E9EFF5', 'border' => '#AAB7C5', 'text' => '#5D6E80'],
    'brief_attente' => ['label' => 'Brief/Script attente', 'bg' => '#FFF0D9', 'border' => '#E1A64F', 'text' => '#A96815'],
    'tournage_attente' => ['label' => 'Tournage attente', 'bg' => '#FFE7C7', 'border' => '#E89A3C', 'text' => '#B86612'],
    'montage_attente' => ['label' => 'Montage attente', 'bg' => '#FFF6C8', 'border' => '#D9BE4E', 'text' => '#8F7800'],
    'production_attente' => ['label' => 'Production attente', 'bg' => '#FFF9DA', 'border' => '#E1C96A', 'text' => '#847100'],
    'validation_attente' => ['label' => 'Validation attente', 'bg' => '#E5F3FF', 'border' => '#8BC4F7', 'text' => '#0D6FB8'],
    'publication_attente' => ['label' => 'Publication attente', 'bg' => '#DCEBFA', 'border' => '#7BA7D4', 'text' => '#1C5D8F'],
    'premiere_collecte' => ['label' => 'Premiere collecte', 'bg' => '#E5F1E9', 'border' => '#8FB8A8', 'text' => '#3E7F67'],
    'publie' => ['label' => 'Publie', 'bg' => '#E4F6EC', 'border' => '#8DCFAF', 'text' => '#2D9B6E'],
];
$calendarColorSchemeInput = is_array($calendarColorScheme ?? null) ? $calendarColorScheme : [];

if (!function_exists('cal_color_sanitize_hex')) {
    function cal_color_sanitize_hex($value, $fallback) {
        $hex = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $hex) ? $hex : strtoupper((string) $fallback);
    }
}

$calendarColorScheme = [];
foreach ($calendarColorDefaults as $stateKey => $defaultPalette) {
    $raw = is_array($calendarColorSchemeInput[$stateKey] ?? null) ? $calendarColorSchemeInput[$stateKey] : [];
    $calendarColorScheme[$stateKey] = [
        'label' => (string) ($raw['label'] ?? $defaultPalette['label']),
        'bg' => cal_color_sanitize_hex($raw['bg'] ?? $defaultPalette['bg'], $defaultPalette['bg']),
        'border' => cal_color_sanitize_hex($raw['border'] ?? $defaultPalette['border'], $defaultPalette['border']),
        'text' => cal_color_sanitize_hex($raw['text'] ?? $defaultPalette['text'], $defaultPalette['text']),
    ];
}

$calendarCssVarMap = [
    'retard' => 'cal-retard',
    'non_rempli' => 'cal-non-rempli',
    'brief_attente' => 'cal-brief-attente',
    'tournage_attente' => 'cal-tournage-attente',
    'montage_attente' => 'cal-montage-attente',
    'production_attente' => 'cal-production-attente',
    'validation_attente' => 'cal-validation-attente',
    'publication_attente' => 'cal-publication-attente',
    'premiere_collecte' => 'cal-premiere-collecte',
    'publie' => 'cal-publie',
];

$calendarStageSchemeMap = [
    'cal-retard' => 'retard',
    'cal-non-rempli' => 'non_rempli',
    'cal-brief-attente' => 'brief_attente',
    'cal-tournage-attente' => 'tournage_attente',
    'cal-montage-attente' => 'montage_attente',
    'cal-production-attente' => 'production_attente',
    'cal-validation-attente' => 'validation_attente',
    'cal-publication-attente' => 'publication_attente',
    'cal-premiere-collecte' => 'premiere_collecte',
    'cal-publie' => 'publie',
];

if (!function_exists('cal_global_item_style')) {
    function cal_global_item_style($stageClass, array $scheme, array $stageMap) {
        $stageClass = (string) $stageClass;
        $schemeKey = (string) ($stageMap[$stageClass] ?? '');
        if ($schemeKey === '' || !isset($scheme[$schemeKey]) || !is_array($scheme[$schemeKey])) {
            return '';
        }

        $palette = $scheme[$schemeKey];
        $bg = (string) ($palette['bg'] ?? '');
        $border = (string) ($palette['border'] ?? '');
        $text = (string) ($palette['text'] ?? '');
        if ($bg === '' || $border === '' || $text === '') {
            return '';
        }

        return sprintf('background:%s;border-color:%s;color:%s;', $bg, $border, $text);
    }
}

if (!function_exists('cal_global_item_text_color')) {
    function cal_global_item_text_color($stageClass, array $scheme, array $stageMap, $fallback = '#102A43') {
        $stageClass = (string) $stageClass;
        $schemeKey = (string) ($stageMap[$stageClass] ?? '');
        if ($schemeKey === '' || !isset($scheme[$schemeKey]) || !is_array($scheme[$schemeKey])) {
            return (string) $fallback;
        }
        $text = strtoupper(trim((string) ($scheme[$schemeKey]['text'] ?? '')));
        if (preg_match('/^#[0-9A-F]{6}$/', $text)) {
            return $text;
        }
        return (string) $fallback;
    }
}

if (!function_exists('cal_global_stage_class')) {
    function cal_global_stage_class($stageKey) {
        // Nouvelle palette de couleurs pour le calendrier
        $map = [
            // Nouveau système avec couleurs spécifiques
            'cal-retard' => 'cal-retard',
            'cal-non-rempli' => 'cal-non-rempli',
            'cal-brief-attente' => 'cal-brief-attente',
            'cal-tournage-attente' => 'cal-tournage-attente',
            'cal-montage-attente' => 'cal-montage-attente',
            'cal-production-attente' => 'cal-production-attente',
            'cal-validation-attente' => 'cal-validation-attente',
            'cal-publication-attente' => 'cal-publication-attente',
            'cal-publie' => 'cal-publie',
            'cal-premiere-collecte' => 'cal-premiere-collecte',
            
            // Ancien système (compatibility fallback)
            'publication' => 'cal-publie',
            'validation-client' => 'cal-validation-attente',
            'validation-interne' => 'cal-validation-attente',
            'montage' => 'cal-montage-attente',
            'tournage' => 'cal-tournage-attente',
            'script' => 'cal-premiere-collecte',
            'production' => 'cal-production-attente',
            'brief' => 'cal-premiere-collecte',
            'script-attente' => 'cal-brief-attente',
            'brief-attente' => 'cal-brief-attente',
        ];
        return $map[(string) $stageKey] ?? 'cal-non-rempli';
    }
}

if (!function_exists('cal_global_type_icon')) {
    function cal_global_type_icon($type) {
        return (string) $type === 'Video' ? 'VID' : 'VIS';
    }
}
?>
<style>
<?php foreach ($calendarCssVarMap as $stateKey => $varKey): ?>
:root {
    --<?= htmlspecialchars($varKey) ?>-bg: <?= htmlspecialchars((string) ($calendarColorScheme[$stateKey]['bg'] ?? '#FFFFFF')) ?>;
    --<?= htmlspecialchars($varKey) ?>-border: <?= htmlspecialchars((string) ($calendarColorScheme[$stateKey]['border'] ?? '#DDDDDD')) ?>;
    --<?= htmlspecialchars($varKey) ?>-text: <?= htmlspecialchars((string) ($calendarColorScheme[$stateKey]['text'] ?? '#111111')) ?>;
}
<?php endforeach; ?>
</style>
<section class="page-intro-card calendar-page-intro"><div><span class="page-eyebrow">Planification éditoriale</span><h2>Calendrier et pilotage</h2><p>Suivez les plans mensuels, les échéances et la progression de tous les projets depuis une vue consolidée.</p></div><span class="context-pill"><?= count($projects) ?> projet<?= count($projects) > 1 ? 's' : '' ?></span></section>
<section class="panel calendar-stats-panel">
    <div class="panel-head">
        <div>
            <h2>Statistiques globales</h2>
            <p class="panel-subtitle">Vue macro des calendriers et de leur taux de completion.</p>
        </div>
    </div>
    <div class="stats-grid">
        <article class="stat-card">
            <span class="stat-label">Plans mensuels</span>
            <span class="stat-value"><?= htmlspecialchars((string) ($globalStats['plans_total'] ?? 0)) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Calendriers termines</span>
            <span class="stat-value"><?= htmlspecialchars((string) ($globalStats['calendar_tasks_done'] ?? 0)) ?>/<?= htmlspecialchars((string) ($globalStats['calendar_tasks_total'] ?? 0)) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Taux de completion calendrier</span>
            <span class="stat-value"><?= htmlspecialchars((string) ($globalStats['calendar_completion_rate'] ?? 0)) ?>%</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Calendriers invalides</span>
            <span class="stat-value"><?= htmlspecialchars((string) ($globalStats['calendar_tasks_invalid'] ?? 0)) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Retard moyen</span>
            <span class="stat-value"><?= htmlspecialchars((string) ($globalStats['avg_delay_days'] ?? 0)) ?> j</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Validation client au 1er passage</span>
            <span class="stat-value"><?= htmlspecialchars((string) ($globalStats['first_pass_validation_rate'] ?? 0)) ?>%</span>
        </article>
    </div>
    <?php $monthlyRatios = (array) ($globalStats['monthly_invalidation_ratio'] ?? []); ?>
    <?php if (!empty($monthlyRatios)): ?>
        <div class="table-wrap compact-table" style="margin-top: 16px;">
            <table>
                <thead>
                    <tr>
                        <th>Mois</th>
                        <th>Ratio invalidations</th>
                        <th>Invalidations</th>
                        <th>Decisions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyRatios as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['month'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['ratio'] ?? 0)) ?>%</td>
                            <td><?= htmlspecialchars((string) ($row['invalid_count'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars((string) ($row['total_count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($showGlobalCalendar): ?>
<section class="panel" id="global-month-calendar-section">
    <div class="panel-head">
        <div>
            <h2>Calendrier global mensuel</h2>
            <p class="panel-subtitle">Planification de tous les contenus du mois, tous clients confondus, avec etat du workflow par publication.</p>
        </div>
        <button type="button" class="button secondary" id="global-calendar-toggle" aria-expanded="<?= $openGlobalCalendar ? 'true' : 'false' ?>" aria-controls="global-calendar-body"><?= $openGlobalCalendar ? 'Masquer' : 'Afficher' ?></button>
    </div>
    <div id="global-calendar-body"<?= $openGlobalCalendar ? '' : ' hidden' ?>>

    <form method="get" class="list-toolbar">
        <label class="field toolbar-field">
            <span>Mois</span>
            <input type="month" name="month" value="<?= htmlspecialchars((string) ($filters['month'] ?? date('Y-m'))) ?>">
        </label>
        <label class="field toolbar-field">
            <span>Client</span>
            <select name="client_id">
                <option value="">Tous</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars((string) ($client['id'] ?? '')) ?>" <?= (($filters['client_id'] ?? '') === (string) ($client['id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($client['entreprise'] ?? 'Client')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field toolbar-field">
            <span>Affichage</span>
            <select name="group_by_client">
                <option value="0" <?= (($filters['group_by_client'] ?? '0') !== '1') ? 'selected' : '' ?>>Detail par contenu</option>
                <option value="1" <?= (($filters['group_by_client'] ?? '0') === '1') ? 'selected' : '' ?>>Regrouper par client</option>
            </select>
        </label>
        <div class="toolbar-actions">
            <button class="button" type="submit">Afficher</button>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier')) ?>">Mois courant</a>
        </div>
    </form>

    <div class="chips-row" style="margin-bottom:10px; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">
        <?php foreach ($calendarColorScheme as $stateKey => $palette): ?>
            <?php $cssKey = (string) ($calendarCssVarMap[$stateKey] ?? 'cal-non-rempli'); ?>
            <span class="chip" style="background: var(--<?= htmlspecialchars($cssKey) ?>-bg); border-color: var(--<?= htmlspecialchars($cssKey) ?>-border); color: var(--<?= htmlspecialchars($cssKey) ?>-text);">
                <span style="width:10px; height:10px; border-radius:50%; background: var(--<?= htmlspecialchars($cssKey) ?>-text); display:inline-block; margin-right:6px;"></span>
                <?= htmlspecialchars((string) ($palette['label'] ?? $stateKey)) ?>
            </span>
        <?php endforeach; ?>
    </div>

    <?php
    $monthRaw = (string) ($globalMonthCalendar['month'] ?? date('Y-m'));
    $monthStart = new DateTime($monthRaw . '-01');
    $monthEnd = new DateTime(date('Y-m-t', strtotime($monthRaw . '-01')));
    $gridStart = clone $monthStart;
    $gridStart->modify('monday this week');
    $gridEnd = clone $monthEnd;
    $gridEnd->modify('sunday this week');
    $itemsByDate = (array) ($globalMonthCalendar['items_by_date'] ?? []);
    $groupByClient = (($filters['group_by_client'] ?? '0') === '1');
    $dayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    ?>

    <div class="global-calendar-scroll">
    <div class="global-calendar-weekdays">
        <?php foreach ($dayLabels as $label): ?>
            <span><?= htmlspecialchars($label) ?></span>
        <?php endforeach; ?>
    </div>

    <div class="global-calendar-grid">
        <?php
        $cursor = clone $gridStart;
        while ($cursor <= $gridEnd):
            $dateKey = $cursor->format('Y-m-d');
            $items = (array) ($itemsByDate[$dateKey] ?? []);
            $outside = $cursor->format('Y-m') !== $monthStart->format('Y-m');
        ?>
            <article class="global-calendar-cell<?= $outside ? ' is-outside' : '' ?>">
                <div class="global-calendar-date"><?= htmlspecialchars($cursor->format('d/m/Y')) ?></div>
                <?php if (empty($items)): ?>
                    <div class="mini-text">Aucun contenu planifie</div>
                <?php else: ?>
                    <div class="global-calendar-items">
                        <?php if ($groupByClient): ?>
                            <?php
                            $clientGroups = [];
                            foreach ($items as $item) {
                                $clientName = (string) ($item['client_nom'] ?? 'Client');
                                if (!isset($clientGroups[$clientName])) {
                                    $clientGroups[$clientName] = ['count' => 0, 'stage' => 'cal-non-rempli'];
                                }
                                $clientGroups[$clientName]['count']++;
                                $itemStage = (string) cal_global_stage_class($item['stage_key'] ?? '');
                                // Priority order: higher number = more critical/displayed
                                $priority = [
                                    'cal-publie' => 0,
                                    'cal-premiere-collecte' => 1,
                                    'cal-publication-attente' => 2,
                                    'cal-validation-attente' => 3,
                                    'cal-montage-attente' => 4,
                                    'cal-production-attente' => 4,
                                    'cal-tournage-attente' => 5,
                                    'cal-brief-attente' => 6,
                                    'cal-non-rempli' => 7,
                                    'cal-retard' => 8,
                                ];
                                $currentPriority = $priority[$clientGroups[$clientName]['stage']] ?? 0;
                                $newPriority = $priority[$itemStage] ?? 0;
                                if ($newPriority >= $currentPriority) {
                                    $clientGroups[$clientName]['stage'] = $itemStage;
                                }
                            }
                            ?>
                            <?php foreach ($clientGroups as $clientName => $meta): ?>
                                <?php
                                $groupStageClass = (string) ($meta['stage'] ?? 'cal-non-rempli');
                                $groupStyle = cal_global_item_style($groupStageClass, $calendarColorScheme, $calendarStageSchemeMap);
                                $groupTextColor = cal_global_item_text_color($groupStageClass, $calendarColorScheme, $calendarStageSchemeMap);
                                ?>
                                <div class="global-calendar-item <?= htmlspecialchars($groupStageClass) ?>"<?= $groupStyle !== '' ? ' style="' . htmlspecialchars($groupStyle) . '"' : '' ?>>
                                    <span class="global-calendar-type-icon">CLT</span>
                                    <span class="global-calendar-client" style="color: <?= htmlspecialchars($groupTextColor) ?>;"><?= htmlspecialchars((string) $clientName) ?></span>
                                    <span class="global-calendar-title" style="color: <?= htmlspecialchars($groupTextColor) ?>;"><?= htmlspecialchars((string) ($meta['count'] ?? 0)) ?> publication(s)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $projectUrl = route_url('/calendrier/projet/' . (int) ($item['projet_id'] ?? 0))
                                    . '?month=' . urlencode(date('Y-m-01', strtotime((string) ($item['date_prevue'] ?? $dateKey))))
                                    . '&focus_deliverable=' . (int) ($item['deliverable_id'] ?? 0)
                                    . '#deliverable-' . (int) ($item['deliverable_id'] ?? 0);
                                $stageClass = cal_global_stage_class($item['stage_key'] ?? '');
                                $stageStyle = cal_global_item_style($stageClass, $calendarColorScheme, $calendarStageSchemeMap);
                                $stageTextColor = cal_global_item_text_color($stageClass, $calendarColorScheme, $calendarStageSchemeMap);
                                ?>
                                <a class="global-calendar-item <?= htmlspecialchars($stageClass) ?>" href="<?= htmlspecialchars($projectUrl) ?>" title="<?= htmlspecialchars((string) ($item['stage_label'] ?? '')) ?>"<?= $stageStyle !== '' ? ' style="' . htmlspecialchars($stageStyle) . '"' : '' ?>>
                                    <span class="global-calendar-type-icon"><?= htmlspecialchars(cal_global_type_icon($item['type_livrable'] ?? '')) ?></span>
                                    <span class="global-calendar-client" style="color: <?= htmlspecialchars($stageTextColor) ?>;"><?= htmlspecialchars((string) ($item['client_nom'] ?? 'Client')) ?></span>
                                    <span class="global-calendar-title" style="color: <?= htmlspecialchars($stageTextColor) ?>;"><?= htmlspecialchars((string) ($item['titre'] ?? 'Contenu')) ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php
            $cursor->modify('+1 day');
        endwhile;
        ?>
    </div>
    </div>
    </div><!-- /global-calendar-body -->
</section>
<?php endif; ?>

<script>
(function () {
    var btn = document.getElementById('global-calendar-toggle');
    var body = document.getElementById('global-calendar-body');
    if (!btn || !body) { return; }
    btn.addEventListener('click', function () {
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        body.hidden = expanded;
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        btn.textContent = expanded ? 'Afficher' : 'Masquer';
    });
})();
</script>

<?php if ($showProjectsPilotage): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Projets pilotes</h2>
            <p class="panel-subtitle">Vue directe des projets avec filtres client, mois/periode et taux de completion.</p>
        </div>
    </div>
    <form method="get" class="list-toolbar">
        <label class="field toolbar-field">
            <span>Client</span>
            <select name="client_id">
                <option value="">Tous</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars((string) ($client['id'] ?? '')) ?>" <?= (($filters['client_id'] ?? '') === (string) ($client['id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($client['entreprise'] ?? 'Client')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field toolbar-field"><span>Mois/periode du</span><input type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>"></label>
        <label class="field toolbar-field"><span>au</span><input type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>"></label>
        <label class="field toolbar-field"><span>Completion min %</span><input type="number" name="completion_min" min="0" max="100" step="1" value="<?= htmlspecialchars((string) ($filters['completion_min'] ?? '')) ?>"></label>
        <label class="field toolbar-field"><span>Completion max %</span><input type="number" name="completion_max" min="0" max="100" step="1" value="<?= htmlspecialchars((string) ($filters['completion_max'] ?? '')) ?>"></label>
        <div class="toolbar-actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier')) ?>">Reinitialiser</a>
        </div>
    </form>
    <div class="table-wrap compact-table">
        <table>
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Projet</th>
                    <th>Periode projet</th>
                    <th>Mois calendrier</th>
                    <th>Plans</th>
                    <th>Completion</th>
                    <th>Prochaine deadline</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($project['client_nom'] ?? '')) ?></td>
                        <td>
                            <strong><?= htmlspecialchars((string) ($project['nom'] ?? '')) ?></strong>
                            <div class="mini-text"><?= htmlspecialchars((string) ($project['type_projet'] ?? '')) ?></div>
                        </td>
                        <td><?= htmlspecialchars((string) ($project['date_debut'] ?? '')) ?> → <?= htmlspecialchars((string) ($project['date_fin'] ?? '')) ?></td>
                        <td>
                            <?php $calendarMonth = (string) ($project['calendar_month'] ?? ''); ?>
                            <?= $calendarMonth !== '' ? htmlspecialchars(date('F Y', strtotime($calendarMonth))) : '—' ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($project['plans_total'] ?? 0)) ?></td>
                        <td>
                            <span class="status-badge status-terminee"><?= htmlspecialchars((string) ($project['completion_rate'] ?? 0)) ?>%</span>
                            <div class="mini-text"><?= htmlspecialchars((string) ($project['tasks_done'] ?? 0)) ?>/<?= htmlspecialchars((string) ($project['tasks_total'] ?? 0)) ?> taches</div>
                        </td>
                        <td><?= htmlspecialchars((string) ($project['prochaine_deadline'] ?? 'Aucune')) ?></td>
                        <td>
                            <?php
                            $projectUrl = route_url('/calendrier/projet/' . $project['id']);
                            if (!empty($project['calendar_month'])) {
                                $projectUrl .= '?month=' . urlencode((string) $project['calendar_month']);
                            }
                            ?>
                            <a class="button" href="<?= htmlspecialchars($projectUrl) ?>">Ouvrir le calendrier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="8">Aucun projet disponible avec ces filtres.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
