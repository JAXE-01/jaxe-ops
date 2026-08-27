<?php
/** Management lookup includes suspended memberships; login/assignment guards stay unchanged. */
class ManagedUserModel extends CrudModel {
    public function __construct(){parent::__construct(ModuleRegistry::get('user'));}
    public function getAll(){
        $q=$this->db->prepare("SELECT u.*,tm.tenant_id,tm.status membership_status FROM users u JOIN tenant_memberships tm ON tm.user_id=u.id WHERE tm.tenant_id=:tenant ORDER BY u.nom");
        $q->execute(['tenant'=>TenantGuard::tenantId()]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById($id){
        $q=$this->db->prepare('SELECT u.*,tm.tenant_id,tm.status membership_status FROM users u JOIN tenant_memberships tm ON tm.user_id=u.id WHERE u.id=:id AND tm.tenant_id=:tenant');
        $q->execute(['id'=>(int)$id,'tenant'=>TenantGuard::tenantId()]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function update($id,array$data){
        if(isset($data['password'])&&trim((string)$data['password'])==='')unset($data['password']);
        // Shared identities must not have their global roles/password changed from one tenant.
        $q=$this->db->prepare('SELECT COUNT(*) FROM tenant_memberships WHERE user_id=:user AND tenant_id<>:tenant');
        $q->execute(['user'=>(int)$id,'tenant'=>TenantGuard::tenantId()]);
        if((int)$q->fetchColumn()>0)throw new RuntimeException('Compte partagé entre plusieurs entreprises : gérez son adhésion dans Mon équipe et son profil depuis la supervision SaaS.');
        return parent::update($id,$data);
    }
}
