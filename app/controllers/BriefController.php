<?php
class BriefController extends CrudController {
    protected $moduleKey = 'brief';

    public function __construct() {
        parent::__construct();
        $this->model = new BriefModel();
    }

    public function create() {
        $this->flash('error', 'Les briefs et scripts se gerent maintenant depuis le calendrier projet.');
        $this->redirect('/calendrier');
    }

    public function index() {
        $this->flash('error', 'Les briefs et scripts se pilotent depuis le calendrier projet.');
        $this->redirect('/calendrier');
    }

    public function show($id) {
        $this->redirectToCalendarWorkspace($id);
    }

    public function edit($id) {
        $this->redirectToCalendarWorkspace($id);
    }

    public function delete($id) {
        $this->flash('error', 'Supprime ou modifie le brief depuis l espace calendrier du livrable.');
        $this->redirectToCalendarWorkspace($id);
    }

    private function redirectToCalendarWorkspace($id) {
        $record = $this->model->getById($id);
        if (!$record || empty($record['livrable_item_id'])) {
            $this->redirect('/calendrier');
        }

        $workspace = (new CalendrierModel())->getDeliverableWorkspace((int) $record['livrable_item_id']);
        $taskType = ($record['nature_brief'] ?? '') === 'Script video' ? 'Script' : 'Brief';
        $task = $workspace['taskMap'][$taskType] ?? null;
        $target = !empty($task['id'])
            ? route_url('/calendrier/task/' . $task['id'])
            : route_url('/calendrier/projet/' . (int) ($workspace['projet_id'] ?? 0));

        header('Location: ' . $target);
        exit;
    }
}
