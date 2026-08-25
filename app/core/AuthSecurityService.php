<?php
class AuthSecurityService {
    private PDO $pdo;
    private string $identityHash;
    private string $ipHash;

    public function __construct(string $email) {
        $this->pdo = Database::getConnection();
        $normalized = strtolower(trim($email));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = defined('APP_ENCRYPTION_KEY') ? APP_ENCRYPTION_KEY : 'local-auth-key';
        $this->identityHash = hash_hmac('sha256', $normalized, $key);
        $this->ipHash = hash_hmac('sha256', $ip, $key);
    }

    public function retryAfterSeconds(): int {
        $windowMinutes = 15;
        $threshold = 5;
        $stmt = $this->pdo->prepare("SELECT MIN(attempted_at) FROM auth_login_attempts WHERE succeeded=0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE) AND (identity_hash=:identity OR ip_hash=:ip) HAVING COUNT(*) >= :threshold");
        $stmt->bindValue(':window', $windowMinutes, PDO::PARAM_INT);
        $stmt->bindValue(':identity', $this->identityHash);
        $stmt->bindValue(':ip', $this->ipHash);
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->execute();
        $oldest = $stmt->fetchColumn();
        if (!$oldest) { return 0; }
        return max(1, strtotime($oldest . ' +' . $windowMinutes . ' minutes') - time());
    }

    public function record(bool $succeeded): void {
        $stmt = $this->pdo->prepare('INSERT INTO auth_login_attempts(identity_hash,ip_hash,succeeded) VALUES (:identity,:ip,:succeeded)');
        $stmt->execute(['identity'=>$this->identityHash,'ip'=>$this->ipHash,'succeeded'=>$succeeded ? 1 : 0]);
        if ($succeeded || random_int(1, 100) === 1) {
            $this->pdo->exec('DELETE FROM auth_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }
    }
}
