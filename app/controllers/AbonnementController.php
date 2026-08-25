<?php
class AbonnementController extends CrudController {
    protected $moduleKey = 'abonnement';
    private $settingsModel;

    public function __construct() {
        parent::__construct();
        $this->settingsModel = new SettingsModel();
    }

    public function create() {
        $this->requirePermission('subscriptions.manage');
        $types = $this->settingsModel->getSubscriptionTypeOptions();
        $this->module['formFields']['type_projet']['options'] = array_combine($types, $types);
        parent::create();
    }

    public function edit($id) {
        $this->requirePermission('subscriptions.manage');
        $types = $this->settingsModel->getSubscriptionTypeOptions();
        $this->module['formFields']['type_projet']['options'] = array_combine($types, $types);
        parent::edit($id);
    }
}