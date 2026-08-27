<?php
class DashboardModel extends Model {
    public function getOverviewStats(array $currentUser = null) {
        if (UserScope::isScopedOperationalUser($currentUser)) {
            return $this->getScopedOverviewStats($currentUser);
        }

        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','dashboard_stats');
        $params=$scope['params'];$where=$scope['sql'];
        return [
            'clients'=>$this->scalarPrepared('SELECT COUNT(*) FROM clients c WHERE '.$where,$params),
            'projets'=>$this->scalarPrepared('SELECT COUNT(*) FROM projets p JOIN clients c ON c.id=p.client_id WHERE '.$where,$params),
            'abonnements'=>$this->scalarPrepared("SELECT COUNT(*) FROM projets p JOIN clients c ON c.id=p.client_id WHERE ".$where." AND p.type_projet IN ('Abonnement mensuel','Abonnement mixte')",$params),
            'sea'=>$this->scalarPrepared("SELECT COUNT(*) FROM projets p JOIN clients c ON c.id=p.client_id WHERE ".$where." AND p.type_projet='SEA ponctuel'",$params),
            'taches_en_retard'=>$this->scalarPrepared("SELECT COUNT(*) FROM taches_pipeline tp JOIN projets p ON p.id=tp.projet_id JOIN clients c ON c.id=p.client_id WHERE ".$where." AND tp.statut NOT IN ('Terminee','Annulee') AND tp.deadline<CURDATE()",$params),
            'taches_a_faire'=>$this->scalarPrepared("SELECT COUNT(*) FROM taches_pipeline tp JOIN projets p ON p.id=tp.projet_id JOIN clients c ON c.id=p.client_id WHERE ".$where." AND tp.statut IN ('A faire','En cours')",$params)
        ];
    }

