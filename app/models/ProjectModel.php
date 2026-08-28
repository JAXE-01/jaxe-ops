<?php
class ProjectModel extends CrudModel {
    private $settingsModel;

    public function __construct() {
        parent::__construct(ModuleRegistry::get('projet'));
        $this->settingsModel = new SettingsModel();
    }

    public function getRelationOptions(string $moduleKey) {
        if($moduleKey!=='abonnement')return parent::getRelationOptions($moduleKey);
        $q=$this->db->prepare("SELECT id,nom FROM abonnements WHERE tenant_id=:tenant AND statut='Actif' ORDER BY nom");$q->execute(['tenant'=>TenantGuard::tenantId()]);return $q->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    public function getById($id) {
        $record = parent::getById($id);
        if ($record) {
            // Projects inherit their tenant from their client (no tenant_id column).
            $scope=$this->db->prepare('SELECT tenant_id FROM clients WHERE id=:client');
            $scope->execute(['client'=>$record['client_id']]);
            $record['tenant_id']=(int)$scope->fetchColumn();
            $record['configuration_mode'] = !empty($record['abonnement_id']) ? 'abonnement' : 'custom';
        }
        return $record;
    }

    public function create(array $data) {
        $this->db->beginTransaction();
        try {
            $id=parent::create($this->normalizeProjectPayload($data)); EditorialCadence::save($this->db,(int)$id,$data);
            if(isset($data['social_pages_present'])) ProjectSocialPages::save((int)$id,(int)$data['client_id'],(array)($data['social_page_ids']??[]));
            $this->db->commit();return $id;
        } catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function update($id, array $data) {
        $previous=$this->getById($id);
        if(!$previous) throw new RuntimeException('Projet inaccessible.');
        $this->db->beginTransaction();
        try {
            $result=parent::update($id,$this->normalizeProjectPayload($data)); EditorialCadence::save($this->db,(int)$id,$data);
            if(isset($data['social_pages_present'])) ProjectSocialPages::save((int)$id,(int)$data['client_id'],(array)($data['social_page_ids']??[]));
            elseif(isset($data['client_id'])&&(int)$data['client_id']!==(int)$previous['client_id']) ProjectSocialPages::save((int)$id,(int)$data['client_id'],[]);
            if(CadenceRevision::hasHistory($this->db,(int)$id))PipelineService::syncProject((int)$id);
            $this->db->commit();return $result;
        } catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function extendOneMonth($id) {
        $project = $this->getById($id);
        if (!$project) {
            throw new RuntimeException('Projet introuvable.');
        }

        $dateEnd = new DateTime((string) $project['date_fin']);
        $dateEnd->modify('+1 month');

        return parent::update($id, [
            'date_fin' => $dateEnd->format('Y-m-d'),
            'duree_mois' => max(1, (int) ($project['duree_mois'] ?? 1)) + 1
        ]);
    }

    public function getDefaultProjectValues() {
        $defaults = $this->settingsModel->getProjectDefaults();
        $defaults['configuration_mode'] = 'abonnement';
        return $defaults;
    }

    private function normalizeProjectPayload(array $data) {
        $payload = $data;
        $payload = array_merge($this->settingsModel->getProjectDefaults(), $payload);
        $mode = trim((string) ($data['configuration_mode'] ?? (!empty($data['abonnement_id']) ? 'abonnement' : 'custom')));
        unset($payload['configuration_mode']);

        if ($mode === 'abonnement') {
            $subscriptionId = (int) ($data['abonnement_id'] ?? 0);
            $subscription = $this->getSubscription($subscriptionId);
            if (!$subscription) {
                throw new RuntimeException('Selectionne un abonnement valide.');
            }

            $payload['abonnement_id'] = $subscription['id'];
            $payload['type_projet'] = $subscription['type_projet'];
            $payload['canal_principal'] = $subscription['canal_principal'];
            $payload['sea_budget'] = $subscription['sea_budget'];
            $payload['quota_videos_mensuel'] = $subscription['quota_videos_mensuel'];
            $payload['quota_visuels_mensuel'] = $subscription['quota_visuels_mensuel'];
            $payload['duree_mois'] = max(1, (int) ($subscription['duree_mois'] ?? 1));
        } else {
            $payload['abonnement_id'] = null;
        }

        $payload['duree_mois'] = $this->resolveDurationMonths(
            (string) ($payload['date_debut'] ?? ''),
            (string) ($payload['date_fin'] ?? ''),
            (int) ($payload['duree_mois'] ?? 0)
        );

        return $payload;
    }

    private function getSubscription($id) {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM abonnements WHERE id = :id AND tenant_id = :tenant AND statut = :statut LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'statut' => 'Actif', 'tenant'=>TenantGuard::tenantId()
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function resolveDurationMonths($startDate, $endDate, $fallbackDuration) {
        if ($startDate !== '' && $endDate !== '') {
            $start = new DateTime(date('Y-m-01', strtotime($startDate)));
            $end = new DateTime(date('Y-m-01', strtotime($endDate)));
            $diff = $start->diff($end);
            return max(1, ((int) $diff->y * 12) + (int) $diff->m + 1);
        }

        return max(1, $fallbackDuration);
    }
}
