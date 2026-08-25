<?php
class ClientModel extends Model {
    public function getAll() {
        $stmt = $this->db->query('SELECT * FROM clients');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById($id) {
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function create($data) {
        $stmt = $this->db->prepare('INSERT INTO clients (nom, entreprise, secteur, telephone, email, statut, date_creation) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $data['nom'], $data['entreprise'], $data['secteur'], $data['telephone'], $data['email'], $data['statut']
        ]);
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare('UPDATE clients SET nom=?, entreprise=?, secteur=?, telephone=?, email=?, statut=? WHERE id=?');
        $stmt->execute([
            $data['nom'], $data['entreprise'], $data['secteur'], $data['telephone'], $data['email'], $data['statut'], $id
        ]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM clients WHERE id = ?');
        $stmt->execute([$id]);
    }
}
