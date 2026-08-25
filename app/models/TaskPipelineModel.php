<?php
class TaskPipelineModel extends CrudModel {
    public function __construct() {
        parent::__construct(ModuleRegistry::get('tache-pipeline'));
    }

    public function getTaskContext($taskId) {
        $stmt = $this->db->prepare("SELECT tp.id, tp.titre, tp.type_tache,
                                           li.type_livrable, li.sous_type, li.nombre_pages, li.titre AS livrable_titre,
                                           pm.periode_mois, p.id AS projet_id, p.nom AS projet_nom
                                    FROM taches_pipeline tp
                                    LEFT JOIN livrable_items li ON li.id = tp.livrable_item_id
                                    LEFT JOIN plans_mensuels pm ON pm.id = tp.plan_mensuel_id
                                    LEFT JOIN projets p ON p.id = tp.projet_id
                                    WHERE tp.id = :id LIMIT 1");
        $stmt->execute(['id' => $taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
