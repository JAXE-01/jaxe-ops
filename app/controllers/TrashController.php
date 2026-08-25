<?php
class TrashController extends Controller {
    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('settings.manage');
    }

    public function index() {
        $items = FileTrashService::listTrashItems(1000);
        $this->render('trash/index', [
            'pageTitle' => 'Corbeille fichiers',
            'items' => $items,
        ]);
    }

    public function purge($id) {
        $success = FileTrashService::purgeTrashItem((int) $id);
        if ($success) {
            $this->flash('success', 'Fichier supprime definitivement.');
        } else {
            $this->flash('error', 'Element de corbeille introuvable.');
        }
        $this->redirect('/trash');
    }
}
