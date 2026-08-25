<?php
class HomeController extends Controller {
    private $dashboardModel;

    public function __construct() {
        parent::__construct();
        $this->dashboardModel = new DashboardModel();
    }

    public function index() {
        if ($this->currentUser() === null) {
            $this->render('public/home', ['pageTitle' => 'Strax — Pilotez vos contenus']);
            return;
        }
        $this->requirePermission('dashboard.view');

        $currentUser = $this->currentUser();
        $isScopedDashboard = UserScope::isScopedOperationalUser($currentUser);

        $stats = [];
        if (!$isScopedDashboard) {
            foreach (ModuleRegistry::navigable() as $key => $module) {
                $permissionKey = PermissionModel::resolveModulePermission($key, 'view');
                if ($permissionKey !== null && !$this->can($permissionKey)) {
                    continue;
                }
                $model = new CrudModel($module);
                $stats[] = [
                    'key' => $key,
                    'label' => $module['label'],
                    'route' => $module['route'],
                    'count' => $model->countAll()
                ];
            }
        }

        $this->render('home/index', [
            'pageTitle' => 'Tableau de bord',
            'stats' => $stats,
            'isScopedDashboard' => $isScopedDashboard,
            'overview' => $this->dashboardModel->getOverviewStats($currentUser),
            'projectsByType' => $this->dashboardModel->getProjectsByType($currentUser),
            'currentMonthPlans' => $this->dashboardModel->getCurrentMonthPlans($currentUser),
            'upcomingDeadlines' => $this->dashboardModel->getUpcomingDeadlines($currentUser),
            'delayedTasks' => $this->dashboardModel->getDelayedTasks($currentUser),
            'philsFocus' => $this->dashboardModel->getPhilsFocus($currentUser)
        ]);
    }
}
