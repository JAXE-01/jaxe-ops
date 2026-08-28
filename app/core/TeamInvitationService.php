<?php
class TeamInvitationService {
    private PDO $pdo;
    private array $user;
    private array $organization;

    public function __construct(array $user) {
        $this->pdo = Database::getConnection();
        $this->user = $user;
        $this->organization = OrganizationContext::forUser($user) ?: [];
        if (empty($this->organization['id'])) throw new RuntimeException('Organisation active introuvable.');
    }

    public function members(): array {
        $stmt=$this->pdo->prepare("SELECT tm.id membership_id,tm.membership_role,tm.status,tm.invited_at,tm.joined_at,u.id user_id,u.nom,u.email,u.role,u.statut FROM tenant_memberships tm JOIN users u ON u.id=tm.user_id WHERE tm.tenant_id=:tenant AND tm.organization_id=:org ORDER BY FIELD(tm.status,'Invite','Actif','Suspendu'),u.nom,u.email");
        $stmt->execute(['tenant'=>TenantGuard::tenantId(),'org'=>$this->organization['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function invite(string $name,string $email,string $membershipRole,string $operationalRole,int $organizationId=0): array {
        $name=trim($name);$email=strtolower(trim($email));
        if($name===''||mb_strlen($name)>100)throw new RuntimeException('Renseignez le nom du collaborateur.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>120)throw new RuntimeException('Adresse e-mail invalide.');
        if(!in_array($membershipRole,['Admin','Manager','Member','Client'],true))throw new RuntimeException('Niveau d accès invalide.');$targetOrganization=$this->organization;if($organizationId>0&&$organizationId!==(int)$this->organization['id']){if(!OrganizationContext::isPlatformAdmin($this->user))throw new RuntimeException('Organisation cible inaccessible.');$orgStmt=$this->pdo->prepare("SELECT id,name FROM organizations WHERE id=:id AND tenant_id=:tenant AND status='Actif'");$orgStmt->execute(['id'=>$organizationId,'tenant'=>TenantGuard::tenantId()]);$targetOrganization=$orgStmt->fetch(PDO::FETCH_ASSOC)?:[];if(empty($targetOrganization['id']))throw new RuntimeException('Organisation cible introuvable.');}
        if(!array_key_exists($operationalRole,ModuleRegistry::roleOptions()))throw new RuntimeException('Rôle opérationnel invalide.');
        $token=bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT id,statut FROM users WHERE email=:email LIMIT 1');$q->execute(['email'=>$email]);$existing=$q->fetch(PDO::FETCH_ASSOC);
            if($existing){$userId=(int)$existing['id'];}else{$temporary=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);$q=$this->pdo->prepare("INSERT INTO users(nom,email,password,role,statut) VALUES(:name,:email,:password,:role,'Inactif')");$q->execute(['name'=>$name,'email'=>$email,'password'=>$temporary,'role'=>$operationalRole]);$userId=(int)$this->pdo->lastInsertId();}
            $membershipCheck=$this->pdo->prepare('SELECT organization_id,status,membership_role FROM tenant_memberships WHERE tenant_id=:tenant AND user_id=:user FOR UPDATE');
            $membershipCheck->execute(['tenant'=>TenantGuard::tenantId(),'user'=>$userId]);$membership=$membershipCheck->fetch(PDO::FETCH_ASSOC);
            if($membership&&((int)$membership['organization_id']!==(int)$targetOrganization['id']||$membership['status']!=='Invite'||$membership['membership_role']==='Owner'))throw new RuntimeException('Ce compte possède déjà une adhésion. Gérez ses droits depuis son équipe, sans le réinviter ni le déplacer.');
            $q=$this->pdo->prepare("INSERT INTO tenant_memberships(tenant_id,organization_id,user_id,membership_role,status,invited_at) VALUES(:tenant,:org,:user,:role,'Invite',NOW()) ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),membership_role=VALUES(membership_role),status='Invite',invited_at=NOW()");
            $q->execute(['tenant'=>TenantGuard::tenantId(),'org'=>$targetOrganization['id'],'user'=>$userId,'role'=>$membershipRole]);
            $q=$this->pdo->prepare('SELECT id FROM tenant_memberships WHERE tenant_id=:tenant AND user_id=:user LIMIT 1');$q->execute(['tenant'=>TenantGuard::tenantId(),'user'=>$userId]);$membershipId=(int)$q->fetchColumn();
            $this->pdo->prepare('UPDATE team_invitation_tokens SET accepted_at=NOW() WHERE membership_id=:membership AND accepted_at IS NULL')->execute(['membership'=>$membershipId]);
            $q=$this->pdo->prepare('INSERT INTO team_invitation_tokens(membership_id,token_hash,expires_at,created_by) VALUES(:membership,:hash,DATE_ADD(NOW(),INTERVAL 48 HOUR),:creator)');$q->execute(['membership'=>$membershipId,'hash'=>hash('sha256',$token),'creator'=>$this->user['id']]);
            $this->pdo->commit();
            return ['email'=>$email,'name'=>$name,'organization'=>$targetOrganization['name'],'token'=>$token,'existing'=>!empty($existing)];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function suspend(int $membershipId): void {
        $stmt=$this->pdo->prepare("UPDATE tenant_memberships SET status='Suspendu' WHERE id=:id AND tenant_id=:tenant AND organization_id=:org AND membership_role<>'Owner'");
        $stmt->execute(['id'=>$membershipId,'tenant'=>TenantGuard::tenantId(),'org'=>$this->organization['id']]);
        if(!$stmt->rowCount())throw new RuntimeException('Accès introuvable ou protégé.');
    }

    public function reactivate(int $membershipId): void {
        $q=$this->pdo->prepare("UPDATE tenant_memberships SET status=CASE WHEN joined_at IS NULL THEN 'Invite' ELSE 'Actif' END WHERE id=:id AND tenant_id=:tenant AND organization_id=:org AND status='Suspendu'");
        $q->execute(['id'=>$membershipId,'tenant'=>TenantGuard::tenantId(),'org'=>$this->organization['id']]);
        if(!$q->rowCount())throw new RuntimeException('Accès inaccessible ou invitation jamais acceptée : une invitation doit être renouvelée.');
    }
    public static function inspect(string $token): ?array {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;
        $stmt=Database::getConnection()->prepare("SELECT ti.id token_id,ti.membership_id,tm.tenant_id,tm.organization_id,tm.status membership_status,u.id user_id,u.nom,u.email,u.statut user_status,o.name organization_name FROM team_invitation_tokens ti JOIN tenant_memberships tm ON tm.id=ti.membership_id JOIN users u ON u.id=tm.user_id JOIN organizations o ON o.id=tm.organization_id WHERE ti.token_hash=:hash AND ti.accepted_at IS NULL AND ti.expires_at>NOW() AND tm.status='Invite' LIMIT 1");
        $stmt->execute(['hash'=>hash('sha256',$token)]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return$row?:null;
    }

    public static function accept(string $token,string $name,string $password,string $confirmation): void {
        $record=self::inspect($token);if(!$record)throw new RuntimeException('Cette invitation est invalide ou a expiré.');
        $needsPassword=$record['user_status']!=='Actif';$name=trim($name);
        if($name===''||mb_strlen($name)>100)throw new RuntimeException('Renseignez votre nom.');
        if($needsPassword&&(strlen($password)<12||!preg_match('/[a-z]/',$password)||!preg_match('/[A-Z]/',$password)||!preg_match('/\d/',$password)))throw new RuntimeException('Utilisez au moins 12 caractères avec majuscule, minuscule et chiffre.');
        if($needsPassword&&!hash_equals($password,$confirmation))throw new RuntimeException('Les mots de passe ne correspondent pas.');
        $pdo=Database::getConnection();$pdo->beginTransaction();
        try{
            $lock=$pdo->prepare("SELECT id FROM tenant_memberships WHERE id=:id AND status='Invite' FOR UPDATE");$lock->execute(['id'=>$record['membership_id']]);if(!$lock->fetchColumn())throw new RuntimeException('Cette invitation a été suspendue ou déjà acceptée.');
            $tokenLock=$pdo->prepare("SELECT id FROM team_invitation_tokens WHERE id=:id AND accepted_at IS NULL AND expires_at>NOW() FOR UPDATE");$tokenLock->execute(['id'=>$record['token_id']]);if(!$tokenLock->fetchColumn())throw new RuntimeException('Cette invitation a expiré ou a déjà été utilisée.');
            if($needsPassword){$pdo->prepare("UPDATE users SET nom=:name,password=:password,statut='Actif',email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=:id")->execute(['name'=>$name,'password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$record['user_id']]);}else{$pdo->prepare('UPDATE users SET nom=:name WHERE id=:id')->execute(['name'=>$name,'id'=>$record['user_id']]);}
            $pdo->prepare("UPDATE tenant_memberships SET status='Actif',joined_at=COALESCE(joined_at,NOW()) WHERE id=:id")->execute(['id'=>$record['membership_id']]);
            $pdo->prepare('UPDATE team_invitation_tokens SET accepted_at=NOW() WHERE id=:id')->execute(['id'=>$record['token_id']]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
}
