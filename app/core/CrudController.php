<?php
abstract class CrudController extends Controller {
    protected $moduleKey;
    protected $module;
    protected $model;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->module = ModuleRegistry::get($this->moduleKey);
        if ($this->module === null) {
            throw new RuntimeException('Module introuvable: ' . $this->moduleKey);
        }
        $this->model = new CrudModel($this->module);
    }

    public function index() {
        $this->requireModulePermission('view');
        $allRows = $this->model->getAll();
        $options = $this->loadOptions();
        $defaultUserStatus = '';
        if (($this->module['route'] ?? '') === 'user' && !array_key_exists('statut', $_GET)) {
            $defaultUserStatus = 'Actif';
        }
        $filters = [
            'client_id' => trim((string) ($_GET['client_id'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'role' => trim((string) ($_GET['role'] ?? '')),
            'secondary_role' => trim((string) ($_GET['secondary_role'] ?? '')),
            'statut' => trim((string) ($_GET['statut'] ?? $defaultUserStatus)),
        ];
        $requiresClientSelection = !empty($this->module['requireClientSelection']);
        $clientOptions = $this->model->getClientFilterOptions($allRows);
        $showClientPicker = $requiresClientSelection && $filters['client_id'] === '';
        $rows = $showClientPicker ? [] : $this->model->filterRows($allRows, $filters, $options);

        $this->render('crud/index', [
            'pageTitle' => $this->module['label'],
            'module' => $this->module,
            'rows' => $rows,
            'groupedRows' => $this->model->getClientGroups($rows),
            'options' => $options,
            'filters' => $filters,
            'clientOptions' => $clientOptions,
            'showClientPicker' => $showClientPicker,
            'requiresClientSelection' => $requiresClientSelection
        ]);
    }

    public function show($id) {
        $this->requireModulePermission('view');
        $record = $this->model->getById($id);
        if (!$record) {
            $this->flash('error', 'Element introuvable.');
            $this->redirect('/' . $this->module['route']);
        }

        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);

        $this->render('crud/show', [
            'pageTitle' => $this->module['label'],
            'module' => $this->module,
            'record' => $record,
            'options' => $this->loadOptions(),
            'returnTo' => $returnTo,
            'backLabel' => $this->buildBackLabel($returnTo, '/' . $this->module['route'])
        ]);
    }

    public function create() {
        $this->requireModulePermission('manage');
        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);
        $prefill = $this->extractPrefillData();

        if ($this->isPost()) {
            try {
                $this->model->create($this->collectRequestData());
                $this->flash('success', 'Enregistrement cree avec succes.');
                $this->redirectToTarget($returnTo, '/' . $this->module['route']);
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
            }
        }

        $this->render('crud/form', [
            'pageTitle' => 'Nouveau ' . $this->module['label'],
            'module' => $this->module,
            'record' => array_merge($prefill, $_POST),
            'options' => $this->loadOptions(),
            'returnTo' => $returnTo,
            'backLabel' => $this->buildBackLabel($returnTo, '/' . $this->module['route'])
        ]);
    }

    public function edit($id) {
        $this->requireModulePermission('manage');
        $record = $this->model->getById($id);
        if (!$record) {
            $this->flash('error', 'Element introuvable.');
            $this->redirect('/' . $this->module['route']);
        }

        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);

        if ($this->isPost()) {
            try {
                $this->model->update($id, $this->collectRequestData($record));
                $this->flash('success', 'Enregistrement mis a jour.');
                $this->redirectToTarget($returnTo, '/' . $this->module['route']);
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
                $record = array_merge($record, $_POST);
            }
        }

        $this->render('crud/form', [
            'pageTitle' => 'Modifier ' . $this->module['label'],
            'module' => $this->module,
            'record' => $record,
            'options' => $this->loadOptions(),
            'returnTo' => $returnTo,
            'backLabel' => $this->buildBackLabel($returnTo, '/' . $this->module['route'])
        ]);
    }

    public function delete($id) {
        $this->requireModulePermission('manage');
        $record = $this->model->getById($id);
        if ($record) {
            $this->moveRecordFilesToTrash($record, (int) $id);
        }
        $this->model->delete($id);
        $this->flash('success', 'Enregistrement supprime.');
        $this->redirect('/' . $this->module['route']);
    }

    public function bulk() {
        $this->requireModulePermission('manage');
        if (!$this->isPost()) {
            $this->redirect('/' . $this->module['route']);
        }

        if (($this->module['route'] ?? '') !== 'user') {
            $this->flash('error', 'Actions de masse non disponibles sur ce module.');
            $this->redirect('/' . $this->module['route']);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['selected_ids'] ?? [])))));
        if (empty($ids)) {
            $this->flash('error', 'Selectionne au moins un utilisateur.');
            $this->redirect('/' . $this->module['route']);
        }

        $action = trim((string) ($_POST['bulk_action'] ?? ''));
        $currentUserId = (int) ($this->currentUser()['id'] ?? 0);

        if ($action === 'delete') {
            $deleted = $this->model->bulkDeleteByIds($ids, $this->module['primaryKey'], $currentUserId);
            $this->flash('success', $deleted . ' utilisateur(s) supprime(s).');
            $this->redirect('/' . $this->module['route']);
        }

        if ($action === 'set_status') {
            $status = trim((string) ($_POST['bulk_status'] ?? ''));
            if (!in_array($status, ['Actif', 'Inactif'], true)) {
                $this->flash('error', 'Statut invalide pour l action de masse.');
                $this->redirect('/' . $this->module['route']);
            }

            $updated = $this->model->bulkUpdateByIds($ids, $this->module['primaryKey'], ['statut' => $status], $currentUserId);
            $this->flash('success', $updated . ' utilisateur(s) mis a jour.');
            $this->redirect('/' . $this->module['route']);
        }

        if ($action === 'set_role') {
            $role = trim((string) ($_POST['bulk_role'] ?? ''));
            $allowedRoles = array_keys(ModuleRegistry::roleOptions());
            if (!in_array($role, $allowedRoles, true)) {
                $this->flash('error', 'Role invalide pour l action de masse.');
                $this->redirect('/' . $this->module['route']);
            }

            $updated = $this->model->bulkUpdateByIds($ids, $this->module['primaryKey'], ['role' => $role], $currentUserId);
            $this->flash('success', $updated . ' utilisateur(s) mis a jour.');
            $this->redirect('/' . $this->module['route']);
        }

        $this->flash('error', 'Action de masse non prise en charge.');
        $this->redirect('/' . $this->module['route']);
    }

    protected function requireModulePermission($action = 'view') {
        $permissionKey = PermissionModel::resolveModulePermission($this->moduleKey, $action);
        if ($permissionKey !== null) {
            $this->requirePermission($permissionKey);
        }
    }

    protected function loadOptions() {
        $options = [];
        foreach ($this->module['formFields'] as $field => $meta) {
            if (($meta['type'] ?? null) === 'relation') {
                $options[$field] = $this->model->getRelationOptions($meta['module']);
            }
        }
        return $options;
    }

    protected function extractPrefillData() {
        $prefill = [];
        foreach ($this->module['formFields'] as $field => $meta) {
            if (isset($_GET[$field])) {
                $prefill[$field] = $_GET[$field];
            }
        }

        return $prefill;
    }

    protected function collectRequestData(array $currentRecord = []) {
        $payload = $_POST;

        foreach ($this->module['formFields'] as $field => $meta) {
            $type = $meta['type'] ?? 'text';

            if ($type === 'checkbox' && !isset($payload[$field])) {
                $payload[$field] = 0;
            }

            if (in_array($type, ['file', 'files'], true)) {
                $uploaded = $this->processUploadedFiles($field, $meta, $currentRecord[$field] ?? null);
                if ($uploaded !== null) {
                    $payload[$field] = $uploaded;
                }
            }
        }

        return $payload;
    }

    protected function resolveReturnTo($fallbackPath) {
        $candidate = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
        if ($candidate === '') {
            return route_url($fallbackPath);
        }

        if (strpos($candidate, '/') === 0) {
            return $candidate;
        }

        return route_url($fallbackPath);
    }

    protected function redirectToTarget($target, $fallbackPath) {
        $location = $target ?: route_url($fallbackPath);
        header('Location: ' . $location);
        exit;
    }

    protected function buildBackLabel($returnTo, $fallbackPath) {
        if ($returnTo === route_url($fallbackPath)) {
            return 'Retour a la liste';
        }

        if (strpos($returnTo, '/calendrier/projet/') !== false) {
            return 'Retour au projet';
        }

        if (strpos($returnTo, '/calendrier/client/') !== false) {
            return 'Retour au client';
        }

        if (strpos($returnTo, '/calendrier') !== false) {
            return 'Retour au pilotage';
        }

        return 'Retour';
    }

    private function processUploadedFiles($field, array $meta, $existingValue) {
        if (!isset($_FILES[$field])) {
            return null;
        }

        $fileBag = $_FILES[$field];
        $isMultiple = ($meta['type'] ?? '') === 'files';
        $existingFiles = $this->decodeFileMetadata($existingValue);
        $newFiles = [];

        if ($isMultiple) {
            $names = $fileBag['name'] ?? [];
            foreach ($names as $index => $name) {
                $error = $fileBag['error'][$index] ?? UPLOAD_ERR_NO_FILE;
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $newFiles[] = $this->storeUploadedFile([
                    'name' => $name,
                    'tmp_name' => $fileBag['tmp_name'][$index] ?? '',
                    'error' => $error,
                    'size' => $fileBag['size'][$index] ?? 0,
                    'type' => $fileBag['type'][$index] ?? ''
                ], $meta);
            }
        } else {
            if (($fileBag['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return null;
            }

            $newFiles[] = $this->storeUploadedFile($fileBag, $meta);
        }

        return array_values(array_merge($existingFiles, $newFiles));
    }

    private function storeUploadedFile(array $file, array $meta) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Impossible de televerser le fichier.');
        }

        $originalName = (string) ($file['name'] ?? 'fichier');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $meta['extensions'] ?? [];
        if (!empty($allowed) && !in_array($extension, $allowed, true)) {
            throw new RuntimeException('Format de fichier non autorise pour ' . $originalName . '.');
        }

        $targetDirectory = UPLOADS_PATH . '/' . $this->module['route'] . '/' . date('Y') . '/' . date('m');
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Impossible de creer le dossier de televersement.');
        }

        $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBaseName = trim((string) $safeBaseName, '-');
        $safeBaseName = $safeBaseName !== '' ? $safeBaseName : 'fichier';
        $fileName = uniqid($safeBaseName . '-', true) . ($extension !== '' ? '.' . $extension : '');
        $targetPath = $targetDirectory . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Le televersement du fichier a echoue.');
        }

        $relativePath = $this->module['route'] . '/' . date('Y') . '/' . date('m') . '/' . $fileName;

        return [
            'name' => $originalName,
            'path' => $relativePath,
            'size' => (int) ($file['size'] ?? 0),
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }

    private function decodeFileMetadata($value) {
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

    private function moveRecordFilesToTrash(array $record, $recordId) {
        foreach ($this->module['formFields'] as $field => $meta) {
            $type = (string) ($meta['type'] ?? '');
            if (!in_array($type, ['file', 'files'], true)) {
                continue;
            }

            $files = $this->decodeFileMetadata($record[$field] ?? null);
            foreach ($files as $fileMeta) {
                if (!is_array($fileMeta)) {
                    continue;
                }

                FileTrashService::trashByRelativePath((string) ($fileMeta['path'] ?? ''), [
                    'original_name' => (string) ($fileMeta['name'] ?? ''),
                    'size_bytes' => (int) ($fileMeta['size'] ?? 0),
                    'module_key' => (string) ($this->module['route'] ?? ''),
                    'source_table' => (string) ($this->module['table'] ?? ''),
                    'source_record_id' => (int) $recordId,
                    'deleted_by' => (int) (($this->currentUser()['id'] ?? 0)),
                ]);
            }
        }
    }
}
