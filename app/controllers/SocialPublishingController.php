<?php
class SocialPublishingController extends Controller {
    private SocialPublishingModel $model;
    public function __construct(){parent::__construct();$this->requireAuth();$this->requirePermission('publishing.view');$this->model=new SocialPublishingModel();}
    public function index(){
        if($this->isPost()){$action=(string)($_POST['action']??'');$this->requirePermission(in_array($action,['approve','delete_remote'],true)?'publishing.approve':'publishing.manage');try{$user=(int)$this->currentUser()['id'];
            if($action==='connection'){$id=$this->model->saveConnection($_POST,$user);$this->flash('success','Destination préparée. Lancez maintenant la connexion Meta.');}
            elseif($action==='publication'){$id=$this->model->createPublication($_POST,$_FILES['media_file']??[],$user);$this->flash('success',!empty($_POST['submit_approval'])?'Publication envoyée en validation.':'Brouillon enregistré.');}
            elseif($action==='submit'){$this->model->submit((int)$_POST['publication_id'],$user);$this->flash('success','Brouillon envoyé en validation.');}
            elseif($action==='approve'){$this->requirePermission('publishing.approve');$this->model->approve((int)$_POST['publication_id'],$user);$run=(new SocialPublisherService())->processDue(100,TenantGuard::tenantId(),null,(int)$_POST['publication_id']);$this->flash($run['failed']?'error':'success',$run['processed']?($run['published'].' publication(s) envoyée(s), '.$run['failed'].' échec(s).'):'Publication approuvée et programmée.');}
            elseif($action==='retry'){$this->model->retry((int)$_POST['target_id'],$user);$run=(new SocialPublisherService())->processDue(1,TenantGuard::tenantId(),(int)$_POST['target_id']);$this->flash($run['failed']?'error':'success',$run['published']?'Nouvelle tentative réussie.':'Nouvelle tentative enregistrée.');}
            elseif($action==='run_target'){$run=(new SocialPublisherService())->processDue(1,TenantGuard::tenantId(),(int)($_POST['target_id']??0));$this->flash($run['failed']?'error':'success',$run['processed']?($run['published']?'Publication envoyée.':'Échec : consultez le détail de la destination.'):'Aucun envoi : échéance future, validation absente ou traitement déjà en cours.');}
            elseif($action==='cancel'){$this->model->cancel((int)$_POST['publication_id'],$user);$this->flash('success','Publication annulée avant envoi.');}
            elseif($action==='delete_remote'){$this->requirePermission('publishing.approve');(new SocialPublisherService())->deleteRemote((int)$_POST['target_id'],TenantGuard::tenantId(),$user);$this->flash('success','Publication Facebook supprimée.');}
            elseif($action==='collect_metrics'){(new SocialMetricsCollectorService())->collectTarget((int)$_POST['target_id'],TenantGuard::tenantId(),$user);$this->flash('success','Données collectées depuis Meta et ajoutées aux statistiques.');}
            elseif($action==='collect_all_metrics'){$result=(new SocialMetricsCollectorService())->collectPublished(TenantGuard::tenantId(),$user,50);$message=$result['collected'].' destination(s) actualisée(s).';if($result['failed'])$message.=' '.$result['failed'].' échec(s).';if(strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$result['collected']>0,'message'=>$message,'collected'=>$result['collected'],'failed'=>$result['failed']],JSON_UNESCAPED_UNICODE);return;}$this->flash($result['collected']?'success':'error',$message);}
            else throw new RuntimeException('Action de publication inconnue.');
        }catch(Throwable$e){$this->flash('error',$e->getMessage());}$this->redirect('/social-publishing');}
        $data=$this->model->dashboardData(WorkingMonth::resolve());$this->render('social-publishing/index',array_merge($data,['pageTitle'=>'Publication multiréseau','clients'=>$this->model->clients(),'projects'=>$this->model->projects(),'providers'=>SocialPublishingModel::PROVIDERS,'canManage'=>$this->can('publishing.manage'),'canApprove'=>$this->can('publishing.approve')]));
    }
}
