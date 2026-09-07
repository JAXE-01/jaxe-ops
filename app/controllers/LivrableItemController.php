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
        $this->requirePermission('calendar.view');
        $record=$this->model->getById((int)$id);
        if(!$record){$this->flash('error','Contenu introuvable.');$this->redirect('/calendrier');}
        TenantGuard::assertProject((int)$record['projet_id']);
        $db=Database::getConnection();$published=$db->prepare("SELECT 1 FROM taches_pipeline WHERE livrable_item_id=? AND type_tache='Publication' AND statut='Terminee' LIMIT 1");$published->execute([(int)$id]);
        if(($record['statut']??'')==='Publie'||$published->fetchColumn()){$this->flash('error','Un contenu déjà publié ne peut pas être retiré du calendrier.');$this->redirectToProjectCalendar($id);}
        $db->beginTransaction();try{$db->prepare("UPDATE livrable_items SET statut='Exclu' WHERE id=?")->execute([(int)$id]);$db->prepare("UPDATE taches_pipeline SET statut='Annulee' WHERE livrable_item_id=? AND statut<>'Terminee'")->execute([(int)$id]);$db->commit();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
        $this->flash('success','Contenu retiré du calendrier. Les informations existantes sont conservées.');
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
