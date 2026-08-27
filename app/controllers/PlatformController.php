<?php
/** SaaS diagnostics never switch tenant or impersonate a customer's account. */
class PlatformController extends Controller {
    public function __construct(){
        parent::__construct();$this->requireAuth();
        if(!OrganizationContext::isPlatformAdmin($this->currentUser())){http_response_code(403);exit('Accès réservé à l’administration SaaS.');}
    }
    public function index(){
        $_SESSION['workspace_mode']='platform';
        $pdo=Database::getConnection();
        $tenants=$pdo->query('SELECT t.id,t.name,t.status,t.plan_code,t.created_at,(SELECT COUNT(*) FROM organizations o WHERE o.tenant_id=t.id) organization_count,(SELECT COUNT(*) FROM tenant_memberships tm WHERE tm.tenant_id=t.id) member_count FROM tenants t ORDER BY t.created_at DESC,t.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $selected=(int)($_GET['tenant_id']??0);$members=[];
        if($selected){$stmt=$pdo->prepare('SELECT u.nom,u.email,u.statut,tm.membership_role,tm.status membership_status,o.name organization_name FROM tenant_memberships tm JOIN users u ON u.id=tm.user_id LEFT JOIN organizations o ON o.id=tm.organization_id WHERE tm.tenant_id=:tenant ORDER BY u.nom');$stmt->execute(['tenant'=>$selected]);$members=$stmt->fetchAll(PDO::FETCH_ASSOC);}
        $this->render('platform/index',['pageTitle'=>'Administration SaaS','tenants'=>$tenants,'members'=>$members,'selected'=>$selected]);
    }
}
