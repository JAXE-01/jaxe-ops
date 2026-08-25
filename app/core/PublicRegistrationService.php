<?php
class PublicRegistrationService {
    private PDO $pdo;
    public function __construct(){ $this->pdo=Database::getConnection(); }

    public function retryAfterSeconds(string $email): int {
        [$identity,$ip]=$this->fingerprints($email);
        $stmt=$this->pdo->prepare("SELECT MIN(attempted_at) FROM public_registration_attempts WHERE succeeded=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL 30 MINUTE) AND (identity_hash=:identity OR ip_hash=:ip) HAVING COUNT(*)>=5");
        $stmt->execute(['identity'=>$identity,'ip'=>$ip]);$oldest=$stmt->fetchColumn();
        return $oldest?max(1,strtotime($oldest.' +30 minutes')-time()):0;
    }

    public function register(array $input): array {
        $name=trim((string)($input['name']??''));$email=strtolower(trim((string)($input['email']??'')));
        $company=trim((string)($input['company']??''));$type=(string)($input['account_type']??'ClientCompany');
        $password=(string)($input['password']??'');$confirmation=(string)($input['password_confirmation']??'');
        if($name===''||mb_strlen($name)>100)throw new RuntimeException('Renseignez votre nom complet.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>120)throw new RuntimeException('Adresse e-mail invalide.');
        if($company===''||mb_strlen($company)>150)throw new RuntimeException('Renseignez le nom de votre entreprise.');
        if(!in_array($type,['Agency','ClientCompany'],true))throw new RuntimeException('Type de compte invalide.');
        if(strlen($password)<12||!preg_match('/[a-z]/',$password)||!preg_match('/[A-Z]/',$password)||!preg_match('/\d/',$password))throw new RuntimeException('Le mot de passe doit contenir 12 caractères, une majuscule, une minuscule et un chiffre.');
        if(!hash_equals($password,$confirmation))throw new RuntimeException('Les mots de passe ne correspondent pas.');
        if($this->retryAfterSeconds($email)>0)throw new RuntimeException('Trop de tentatives. Réessayez plus tard.');
        $exists=$this->pdo->prepare('SELECT 1 FROM users WHERE email=:email LIMIT 1');$exists->execute(['email'=>$email]);
        if($exists->fetchColumn()){ $this->record($email,false); throw new RuntimeException('Cette adresse ne peut pas être utilisée.'); }
        $token=bin2hex(random_bytes(32));$slug=$this->uniqueSlug($company);
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare("INSERT INTO tenants(name,slug,status,plan_code) VALUES (:name,:slug,'Actif','trial')");$stmt->execute(['name'=>$company,'slug'=>$slug]);$tenantId=(int)$this->pdo->lastInsertId();
            $stmt=$this->pdo->prepare("INSERT INTO organizations(tenant_id,name,slug,account_type,project_mode,registration_state,status) VALUES (:tenant,:name,:slug,:type,:mode,'Registered','Actif')");$stmt->execute(['tenant'=>$tenantId,'name'=>$company,'slug'=>$slug,'type'=>$type,'mode'=>$type==='Agency'?'Multiple':'Single']);$organizationId=(int)$this->pdo->lastInsertId();
            $stmt=$this->pdo->prepare("INSERT INTO users(nom,email,password,role,statut) VALUES (:name,:email,:password,'Admin','Inactif')");$stmt->execute(['name'=>$name,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]);$userId=(int)$this->pdo->lastInsertId();
            $stmt=$this->pdo->prepare("INSERT INTO tenant_memberships(tenant_id,organization_id,user_id,membership_role,status,invited_at) VALUES (:tenant,:org,:user,'Owner','Invite',NOW())");$stmt->execute(['tenant'=>$tenantId,'org'=>$organizationId,'user'=>$userId]);
            $stmt=$this->pdo->prepare('INSERT INTO email_verification_tokens(user_id,token_hash,expires_at) VALUES (:user,:hash,DATE_ADD(NOW(),INTERVAL 24 HOUR))');$stmt->execute(['user'=>$userId,'hash'=>hash('sha256',$token)]);
            $this->record($email,true);$this->pdo->commit();
            return ['email'=>$email,'token'=>$token,'company'=>$company];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function verify(string $token): bool {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return false;
        $this->pdo->beginTransaction();
        try{$stmt=$this->pdo->prepare('SELECT * FROM email_verification_tokens WHERE token_hash=:hash AND used_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE');$stmt->execute(['hash'=>hash('sha256',$token)]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row){$this->pdo->rollBack();return false;}
            $this->pdo->prepare("UPDATE users SET statut='Actif',email_verified_at=NOW() WHERE id=:id")->execute(['id'=>$row['user_id']]);
            $this->pdo->prepare("UPDATE tenant_memberships SET status='Actif',joined_at=NOW() WHERE user_id=:id AND status='Invite'")->execute(['id'=>$row['user_id']]);
            $this->pdo->prepare('UPDATE email_verification_tokens SET used_at=NOW() WHERE id=:id')->execute(['id'=>$row['id']]);$this->pdo->commit();return true;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function fingerprints(string $email): array {$key=defined('APP_ENCRYPTION_KEY')?APP_ENCRYPTION_KEY:'local-registration-key';return[hash_hmac('sha256',strtolower(trim($email)),$key),hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),$key)];}
    private function record(string $email,bool $success): void {[$identity,$ip]=$this->fingerprints($email);$this->pdo->prepare('INSERT INTO public_registration_attempts(identity_hash,ip_hash,succeeded) VALUES (:identity,:ip,:success)')->execute(['identity'=>$identity,'ip'=>$ip,'success'=>$success?1:0]);}
    private function uniqueSlug(string $name): string {$base=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name)?:$name),'-'));if($base==='')$base='entreprise';$slug=$base;$i=2;$stmt=$this->pdo->prepare('SELECT 1 FROM tenants WHERE slug=:slug LIMIT 1');do{$stmt->execute(['slug'=>$slug]);if(!$stmt->fetchColumn())return$slug;$slug=$base.'-'.$i++;}while($i<10000);throw new RuntimeException('Impossible de créer cet espace.');}
}
