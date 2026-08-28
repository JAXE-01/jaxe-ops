<?php
class RealisationController extends Controller {
    private $calendrierModel;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('calendar.view');
        $this->calendrierModel = new CalendrierModel();
    }

    public function index() {
        $perPage = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $preset = trim((string) ($_GET['period'] ?? 'current_month'));

        $filters = [
            'client_id'          => trim((string) ($_GET['client_id'] ?? '')),
            'from'               => trim((string) ($_GET['from'] ?? '')),
            'to'                 => trim((string) ($_GET['to'] ?? '')),
            'period'             => $preset,
            'validation_interne' => trim((string) ($_GET['validation_interne'] ?? '')),
            'validation_client'  => trim((string) ($_GET['validation_client'] ?? '')),
            'sort'               => trim((string) ($_GET['sort'] ?? 'date_desc')),
        ];

        if(!isset($_GET['period']) && $filters['from']==='' && $filters['to']===''){
            $workDate=new DateTimeImmutable(WorkingMonth::resolve().'-01');
            $filters['from']=$workDate->format('Y-m-d');$filters['to']=$workDate->format('Y-m-t');$filters['period']='';
        }
        if ($preset !== '' && $filters['from'] === '' && $filters['to'] === '') {
            $range = $this->resolvePeriodPreset($preset);
            if ($range) {
                $filters['from'] = $range['from'];
                $filters['to']   = $range['to'];
            }
        }

        if ($this->isPost()) {
            $selectedIds = array_values(array_filter(array_map('intval', (array) ($_POST['deliverable_ids'] ?? []))));
            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'generate_links') {
                $links = $this->calendrierModel->generatePublicLinksBySelectedDeliverables(
                    $selectedIds,
                    (int) ($this->currentUser()['id'] ?? 0),
                    (int) ($_POST['expiry_days'] ?? 45)
                );
                $_SESSION['realisation_generated_links'] = $links;
                $this->flash('success', count($links) . ' lien(s) genere(s).');
                $this->redirect('/realisation');
            }

            if ($action === 'download_bundle') {
                $this->downloadBundle($selectedIds);
                return;
            }
        }

        $total = $this->calendrierModel->countRealisations($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));

        $this->render('realisation/index', [
            'pageTitle'      => 'Realisations',
            'clients'        => $this->calendrierModel->getAllClientsSimple(),
            'filters'        => $filters,
            'items'          => $this->calendrierModel->getRealisations($filters, $page, $perPage),
            'page'           => $page,
            'perPage'        => $perPage,
            'total'          => $total,
            'totalPages'     => $totalPages,
            'generatedLinks' => (array) ($_SESSION['realisation_generated_links'] ?? []),
            'currentUser'    => $this->currentUser(),
            'canOpenValidationTaskLinks' => $this->can('calendar.view'),
        ]);
        unset($_SESSION['realisation_generated_links']);
    }

    private function downloadBundle(array $deliverableIds) {
        if (!class_exists('ZipArchive')) {
            $this->flash('error', 'L extension ZipArchive n est pas active sur ce serveur.');
            $this->redirect('/realisation');
        }

        if (empty($deliverableIds)) {
            $this->flash('error', 'Selectionne au moins un contenu pour telecharger.');
            $this->redirect('/realisation');
        }

        $files = $this->calendrierModel->getDeliverableFilesForBundle($deliverableIds);
        if (empty($files)) {
            $this->flash('error', 'Aucun fichier trouve pour la selection.');
            $this->redirect('/realisation');
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'jaxe-real-');
        if ($tmpZip === false) {
            throw new RuntimeException('Impossible de preparer le telechargement.');
        }

        $zipPath = $tmpZip . '.zip';
        @rename($tmpZip, $zipPath);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de creer l archive ZIP.');
        }

        foreach ($files as $file) {
            $relative = (string) ($file['path'] ?? '');
            if ($relative === '') {
                continue;
            }
            $absolute = UPLOADS_PATH . '/' . ltrim($relative, '/');
            if (!is_file($absolute)) {
                continue;
            }

            $folder = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($file['deliverable_title'] ?? 'contenu'));
            $entryName = $folder . '/' . basename($absolute);
            $zip->addFile($absolute, $entryName);
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="realisations-' . date('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }
}
