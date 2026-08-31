<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$growthSeries = is_array($growthSeries ?? null) ? $growthSeries : [];
$impactStats = is_array($impactStats ?? null) ? $impactStats : ['sample_size' => 0, 'avg_views' => 0, 'correlations' => []];
$publicationReport = is_array($publicationReport ?? null) ? $publicationReport : [];
$monthlyReport = is_array($monthlyReport ?? null) ? $monthlyReport : [];
$analysisDashboard = is_array($analysisDashboard ?? null) ? $analysisDashboard : [];
$campaignOptions = is_array($campaignOptions ?? null) ? $campaignOptions : [];
$publicationOptions = is_array($publicationOptions ?? null) ? $publicationOptions : [];
$socialPublicationOptions = is_array($socialPublicationOptions ?? null) ? $socialPublicationOptions : [];
$platformOptions = is_array($platformOptions ?? null) ? $platformOptions : [];
$networkConfig = is_array($networkConfig ?? null) ? $networkConfig : [];
$defaultNetwork = (string) ($defaultNetwork ?? (array_key_first($networkConfig) ?: 'facebook'));
$canManage = !empty($canManage);
$metricValue = static fn(array $row, string $key): string => !array_key_exists($key, $row) || $row[$key] === null
    ? '<span title="Métrique indisponible pour cette publication">—</span>'
    : number_format((int)$row[$key], 0, ',', ' ');

$filterQuery = http_build_query(array_filter([
    'campagne_id' => (int) ($filters['campagne_id'] ?? 0) > 0 ? (int) $filters['campagne_id'] : null,
    'publication_ref' => trim((string) ($filters['publication_ref'] ?? '')),
    'plateforme' => trim((string) ($filters['plateforme'] ?? '')),
    'from' => trim((string) ($filters['from'] ?? '')),
    'to' => trim((string) ($filters['to'] ?? '')),
], static function ($value) {
    return $value !== null && $value !== '';
}));

function report_export_url($format, $type, $filterQuery) {
    return route_url('/reporting-metric') . '?export=' . urlencode((string) $format)
        . '&report_type=' . urlencode((string) $type)
        . ($filterQuery !== '' ? '&' . $filterQuery : '');
}

$viewValues = array_map(static function ($item) {
    return (int) ($item['vues'] ?? 0);
}, $growthSeries);
$impressionValues = array_map(static function ($item) {
    return (int) ($item['impressions'] ?? 0);
}, $growthSeries);
$maxY = max(1, max($viewValues ?: [0]), max($impressionValues ?: [0]));
$chartWidth = 760;
$chartHeight = 230;
$padding = 26;
$plotWidth = $chartWidth - ($padding * 2);
$plotHeight = $chartHeight - ($padding * 2);

$analysisCards = is_array($analysisDashboard['cards'] ?? null) ? $analysisDashboard['cards'] : [];
$analysisLineSeries = is_array($analysisDashboard['line_series'] ?? null) ? $analysisDashboard['line_series'] : [];
$analysisNetworkComparison = is_array($analysisDashboard['network_comparison'] ?? null) ? $analysisDashboard['network_comparison'] : [];
$analysisTopPublications = is_array($analysisDashboard['top_publications'] ?? null) ? $analysisDashboard['top_publications'] : [];
$analysisWeakPublications = is_array($analysisDashboard['weak_publications'] ?? null) ? $analysisDashboard['weak_publications'] : [];
$analysisInsights = is_array($analysisDashboard['global_insights'] ?? null) ? $analysisDashboard['global_insights'] : [];

$viewPoints = [];
$impressionPoints = [];
$countSeries = count($growthSeries);
foreach ($growthSeries as $index => $item) {
    $x = $countSeries > 1 ? $padding + ((($plotWidth) / ($countSeries - 1)) * $index) : $padding + ($plotWidth / 2);
    $viewY = $padding + $plotHeight - ((((int) ($item['vues'] ?? 0)) / $maxY) * $plotHeight);
    $impressionY = $padding + $plotHeight - ((((int) ($item['impressions'] ?? 0)) / $maxY) * $plotHeight);
    $viewPoints[] = round($x, 2) . ',' . round($viewY, 2);
    $impressionPoints[] = round($x, 2) . ',' . round($impressionY, 2);
}

