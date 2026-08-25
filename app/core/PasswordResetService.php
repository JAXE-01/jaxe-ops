<?php
class PasswordResetService {
    private PDO $pdo;
    public function __construct(){ $this->pdo=Database::getConnection(); }
    public function request(string$email): void {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return;
        $stmt=$this->pdo->prepare("SELECT id,nom,email FROM users WHERE LOWER(email)=:email AND statut='Actif' LIMIT 1");$stmt->execute(['email'=>$email]);$user=$stmt->fetch();if(!$user)return;
        $recent=$this->pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id=:user AND created_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)');$recent->execute(['user'=>$user['id']]);if((int)$recent->fetchColumn()>=2)return;
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$key=defined('APP_ENCRYPTION_KEY')?APP_ENCRYPTION_KEY:'reset-key';$ipHash=hash_hmac('sha256',$ip,$key);
        $this->pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=:user AND used_at IS NULL')->execute(['user'=>$user['id']]);
        $insert=$this->pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,request_ip_hash,expires_at) VALUES(:user,:hash,:ip,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');$insert->execute(['user'=>$user['id'],'hash'=>$hash,'ip'=>$ipHash]);
        $scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'localhost');$url=$scheme.'://'.$host.route_url('/password-reset/reset').'?token='.urlencode($token);
        if(!PasswordResetMailer::send((string)$user['email'],(string)$user['nom'],$url)){error_log('Password reset email delivery failed for user '.$user['id']);}
    }
    public function inspect(string$token): ?array {if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;$stmt=$this->pdo->prepare("SELECT pr.id,pr.user_id,u.email FROM password_reset_tokens pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=:hash AND pr.used_at IS NULL AND pr.expires_at>NOW() AND u.statut='Actif' LIMIT 1");$stmt->execute(['hash'=>hash('sha256',$token)]);$row=$stmt->fetch();return$row?:null;}
    public function reset(string$token,string$password,string$confirmation): void {
        if(strlen($password)<12||!preg_match('/[a-z]/',$password)||!preg_match('/[A-Z]/',$password)||!preg_match('/\d/',$password))throw new RuntimeException('Utilisez au moins 12 caractères avec majuscule, minuscule et chiffre.');if(!hash_equals($password,$confirmation))throw new RuntimeException('Les mots de passe ne correspondent pas.');$record=$this->inspect($token);if(!$record)throw new RuntimeException('Ce lien est invalide ou a expiré.');
        $this->pdo->beginTransaction();try{$this->pdo->prepare('UPDATE users SET password=:password WHERE id=:id')->execute(['password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$record['user_id']]);$this->pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=:user AND used_at IS NULL')->execute(['user'=>$record['user_id']]);$identity=hash_hmac('sha256',strtolower((string)$record['email']),defined('APP_ENCRYPTION_KEY')?APP_ENCRYPTION_KEY:'local-auth-key');$this->pdo->prepare('DELETE FROM auth_login_attempts WHERE identity_hash=:identity')->execute(['identity'=>$identity]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }
}
