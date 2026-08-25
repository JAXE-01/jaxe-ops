<?php
class AuthModel extends Model {
    public function findByEmail($email) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND statut = :statut LIMIT 1');
        $stmt->execute(['email' => $email, 'statut' => 'Actif']);
        return $stmt->fetch();
    }
}
