<?php
class Model {
    protected $db;

    public function __construct() {
        try {
            $this->db = Database::getConnection();
        } catch (PDOException $e) {
            die('Erreur DB: ' . $e->getMessage());
        }
    }
}
