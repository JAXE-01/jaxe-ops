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
            elseif($action==='collect_all_metrics'){$result=(new SocialMetricsCollectorService())->collectPublished(TenantGuard::tenantId(),$user,50);$message=$result['collected'].' destination(s) actualisée(s).';if($result['failed']){$message.=' '.$result['failed'].' échec(s).';$errors=array_values(array_unique(array_filter(array_map('trim',(array)($result['errors']??[])))));if($errors)$message.=' Motif : '.implode(' | ',array_slice($errors,0,3));}elseif(!$result['collected']){$message='Aucune destination publiée éligible à la collecte.';}if($this->isAjaxRequest()){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$result['collected']>0,'message'=>$message,'collected'=>$result['collected'],'failed'=>$result['failed'],'errors'=>$result['errors']??[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return;}$this->flash($result['collected']?'success':'error',$message);}
            else throw new RuntimeException('Action de publication inconnue.');
        }catch(Throwable$e){$this->flash('error',$e->getMessage());}$this->redirect('/social-publishing');}
        $data=$this->model->dashboardData(WorkingMonth::resolve());$this->render('social-publishing/index',array_merge($data,['pageTitle'=>'Publication multiréseau','clients'=>$this->model->clients(),'projects'=>$this->model->projects(),'providers'=>SocialPublishingModel::PROVIDERS,'canManage'=>$this->can('publishing.manage'),'canApprove'=>$this->can('publishing.approve')]));
    }

    public function collectAllMetrics(): void {
        if (!$this->isPost()) { http_response_code(405); header('Allow: POST'); echo 'Methode non autorisee.'; return; }
        $this->requirePermission('publishing.manage');
        try {
            $result=(new SocialMetricsCollectorService())->collectPublished(TenantGuard::tenantId(),(int)$this->currentUser()['id'],50);
            $message=$result['collected'].' destination(s) actualisee(s).';
            if($result['failed']){$message.=' '.$result['failed'].' echec(s).';$errors=array_values(array_unique(array_filter(array_map('trim',(array)($result['errors']??[])))));if($errors)$message.=' Motif : '.implode(' | ',array_slice($errors,0,3));}
            elseif(!$result['collected']){$message='Aucune destination publiee eligible a la collecte.';}
            $this->respondJson(['success'=>$result['collected']>0,'message'=>$message]+$result);
        } catch(Throwable $e) { $this->respondJson(['success'=>false,'message'=>$e->getMessage()],500); }
    }

    public function importHistory(): void {
        if (!$this->isPost()) { http_response_code(405); header('Allow: POST'); echo 'Methode non autorisee.'; return; }
        $this->requirePermission('publishing.manage');
        try {
            $result=(new SocialMetricsCollectorService())->importHistory((int)($_POST['connection_id']??0),TenantGuard::tenantId(),(int)$this->currentUser()['id'],(string)($_POST['from']??''),(string)($_POST['to']??''),100);
            $message=$result['imported'].' publication(s) importee(s), '.$result['existing'].' deja connue(s), '.$result['collected'].' collecte(s) KPI.';
            if($result['failed'])$message.=' '.$result['failed'].' echec(s).';
            $this->flash($result['imported']||$result['collected']?'success':'error',$message);
        } catch(Throwable $e) { $this->flash('error',$e->getMessage()); }
        $this->redirect('/social-publishing');
    }}
