<?php
class WorkspaceModeController extends Controller {
    public function __construct(){parent::__construct();$this->requireAuth();}
    public function mode($mode){
        if(!OrganizationContext::isPlatformAdmin($this->currentUser())){$this->flash('error','Ce sélecteur est réservé à l administration SaaS.');$this->redirect('/');}
        $mode=(string)$mode;if(!in_array($mode,['platform','agency'],true))$mode='agency';
        $_SESSION['workspace_mode']=$mode;
        $this->redirect($mode==='platform'?'/platform':'/');
    }
}
