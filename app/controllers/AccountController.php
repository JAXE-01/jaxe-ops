<?php
class AccountController extends Controller {
    public function __construct(){parent::__construct();$this->requireAuth();}
    public function index(){
        $organization=OrganizationContext::forUser($this->currentUser());
        if(!$organization)throw new RuntimeException('Organisation active introuvable.');
        $stmt=Database::getConnection()->prepare("SELECT tm.membership_role,tm.joined_at,u.nom,u.email,u.email_verified_at FROM tenant_memberships tm JOIN users u ON u.id=tm.user_id WHERE tm.user_id=:user AND tm.tenant_id=:tenant AND tm.status='Actif' LIMIT 1");
        $stmt->execute(['user'=>$this->currentUser()['id'],'tenant'=>TenantGuard::tenantId()]);
        $this->render('account/index',['pageTitle'=>'Mon espace','organization'=>$organization,'membership'=>$stmt->fetch(PDO::FETCH_ASSOC)?:[]]);
    }
}