    public function getProjectsByType(array $currentUser = null) {
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $counts = [];
            foreach ($this->getScopedProjectRows(UserScope::userId($currentUser), $currentUser) as $row) {
                $projectType = (string) ($row['type_projet'] ?? '');
                $projectId = (int) ($row['project_id'] ?? 0);
                if ($projectType === '' || $projectId <= 0) {
                    continue;
                }
                $counts[$projectType][$projectId] = true;
            }

            $result = [];
            foreach ($counts as $type => $projectIds) {
                $result[] = ['type_projet' => $type, 'total' => count($projectIds)];
            }

            usort($result, static function ($left, $right) {
                return $right['total'] <=> $left['total'];
            });

            return $result;
        }

        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','dashboard_types');
        $stmt=$this->db->prepare("SELECT p.type_projet,COUNT(*) AS total FROM projets p JOIN clients c ON c.id=p.client_id WHERE ".$scope['sql']." GROUP BY p.type_projet ORDER BY total DESC");
        $stmt->execute($scope['params']);return $stmt->fetchAll();
    }

    public function getCurrentMonthPlans(array $currentUser = null) {
        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','dashboard_month');$params=$scope['params'];
        $sql="SELECT DISTINCT pm.id,pm.periode_mois,pm.videos_prevus,pm.videos_livres,pm.visuels_prevus,pm.visuels_livres,pm.livrables_prevus,pm.livrables_livres,pm.statut,p.nom AS projet_nom,c.entreprise
              FROM plans_mensuels pm JOIN projets p ON p.id=pm.projet_id JOIN clients c ON c.id=p.client_id";
        if(UserScope::isScopedOperationalUser($currentUser)){$sql.=' JOIN taches_pipeline scope_tp ON scope_tp.projet_id=p.id AND scope_tp.auteur_id=:user_id AND scope_tp.statut<>\'Bloquee\'';$params['user_id']=UserScope::userId($currentUser);}
        $sql.=" WHERE pm.periode_mois=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND ".$scope['sql'].' ORDER BY c.entreprise,p.nom';
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }
    public function getUpcomingDeadlines(array $currentUser = null) {
        if(UserScope::isScopedOperationalUser($currentUser))return $this->getScopedPendingTasks(UserScope::userId($currentUser),$currentUser);
        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','dashboard_upcoming');
        $sql="SELECT tp.id,tp.titre,tp.type_tache,tp.statut,tp.deadline,u.nom AS auteur,p.nom AS projet_nom,c.entreprise FROM taches_pipeline tp JOIN projets p ON p.id=tp.projet_id JOIN clients c ON c.id=p.client_id LEFT JOIN users u ON u.id=tp.auteur_id WHERE tp.statut IN ('A faire','En cours') AND ".$scope['sql'].' ORDER BY tp.deadline ASC LIMIT 12';
        $stmt=$this->db->prepare($sql);$stmt->execute($scope['params']);return $stmt->fetchAll();
    }
    public function getDelayedTasks(array $currentUser = null) {
        if(UserScope::isScopedOperationalUser($currentUser))return $this->getScopedVisibleTasks(UserScope::userId($currentUser),$currentUser,"tp.statut NOT IN ('Terminee','Annulee','Bloquee') AND tp.deadline<CURDATE()",[],10);
        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','dashboard_delayed');
        $sql="SELECT tp.id,tp.titre,tp.deadline,tp.statut,p.nom AS projet_nom,c.entreprise FROM taches_pipeline tp JOIN projets p ON p.id=tp.projet_id JOIN clients c ON c.id=p.client_id WHERE tp.statut NOT IN ('Terminee','Annulee') AND tp.deadline<CURDATE() AND ".$scope['sql'].' ORDER BY tp.deadline ASC LIMIT 10';
        $stmt=$this->db->prepare($sql);$stmt->execute($scope['params']);return $stmt->fetchAll();
    }
    public function getPhilsFocus(array $currentUser = null) {
        if(UserScope::isScopedOperationalUser($currentUser))return $this->getTasksToCorrect(UserScope::userId($currentUser),$currentUser);
        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','dashboard_focus');
        $sql="SELECT p.id,p.nom,p.type_projet,p.date_debut,p.date_fin,p.quota_videos_mensuel,p.quota_visuels_mensuel,COUNT(DISTINCT pm.id) AS mois_planifies,COUNT(DISTINCT li.id) AS livrables_generes,SUM(CASE WHEN tp.statut='Terminee' THEN 1 ELSE 0 END) AS taches_terminees,SUM(CASE WHEN tp.statut IN ('A faire','En cours','Bloquee') THEN 1 ELSE 0 END) AS taches_restantes FROM projets p JOIN clients c ON c.id=p.client_id LEFT JOIN plans_mensuels pm ON pm.projet_id=p.id LEFT JOIN livrable_items li ON li.projet_id=p.id LEFT JOIN taches_pipeline tp ON tp.projet_id=p.id WHERE c.entreprise='Galerie PHIL''S' AND ".$scope['sql'].' GROUP BY p.id ORDER BY p.date_fin DESC';
        $stmt=$this->db->prepare($sql);$stmt->execute($scope['params']);return $stmt->fetchAll();
    }
    private function getScopedOverviewStats(array $currentUser) {
        $userId = UserScope::userId($currentUser);
        $visibleProjects = $this->getScopedProjectRows($userId, $currentUser);
        $clientIds = [];
        $projectIds = [];
        $subscriptionProjectIds = [];
        $seaProjectIds = [];

        foreach ($visibleProjects as $row) {
            $clientIds[(int) $row['client_id']] = true;
            $projectIds[(int) $row['project_id']] = true;

            if (in_array($row['type_projet'], ['Abonnement mensuel', 'Abonnement mixte'], true)) {
                $subscriptionProjectIds[(int) $row['project_id']] = true;
            }

            if (($row['type_projet'] ?? null) === 'SEA ponctuel') {
                $seaProjectIds[(int) $row['project_id']] = true;
            }
        }

        return [
            'clients' => count($clientIds),
            'projets' => count($projectIds),
            'abonnements' => count($subscriptionProjectIds),
            'sea' => count($seaProjectIds),
            'taches_en_retard' => count($this->getScopedVisibleTasks($userId, $currentUser, "tp.statut NOT IN ('Terminee', 'Annulee', 'Bloquee') AND tp.deadline < CURDATE()", [], null)),
            'taches_a_faire' => count($this->getScopedVisibleTasks($userId, $currentUser, "tp.statut IN ('A faire', 'En cours')", [], null)),
            'corrections' => count($this->getTasksToCorrect($userId, $currentUser, null))
        ];
    }

    private function getScopedPendingTasks($userId, array $currentUser = null) {
        return $this->getScopedVisibleTasks($userId, $currentUser, "tp.statut IN ('A faire', 'En cours')", [], 12);
    }

    private function getTasksToCorrect($userId, array $currentUser = null, $limit = 10) {
        $sql = "SELECT DISTINCT tp.id, tp.titre, tp.type_tache, tp.statut, tp.deadline, p.nom AS projet_nom, c.entreprise,
                       validation.type_tache AS validation_type, validation.validation_commentaire
                FROM taches_pipeline tp
                JOIN projets p ON p.id = tp.projet_id
                JOIN clients c ON c.id = p.client_id
                                LEFT JOIN taches_pipeline validation ON validation.parent_task_id = tp.id
                WHERE tp.auteur_id = :user_id AND c.tenant_id = :dashboard_tenant
                                    AND tp.statut <> 'Bloquee'
                                    AND (
                                        validation.validation_decision = 'Non valide'
                                        OR tp.statut = 'Annulee'
                                    )
                ORDER BY CASE WHEN tp.deadline IS NULL THEN 1 ELSE 0 END, tp.deadline ASC, tp.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId,'dashboard_tenant'=>TenantGuard::tenantId()]);
        $tasks = $this->filterVisibleOperationalTasks($stmt->fetchAll(), $currentUser, 'type_tache');
        if ($limit === null) {
            return $tasks;
        }

        return array_slice($tasks, 0, (int) $limit);
    }

    private function getScopedVisibleTasks($userId, array $currentUser = null, $whereClause = '1 = 1', array $params = [], $limit = 12) {
        $sql = "SELECT tp.id, tp.titre, tp.type_tache, tp.statut, tp.deadline, u.nom AS auteur, p.nom AS projet_nom, c.entreprise
                FROM taches_pipeline tp
                JOIN projets p ON p.id = tp.projet_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN users u ON u.id = tp.auteur_id
                WHERE tp.auteur_id = :user_id AND c.tenant_id = :dashboard_tenant
                  AND " . $whereClause . "
                ORDER BY CASE WHEN tp.deadline IS NULL THEN 1 ELSE 0 END, tp.deadline ASC, tp.id ASC";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(['user_id' => $userId,'dashboard_tenant'=>TenantGuard::tenantId()], $params));
        return $this->filterVisibleOperationalTasks($stmt->fetchAll(), $currentUser, 'type_tache');
    }

    private function getScopedProjectRows($userId, array $currentUser = null) {
        $sql = "SELECT tp.type_tache, p.id AS project_id, p.client_id, p.type_projet
                FROM taches_pipeline tp
                JOIN projets p ON p.id = tp.projet_id
                JOIN clients c ON c.id = p.client_id
                WHERE tp.auteur_id = :user_id AND c.tenant_id = :dashboard_tenant
                  AND tp.statut <> 'Bloquee'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId,'dashboard_tenant'=>TenantGuard::tenantId()]);
        return $this->filterVisibleOperationalTasks($stmt->fetchAll(), $currentUser, 'type_tache');
    }

    private function filterVisibleOperationalTasks(array $rows, array $currentUser = null, $taskTypeKey = 'type_tache') {
        if (!UserScope::isScopedOperationalUser($currentUser)) {
            return $rows;
        }

        return array_values(array_filter($rows, static function ($row) use ($currentUser, $taskTypeKey) {
            return UserScope::canAccessTaskType($currentUser, $row[$taskTypeKey] ?? null);
        }));
    }

    private function scalar($sql) {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function scalarPrepared($sql, array $params) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
