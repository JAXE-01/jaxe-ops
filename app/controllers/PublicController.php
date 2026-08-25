<?php
class PublicController extends Controller {
    public function index(){if($this->currentUser())$this->redirect('/');$this->render('public/home',['pageTitle'=>'Strax — Pilotez vos contenus']);}
    public function solutions(){if($this->currentUser())$this->redirect('/');$this->render('public/solutions',['pageTitle'=>'Solutions Strax']);}
    public function register(){
        if($this->currentUser())$this->redirect('/');$values=[];
        if($this->isPost()){$values=$_POST;try{if(!empty($_POST['website']))throw new RuntimeException('Inscription non autorisée.');$service=new PublicRegistrationService();$result=$service->register($_POST);$url=route_url('/public/verify/'.$result['token']);$sent=EmailNotificationService::sendAccountVerification($result['email'],$result['company'],$url);$_SESSION['registration_preview_url']=$sent?null:$url;$this->flash('success','Compte créé. Consultez votre e-mail pour confirmer votre adresse.');$this->redirect('/public/registration-sent');}catch(Throwable $e){$this->flash('error',$e->getMessage());}}
        $this->render('public/register',['pageTitle'=>'Créer un compte Strax','values'=>$values]);
    }
    public function registrationSent(){if($this->currentUser())$this->redirect('/');$this->render('public/registration-sent',['pageTitle'=>'Confirmez votre e-mail','previewUrl'=>$_SESSION['registration_preview_url']??null]);unset($_SESSION['registration_preview_url']);}
    public function verify($token=''){if($this->currentUser())$this->redirect('/');$ok=(new PublicRegistrationService())->verify((string)$token);$this->render('public/verify',['pageTitle'=>$ok?'Compte activé':'Lien invalide','verified'=>$ok]);}
}
