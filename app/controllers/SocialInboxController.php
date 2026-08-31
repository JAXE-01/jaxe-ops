<?php
class SocialInboxController extends Controller {
    private SocialInboxService $service;
    public function __construct(){parent::__construct();$this->requireAuth();$this->requirePermission('publishing.view');$this->service=new SocialInboxService();}
    public function index(){ $connections=$this->service->connections(TenantGuard::tenantId());$selected=(int)($_GET['connection_id']??($connections[0]['id']??0));$type=($_GET['type']??'comments')==='messages'?'messages':'comments';$inbox=['posts'=>[],'conversations'=>[]];$error=null;if($selected)try{$inbox=$this->service->inbox($selected,TenantGuard::tenantId(),$type);}catch(Throwable$e){$error=$e->getMessage();}$this->render('social-inbox/index',compact('connections','selected','type','inbox','error')+['pageTitle'=>'Messages et commentaires','canReply'=>$this->can('publishing.manage')]); }
    public function replyComment(){ $this->reply('comment'); }
    public function replyMessage(){ $this->reply('message'); }
    private function reply(string$type): void{if(!$this->isPost()){http_response_code(405);return;}$this->requirePermission('publishing.manage');try{if($type==='comment')$this->service->replyComment((int)$_POST['connection_id'],TenantGuard::tenantId(),(string)$_POST['comment_id'],(string)$_POST['message']);else$this->service->replyMessage((int)$_POST['connection_id'],TenantGuard::tenantId(),(string)$_POST['recipient_id'],(string)$_POST['message']);$this->flash('success','Réponse envoyée via Meta.');}catch(Throwable$e){$this->flash('error',$e->getMessage());}$this->redirect('/social-inbox?type='.($type==='message'?'messages':'comments').'&connection_id='.(int)($_POST['connection_id']??0));}
}
