<?php
class LivrableItemController extends CrudController {
    protected $moduleKey = 'livrable-item';

    public function index() {
        $this->flash('error', 'Les livrables sont pilotes depuis le calendrier projet.');
        $this->redirect('/calendrier');
    }

    public function create() {
        $this->flash('error', 'Les livrables sont generes automatiquement depuis le projet et ses quotas.');
        $this->redirect('/calendrier');
    }

    public function show($id) {
        $this->redirectToProjectCalendar($id);
    }

    public function edit($id) {
        $this->redirectToProjectCalendar($id);
    }

    public function delete($id) {
        $this->flash('error', 'Ajuste les quotas du projet puis resynchronise le pipeline pour recalculer les livrables.');
        $this->redirectToProjectCalendar($id);
    }

    private function redirectToProjectCalendar($id) {
        $record = $this->model->getById($id);
        if (!$record) {
            $this->redirect('/calendrier');
        }

        $url = route_url('/calendrier/projet/' . $record['projet_id']);
        if (!empty($record['plan_mensuel_id'])) {
            $planModel = new CrudModel(ModuleRegistry::get('plan-mensuel'));
            $plan = $planModel->getById($record['plan_mensuel_id']);
            if (!empty($plan['periode_mois'])) {
                $url .= '?month=' . urlencode((string) $plan['periode_mois']);
            }
        }

        header('Location: ' . $url);
        exit;
    }
}
