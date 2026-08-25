<?php
class AgencyAccessController extends Controller {
    public function __construct() { parent::__construct(); $this->requireAuth(); }

    public function index() {
        $service=new AgencyConnectionService($this->currentUser());
        $service->assertCanAdminister();
        if($this->isPost()){
            try{$this->handleAction($service);}catch(Throwable$e){$this->flash('error',$e->getMessage());}
            $this->redirect('/agency-access');
        }
        $this->render('agency-access/index',['pageTitle'=>'Connexion agence-client','organization'=>$service->organization(),'grants'=>$service->grants(),'agencies'=>$service->agencies(),'activity'=>$service->activity(),'generatedCode'=>$_SESSION['generated_sync_code']??null]);
        unset($_SESSION['generated_sync_code']);
    }

    private function handleAction(AgencyConnectionService $service): void {
        $action=(string)($_POST['action']??'');$grantId=(int)($_POST['grant_id']??0);$scope=AgencyConnectionService::sanitizeScope((array)($_POST['scope']??[]));
        if($action==='generate_code'){$_SESSION['generated_sync_code']=$service->createSyncCode();$this->flash('success','Code temporaire genere. Il expire dans 30 minutes et ne peut etre utilise qu une fois.');return;}
        if($action==='connect_with_code'){$service->requestWithCode((string)($_POST['sync_code']??''),$scope);$this->flash('success','Demande envoyee au client. Aucun acces n est ouvert avant sa validation.');return;}
        if($action==='invite_agency'){$service->inviteAgency((int)($_POST['agency_organization_id']??0),$scope);$this->flash('success','Invitation envoyee a l agence avec les droits choisis.');return;}
        if($action==='approve'){$service->decide($grantId,true);$this->flash('success','Connexion approuvee.');return;}
        if($action==='decline'){$service->decide($grantId,false);$this->flash('success','Demande refusee.');return;}
        if($action==='update_permissions'){$service->updatePermissions($grantId,$scope);$this->flash('success','Droits de l agence mis a jour.');return;}
        if($action==='revoke'){$service->revoke($grantId);$this->flash('success','Acces revoque immediatement.');return;}
        throw new RuntimeException('Action de connexion inconnue.');
    }
}