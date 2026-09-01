<?php
class ReportingMetricController extends Controller {
    private $reportingMetricModel;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('reporting.view');
        $this->reportingMetricModel = new ReportingMetricModel();
    }

    public function index() {
        $networkConfig = $this->reportingMetricModel->getNetworkKpiConfig();
        $defaultNetwork = array_key_first($networkConfig) ?: 'facebook';
        $publicationRef = trim((string) ($_GET['publication_ref'] ?? ''));
        $filters = [
            'campagne_id' => (int) ($_GET['campagne_id'] ?? 0),
            'contenu_id' => ctype_digit($publicationRef) ? (int) $publicationRef : 0,
            'social_publication_id' => preg_match('/^social-(\d+)$/', $publicationRef, $match) ? (int) $match[1] : 0,
            'publication_ref' => $publicationRef,
            'plateforme' => strtolower(trim((string) ($_GET['plateforme'] ?? ''))),
            'client_id' => (int)($_GET['client_id']??0),
            'connection_id' => (int)($_GET['connection_id']??0),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];

        if (($this->isPost()) && $this->can('reporting.manage')) {
            $this->handleCreate();
        }

        $exportFormat = trim((string) ($_GET['export'] ?? ''));
        $reportType = trim((string) ($_GET['report_type'] ?? 'individual'));
        if (in_array($exportFormat, ['csv', 'pdf', 'excel'], true)) {
            $this->downloadReport($filters, $reportType, $exportFormat);
        }

        $rows = $this->reportingMetricModel->getMetrics($filters, 800);
        $growthSeries = $this->reportingMetricModel->getGrowthSeries($filters);
        $impactStats = $this->reportingMetricModel->getImpactStats($filters);
        $publicationReport = $this->reportingMetricModel->getPublicationAggregateReport($filters);
        $monthlyReport = $this->reportingMetricModel->getMonthlyAggregateReport($filters);
        $analysisDashboard = $this->reportingMetricModel->getDashboardAnalysis($filters);
        $connections=(new SocialPublishingModel())->dashboardData(WorkingMonth::resolve())['connections']??[];

        $this->render('reporting-metric/index', [
            'pageTitle' => 'Statistiques & rapports',
            'canManage' => $this->can('reporting.manage'),
            'campaignOptions' => $this->reportingMetricModel->getCampaignOptions(),
            'publicationOptions' => $this->reportingMetricModel->getPublicationOptions($filters['campagne_id']),
            'socialPublicationOptions' => $this->reportingMetricModel->getSocialPublicationOptions($filters['campagne_id']),
            'platformOptions' => $this->reportingMetricModel->getPlatformOptions(),
            'clientOptions' => $this->reportingMetricModel->getClientOptions(),
            'pageOptions' => $this->reportingMetricModel->getPageOptions($filters['client_id']),
            'networkConfig' => $networkConfig,
            'defaultNetwork' => $defaultNetwork,
            'filters' => $filters,
            'rows' => $rows,
            'growthSeries' => $growthSeries,
            'impactStats' => $impactStats,
            'publicationReport' => $publicationReport,
            'monthlyReport' => $monthlyReport,
            'analysisDashboard' => $analysisDashboard,
            'socialConnections' => $connections,
        ]);
    }

    public function collectSocialMetrics(): void {
        if(!$this->isPost()){http_response_code(405);return;}
        $this->requirePermission('publishing.manage');
        try{$result=(new SocialMetricsCollectorService())->collectPublished(TenantGuard::tenantId(),(int)$this->currentUser()['id'],100);$this->flash($result['collected']?'success':'error',$result['collected'].' collecte(s) actualisée(s), '.$result['failed'].' échec(s).');}
        catch(Throwable $e){$this->flash('error',$e->getMessage());}
        $this->redirect('/reporting-metric');
    }

    public function importSocialHistory(): void {
        if(!$this->isPost()){http_response_code(405);return;}
        $this->requirePermission('publishing.manage');
        try{$result=(new SocialMetricsCollectorService())->importHistory((int)($_POST['connection_id']??0),TenantGuard::tenantId(),(int)$this->currentUser()['id'],(string)($_POST['from']??''),(string)($_POST['to']??''),250);$this->flash('success',$result['imported'].' publication(s) importée(s), '.$result['collected'].' KPI collectés.');}
        catch(Throwable $e){$this->flash('error',$e->getMessage());}
        $this->redirect('/reporting-metric');
    }

    public function create() {
        $this->redirect('/reporting-metric');
    }

    public function show($id) {
        $this->redirect('/reporting-metric');
    }

    public function edit($id) {
        $this->redirect('/reporting-metric');
    }

    public function delete($id) {
        $this->requirePermission('reporting.manage');
        $this->reportingMetricModel->deleteMetric((int) $id);
        $this->flash('success', 'Collecte supprimee.');
        $this->redirect('/reporting-metric');
    }

    private function handleCreate() {
        $this->requirePermission('reporting.manage');

        $input = [
            'campagne_id' => (int) ($_POST['campagne_id'] ?? 0),
            'contenu_id' => trim((string) ($_POST['contenu_id'] ?? '')),
            'plateforme' => strtolower(trim((string) ($_POST['plateforme'] ?? ''))),
            'date_collecte' => trim((string) ($_POST['date_collecte'] ?? '')),
            'kpi_values' => is_array($_POST['kpi_values'] ?? null) ? $_POST['kpi_values'] : [],
            'url_publication' => trim((string) ($_POST['url_publication'] ?? '')),
        ];

        if ($input['campagne_id'] <= 0 && (int)$input['contenu_id'] <= 0) {
            $this->flash('error', 'Selectionne une campagne ou une publication.');
            $this->redirect('/reporting-metric');
        }

        if ($input['plateforme'] === '') {
            $this->flash('error', 'Renseigne le reseau de collecte.');
            $this->redirect('/reporting-metric');
        }

        if ($input['date_collecte'] === '') {
            $this->flash('error', 'Renseigne la date de collecte.');
            $this->redirect('/reporting-metric');
        }

        $payload = $this->reportingMetricModel->normalizePayload($input);
        $this->reportingMetricModel->createMetric($payload);
        $this->flash('success', 'Collecte manuelle enregistree.');
        $this->redirect('/reporting-metric');
    }

    private function downloadReport(array $filters, $reportType, $format) {
        $reportType = in_array($reportType, ['individual', 'publication', 'monthly', 'flat', 'client'], true) ? $reportType : 'individual';

        if ($format === 'pdf' && $reportType === 'client') {
            $analysis = $this->reportingMetricModel->getDashboardAnalysis($filters);
            PdfExportService::outputKpiClientPdf('Rapport client performances KPI', $analysis, 'rapport-client-kpi.pdf');
            return;
        }

        if ($format === 'excel' || $reportType === 'flat') {
            $rows = $this->reportingMetricModel->getExcelFlatRows($filters);
            $title = 'Export analyse KPI (flat)';
            $fileStem = 'analyse-kpi-flat';
            $columns = ['Date_collecte','Date_publication','Client','Page','Publication_ID','Reseau','KPI','Valeur','Growth_rate','Daily_rate'];
        } elseif ($reportType === 'publication') {
            $rows = $this->reportingMetricModel->getPublicationAggregateReport($filters);
            $title = 'Rapport global par publication';
            $fileStem = 'rapport-publication';
            $columns = ['client_nom', 'page_nom', 'publication', 'collectes', 'impressions', 'couverture', 'vues', 'likes', 'commentaires', 'partages', 'clics', 'ctr_moyen', 'engagement_rate_moyen', 'url_publication'];
        } elseif ($reportType === 'monthly') {
            $rows = $this->reportingMetricModel->getMonthlyAggregateReport($filters);
            $title = 'Rapport global mensuel';
            $fileStem = 'rapport-mensuel';
            $columns = ['mois', 'client_nom', 'page_nom', 'plateforme', 'publications', 'collectes', 'impressions_total', 'impressions_moyenne', 'couverture_total', 'couverture_moyenne', 'vues_total', 'vues_moyenne', 'clics_total', 'clics_moyenne', 'ctr_moyen', 'engagement_rate_moyen'];
        } else {
            $rows = $this->reportingMetricModel->getIndividualReportRows($filters);
            $title = 'Rapport individuel par publication';
            $fileStem = 'rapport-individuel';
            $columns = ['id','client_nom','page_nom','campagne_nom','publication_titre','plateforme','date_publication','date_collecte','periode_analysee','impressions','couverture','vues','likes','commentaires','partages','clics','ctr','engagement_rate','score_global','growth_rate','daily_rate','url_publication'];
        }

        if ($format === 'pdf') {
            PdfExportService::outputTablePdf($title, $rows, $columns, $fileStem . '.pdf');
            return;
        }

        $fileExtension = $format === 'excel' ? 'csv' : 'csv';
        $mimeType = $format === 'excel'
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileStem . '.' . $fileExtension . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        fputcsv($out, $columns, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = (string) ($row[$column] ?? '');
            }
            fputcsv($out, $line, ';');
        }

        fclose($out);
        exit;
    }
}