function reporting_correlation_badge($correlation) {
    $abs = abs((float) $correlation);
    if ($abs >= 0.7) {
        return ['class' => 'status-terminee', 'label' => 'Forte'];
    }
    if ($abs >= 0.4) {
        return ['class' => 'status-en-cours', 'label' => 'Moyenne'];
    }
    if ($abs >= 0.2) {
        return ['class' => 'status-a-faire', 'label' => 'Faible'];
    }
    return ['class' => 'status-bloquee', 'label' => 'Tres faible'];
}

$scoreValues = array_map(static function ($item) {
    return (float) ($item['score_global'] ?? 0);
}, $analysisLineSeries);
$scoreMax = max(1, max($scoreValues ?: [0]));
$scorePoints = [];
$scoreSeriesCount = count($analysisLineSeries);
foreach ($analysisLineSeries as $index => $item) {
    $x = $scoreSeriesCount > 1 ? $padding + (($plotWidth / ($scoreSeriesCount - 1)) * $index) : $padding + ($plotWidth / 2);
    $y = $padding + $plotHeight - ((((float) ($item['score_global'] ?? 0)) / $scoreMax) * $plotHeight);
    $scorePoints[] = round($x, 2) . ',' . round($y, 2);
}

$barChartWidth = 760;
$barChartHeight = 240;
$barPadding = 28;
$barPlotWidth = $barChartWidth - ($barPadding * 2);
$barPlotHeight = $barChartHeight - ($barPadding * 2);
$barCount = max(1, count($analysisNetworkComparison));
$barMax = max(1, max(array_map(static function ($item) {
    return (float) ($item['performance_globale'] ?? 0);
}, $analysisNetworkComparison) ?: [0]));
$barWidth = min(90, ($barPlotWidth / $barCount) * 0.65);
?>
<style>
.reporting-filter-row{display:grid!important;grid-template-columns:minmax(170px,1fr) minmax(210px,1.35fr) minmax(130px,.75fr) minmax(140px,.72fr) minmax(140px,.72fr) auto;gap:10px;align-items:end}.reporting-filter-row label{min-width:0}.reporting-filter-row select,.reporting-filter-row input{width:100%;box-sizing:border-box}.reporting-filter-row.is-loading{opacity:.62;pointer-events:none}@media(max-width:1100px){.reporting-filter-row{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.reporting-filter-row{grid-template-columns:1fr}}
</style>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Statistiques & rapports</h2>
            <p class="panel-subtitle">Analysez les publications collectées automatiquement et exportez des rapports prêts à partager.</p>
        </div>
        <div class="form-actions">
            <a class="button" href="<?= htmlspecialchars(report_export_url('excel', 'flat', $filterQuery)) ?>">Excel analyse (flat)</a>
            <a class="button" href="<?= htmlspecialchars(report_export_url('pdf', 'client', $filterQuery)) ?>">PDF client</a>
            <a class="button secondary" href="<?= htmlspecialchars(report_export_url('csv', 'individual', $filterQuery)) ?>">CSV individuel</a>
            <a class="button secondary" href="<?= htmlspecialchars(report_export_url('pdf', 'individual', $filterQuery)) ?>">PDF individuel</a>
            <a class="button secondary" href="<?= htmlspecialchars(report_export_url('csv', 'publication', $filterQuery)) ?>">CSV par publication</a>
            <a class="button secondary" href="<?= htmlspecialchars(report_export_url('pdf', 'publication', $filterQuery)) ?>">PDF par publication</a>
            <a class="button secondary" href="<?= htmlspecialchars(report_export_url('csv', 'monthly', $filterQuery)) ?>">CSV mensuel</a>
            <a class="button secondary" href="<?= htmlspecialchars(report_export_url('pdf', 'monthly', $filterQuery)) ?>">PDF mensuel</a>
        </div>
    </div>

    <form class="compact-filters reporting-filter-row" id="reporting-filter-form" method="get" action="<?= htmlspecialchars(route_url('/reporting-metric')) ?>">
        <label>
            Campagne
            <select name="campagne_id">
                <option value="">Toutes</option>
                <?php foreach ($campaignOptions as $id => $label): ?>
                    <option value="<?= (int) $id ?>" <?= ((int) ($filters['campagne_id'] ?? 0) === (int) $id) ? 'selected' : '' ?>><?= htmlspecialchars((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Publication
            <select name="publication_ref">
                <option value="">Toutes</option>
                <?php if ($publicationOptions): ?><optgroup label="Contenus du calendrier"><?php endif; ?>
                <?php foreach ($publicationOptions as $id => $label): ?>
                    <option value="<?= (int) $id ?>" <?= ((string) ($filters['publication_ref'] ?? '') === (string) $id) ? 'selected' : '' ?>><?= htmlspecialchars((string) $label) ?></option>
                <?php endforeach; ?>
                <?php if ($publicationOptions): ?></optgroup><?php endif; ?>
                <?php if ($socialPublicationOptions): ?><optgroup label="Publications créées directement"><?php endif; ?>
                <?php foreach ($socialPublicationOptions as $id => $label): ?>
                    <option value="<?= htmlspecialchars((string) $id) ?>" <?= ((string) ($filters['publication_ref'] ?? '') === (string) $id) ? 'selected' : '' ?>><?= htmlspecialchars((string) $label) ?></option>
                <?php endforeach; ?>
                <?php if ($socialPublicationOptions): ?></optgroup><?php endif; ?>
            </select>
        </label>
        <label>
            Reseau
            <select name="plateforme">
                <option value="">Tous</option>
                <?php foreach ($platformOptions as $networkKey => $networkLabel): ?>
                    <option value="<?= htmlspecialchars((string) $networkKey) ?>" <?= ((string) ($filters['plateforme'] ?? '') === (string) $networkKey) ? 'selected' : '' ?>><?= htmlspecialchars((string) $networkLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Du
            <input type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>">
        </label>
        <label>
            Au
            <input type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>">
        </label>
        <div class="form-actions" style="align-self:flex-end;">
            <button class="button secondary" type="submit">Filtrer</button>
            <a class="button ghost" href="<?= htmlspecialchars(route_url('/reporting-metric')) ?>">Reinitialiser</a>
        </div>
    </form>
</section>

<?php if ($canManage): ?>
<details class="panel" style="margin-top:14px;">
    <summary style="cursor:pointer;font-weight:700;">Saisie manuelle de secours</summary>
<section style="margin-top:12px;">
    <div class="panel-head">
        <div>
            <h2>Nouvelle collecte manuelle</h2>
            <p class="panel-subtitle">Les indicateurs affiches changent automatiquement selon le reseau selectionne.</p>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars(route_url('/reporting-metric')) ?>" class="grid-form" id="kpi-form">
        <label>
            Campagne
            <select name="campagne_id">
                <option value="">Selectionner</option>
                <?php foreach ($campaignOptions as $id => $label): ?>
                    <option value="<?= (int) $id ?>"><?= htmlspecialchars((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Publication
            <select name="contenu_id">
                <option value="">Selectionner (optionnel)</option>
                <?php foreach ($publicationOptions as $id => $label): ?>
                    <option value="<?= (int) $id ?>"><?= htmlspecialchars((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Reseau de collecte
            <select name="plateforme" id="kpi-network" required>
                <?php foreach ($platformOptions as $networkKey => $networkLabel): ?>
                    <option value="<?= htmlspecialchars((string) $networkKey) ?>" <?= $defaultNetwork === (string) $networkKey ? 'selected' : '' ?>><?= htmlspecialchars((string) $networkLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Date de collecte
            <input type="date" name="date_collecte" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
        </label>
        <label style="grid-column: span 2;">
            URL publication (optionnel)
            <input type="url" name="url_publication" placeholder="https://...">
        </label>

        <div style="grid-column:1 / -1; overflow-x:auto;">
            <div id="kpi-dynamic-grid" style="display:grid; grid-auto-flow:column; grid-auto-columns:minmax(200px, 1fr); gap:10px; padding-bottom:8px;"></div>
        </div>

        <div class="form-actions" style="grid-column: 1 / -1;">
            <button class="button" type="submit">Enregistrer la collecte</button>
        </div>
    </form>
</section>
</details>
<?php endif; ?>

<div id="reporting-results" aria-live="polite">
<section class="panel" style="margin-top:14px;">
    <div class="panel-head">
        <div>
            <h2>Vue d’ensemble des performances</h2>
            <p class="panel-subtitle">Évolution, comparaison par réseau, publications fortes et points à optimiser.</p>
        </div>
    </div>

    <div class="stats-grid" style="grid-template-columns:repeat(4,minmax(180px,1fr)); margin-bottom:12px;">
        <article class="stat-card">
            <span class="stat-label">Collectes</span>
            <span class="stat-value"><?= (int) ($analysisCards['collectes'] ?? 0) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Score moyen</span>
            <span class="stat-value"><?= number_format((float) ($analysisCards['score_moyen'] ?? 0), 1, ',', ' ') ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Croissance moyenne</span>
            <span class="stat-value"><?= number_format((float) ($analysisCards['growth_moyen'] ?? 0), 2, ',', ' ') ?>%</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Performance journaliere</span>
            <span class="stat-value"><?= number_format((float) ($analysisCards['daily_moyen'] ?? 0), 2, ',', ' ') ?></span>
        </article>
    </div>

    <?php if (!empty($analysisInsights)): ?>
        <ul class="requirement-list" style="margin-bottom:12px;">
            <?php foreach ($analysisInsights as $insight): ?>
                <li class="requirement-item is-done"><span class="requirement-state">Insight</span><span><?= htmlspecialchars((string) $insight) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($analysisLineSeries)): ?>
        <div style="overflow-x:auto; margin-bottom:10px;">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" width="100%" height="260" aria-label="Evolution du score global">
                <rect x="0" y="0" width="<?= $chartWidth ?>" height="<?= $chartHeight ?>" fill="#f7fbff"></rect>
                <line x1="<?= $padding ?>" y1="<?= $padding ?>" x2="<?= $padding ?>" y2="<?= $padding + $plotHeight ?>" stroke="#8ea5b5" stroke-width="1"></line>
                <line x1="<?= $padding ?>" y1="<?= $padding + $plotHeight ?>" x2="<?= $padding + $plotWidth ?>" y2="<?= $padding + $plotHeight ?>" stroke="#8ea5b5" stroke-width="1"></line>
                <polyline fill="none" stroke="#23537a" stroke-width="3" points="<?= htmlspecialchars(implode(' ', $scorePoints)) ?>"></polyline>
                <text x="<?= $padding + 6 ?>" y="<?= $padding + 12 ?>" fill="#23537a" font-size="11">Score global</text>
            </svg>
        </div>
    <?php endif; ?>

    <?php if (!empty($analysisNetworkComparison)): ?>
        <div style="overflow-x:auto; margin-bottom:12px;">
            <svg viewBox="0 0 <?= $barChartWidth ?> <?= $barChartHeight ?>" width="100%" height="260" aria-label="Comparaison reseaux">
                <rect x="0" y="0" width="<?= $barChartWidth ?>" height="<?= $barChartHeight ?>" fill="#f7fbff"></rect>
                <line x1="<?= $barPadding ?>" y1="<?= $barPadding + $barPlotHeight ?>" x2="<?= $barPadding + $barPlotWidth ?>" y2="<?= $barPadding + $barPlotHeight ?>" stroke="#8ea5b5" stroke-width="1"></line>
                <?php foreach ($analysisNetworkComparison as $index => $networkRow): ?>
                    <?php
                    $x = $barPadding + (($barPlotWidth / $barCount) * $index) + ((($barPlotWidth / $barCount) - $barWidth) / 2);
                    $value = (float) ($networkRow['performance_globale'] ?? 0);
                    $height = ($value / $barMax) * $barPlotHeight;
                    $y = $barPadding + $barPlotHeight - $height;
                    ?>
                    <rect x="<?= round($x, 2) ?>" y="<?= round($y, 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round($height, 2) ?>" fill="#346d9b"></rect>
                    <text x="<?= round($x + ($barWidth / 2), 2) ?>" y="<?= round($barPadding + $barPlotHeight + 13, 2) ?>" text-anchor="middle" fill="#1f3b53" font-size="10"><?= htmlspecialchars((string) ($networkRow['reseau_label'] ?? $networkRow['reseau'] ?? '')) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
    <?php endif; ?>

    <div class="detail-grid" style="margin-bottom:12px;">
        <article class="detail-card">
            <span class="detail-label">Top 3 publications</span>
            <?php if (!empty($analysisTopPublications)): ?>
                <?php foreach ($analysisTopPublications as $item): ?>
                    <div class="mini-text"><?= htmlspecialchars((string) ($item['publication_titre'] ?? 'Publication')) ?> · Score <?= number_format((float) ($item['score_moyen'] ?? 0), 1, ',', ' ') ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mini-text">Aucune publication a classer.</div>
            <?php endif; ?>
        </article>
        <article class="detail-card">
            <span class="detail-label">Publications a ameliorer</span>
            <?php if (!empty($analysisWeakPublications)): ?>
                <?php foreach ($analysisWeakPublications as $item): ?>
                    <div class="mini-text"><?= htmlspecialchars((string) ($item['publication_titre'] ?? 'Publication')) ?> · Score <?= number_format((float) ($item['score_moyen'] ?? 0), 1, ',', ' ') ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mini-text">Aucune publication faible detectee.</div>
            <?php endif; ?>
        </article>
        <article class="detail-card">
            <span class="detail-label">Comparaison multi-reseaux</span>
            <?php foreach ($analysisNetworkComparison as $networkRow): ?>
                <div class="mini-text"><?= htmlspecialchars((string) ($networkRow['reseau_label'] ?? 'Reseau')) ?> · Score <?= number_format((float) ($networkRow['performance_globale'] ?? 0), 1, ',', ' ') ?> · Collectes <?= (int) ($networkRow['collectes'] ?? 0) ?></div>
            <?php endforeach; ?>
        </article>
    </div>

    <?php if (!empty($growthSeries)): ?>
        <div style="overflow-x:auto; margin-bottom:10px;">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" width="100%" height="260" aria-label="Courbe KPI">
                <rect x="0" y="0" width="<?= $chartWidth ?>" height="<?= $chartHeight ?>" fill="#f7fbff"></rect>
                <line x1="<?= $padding ?>" y1="<?= $padding ?>" x2="<?= $padding ?>" y2="<?= $padding + $plotHeight ?>" stroke="#8ea5b5" stroke-width="1"></line>
                <line x1="<?= $padding ?>" y1="<?= $padding + $plotHeight ?>" x2="<?= $padding + $plotWidth ?>" y2="<?= $padding + $plotHeight ?>" stroke="#8ea5b5" stroke-width="1"></line>
                <polyline fill="none" stroke="#2f7dd1" stroke-width="3" points="<?= htmlspecialchars(implode(' ', $viewPoints)) ?>"></polyline>
                <polyline fill="none" stroke="#e07a2a" stroke-width="2" stroke-dasharray="6 4" points="<?= htmlspecialchars(implode(' ', $impressionPoints)) ?>"></polyline>
                <text x="<?= $padding + 6 ?>" y="<?= $padding + 12 ?>" fill="#2f7dd1" font-size="11">Vues</text>
                <text x="<?= $padding + 62 ?>" y="<?= $padding + 12 ?>" fill="#e07a2a" font-size="11">Impressions</text>
            </svg>
        </div>
    <?php endif; ?>

    <div class="stats-grid" style="grid-template-columns:repeat(2,minmax(200px,1fr)); margin-bottom:10px;">
        <article class="stat-card">
            <span class="stat-label">Nombre de collectes</span>
            <span class="stat-value"><?= (int) ($impactStats['sample_size'] ?? 0) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Moyenne des vues</span>
            <span class="stat-value"><?= number_format((float) ($impactStats['avg_views'] ?? 0), 1, ',', ' ') ?></span>
        </article>
    </div>

    <div class="table-wrap compact-table" style="margin-bottom:10px;">
        <table>
            <thead>
            <tr>
                <th>Indicateur</th>
                <th>Correlation avec vues</th>
                <th>Niveau</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ((array) ($impactStats['correlations'] ?? []) as $corr): ?>
                <?php $badge = reporting_correlation_badge((float) ($corr['correlation'] ?? 0)); ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($corr['label'] ?? '')) ?></td>
                    <td><?= number_format((float) ($corr['correlation'] ?? 0), 4, ',', ' ') ?></td>
                    <td><span class="status-badge <?= htmlspecialchars((string) $badge['class']) ?>"><?= htmlspecialchars((string) $badge['label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:14px;">
    <div class="panel-head">
        <div>
            <h2>Rapport global par publication</h2>
            <p class="panel-subtitle">Aggregation multi-reseaux pour mesurer la performance d une publication.</p>
        </div>
    </div>
    <div class="table-wrap compact-table">
        <table>
            <thead>
            <tr>
                <th>Publication</th>
                <th>Collectes</th>
                <th>Impressions</th>
                <th>Couverture</th>
                <th>Vues</th>
                <th>Clics</th>
                <th>CTR moyen</th>
                <th>Engagement rate moyen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($publicationReport as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['publication'] ?? '')) ?></td>
                    <td><?= number_format((int) ($row['collectes'] ?? 0), 0, ',', ' ') ?></td>
                    <td><?= $metricValue($row, 'impressions') ?></td>
                    <td><?= $metricValue($row, 'couverture') ?></td>
                    <td><?= $metricValue($row, 'vues') ?></td>
                    <td><?= $metricValue($row, 'clics') ?></td>
                    <td><?= number_format((float) ($row['ctr_moyen'] ?? 0), 2, ',', ' ') ?>%</td>
                    <td><?= number_format((float) ($row['engagement_rate_moyen'] ?? 0), 2, ',', ' ') ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:14px;">
    <div class="panel-head">
        <div>
            <h2>Rapport global mensuel</h2>
            <p class="panel-subtitle">Vue consolidee par mois, reseau et publication avec totaux + moyennes.</p>
        </div>
    </div>
    <div class="table-wrap compact-table">
        <table>
            <thead>
            <tr>
                <th>Mois</th>
                <th>Reseau</th>
                <th>Publication</th>
                <th>Collectes</th>
                <th>Vues totales</th>
                <th>Vues moyennes</th>
                <th>Clics totaux</th>
                <th>Clics moyens</th>
                <th>CTR moyen</th>
                <th>Engagement rate moyen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlyReport as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['mois'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($platformOptions[$row['plateforme'] ?? ''] ?? ($row['plateforme'] ?? '')) ) ?></td>
                    <td><?= htmlspecialchars((string) ($row['publication'] ?? '')) ?></td>
                    <td><?= number_format((int) ($row['collectes'] ?? 0), 0, ',', ' ') ?></td>
                    <td><?= number_format((int) ($row['vues_total'] ?? 0), 0, ',', ' ') ?></td>
                    <td><?= number_format((float) ($row['vues_moyenne'] ?? 0), 2, ',', ' ') ?></td>
                    <td><?= number_format((int) ($row['clics_total'] ?? 0), 0, ',', ' ') ?></td>
                    <td><?= number_format((float) ($row['clics_moyenne'] ?? 0), 2, ',', ' ') ?></td>
                    <td><?= number_format((float) ($row['ctr_moyen'] ?? 0), 2, ',', ' ') ?>%</td>
                    <td><?= number_format((float) ($row['engagement_rate_moyen'] ?? 0), 2, ',', ' ') ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:14px;">
    <div class="panel-head">
        <div>
            <h2>Rapport individuel par publication</h2>
            <p class="panel-subtitle">Historique detaille, filtreable par reseau/publication/date.</p>
        </div>
    </div>

    <div class="table-wrap compact-table">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Periode</th>
                <th>Campagne</th>
                <th>Publication</th>
                <th>Reseau</th>
                <th>Impr.</th>
                <th>Couv.</th>
                <th>Vues</th>
                <th>Clics</th>
                <th>CTR</th>
                <th>Engagement</th>
                <th>Score</th>
                <th>Croissance</th>
                <th>Perf/jour</th>
                <?php if ($canManage): ?><th>Action</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['date_collecte'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['periode_analysee'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['campagne_nom'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['publication_titre'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($platformOptions[$row['plateforme'] ?? ''] ?? ($row['plateforme'] ?? '')) ) ?></td>
                    <td><?= $metricValue($row, 'impressions') ?></td>
                    <td><?= $metricValue($row, 'couverture') ?></td>
                    <td><?= $metricValue($row, 'vues') ?></td>
                    <td><?= $metricValue($row, 'clics') ?></td>
                    <td><?= number_format((float) ($row['ctr'] ?? 0), 2, ',', ' ') ?>%</td>
                    <td><?= number_format((float) ($row['engagement_rate'] ?? 0), 2, ',', ' ') ?>%</td>
                    <td><?= number_format((float) ($row['score_global'] ?? 0), 2, ',', ' ') ?></td>
                    <td><?= number_format((float) ($row['growth_rate'] ?? 0), 2, ',', ' ') ?>%</td>
                    <td><?= number_format((float) ($row['daily_rate'] ?? 0), 2, ',', ' ') ?></td>
                    <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="<?= htmlspecialchars(route_url('/reporting-metric/delete/' . (int) ($row['id'] ?? 0))) ?>" onsubmit="return confirm('Supprimer cette collecte ?');">
                                <button type="submit" class="button ghost">Supprimer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

</div>
<script>
(function () {
    var config = <?= json_encode($networkConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var networkSelect = document.getElementById('kpi-network');
    var dynamicGrid = document.getElementById('kpi-dynamic-grid');
    if (!networkSelect || !dynamicGrid) {
        return;
    }

    function createField(kpi) {
        var wrapper = document.createElement('label');
        wrapper.style.minWidth = '200px';
        wrapper.textContent = String(kpi.label || kpi.name || 'KPI');

        var input = document.createElement('input');
        input.type = 'number';
        input.name = 'kpi_values[' + String(kpi.name || '') + ']';
        input.min = '0';
        input.step = (String(kpi.type || 'integer') === 'float') ? '0.01' : '1';
        input.placeholder = String(kpi.placeholder || '0');
        input.required = true;

        wrapper.appendChild(input);
        return wrapper;
    }

    function renderKpiFields() {
        var network = String(networkSelect.value || '').toLowerCase();
        var networkMeta = config[network] || null;
        dynamicGrid.innerHTML = '';

        if (!networkMeta || !Array.isArray(networkMeta.kpis)) {
            return;
        }

        networkMeta.kpis.forEach(function (kpi) {
            dynamicGrid.appendChild(createField(kpi));
        });
    }

    networkSelect.addEventListener('change', renderKpiFields);
    renderKpiFields();
})();
(function(){var form=document.getElementById('reporting-filter-form');var results=document.getElementById('reporting-results');if(!form||!results||!window.fetch||!window.DOMParser)return;form.addEventListener('submit',function(event){event.preventDefault();var url=form.action+'?'+new URLSearchParams(new FormData(form)).toString();form.classList.add('is-loading');fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(response){if(!response.ok)throw new Error('HTTP '+response.status);return response.text();}).then(function(html){var copy=new DOMParser().parseFromString(html,'text/html');var next=copy.getElementById('reporting-results');if(!next)throw new Error('Résultats indisponibles');results.replaceWith(next);results=next;history.replaceState({},'',url);}).catch(function(){window.location.href=url;}).finally(function(){form.classList.remove('is-loading');});});})();
</script>
