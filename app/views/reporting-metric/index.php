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
$socialConnections = is_array($socialConnections ?? null) ? $socialConnections : [];
$clientOptions = is_array($clientOptions ?? null) ? $clientOptions : [];
$pageOptions = is_array($pageOptions ?? null) ? $pageOptions : [];
$metricValue = static fn(array $row, string $key): string => !array_key_exists($key, $row) || $row[$key] === null
    ? '<span title="Métrique indisponible pour cette publication">—</span>'
    : number_format((int)$row[$key], 0, ',', ' ');
$metricPercent = static fn(array $row, string $key): string => !array_key_exists($key, $row) || $row[$key] === null
    ? '<span title="Métrique indisponible pour cette publication">—</span>'
    : number_format((float)$row[$key], 2, ',', ' ') . '%';

$filterQuery = http_build_query(array_filter([
    'columns' => is_array($_GET['columns']??null)?$_GET['columns']:[],
    'tables' => array_merge([''],ReportPresentation::tables($_GET)),
    'content_type' => $filters['content_type']??'',
    'sort' => $filters['sort']??'date_publication',
    'direction' => $filters['direction']??'desc',
    'campagne_id' => (int) ($filters['campagne_id'] ?? 0) > 0 ? (int) $filters['campagne_id'] : null,
    'publication_ref' => trim((string) ($filters['publication_ref'] ?? '')),
    'plateforme' => trim((string) ($filters['plateforme'] ?? '')),
    'client_id' => (int)($filters['client_id']??0)?:null,
    'connection_id' => (int)($filters['connection_id']??0)?:null,
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
$hasViewData=(bool)array_filter($growthSeries,static fn($item)=>array_key_exists('vues',$item)&&$item['vues']!==null);
$hasImpressionData=(bool)array_filter($growthSeries,static fn($item)=>array_key_exists('impressions',$item)&&$item['impressions']!==null);
$hasGrowthData=$hasViewData||$hasImpressionData;
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
.report-icon-actions,.social-reporting-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.social-reporting-actions{margin:10px 0 14px}.social-reporting-actions form{margin:0}.social-reporting-actions details{position:relative}.report-icon-button{position:relative;display:inline-grid;place-items:center;width:38px;height:38px;padding:0;border:1px solid #dbe5ee;border-radius:10px;background:#fff;color:#3d5872;cursor:pointer;text-decoration:none;transition:.15s}.report-icon-button:hover{border-color:#9bbbd6;background:#f4f9fd;color:#1f5f93}.report-icon-button.primary{background:#244f78;color:#fff;border-color:#244f78}.report-icon-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.report-icon-button[data-format]::after{content:attr(data-format);position:absolute;right:-4px;bottom:-4px;padding:1px 3px;border-radius:4px;background:#eef3f7;color:#526b82;font-size:7px;font-weight:800}.social-reporting-actions summary{list-style:none}.social-reporting-actions summary::-webkit-details-marker{display:none}.social-history-form{position:absolute;z-index:20;left:0;top:44px;display:grid;grid-template-columns:repeat(3,minmax(130px,1fr)) auto;gap:8px;align-items:end;width:min(720px,80vw);padding:12px;border:1px solid #dce6f0;border-radius:12px;background:#fff;box-shadow:0 16px 38px #17345222}.social-history-form label{display:grid;gap:4px;font-size:11px}.reporting-filter-row{display:grid!important;grid-template-columns:minmax(150px,1fr) minmax(190px,1.35fr) minmax(110px,.7fr) minmax(130px,.7fr) minmax(130px,.7fr) auto;gap:8px;align-items:end;padding-top:12px;border-top:1px solid #edf1f5}.reporting-filter-row label{min-width:0;font-size:11px}.reporting-filter-row select,.reporting-filter-row input{width:100%;box-sizing:border-box}.reporting-filter-row.is-loading{opacity:.62;pointer-events:none}#reporting-results>.panel{padding:20px}#reporting-results .stat-card{padding:14px 16px;border:0;border-left:2px solid #80b8e6;border-radius:8px;box-shadow:none;background:#f8fafc}#reporting-results .stat-value{font-size:clamp(24px,3vw,34px)}#reporting-results .detail-card{padding:13px;border-color:#e7edf3;border-radius:10px;box-shadow:none}#reporting-results svg{display:block;max-width:100%;border-radius:10px}#reporting-results svg rect:first-child{fill:#fafcfe}#reporting-results .requirement-list{display:flex;gap:6px;overflow:auto}#reporting-results .requirement-item{min-width:max-content;padding:7px 10px;border:0;background:#f1f7f4}.reporting-filter-row .form-actions{gap:5px}.reporting-filter-row .report-icon-button{width:36px;height:36px}@media(max-width:1100px){.reporting-filter-row{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.reporting-filter-row{grid-template-columns:1fr}.social-history-form{position:fixed;left:4vw;top:20vh;width:92vw;grid-template-columns:1fr}.report-icon-actions{max-width:100%}}
</style>
<style>.reporting-filter-row{grid-template-columns:repeat(3,minmax(120px,1fr)) minmax(170px,1.25fr) repeat(3,minmax(105px,.72fr)) auto}.overview-chart-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:10px}.overview-chart-grid>div{margin:0!important;min-width:0}.publication-cell{display:flex;align-items:center;gap:7px}.publication-link{display:inline-grid;place-items:center;width:25px;height:25px;border:1px solid #dbe5ee;border-radius:7px;color:#315f87;text-decoration:none;flex:0 0 auto}.reporting-filter-status{font-size:11px;color:#60758a}@media(max-width:1100px){.overview-chart-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.overview-chart-grid{grid-template-columns:1fr}}</style>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Statistiques & rapports</h2>
            <p class="panel-subtitle">Analysez les publications collectées automatiquement et exportez des rapports prêts à partager.</p>
        </div>
        <div class="report-icon-actions" id="report-export-actions" aria-label="Exporter les statistiques">
            <a class="report-icon-button" href="<?= htmlspecialchars(report_export_url('pdf','selection',$filterQuery)) ?>" title="PDF des tableaux sélectionnés" aria-label="PDF des tableaux sélectionnés">▤</a>
            <?php foreach([['excel','flat','XLS','Exporter l’analyse Excel'],['pdf','client','PDF','Exporter le rapport client'],['csv','individual','CSV','Exporter les données individuelles'],['pdf','individual','PDF','Exporter le PDF individuel'],['csv','publication','CSV','Exporter par publication'],['pdf','publication','PDF','Exporter le PDF par publication'],['csv','monthly','CSV','Exporter le rapport mensuel'],['pdf','monthly','PDF','Exporter le PDF mensuel']] as $export): ?>
            <a class="report-icon-button" data-format="<?= $export[2] ?>" href="<?= htmlspecialchars(report_export_url($export[0],$export[1],$filterQuery)) ?>" title="<?= htmlspecialchars($export[3]) ?>" aria-label="<?= htmlspecialchars($export[3]) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($canManage): ?>
    <?php $igReconnect=array_filter($socialConnections,static fn($connection)=>($connection['provider']??'')==='instagram' && ($connection['status']??'')==='Connected' && (!(int)($filters['client_id']??0)||(int)$connection['client_id']===(int)$filters['client_id']) && !in_array('instagram_manage_insights',(array)json_decode((string)($connection['scopes_json']??'[]'),true),true)); ?>
    <?php if($igReconnect): ?><details class="report-instagram-access"><summary>Accès aux statistiques Instagram à actualiser (<?= count($igReconnect) ?>)</summary><p>Autorisez <code>instagram_manage_insights</code> dans Meta, puis reconnectez et sélectionnez à nouveau ces comptes Instagram. Une ancienne connexion ne reçoit pas automatiquement les nouveaux droits.</p><?php foreach($igReconnect as $connection): ?><a href="<?= htmlspecialchars(route_url('/social-oauth/connect/'.(int)$connection['id'])) ?>"><?= htmlspecialchars((string)$connection['account_label']) ?> - Reconnecter</a><br><?php endforeach ?></details><?php endif ?>
    <div class="social-reporting-actions">
        <form method="post" action="<?= htmlspecialchars(route_url('/reporting-metric/collect-social-metrics')) ?>">
            <button class="report-icon-button primary" type="submit" title="Collecter les KPI Meta" aria-label="Collecter les KPI Meta"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6v5h-5M4 18v-5h5M6.2 9A7 7 0 0 1 18 6l2 5M4 13l2 5a7 7 0 0 0 11.8-3"/></svg></button>
        </form>
        <details>
            <summary class="report-icon-button" title="Importer l’historique Meta" aria-label="Importer l’historique Meta"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14"/></svg></summary>
            <form method="post" action="<?= htmlspecialchars(route_url('/reporting-metric/import-social-history')) ?>" class="social-history-form">
                <label>Page<select name="connection_id" required><option value="">Choisir</option><?php foreach($socialConnections as $connection):if(($connection['status']??'')!=='Connected'||!in_array($connection['provider']??'',['facebook','instagram'],true))continue;?><option value="<?= (int)$connection['id'] ?>"><?= htmlspecialchars(($connection['account_label']??'Page').' · '.ucfirst($connection['provider'])) ?></option><?php endforeach?></select></label>
                <label>Du<input type="date" name="from" required value="<?= date('Y-m-d',strtotime('-90 days')) ?>"></label>
                <label>Au<input type="date" name="to" required value="<?= date('Y-m-d') ?>"></label>
                <button class="button" type="submit">Importer et collecter</button>
            </form>
        </details>
        <a class="report-icon-button" href="<?= htmlspecialchars(route_url('/social-inbox')) ?>" title="Messages et commentaires" aria-label="Messages et commentaires"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4V5Zm4 4h8M8 12h5"/></svg></a>
    </div>
    <?php endif; ?>

    <form class="compact-filters reporting-filter-row" id="reporting-filter-form" method="get" action="<?= htmlspecialchars(route_url('/reporting-metric')) ?>">
        <details class="report-columns"><summary>Tableaux du rapport</summary><div><input type="hidden" name="tables[]" value=""><?php foreach(ReportPresentation::models() as $model=>$label): ?><label><input type="checkbox" name="tables[]" value="<?= $model ?>" <?= in_array($model,ReportPresentation::tables($_GET),true)?'checked':'' ?>> <?= htmlspecialchars($label) ?></label><?php endforeach ?></div></details>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort']??'date_publication') ?>">
        <input type="hidden" name="direction" value="<?= htmlspecialchars($filters['direction']??'desc') ?>">
        <label>Format<select name="content_type"><option value="">Tous les formats</option><?php foreach(['image'=>'Image','video'=>'Vidéo','reel'=>'Reel','carousel'=>'Carrousel','link'=>'Lien','text'=>'Texte','unknown'=>'Non renseigné'] as $type=>$label): ?><option value="<?= $type ?>" <?= ($filters['content_type']??'')===$type?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
        <label>Client<select name="client_id" id="reporting-client"><option value="">Tous</option><?php foreach($clientOptions as$id=>$label):?><option value="<?= (int)$id?>" <?= (int)($filters['client_id']??0)===(int)$id?'selected':''?>><?= htmlspecialchars($label)?></option><?php endforeach?></select></label>
        <label>Page<select name="connection_id" id="reporting-page"><option value="">Toutes</option><?php foreach($pageOptions as$id=>$label):?><option value="<?= (int)$id?>" <?= (int)($filters['connection_id']??0)===(int)$id?'selected':''?>><?= htmlspecialchars($label)?></option><?php endforeach?></select></label>
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
            <button class="report-icon-button primary" type="submit" title="Appliquer les filtres" aria-label="Appliquer les filtres"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7L4 5Z"/></svg></button>
            <a class="report-icon-button" href="<?= htmlspecialchars(route_url('/reporting-metric')) ?>" title="Réinitialiser les filtres" aria-label="Réinitialiser les filtres"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.5M4 4v4.5h4.5"/></svg></a>
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
            <button class="report-icon-button primary" type="submit" title="Enregistrer la collecte" aria-label="Enregistrer la collecte"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5V4Zm3 0v6h8V4M8 20v-6h8v6"/></svg></button>
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

    <div class="overview-chart-grid">
    <?php if (!empty($analysisLineSeries)): ?>
        <div style="overflow-x:auto; margin-bottom:10px;">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" width="100%" height="168" aria-label="Evolution du score global">
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
            <svg viewBox="0 0 <?= $barChartWidth ?> <?= $barChartHeight ?>" width="100%" height="168" aria-label="Comparaison reseaux">
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

    <?php if ($hasGrowthData): ?>
        <div style="overflow-x:auto;">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" width="100%" height="168" aria-label="Evolution des vues et impressions">
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
    </div>

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

    <div class="stats-grid" style="grid-template-columns:repeat(2,minmax(200px,1fr)); margin-bottom:10px;">
        <article class="stat-card">
            <span class="stat-label">Nombre de collectes</span>
            <span class="stat-value"><?= (int) ($impactStats['sample_size'] ?? 0) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Moyenne des vues</span>
            <span class="stat-value"><?= (int)($impactStats['sample_size']??0)>0?number_format((float)($impactStats['avg_views']??0),1,',',' '):'—' ?></span>
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
                <?php $hasCorrelation=($corr['correlation']??null)!==null;$badge=$hasCorrelation?reporting_correlation_badge((float)$corr['correlation']):['class'=>'','label'=>'Indisponible']; ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($corr['label'] ?? '')) ?></td>
                    <td><?= $hasCorrelation?number_format((float)$corr['correlation'],4,',',' '):'—' ?><?php if(!empty($corr['available_samples'])):?><small> · n=<?= (int)$corr['available_samples']?></small><?php endif?></td>
                    <td><span class="status-badge <?= htmlspecialchars((string) $badge['class']) ?>"><?= htmlspecialchars((string) $badge['label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/tables.php'; ?>

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
</script>
