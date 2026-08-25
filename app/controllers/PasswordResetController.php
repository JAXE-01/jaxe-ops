<?php
class PasswordResetController extends Controller {
    public function request(){if($this->currentUser())$this->redirect('/');if($this->isPost()){(new PasswordResetService())->request((string)($_POST['email']??''));$this->redirect('/password-reset/sent');}$this->render('auth/password-request',['pageTitle'=>'Récupérer votre compte']);}
    public function sent(){if($this->currentUser())$this->redirect('/');$this->render('auth/password-sent',['pageTitle'=>'Consultez votre e-mail']);}
    public function reset(){if($this->currentUser())$this->redirect('/');$token=trim((string)($_GET['token']??$_POST['token']??''));$service=new PasswordResetService();$valid=$service->inspect($token)!==null;if($this->isPost()){try{$service->reset($token,(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));$this->flash('success','Mot de passe mis à jour. Vous pouvez vous connecter.');$this->redirect('/login');}catch(Throwable$e){$this->flash('error',$e->getMessage());$valid=$service->inspect($token)!==null;}}$this->render('auth/password-reset',['pageTitle'=>'Nouveau mot de passe','token'=>$token,'valid'=>$valid]);}
}
