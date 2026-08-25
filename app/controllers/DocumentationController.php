<?php
class DocumentationController extends Controller {
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
            'client_id' => trim((string) ($_GET['client_id'] ?? '')),
            'from'      => trim((string) ($_GET['from'] ?? '')),
            'to'        => trim((string) ($_GET['to'] ?? '')),
            'period'    => $preset,
        ];

        if ($preset !== '' && $filters['from'] === '' && $filters['to'] === '') {
            $range = $this->resolvePeriodPreset($preset);
            if ($range) {
                $filters['from'] = $range['from'];
                $filters['to']   = $range['to'];
            }
        }

        if ($this->isPost()) {
            try {
                $stored = $this->storeUploadedDocument('document_file');
                $this->calendrierModel->createDocumentationFile([
                    'client_id'    => (int) ($_POST['client_id'] ?? 0),
                    'titre'        => (string) ($_POST['titre'] ?? 'Document client'),
                    'categorie'    => (string) ($_POST['categorie'] ?? 'General'),
                    'date_document'=> (string) ($_POST['date_document'] ?? ''),
                    'fichier_path' => $stored['path'],
                    'fichier_nom'  => $stored['name'],
                    'created_by'   => (int) (($this->currentUser()['id'] ?? 0)),
                ]);
                $this->flash('success', 'Document ajoute avec succes.');
                $this->redirect('/documentation');
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
            }
        }

        $total = $this->calendrierModel->countDocumentationFiles($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));

        $this->render('documentation/index', [
            'pageTitle'  => 'Documentation',
            'clients'    => $this->calendrierModel->getAllClientsSimple(),
            'filters'    => $filters,
            'documents'  => $this->calendrierModel->getDocumentationFiles($filters, $page, $perPage),
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => $totalPages,
        ]);
    }

    private function storeUploadedDocument($field) {
        if (!isset($_FILES[$field])) {
            throw new RuntimeException('Aucun fichier televerse.');
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Echec du televersement du document.');
        }

        $originalName = (string) ($file['name'] ?? 'document');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'svg', 'zip', 'txt'];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Format de document non autorise.');
        }

        $targetDirectory = UPLOADS_PATH . '/documentation/' . date('Y') . '/' . date('m');
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Impossible de creer le dossier de documentation.');
        }

        $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBase = trim((string) $safeBase, '-');
        $safeBase = $safeBase !== '' ? $safeBase : 'document';
        $fileName = uniqid($safeBase . '-', true) . ($extension !== '' ? '.' . $extension : '');
        $targetPath = $targetDirectory . '/' . $fileName;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Le televersement du document a echoue.');
        }

        return [
            'name' => $originalName,
            'path' => 'documentation/' . date('Y') . '/' . date('m') . '/' . $fileName,
        ];
    }
}
