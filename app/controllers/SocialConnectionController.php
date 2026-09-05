<?php
class SocialConnectionController extends Controller {
    private SocialPublishingModel $model;
    public function __construct(){parent::__construct();$this->requireAuth();$this->requirePermission('publishing.view');$this->model=new SocialPublishingModel();}
    public function index(){
        if($this->isPost()){
            $this->requirePermission('publishing.manage');
            try{
                $action=(string)($_POST['action']??'');
                if($action==='create'){$this->model->saveConnection($_POST,(int)$this->currentUser()['id']);$this->flash('success','Compte préparé. Cliquez sur Connecter pour autoriser le réseau.');}
                elseif($action==='update'){$this->model->updateConnection((int)($_POST['connection_id']??0),$_POST);$this->flash('success','Compte social modifié.');}
                elseif($action==='remove'){$this->flash('success',$this->model->removeConnection((int)($_POST['connection_id']??0)));}
                else throw new RuntimeException('Action inconnue.');
            }catch(Throwable $e){$this->flash('error',$e instanceof PDOException?'Modification impossible. Réessayez ou contactez l’administrateur.':$e->getMessage());}
            $this->redirect('/social-connection');
        }
        $this->render('social-connection/index',['pageTitle'=>'Comptes sociaux','connections'=>$this->model->connections(),'clients'=>$this->model->clients(),'providers'=>SocialPublishingModel::PROVIDERS,'canManage'=>$this->can('publishing.manage')]);
    }
}
