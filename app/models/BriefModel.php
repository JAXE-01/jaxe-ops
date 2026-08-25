<?php
class BriefModel extends CrudModel {
    public function __construct() {
        parent::__construct(ModuleRegistry::get('brief'));
    }

    public function getContentContext($contentId) {
        $stmt = $this->db->prepare("SELECT ct.id, ct.type, ct.sous_type, ct.nombre_pages_carrousel, ct.sujet,
                                           ca.client_id, ca.nom AS campagne_nom, cl.entreprise AS client_nom
                                    FROM contenus ct
                                    LEFT JOIN campagnes ca ON ca.id = ct.campagne_id
                                    LEFT JOIN clients cl ON cl.id = ca.client_id
                                    WHERE ct.id = :id LIMIT 1");
        $stmt->execute(['id' => $contentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}