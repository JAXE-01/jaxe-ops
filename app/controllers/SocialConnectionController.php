<?php
class SocialConnectionController extends Controller {
    private SocialPublishingModel $model;
    public function __construct(){parent::__construct();$this->requireAuth();$this->requirePermission('publishing.view');$this->model=new SocialPublishingModel();}
    public function index(){
        if($this->isPost()){
            $this->requirePermission('publishing.manage');
            $ajax=$this->isAjaxRequest();
            try{
                $action=(string)($_POST['action']??'');
                if($action==='create'){$this->model->saveConnection($_POST,(int)$this->currentUser()['id']);$message='Compte préparé. Cliquez sur Connecter pour autoriser le réseau.';}
                elseif($action==='update'){$this->model->updateConnection((int)($_POST['connection_id']??0),$_POST);$message='Compte social modifié.';}
                elseif($action==='remove'){$message=$this->model->removeConnection((int)($_POST['connection_id']??0));}
                elseif($action==='bulk'){$result=$this->model->bulkConnections((array)($_POST['connection_ids']??[]),(string)($_POST['bulk_action']??''),(int)($_POST['client_id']??0));$message=$result['message'];}
                else throw new RuntimeException('Action inconnue.');
                if($ajax){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>true,'message'=>$message],JSON_UNESCAPED_UNICODE);return;}
                $this->flash('success',$message);
            }catch(Throwable $e){$message=$e instanceof PDOException?'Modification impossible. Réessayez ou contactez l’administrateur.':$e->getMessage();if($ajax){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE);return;}$this->flash('error',$message);}
            $this->redirect('/social-connection');
        }
        $this->render('social-connection/index',['pageTitle'=>'Comptes sociaux','connections'=>$this->model->connections(),'clients'=>$this->model->clients(),'providers'=>SocialPublishingModel::PROVIDERS,'canManage'=>$this->can('publishing.manage')]);
    }
}
