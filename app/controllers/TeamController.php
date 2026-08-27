<?php
class TeamController extends Controller {
    public function __construct(){parent::__construct();$this->requirePermission('users.view');}
    public function index(){
        $service=new TeamInvitationService($this->currentUser());
        if($this->isPost()){
            $this->requirePermission('users.manage');
            try{
                $action=(string)($_POST['action']??'');
                if($action==='invite'){
                    $invitation=$service->invite((string)($_POST['name']??''),(string)($_POST['email']??''),(string)($_POST['membership_role']??'Member'),(string)($_POST['operational_role']??'Clientele'));
                    $url=$this->absoluteUrl('/team-invitation/accept/'.$invitation['token']);
                    $sent=EmailNotificationService::sendTeamInvitation($invitation['email'],$invitation['name'],$invitation['organization'],$url,$invitation['existing']);
                    $_SESSION['team_invitation_preview']=$sent?null:$url;
                    $this->flash($sent?'success':'info',$sent?'Invitation envoyée par e-mail.':'Invitation créée. Le serveur mail n a pas confirmé l envoi : utilisez le lien de secours affiché.');
                }elseif($action==='reactivate'){$service->reactivate((int)($_POST['membership_id']??0));$this->flash('success','Accès réactivé dans cette entreprise.');}elseif($action==='suspend'){$service->suspend((int)($_POST['membership_id']??0));$this->flash('success','Accès suspendu.');}
                else throw new RuntimeException('Action d équipe inconnue.');
            }catch(Throwable $e){$this->flash('error',$e->getMessage());}
            $this->redirect('/team');
        }
        $preview=$_SESSION['team_invitation_preview']??null;unset($_SESSION['team_invitation_preview']);
        $this->render('team/index',['pageTitle'=>'Mon équipe','members'=>$service->members(),'roles'=>ModuleRegistry::roleOptions(),'previewUrl'=>$preview]);
    }
    private function absoluteUrl(string $path): string {$scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';return$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').route_url($path);}
}
