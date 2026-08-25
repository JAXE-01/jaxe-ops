<?php
class PersonaModel extends Model {
    public function getAll() {
        $stmt = $this->db->query('SELECT * FROM personas');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById($id) {
        $stmt = $this->db->prepare('SELECT * FROM personas WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function create($data) {
        $stmt = $this->db->prepare('INSERT INTO personas (client_id, nom_persona, age, profession, revenu, localisation, objectif, probleme, craintes, desirs, declencheur_achat, freins, valeur_percue, garanties, canaux, horaires, priorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['client_id'], $data['nom_persona'], $data['age'], $data['profession'], $data['revenu'], $data['localisation'], $data['objectif'], $data['probleme'], $data['craintes'], $data['desirs'], $data['declencheur_achat'], $data['freins'], $data['valeur_percue'], $data['garanties'], $data['canaux'], $data['horaires'], $data['priorite']
        ]);
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare('UPDATE personas SET client_id=?, nom_persona=?, age=?, profession=?, revenu=?, localisation=?, objectif=?, probleme=?, craintes=?, desirs=?, declencheur_achat=?, freins=?, valeur_percue=?, garanties=?, canaux=?, horaires=?, priorite=? WHERE id=?');
        $stmt->execute([
            $data['client_id'], $data['nom_persona'], $data['age'], $data['profession'], $data['revenu'], $data['localisation'], $data['objectif'], $data['probleme'], $data['craintes'], $data['desirs'], $data['declencheur_achat'], $data['freins'], $data['valeur_percue'], $data['garanties'], $data['canaux'], $data['horaires'], $data['priorite'], $id
        ]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM personas WHERE id = ?');
        $stmt->execute([$id]);
    }
}
