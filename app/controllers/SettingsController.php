<?php
class SettingsController extends Controller {
    private $settingsModel;
    private $permissionModel;
    private $dolibarrModel;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('settings.view');
        $this->settingsModel = new SettingsModel();
        $this->permissionModel = new PermissionModel();
        $this->dolibarrModel = new DolibarrModel();
    }

    public function index() {
        $availableSections = ['defaults', 'types', 'kpi', 'appearance', 'wcag', 'permissions', 'overrides', 'integrations', 'apis'];
        $activeSection = trim((string) ($_GET['section'] ?? $_POST['section'] ?? ''));
        if (!in_array($activeSection, $availableSections, true)) {
            $activeSection = '';
        }

        $subscriptionModel = new CrudModel(ModuleRegistry::get('abonnement'));
        $userModel = new CrudModel(ModuleRegistry::get('user'));
        $reportingModel = new CrudModel(ModuleRegistry::get('reporting-metric'));
        $allUserOptions = $userModel->getRelationOptions('user');
        $projectDefaults = $this->settingsModel->getProjectDefaults();
        $roleMap = $this->settingsModel->getProjectRoleFieldMap();
        $defaultRoleOptions = [];
        foreach ($roleMap as $field => $roles) {
            $defaultRoleOptions[$field] = $this->settingsModel->getUserOptionsByRoles($roles);
        }
        $projectTypeOptions = $this->settingsModel->getProjectTypeOptions();
        $subscriptionTypeOptions = $this->settingsModel->getSubscriptionTypeOptions();
        $contentObjectiveOptions = $this->settingsModel->getContentObjectiveOptions();
        $workflowRulesConfig = $this->settingsModel->getWorkflowRulesConfig();
        $kpiNetworksConfig = $this->settingsModel->getKpiNetworksConfig();
        $kpiNetworksConfigJson = json_encode($kpiNetworksConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $brandingConfig = $this->settingsModel->getBrandingConfig();
        $calendarColorScheme = $this->settingsModel->getCalendarColorScheme();
        $calendarColorDefaults = $this->settingsModel->getDefaultCalendarColorScheme();
        $permissionGroups = $this->permissionModel->getGroupedDefinitions();
        $rolePermissionMatrix = $this->permissionModel->getRolePermissionMatrix();
        $selectedOverrideUserId = (int) ($_GET['override_user_id'] ?? $_POST['override_user_id'] ?? 0);
        if ($selectedOverrideUserId <= 0 && !empty($allUserOptions)) {
            $selectedOverrideUserId = (int) array_key_first($allUserOptions);
        }
        $selectedUserOverrides = $this->permissionModel->getUserOverrideMatrix($selectedOverrideUserId);
        $dolibarrConfig = $this->dolibarrModel->getConfig();
        $apiIntegrationsConfig = $this->settingsModel->getApiIntegrationsConfig();
        $apiLogFilters = [
            'status' => trim((string) ($_GET['api_status'] ?? $_POST['api_status'] ?? '')),
            'type' => trim((string) ($_GET['api_type'] ?? $_POST['api_type'] ?? '')),
            'from' => trim((string) ($_GET['api_from'] ?? $_POST['api_from'] ?? '')),
            'to' => trim((string) ($_GET['api_to'] ?? $_POST['api_to'] ?? '')),
        ];
        $apiPage = max(1, (int) ($_GET['api_page'] ?? 1));
        $apiPerPage = 40;
        $apiLogsTotal = 0;
        if ($this->can('integrations.view')) {
            $calendrierModel = new CalendrierModel();
            $apiLogsTotal = $calendrierModel->countGlobalApiEventLogs($apiLogFilters, $this->currentUser());
            $apiEventLogs = $calendrierModel->getGlobalApiEventLogs($apiPage, $apiPerPage, $apiLogFilters, $this->currentUser());
        } else {
            $apiEventLogs = [];
        }
        $apiLogsTotalPages = max(1, (int) ceil($apiLogsTotal / $apiPerPage));
        $dolibarrUserStats = $this->dolibarrModel->getUserMappingStats();
        $dolibarrProjectStats = $this->dolibarrModel->getProjectMappingStats();
        $dolibarrUserMappings = $this->dolibarrModel->getUserMappings();
        $dolibarrProjectMappings = $this->dolibarrModel->getProjectMappings();
        $canManageSettings = $this->can('settings.manage');
        $canViewIntegrations = $this->can('integrations.view');
        $canManageIntegrations = $this->can('integrations.manage');

        if ($this->isPost()) {
            try {
                $action = $_POST['settings_action'] ?? '';
                if ($action === 'reset_defaults') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->resetProjectDefaults();
                    $this->flash('success', 'Valeurs par defaut reinitialisees.');
                    $activeSection = 'defaults';
                } elseif ($action === 'save_project_types') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveProjectTypeOptions($_POST['project_type_options'] ?? '');
                    $this->flash('success', 'Types de projet mis a jour.');
                    $activeSection = 'types';
                } elseif ($action === 'save_subscription_types') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveSubscriptionTypeOptions($_POST['subscription_type_options'] ?? '');
                    $this->flash('success', 'Types d abonnement mis a jour.');
                    $activeSection = 'types';
                } elseif ($action === 'save_content_objectives') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveContentObjectiveOptions($_POST['content_objective_options'] ?? '');
                    $this->flash('success', 'Objectifs de contenu mis a jour.');
                    $activeSection = 'types';
                } elseif ($action === 'save_workflow_rules') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveWorkflowRulesConfig([
                        'require_second_montage_video' => !empty($_POST['require_second_montage_video']),
                    ]);
                    $this->flash('success', 'Regles workflow video mises a jour.');
                    $activeSection = 'types';
                } elseif ($action === 'save_kpi_networks_config') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveKpiNetworksConfigFromJson($_POST['kpi_networks_config_json'] ?? '');
                    $this->flash('success', 'Configuration des reseaux KPI mise a jour.');
                    $activeSection = 'kpi';
                } elseif ($action === 'save_branding') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveBrandingConfig([
                        'app_name' => $_POST['app_name'] ?? '',
                        'logo_url' => $_POST['logo_url'] ?? '',
                        'brand_caption' => $_POST['brand_caption'] ?? '',
                    ]);
                    $this->flash('success', 'Nom et logo de navigation mis a jour.');
                    $activeSection = 'defaults';
                } elseif ($action === 'save_calendar_colors') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveCalendarColorScheme($_POST['calendar_colors'] ?? []);
                    $this->flash('success', 'Palette du calendrier mise a jour.');
                    $activeSection = 'appearance';
                } elseif ($action === 'reset_calendar_colors') {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->resetCalendarColorScheme();
                    $this->flash('success', 'Palette du calendrier reinitialisee.');
                    $activeSection = 'appearance';
                } elseif ($action === 'save_role_permissions') {
                    $this->requirePermission('settings.manage');
                    $this->permissionModel->saveRolePermissionMatrix($_POST['role_permissions'] ?? []);
                    $this->flash('success', 'Matrice des permissions par role mise a jour.');
                    $activeSection = 'permissions';
                } elseif ($action === 'save_user_permissions') {
                    $this->requirePermission('settings.manage');
                    $selectedOverrideUserId = (int) ($_POST['override_user_id'] ?? 0);
                    $this->permissionModel->saveUserOverrideMatrix($selectedOverrideUserId, $_POST['user_permissions'] ?? []);
                    $this->flash('success', 'Surcharges utilisateur mises a jour.');
                    $this->redirect('/settings?section=overrides&override_user_id=' . $selectedOverrideUserId);
                } elseif ($action === 'save_dolibarr') {
                    $this->requirePermission('integrations.manage');
                    $dolibarrConfig = $this->extractDolibarrConfigFromRequest();
                    $this->dolibarrModel->saveConfig($dolibarrConfig);
                    $this->flash('success', 'Configuration Dolibarr enregistree.');
                    $activeSection = 'integrations';
                } elseif ($action === 'test_dolibarr') {
                    $this->requirePermission('integrations.manage');
                    $dolibarrConfig = $this->extractDolibarrConfigFromRequest();
                    $this->dolibarrModel->saveConfig($dolibarrConfig);
                    $result = (new DolibarrService($dolibarrConfig))->testConnection();
                    $this->flash('success', $result['message']);
                    $activeSection = 'integrations';
                } elseif ($action === 'sync_dolibarr_users') {
                    $this->requirePermission('integrations.manage');
                    $dolibarrConfig = $this->extractDolibarrConfigFromRequest();
                    $this->dolibarrModel->saveConfig($dolibarrConfig);
                    $count = $this->dolibarrModel->syncUsers((new DolibarrService($dolibarrConfig))->fetchUsers());
                    $this->flash('success', $count . ' utilisateur(s) Dolibarr synchronise(s) dans la table de mapping.');
                    $activeSection = 'integrations';
                } elseif ($action === 'sync_dolibarr_projects') {
                    $this->requirePermission('integrations.manage');
                    $dolibarrConfig = $this->extractDolibarrConfigFromRequest();
                    $this->dolibarrModel->saveConfig($dolibarrConfig);
                    $count = $this->dolibarrModel->syncProjects((new DolibarrService($dolibarrConfig))->fetchProjects());
                    $this->flash('success', $count . ' projet(s) Dolibarr synchronise(s) dans la table de mapping.');
                    $activeSection = 'integrations';
                } elseif ($action === 'save_api_integrations') {
                    $this->requirePermission('integrations.manage');
                    $apiIntegrationsConfig = $this->extractApiIntegrationsConfigFromRequest();
                    $this->settingsModel->saveApiIntegrationsConfig($apiIntegrationsConfig);
                    $this->flash('success', 'Configuration API enregistree.');
                    $activeSection = 'apis';
                } else {
                    $this->requirePermission('settings.manage');
                    $this->settingsModel->saveProjectDefaults([
                        'charge_compte_id' => $_POST['charge_compte_id'] ?? null,
                        'charge_clientele_id' => $_POST['charge_clientele_id'] ?? null,
                        'cm_id' => $_POST['cm_id'] ?? null,
                        'createur_id' => $_POST['createur_id'] ?? null,
                        'cadreur_id' => $_POST['cadreur_id'] ?? null,
                        'videaste_id' => $_POST['videaste_id'] ?? null
                    ]);
                    $this->flash('success', 'Parametres par defaut mis a jour.');
                    $activeSection = 'defaults';
                }
                $query = [];
                if ($activeSection !== '') {
                    $query['section'] = $activeSection;
                }
                if ($activeSection === 'overrides' && $selectedOverrideUserId > 0) {
                    $query['override_user_id'] = $selectedOverrideUserId;
                }
                $this->redirect('/settings' . (!empty($query) ? '?' . http_build_query($query) : ''));
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
                $projectDefaults = [
                    'charge_compte_id' => $_POST['charge_compte_id'] ?? null,
                    'charge_clientele_id' => $_POST['charge_clientele_id'] ?? null,
                    'cm_id' => $_POST['cm_id'] ?? null,
                    'createur_id' => $_POST['createur_id'] ?? null,
                    'cadreur_id' => $_POST['cadreur_id'] ?? null,
                    'videaste_id' => $_POST['videaste_id'] ?? null
                ];
                $projectTypeOptions = $this->splitTextareaOptions($_POST['project_type_options'] ?? '', $projectTypeOptions);
                $subscriptionTypeOptions = $this->splitTextareaOptions($_POST['subscription_type_options'] ?? '', $subscriptionTypeOptions);
                $contentObjectiveOptions = $this->splitTextareaOptions($_POST['content_objective_options'] ?? '', $contentObjectiveOptions);
                $workflowRulesConfig = [
                    'require_second_montage_video' => (($_POST['settings_action'] ?? '') === 'save_workflow_rules')
                        ? !empty($_POST['require_second_montage_video'])
                        : !empty($workflowRulesConfig['require_second_montage_video']),
                ];
                if (($_POST['settings_action'] ?? '') === 'save_kpi_networks_config') {
                    $kpiNetworksConfigJson = (string) ($_POST['kpi_networks_config_json'] ?? $kpiNetworksConfigJson);
                }
                $brandingConfig = [
                    'app_name' => trim((string) ($_POST['app_name'] ?? ($brandingConfig['app_name'] ?? 'Strax'))),
                    'logo_url' => trim((string) ($_POST['logo_url'] ?? ($brandingConfig['logo_url'] ?? ''))),
                    'brand_caption' => trim((string) ($_POST['brand_caption'] ?? ($brandingConfig['brand_caption'] ?? ''))),
                ];
                $calendarColorScheme = $this->settingsModel->getCalendarColorScheme();
                $postedPalette = $_POST['calendar_colors'] ?? [];
                if (is_array($postedPalette)) {
                    foreach ($calendarColorScheme as $stateKey => $palette) {
                        $inputState = is_array($postedPalette[$stateKey] ?? null) ? $postedPalette[$stateKey] : [];
                        $calendarColorScheme[$stateKey]['bg'] = strtoupper((string) ($inputState['bg'] ?? $palette['bg']));
                        $calendarColorScheme[$stateKey]['border'] = strtoupper((string) ($inputState['border'] ?? $palette['border']));
                        $calendarColorScheme[$stateKey]['text'] = strtoupper((string) ($inputState['text'] ?? $palette['text']));
                    }
                }
                $selectedOverrideUserId = (int) ($_POST['override_user_id'] ?? $selectedOverrideUserId);
                $selectedUserOverrides = $this->mergeUserOverrides($selectedUserOverrides, $_POST['user_permissions'] ?? []);
                $rolePermissionMatrix = $this->mergeRolePermissions($rolePermissionMatrix, $_POST['role_permissions'] ?? []);
                $dolibarrConfig = array_merge($dolibarrConfig, $this->extractDolibarrConfigFromRequest());
                $apiIntegrationsConfig = array_replace_recursive($apiIntegrationsConfig, $this->extractApiIntegrationsConfigFromRequest());
            }
        }

        $this->render('settings/index', [
            'pageTitle' => 'Parametres',
            'subscriptionCount' => $subscriptionModel->countAll(),
            'userCount' => $userModel->countAll(),
            'reportingMetricCount' => $reportingModel->countAll(),
            'userOptions' => $allUserOptions,
            'defaultRoleOptions' => $defaultRoleOptions,
            'projectDefaults' => $projectDefaults,
            'activeSection' => $activeSection,
            'projectTypeOptions' => $projectTypeOptions,
            'subscriptionTypeOptions' => $subscriptionTypeOptions,
            'contentObjectiveOptions' => $contentObjectiveOptions,
            'workflowRulesConfig' => $workflowRulesConfig,
            'kpiNetworksConfigJson' => $kpiNetworksConfigJson,
            'brandingConfig' => $brandingConfig,
            'calendarColorScheme' => $calendarColorScheme,
            'calendarColorDefaults' => $calendarColorDefaults,
            'permissionGroups' => $permissionGroups,
            'rolePermissionMatrix' => $rolePermissionMatrix,
            'selectedOverrideUserId' => $selectedOverrideUserId,
            'selectedUserOverrides' => $selectedUserOverrides,
            'canManageSettings' => $canManageSettings,
            'canViewIntegrations' => $canViewIntegrations,
            'canManageIntegrations' => $canManageIntegrations,
            'dolibarrConfig' => $dolibarrConfig,
            'apiIntegrationsConfig' => $apiIntegrationsConfig,
            'apiLogFilters' => $apiLogFilters,
            'apiEventLogs' => $apiEventLogs,
            'apiPage' => $apiPage,
            'apiPerPage' => $apiPerPage,
            'apiLogsTotal' => $apiLogsTotal,
            'apiLogsTotalPages' => $apiLogsTotalPages,
            'dolibarrUserStats' => $dolibarrUserStats,
            'dolibarrProjectStats' => $dolibarrProjectStats,
            'dolibarrUserMappings' => $dolibarrUserMappings,
            'dolibarrProjectMappings' => $dolibarrProjectMappings,
            'projectDefaultLabels' => [
                'charge_compte_id' => !empty($projectDefaults['charge_compte_id']) ? ($allUserOptions[(string) $projectDefaults['charge_compte_id']] ?? 'Non defini') : 'Non defini',
                'charge_clientele_id' => !empty($projectDefaults['charge_clientele_id']) ? ($allUserOptions[(string) $projectDefaults['charge_clientele_id']] ?? 'Non defini') : 'Non defini',
                'cm_id' => !empty($projectDefaults['cm_id']) ? ($allUserOptions[(string) $projectDefaults['cm_id']] ?? 'Non defini') : 'Non defini',
                'createur_id' => !empty($projectDefaults['createur_id']) ? ($allUserOptions[(string) $projectDefaults['createur_id']] ?? 'Non defini') : 'Non defini',
                'cadreur_id' => !empty($projectDefaults['cadreur_id']) ? ($allUserOptions[(string) $projectDefaults['cadreur_id']] ?? 'Non defini') : 'Non defini',
                'videaste_id' => !empty($projectDefaults['videaste_id']) ? ($allUserOptions[(string) $projectDefaults['videaste_id']] ?? 'Non defini') : 'Non defini'
            ]
        ]);
    }

    private function splitTextareaOptions($rawValue, array $fallback) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $rawValue);
        $cleaned = [];
        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value !== '' && !in_array($value, $cleaned, true)) {
                $cleaned[] = $value;
            }
        }

        return !empty($cleaned) ? $cleaned : $fallback;
    }

    private function extractDolibarrConfigFromRequest() {
        return [
            'enabled' => !empty($_POST['dolibarr_enabled']),
            'base_url' => $_POST['dolibarr_base_url'] ?? '',
            'api_key' => $_POST['dolibarr_api_key'] ?? '',
            'entity' => $_POST['dolibarr_entity'] ?? ''
        ];
    }

    private function extractApiIntegrationsConfigFromRequest() {
        $extract = static function ($prefix, array $keys) {
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = trim((string) ($_POST[$prefix . '_' . $key] ?? ''));
            }
            return $result;
        };

        return [
            'facebook' => $extract('facebook', ['mode', 'app_id', 'app_secret', 'access_token']),
            'linkedin' => $extract('linkedin', ['mode', 'client_id', 'client_secret', 'access_token']),
            'instagram' => $extract('instagram', ['mode', 'access_token']),
            'tiktok' => $extract('tiktok', ['mode', 'username', 'password']),
            'youtube' => $extract('youtube', ['mode', 'username', 'password']),
            'whatsapp' => $extract('whatsapp', ['mode', 'username', 'password']),
            'webhooks' => [
                'publication' => trim((string) ($_POST['webhooks_publication'] ?? '')),
                'kpi' => trim((string) ($_POST['webhooks_kpi'] ?? '')),
            ],
        ];
    }

    private function mergeUserOverrides(array $existing, array $submitted) {
        foreach ($existing as $permissionKey => $mode) {
            if (isset($submitted[$permissionKey]) && in_array($submitted[$permissionKey], ['inherit', 'allow', 'deny'], true)) {
                $existing[$permissionKey] = $submitted[$permissionKey];
            }
        }

        return $existing;
    }

    private function mergeRolePermissions(array $existing, array $submitted) {
        foreach ($existing as $role => $permissions) {
            foreach ($permissions as $permissionKey => $allowed) {
                $existing[$role][$permissionKey] = !empty($submitted[$role][$permissionKey]);
            }
        }

        return $existing;
    }
}