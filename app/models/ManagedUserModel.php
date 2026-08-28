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
        if(!$this->getById($id))throw new RuntimeException('Utilisateur inaccessible dans cette entreprise.');
        $payload=[];
        foreach(['nom','email','role','secondary_roles','statut']as$field)if(array_key_exists($field,$data))$payload[$field]=trim((string)$data[$field]);
        if(isset($payload['email'])&&!filter_var($payload['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Adresse email invalide.');
        if(isset($payload['nom'])&&$payload['nom']==='')throw new RuntimeException('Nom obligatoire.');
        if(isset($payload['role'])&&!array_key_exists($payload['role'],ModuleRegistry::roleOptions()))throw new RuntimeException('Rôle invalide.');
        if(isset($payload['statut'])&&!in_array($payload['statut'],['Actif','Inactif'],true))throw new RuntimeException('Statut invalide.');
        if((int)$id===(int)($_SESSION['user']['id']??0)&&($payload['statut']??'Actif')!=='Actif')throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
        if(isset($data['password'])){if(strlen((string)$data['password'])<12)throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 12 caractères.');$payload['password']=password_hash((string)$data['password'],PASSWORD_DEFAULT);}
        if(!$payload)return true;
        $columns=array_map(static fn($field)=>'u.'.$field.'=:'.$field,array_keys($payload));
        $q=$this->db->prepare('UPDATE users u JOIN tenant_memberships tm ON tm.user_id=u.id SET '.implode(',',$columns).' WHERE u.id=:managed_user AND tm.tenant_id=:managed_tenant');
        return $q->execute(array_merge($payload,['managed_user'=>(int)$id,'managed_tenant'=>TenantGuard::tenantId()]));
    }
}
