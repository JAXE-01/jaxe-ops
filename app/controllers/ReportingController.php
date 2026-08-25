<?php
class ReportingController extends CrudController {
    protected $moduleKey = 'reporting';

    public function index() {
        $this->flash('error', 'Le reporting CRUD est secondaire. Utilise le cockpit et les parametres admin pour la maintenance.');
        $this->redirect('/settings');
    }

    public function create() {
        $this->flash('error', 'La creation manuelle de reporting est desactivee dans le flux principal.');
        $this->redirect('/settings');
    }

    public function show($id) {
        $this->redirect('/settings');
    }

    public function edit($id) {
        $this->redirect('/settings');
    }

    public function delete($id) {
        $this->flash('error', 'La suppression manuelle de reporting n est pas exposee dans le flux principal.');
        $this->redirect('/settings');
    }
}
