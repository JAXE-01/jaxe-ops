<?php
class OffreModel extends Model {
    public function getAll() {
        $stmt = $this->db->query('SELECT * FROM offres');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById($id) {
        $stmt = $this->db->prepare('SELECT * FROM offres WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function create($data) {
        $stmt = $this->db->prepare('INSERT INTO offres (client_id, produit_service, description, prix, packages, avantage_offre, usp, positionnement) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['client_id'], $data['produit_service'], $data['description'], $data['prix'], $data['packages'], $data['avantage_offre'], $data['usp'], $data['positionnement']
        ]);
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare('UPDATE offres SET client_id=?, produit_service=?, description=?, prix=?, packages=?, avantage_offre=?, usp=?, positionnement=? WHERE id=?');
        $stmt->execute([
            $data['client_id'], $data['produit_service'], $data['description'], $data['prix'], $data['packages'], $data['avantage_offre'], $data['usp'], $data['positionnement'], $id
        ]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM offres WHERE id = ?');
        $stmt->execute([$id]);
    }
}
