<?php
class CalendrierGlobalController extends Controller {
    private $calendrierModel;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('calendar.view');
        $this->calendrierModel = new CalendrierModel();
    }

    public function index() {
        $currentUser = $this->currentUser();
        $settingsModel = new SettingsModel();
        $monthFilter = trim((string) ($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
            $monthFilter = date('Y-m');
        }

        $filters = [
            'client_id' => trim((string) ($_GET['client_id'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'completion_min' => trim((string) ($_GET['completion_min'] ?? '')),
            'completion_max' => trim((string) ($_GET['completion_max'] ?? '')),
            'month' => $monthFilter,
            'group_by_client' => trim((string) ($_GET['group_by_client'] ?? '0')),
        ];

        $this->render('calendrier/index', [
            'pageTitle' => 'Calendrier global',
            'projects' => $this->calendrierModel->getProjectsOverview($currentUser, $filters),
            'clients' => $this->calendrierModel->getAllClientsSimple(),
            'filters' => $filters,
            'globalStats' => $this->calendrierModel->getGlobalCalendarStats($currentUser),
            'globalMonthCalendar' => $this->calendrierModel->getGlobalPublicationCalendar($monthFilter, $filters['client_id'] ?? '', $currentUser),
            'calendarColorScheme' => $settingsModel->getCalendarColorScheme(),
            'openGlobalCalendar' => true,
            'showGlobalCalendar' => true,
            'showProjectsPilotage' => false,
        ]);
    }
}
