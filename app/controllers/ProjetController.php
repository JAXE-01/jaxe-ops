<?php
class ProjetController extends CrudController {
    protected $moduleKey = 'projet';
    private $settingsModel;

    public function __construct() {
        parent::__construct();
        $this->model = new ProjectModel();
        $this->settingsModel = new SettingsModel();
    }

    public function create() {
        $this->requirePermission('projects.manage');
        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);
        $options = $this->filterProjectRoleOptions($this->loadOptions());
        $this->module['formFields']['type_projet']['options'] = $this->buildTypeOptions($this->settingsModel->getProjectTypeOptions());
        $defaultMode = !empty($options['abonnement_id']) ? 'abonnement' : 'custom';
        $defaultAssignments = $this->model->getDefaultProjectValues();

        if ($this->isPost()) {
            try {
                if(isset($_POST['social_pages_present'])) $this->requirePermission('publishing.manage');
                $projectId = $this->model->create($_POST);
                PipelineService::syncProject($projectId);
                $this->flash('success', 'Projet cree et pipeline initialise.');
                $this->redirectToTarget($returnTo, '/' . $this->module['route']);
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
            }
        }

        $this->render('crud/form', [
            'pageTitle' => 'Nouveau ' . $this->module['label'],
            'module' => $this->module,
            'record' => array_merge($defaultAssignments, ['configuration_mode' => $defaultMode], $_POST),
            'options' => $options,
            'returnTo' => $returnTo,
            'backLabel' => $this->buildBackLabel($returnTo, '/' . $this->module['route']),
            'formHint' => 'Choisis un abonnement existant pour precharger la cadence mensuelle, ou bascule en sur mesure pour une campagne marketing ou un besoin exceptionnel.'
        ]);
    }

    public function edit($id) {
        $this->requirePermission('projects.manage');
        $record = $this->model->getById($id);
        if (!$record) {
            $this->flash('error', 'Projet introuvable.');
            $this->redirect('/' . $this->module['route']);
        }

        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);
        $options = $this->filterProjectRoleOptions($this->loadOptions());
        $this->module['formFields']['type_projet']['options'] = $this->buildTypeOptions($this->settingsModel->getProjectTypeOptions());

        if ($this->isPost()) {
            try {
                if(isset($_POST['social_pages_present'])) $this->requirePermission('publishing.manage');
                $this->model->update($id, $_POST);
                PipelineService::syncProject($id);
                $this->flash('success', 'Projet mis a jour et pipeline synchronise.');
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
            'options' => $options,
            'returnTo' => $returnTo,
            'backLabel' => $this->buildBackLabel($returnTo, '/' . $this->module['route']),
            'formHint' => 'Le pipeline est regenere a partir de l abonnement choisi ou des reglages sur mesure.'
        ]);
    }

    public function regenerate($id) {
        $this->requirePermission('projects.manage');
        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);
        PipelineService::syncProject($id);
        $this->flash('success', 'Pipeline regenere.');
        $this->redirectToTarget($returnTo, '/' . $this->module['route']);
    }

    public function extend($id) {
        $this->requirePermission('projects.manage');
        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);

        try {
            $this->model->extendOneMonth($id);
            PipelineService::syncProject($id);
            $this->flash('success', 'Projet prolonge d un mois et pipeline resynchronise.');
        } catch (Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirectToTarget($returnTo, '/' . $this->module['route']);
    }

    private function filterProjectRoleOptions(array $options) {
        foreach ($this->settingsModel->getProjectRoleFieldMap() as $field => $roles) {
            $options[$field] = $this->settingsModel->getUserOptionsByRoles($roles);
        }

        return $options;
    }

    private function buildTypeOptions(array $types) {
        return array_combine($types, $types);
    }
}
