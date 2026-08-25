<?php
class SocialPublishingController extends Controller {
    private SocialPublishingModel $model;
    public function __construct(){parent::__construct();$this->requireAuth();$this->requirePermission('publishing.view');$this->model=new SocialPublishingModel();}
    public function index(){
        if($this->isPost()){$this->requirePermission('publishing.manage');try{$action=(string)($_POST['action']??'');if($action==='connection'){$this->model->saveConnection($_POST,(int)$this->currentUser()['id']);$this->flash('success','Destination ajoutee. Lancez OAuth lorsque les identifiants du fournisseur seront configures.');}elseif($action==='publication'){$this->model->createPublication($_POST,(int)$this->currentUser()['id']);$this->flash('success',!empty($_POST['submit_approval'])?'Publication envoyee en validation.':'Brouillon enregistre.');}elseif($action==='approve'){$this->model->approve((int)$_POST['publication_id'],(int)$this->currentUser()['id']);$this->flash('success','Publication approuvee et ajoutee a la file.');}elseif($action==='retry'){$this->model->retry((int)$_POST['target_id'],(int)$this->currentUser()['id']);$this->flash('success','Nouvelle tentative programmee.');}}catch(Throwable $e){$this->flash('error',$e->getMessage());}$this->redirect('/social-publishing');}
        $data=$this->model->dashboardData();$this->render('social-publishing/index',array_merge($data,['pageTitle'=>'Publication multireseau','clients'=>$this->model->clients(),'providers'=>SocialPublishingModel::PROVIDERS,'canManage'=>$this->can('publishing.manage'),'canApprove'=>$this->can('publishing.approve')]));
    }
}
