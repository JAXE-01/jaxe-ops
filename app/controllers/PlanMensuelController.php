<?php
class PlanMensuelController extends CrudController {
    protected $moduleKey = 'plan-mensuel';

    public function index() {
        $this->flash('error', 'Les plans mensuels sont geres automatiquement depuis le projet.');
        $this->redirect('/calendrier');
    }

    public function create() {
        $this->flash('error', 'Ajoute un mois via la prolongation du projet, pas via le CRUD technique.');
        $this->redirect('/calendrier');
    }

    public function show($id) {
        $this->redirectToPlanCalendar($id);
    }

    public function edit($id) {
        $this->redirectToPlanCalendar($id);
    }

    public function delete($id) {
        $this->flash('error', 'Supprime ou ajuste un mois via la configuration du projet.');
        $this->redirectToPlanCalendar($id);
    }

    private function redirectToPlanCalendar($id) {
        $record = $this->model->getById($id);
        if (!$record) {
            $this->redirect('/calendrier');
        }

        $url = route_url('/calendrier/projet/' . $record['projet_id']);
        if (!empty($record['periode_mois'])) {
            $url .= '?month=' . urlencode((string) $record['periode_mois']);
        }

        header('Location: ' . $url);
        exit;
    }
}
