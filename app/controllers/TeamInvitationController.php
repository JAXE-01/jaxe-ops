<?php
class TeamInvitationController extends Controller {
    public function accept($token=null){
        if($this->currentUser()){$this->redirect('/team');}
        $token=trim((string)$token);$record=TeamInvitationService::inspect($token);
        if(!$record){http_response_code(404);$this->render('team/accept',['pageTitle'=>'Invitation expirée','record'=>null,'token'=>$token]);return;}
        if($this->isPost()){
            try{TeamInvitationService::accept($token,(string)($_POST['name']??''),(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));$this->flash('success','Invitation acceptée. Vous pouvez maintenant vous connecter.');$this->redirect('/login');}
            catch(Throwable $e){$this->flash('error',$e->getMessage());$record=TeamInvitationService::inspect($token);}
        }
        $this->render('team/accept',['pageTitle'=>'Rejoindre '.$record['organization_name'],'record'=>$record,'token'=>$token]);
    }
}
