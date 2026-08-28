<?php
class CalendrierModel extends Model {
    private $hasResultNetworkColumnCache = null;

    public function getProjectsOverview(array $currentUser = null, array $filters = []) {
        $params = [];
        $scopeSql = '';
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $scopeSql = ' AND (' . $this->buildProjectScopeCondition('p', 'tp') . ')';
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }

        $accessScope=AgencyAccessPolicy::clientSqlScope('c','projects','projects_overview');
        $scopeSql.=' AND '.$accessScope['sql'];
        $params=array_merge($params,$accessScope['params']);

        $sql = "SELECT p.id, p.nom, p.date_debut, p.date_fin, p.statut, p.type_projet,
                c.id AS client_id, c.entreprise AS client_nom,
                COUNT(DISTINCT pm.id) AS plans_total,
            MIN(pm.periode_mois) AS first_plan_month,
            MAX(pm.periode_mois) AS last_plan_month,
                COUNT(DISTINCT tp.id) AS tasks_total,
                SUM(CASE WHEN tp.statut = 'Terminee' THEN 1 ELSE 0 END) AS tasks_done,
                MIN(CASE WHEN tp.statut IN ('A faire', 'En cours') THEN tp.deadline END) AS prochaine_deadline
            FROM projets p
            JOIN clients c ON c.id = p.client_id
            LEFT JOIN plans_mensuels pm ON pm.projet_id = p.id
            LEFT JOIN taches_pipeline tp ON tp.projet_id = p.id AND tp.statut <> 'Bloquee'
            WHERE 1=1" . $scopeSql;

        if (!empty($filters['client_id'])) {
            $sql .= ' AND c.id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND p.date_fin >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND p.date_debut <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }

        $sql .= ' GROUP BY p.id, c.id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $minCompletion = ($filters['completion_min'] ?? '') !== '' ? (float) $filters['completion_min'] : null;
        $maxCompletion = ($filters['completion_max'] ?? '') !== '' ? (float) $filters['completion_max'] : null;

        foreach ($rows as &$row) {
            $total = (int) ($row['tasks_total'] ?? 0);
            $done = (int) ($row['tasks_done'] ?? 0);
            $row['completion_rate'] = $total > 0 ? round(($done / $total) * 100, 1) : 0;

            $firstMonth = (string) ($row['first_plan_month'] ?? '');
            $lastMonth = (string) ($row['last_plan_month'] ?? '');
            $currentMonth = date('Y-m-01');
            if ($firstMonth !== '' && $lastMonth !== '' && $currentMonth >= $firstMonth && $currentMonth <= $lastMonth) {
                $row['calendar_month'] = $currentMonth;
            } else {
                $row['calendar_month'] = $lastMonth !== '' ? $lastMonth : $firstMonth;
            }
        }
        unset($row);

        $rows = array_values(array_filter($rows, static function ($row) use ($minCompletion, $maxCompletion) {
            $rate = (float) ($row['completion_rate'] ?? 0);
            if ($minCompletion !== null && $rate < $minCompletion) {
                return false;
            }
            if ($maxCompletion !== null && $rate > $maxCompletion) {
                return false;
            }
            return true;
        }));

        usort($rows, static function ($left, $right) {
            return strcmp((string) ($left['date_debut'] ?? ''), (string) ($right['date_debut'] ?? '')) * -1;
        });

        return $rows;
    }

    public function getGlobalCalendarStats(array $currentUser = null) {
        $params = [];
        $scopeSql = '';
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $scopeSql = ' AND (' . $this->buildProjectScopeCondition('p', 'tp') . ')';
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }

        $accessScope=AgencyAccessPolicy::clientSqlScope('c','projects','global_calendar');
        $scopeSql.=' AND '.$accessScope['sql'];
        $params=array_merge($params,$accessScope['params']);

        $sql = "SELECT
                COUNT(DISTINCT pm.id) AS plans_total,
                SUM(CASE WHEN tp.type_tache = 'Calendrier' THEN 1 ELSE 0 END) AS calendar_tasks_total,
                SUM(CASE WHEN tp.type_tache = 'Calendrier' AND tp.statut = 'Terminee' THEN 1 ELSE 0 END) AS calendar_tasks_done,
                SUM(CASE WHEN tp.type_tache = 'Calendrier' AND tp.statut = 'En cours' THEN 1 ELSE 0 END) AS calendar_tasks_in_progress,
                SUM(CASE WHEN tp.type_tache = 'Calendrier' AND tp.statut = 'Annulee' THEN 1 ELSE 0 END) AS calendar_tasks_invalid
            FROM plans_mensuels pm
            JOIN projets p ON p.id = pm.projet_id
            JOIN clients c ON c.id = p.client_id
            LEFT JOIN taches_pipeline tp ON tp.plan_mensuel_id = pm.id
            WHERE 1=1" . $scopeSql;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int) ($row['calendar_tasks_total'] ?? 0);
        $done = (int) ($row['calendar_tasks_done'] ?? 0);
        $rate = $total > 0 ? round(($done / $total) * 100, 1) : 0;

        $delaySql = "SELECT AVG(DATEDIFF(CURDATE(), tp.deadline))
            FROM taches_pipeline tp
            JOIN projets p ON p.id = tp.projet_id
            JOIN clients c ON c.id = p.client_id
            WHERE tp.deadline IS NOT NULL
              AND tp.deadline <> ''
              AND tp.deadline < CURDATE()
              AND tp.statut IN ('A faire', 'En cours')" . $scopeSql;
        $delayStmt = $this->db->prepare($delaySql);
        $delayStmt->execute($params);
        $avgDelay = (float) $delayStmt->fetchColumn();

        $firstPassRate = $this->getValidationFirstPassRate(null, $scopeSql, $params);

        $invalidationsMonthly = $this->getInvalidationRatioByMonth(null, null, 6, $scopeSql, $params);

        return [
            'plans_total' => (int) ($row['plans_total'] ?? 0),
            'calendar_tasks_total' => $total,
            'calendar_tasks_done' => $done,
            'calendar_tasks_in_progress' => (int) ($row['calendar_tasks_in_progress'] ?? 0),
            'calendar_tasks_invalid' => (int) ($row['calendar_tasks_invalid'] ?? 0),
            'calendar_completion_rate' => $rate,
            'avg_delay_days' => round($avgDelay, 1),
            'first_pass_validation_rate' => $firstPassRate,
            'monthly_invalidation_ratio' => $invalidationsMonthly,
        ];
    }

    public function getGlobalPublicationCalendar($month, $clientId = '', array $currentUser = null) {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $month) ? (string) $month : date('Y-m');
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $sql = "SELECT li.id AS deliverable_id,
                       li.titre,
                       li.type_livrable,
                       li.date_prevue,
                       p.id AS projet_id,
                       p.nom AS projet_nom,
                       c.id AS client_id,
                       c.entreprise AS client_nom,
                       ts.statut AS script_statut,
                       tb.statut AS brief_statut,
                       tpv.statut AS production_statut,
                       tt.statut AS tournage_statut,
                       tm.statut AS montage_statut,
                       tvi.statut AS validation_interne_statut,
                       tvc.statut AS validation_client_statut,
                       tpub.statut AS publication_statut
                FROM livrable_items li
                JOIN projets p ON p.id = li.projet_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN taches_pipeline ts ON ts.livrable_item_id = li.id AND ts.type_tache = 'Script'
                LEFT JOIN taches_pipeline tb ON tb.livrable_item_id = li.id AND tb.type_tache = 'Brief'
                LEFT JOIN taches_pipeline tpv ON tpv.livrable_item_id = li.id AND tpv.type_tache = 'Production'
                LEFT JOIN taches_pipeline tt ON tt.livrable_item_id = li.id AND tt.type_tache = 'Tournage'
                LEFT JOIN taches_pipeline tm ON tm.livrable_item_id = li.id AND tm.type_tache = 'Montage'
                LEFT JOIN taches_pipeline tvi ON tvi.livrable_item_id = li.id AND tvi.type_tache = 'Validation interne'
                LEFT JOIN taches_pipeline tvc ON tvc.livrable_item_id = li.id AND tvc.type_tache = 'Validation client'
                LEFT JOIN taches_pipeline tpub ON tpub.livrable_item_id = li.id AND tpub.type_tache = 'Publication'
                WHERE li.date_prevue BETWEEN :month_start AND :month_end";

        $params = [
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ];

        $accessScope=AgencyAccessPolicy::clientSqlScope('c','content','publication_calendar');
        $sql.=' AND '.$accessScope['sql'];
        $params=array_merge($params,$accessScope['params']);

        if ((int) $clientId > 0) {
            $sql .= ' AND c.id = :client_id';
            $params['client_id'] = (int) $clientId;
        }

        if (UserScope::isScopedOperationalUser($currentUser)) {
            $sql .= " AND (p.createur_id = :scope_user_id
                      OR p.cadreur_id = :scope_user_id
                      OR p.videaste_id = :scope_user_id
                      OR p.designer_id = :scope_user_id
                      OR p.cm_id = :scope_user_id
                      OR p.charge_compte_id = :scope_user_id
                      OR p.charge_clientele_id = :scope_user_id
                      OR EXISTS (SELECT 1 FROM taches_pipeline scope_tp WHERE scope_tp.projet_id = p.id AND scope_tp.auteur_id = :scope_user_id))";
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }

        $sql .= ' ORDER BY li.date_prevue ASC, c.entreprise ASC, p.nom ASC, li.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $itemsByDate = [];
        foreach ($rows as $row) {
            $dateKey = (string) ($row['date_prevue'] ?? '');
            if ($dateKey === '') {
                continue;
            }
            if (!isset($itemsByDate[$dateKey])) {
                $itemsByDate[$dateKey] = [];
            }

            $stage = $this->resolveGlobalPublicationStage($row);
            $row['stage_key'] = $stage['key'];
            $row['stage_label'] = $stage['label'];
            $itemsByDate[$dateKey][] = $row;
        }

        return [
            'month' => $month,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'items_by_date' => $itemsByDate,
            'total_items' => count($rows),
        ];
    }

    public function getPlanPerformanceStats($planId) {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return [
                'avg_delay_days' => 0,
                'first_pass_validation_rate' => 0,
                'invalidation_ratio' => 0,
            ];
        }

        $delayStmt = $this->db->prepare("SELECT AVG(DATEDIFF(CURDATE(), deadline))
            FROM taches_pipeline
            WHERE plan_mensuel_id = :plan_id
              AND deadline IS NOT NULL
              AND deadline <> ''
              AND deadline < CURDATE()
              AND statut IN ('A faire', 'En cours')");
        $delayStmt->execute(['plan_id' => $planId]);
        $avgDelay = (float) $delayStmt->fetchColumn();

        $firstPassRate = $this->getValidationFirstPassRate($planId);

        $ratioStmt = $this->db->prepare("SELECT
                SUM(CASE WHEN decision = 'Non valide' THEN 1 ELSE 0 END) AS invalid_count,
                COUNT(*) AS total_count
            FROM validation_decision_logs
            WHERE plan_mensuel_id = :plan_id");
        $ratioStmt->execute(['plan_id' => $planId]);
        $ratioRow = $ratioStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $invalidCount = (int) ($ratioRow['invalid_count'] ?? 0);
        $ratioTotal = (int) ($ratioRow['total_count'] ?? 0);
        $invalidationRatio = $ratioTotal > 0 ? round(($invalidCount / $ratioTotal) * 100, 1) : 0;

        return [
            'avg_delay_days' => round($avgDelay, 1),
            'first_pass_validation_rate' => $firstPassRate,
            'invalidation_ratio' => $invalidationRatio,
        ];
    }

    public function getProjectStatsByCalendars($projectId) {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT id FROM plans_mensuels WHERE projet_id = :project_id ORDER BY periode_mois ASC");
        $stmt->execute(['project_id' => $projectId]);
        $planIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $stats = [];
        foreach ($planIds as $planId) {
            $stats[$planId] = $this->getPlanPerformanceStats($planId);
        }
        return $stats;
    }

    public function getClientProjectsCalendarStats($clientId, array $currentUser = null) {
        $sql = "SELECT p.id AS project_id, pm.id AS plan_id,
                       COUNT(tp.id) AS total_tasks,
                       SUM(CASE WHEN tp.statut = 'Terminee' THEN 1 ELSE 0 END) AS done_tasks
                FROM projets p
                JOIN plans_mensuels pm ON pm.projet_id = p.id
                LEFT JOIN taches_pipeline tp ON tp.plan_mensuel_id = pm.id AND tp.statut <> 'Bloquee'
                WHERE p.client_id = :client_id";

        $params = ['client_id' => (int) $clientId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $sql .= ' AND ' . $this->buildProjectScopeCondition('p', 'tp');
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $sql .= ' GROUP BY p.id, pm.id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byProject = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            $planId = (int) ($row['plan_id'] ?? 0);
            if ($projectId <= 0 || $planId <= 0) {
                continue;
            }
            if (!isset($byProject[$projectId])) {
                $byProject[$projectId] = [
                    'calendar_completion_rate' => 0,
                    'avg_delay_days' => 0,
                    'first_pass_validation_rate' => 0,
                    'invalidation_ratio' => 0,
                    'plans_count' => 0,
                ];
            }

            $totalTasks = (int) ($row['total_tasks'] ?? 0);
            $doneTasks = (int) ($row['done_tasks'] ?? 0);
            $completionRate = $totalTasks > 0 ? (($doneTasks / $totalTasks) * 100) : 0;
            $planStats = $this->getPlanPerformanceStats($planId);

            $byProject[$projectId]['calendar_completion_rate'] += $completionRate;
            $byProject[$projectId]['avg_delay_days'] += (float) ($planStats['avg_delay_days'] ?? 0);
            $byProject[$projectId]['first_pass_validation_rate'] += (float) ($planStats['first_pass_validation_rate'] ?? 0);
            $byProject[$projectId]['invalidation_ratio'] += (float) ($planStats['invalidation_ratio'] ?? 0);
            $byProject[$projectId]['plans_count']++;
        }

        foreach ($byProject as $projectId => $stats) {
            $count = max(1, (int) ($stats['plans_count'] ?? 1));
            $byProject[$projectId]['calendar_completion_rate'] = round($stats['calendar_completion_rate'] / $count, 1);
            $byProject[$projectId]['avg_delay_days'] = round($stats['avg_delay_days'] / $count, 1);
            $byProject[$projectId]['first_pass_validation_rate'] = round($stats['first_pass_validation_rate'] / $count, 1);
            $byProject[$projectId]['invalidation_ratio'] = round($stats['invalidation_ratio'] / $count, 1);
        }

        return $byProject;
    }

    public function getClientsOverview(array $currentUser = null) {
        $accessScope=AgencyAccessPolicy::clientSqlScope('c','projects','clients_overview');
        $params=$accessScope['params'];
        $where=$accessScope['sql'];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $where.=' AND '.$this->buildProjectScopeCondition('p','tp');
            $params['scope_user_id']=UserScope::userId($currentUser);
        }
        $sql="SELECT c.id,c.entreprise,c.secteur,
                     COUNT(DISTINCT p.id) AS projets_total,
                     SUM(CASE WHEN p.statut='Actif' THEN 1 ELSE 0 END) AS projets_actifs,
                     MIN(CASE WHEN tp.statut IN ('A faire','En cours') THEN tp.deadline END) AS prochaine_deadline
              FROM clients c
              LEFT JOIN projets p ON p.client_id=c.id
              LEFT JOIN taches_pipeline tp ON tp.projet_id=p.id AND tp.statut<>'Bloquee'
              WHERE ".$where."
              GROUP BY c.id,c.entreprise,c.secteur
              ORDER BY c.entreprise ASC";
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }
    public function getClientProjects($clientId, array $currentUser = null) {
        TenantGuard::assertClient((int)$clientId);
         $sql = "SELECT p.*, ca.nom AS campagne_nom,
                  u1.nom AS charge_compte_nom,
                  u4.nom AS charge_clientele_nom,
                                    u5.nom AS cadreur_nom,
                                    u6.nom AS videaste_nom,
                                    u7.nom AS designer_nom,
                       COUNT(DISTINCT pm.id) AS plans_total,
                       COUNT(DISTINCT li.id) AS livrables_total,
                       SUM(CASE WHEN tp.statut = 'Terminee' THEN 1 ELSE 0 END) AS taches_terminees,
                       SUM(CASE WHEN tp.statut IN ('A faire', 'En cours') THEN 1 ELSE 0 END) AS taches_restantes
                FROM projets p
                LEFT JOIN campagnes ca ON ca.id = p.campagne_id
              LEFT JOIN users u1 ON u1.id = p.charge_compte_id
              LEFT JOIN users u4 ON u4.id = p.charge_clientele_id
                            LEFT JOIN users u5 ON u5.id = p.cadreur_id
                            LEFT JOIN users u6 ON u6.id = p.videaste_id
                            LEFT JOIN users u7 ON u7.id = p.designer_id
                LEFT JOIN plans_mensuels pm ON pm.projet_id = p.id
                LEFT JOIN livrable_items li ON li.projet_id = p.id
                LEFT JOIN taches_pipeline tp ON tp.projet_id = p.id AND tp.statut <> 'Bloquee'
                WHERE p.client_id = :client_id
                " . (UserScope::isScopedOperationalUser($currentUser) ? " AND " . $this->buildProjectScopeCondition('p', 'tp') : '') . "
                GROUP BY p.id
                ORDER BY p.date_debut DESC";
        $stmt = $this->db->prepare($sql);
        $params = ['client_id' => $clientId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getClient($clientId) {
        TenantGuard::assertClient((int)$clientId);
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        return $stmt->fetch();
    }

    public function getProjectCalendar($projectId, $selectedMonth = null, array $currentUser = null) {
        $project = $this->getProject($projectId, $currentUser);
        if (!$project) {
            return null;
        }

        $plansStmt = $this->db->prepare("SELECT pm.*
            FROM plans_mensuels pm
            WHERE pm.projet_id = :projet_id
            ORDER BY pm.periode_mois ASC");
        $plansStmt->execute(['projet_id' => $projectId]);
        $plans = $plansStmt->fetchAll();

        $availableMonths = array_map(static function ($plan) {
            return [
                'value' => $plan['periode_mois'],
                'label' => date('F Y', strtotime($plan['periode_mois']))
            ];
        }, $plans);

        $resolvedSelectedMonth = $this->resolveDefaultProjectMonth($plans, $selectedMonth);

        if (!empty($resolvedSelectedMonth)) {
            $plans = array_values(array_filter($plans, static function ($plan) use ($resolvedSelectedMonth) {
                return $plan['periode_mois'] === $resolvedSelectedMonth;
            }));
        }

        foreach ($plans as &$plan) {
            $plan['month_tasks'] = $this->getMonthTasks($plan['id'], $currentUser);
            $plan['deliverables'] = $this->getDeliverablesForPlan($plan['id'], $currentUser);
        }

        return [
            'project' => $project,
            'plans' => $plans,
            'availableMonths' => $availableMonths,
            'selectedMonth' => $resolvedSelectedMonth
        ];
    }

    public function getTaskWorkspace($taskId, array $currentUser = null) {
        $sql = "SELECT tp.*, pm.periode_mois, pm.index_mois,
                  pm.contexte_mois, pm.objectif_mois, pm.temps_forts_mois,
                       p.nom AS projet_nom, p.id AS projet_id, p.client_id, p.campagne_id, p.canal_principal,
                       c.entreprise AS client_nom,
                       li.titre AS livrable_titre, li.type_livrable, li.sous_type, li.nombre_pages,
                  u.nom AS auteur_nom,
                  ct.id AS content_id, ct.sujet AS contenu_sujet, ct.message AS contenu_message,
                  ct.objectif_publication, ct.cible_libre, ct.reseau_cible, ct.statut AS contenu_statut,
                  ct.persona_id, pe.nom_persona AS persona_nom, pe.profession AS persona_profession,
                  pe.objectif AS persona_objectif, pe.probleme AS persona_probleme,
                  pe.desirs AS persona_desirs, pe.canaux AS persona_canaux
                FROM taches_pipeline tp
                JOIN projets p ON p.id = tp.projet_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN plans_mensuels pm ON pm.id = tp.plan_mensuel_id
                LEFT JOIN livrable_items li ON li.id = tp.livrable_item_id
                LEFT JOIN users u ON u.id = tp.auteur_id
              LEFT JOIN contenus ct ON ct.livrable_item_id = tp.livrable_item_id
              LEFT JOIN personas pe ON pe.id = ct.persona_id
                WHERE tp.id = :id
                " . (UserScope::isScopedOperationalUser($currentUser) ? " AND tp.auteur_id = :scope_user_id AND tp.statut <> 'Bloquee'" : '') . "
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $params = ['id' => $taskId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task || !UserScope::canAccessTaskType($currentUser, $task['type_tache'] ?? null)) {
            return null;
        }

        $task['strategyStats'] = $task['type_tache'] === 'Strategie' ? $this->getStrategyStats((int) $task['client_id']) : [];
        $task['content_ready'] = $this->isContentReady($task);

        if (!empty($task['livrable_item_id'])) {
            $task['deliverable'] = $this->getDeliverableWorkspace((int) $task['livrable_item_id'], $currentUser);
            $task['brief'] = $task['deliverable']['brief'] ?? $this->getBriefByDeliverableId((int) $task['livrable_item_id']);
            $task['publication_entries'] = !empty($task['content_id'])
                ? $this->getContentPublicationEntries((int) $task['content_id'])
                : [];
            $task['latest_publication'] = !empty($task['publication_entries']) ? $task['publication_entries'][0] : null;
            $task['result_entries'] = !empty($task['content_id'])
                ? $this->getContentResultEntries((int) $task['content_id'])
                : [];
        } else {
            $task['deliverable'] = null;
            $task['brief'] = null;
            $task['publication_entries'] = [];
            $task['latest_publication'] = null;
            $task['result_entries'] = [];
        }

        return $task;
    }

    public function getDeliverableWorkspace($deliverableId, array $currentUser = null) {
        $sql = "SELECT li.*, pm.periode_mois, pm.index_mois,
                       pm.contexte_mois, pm.objectif_mois, pm.temps_forts_mois,
                       p.nom AS projet_nom, p.id AS projet_id, p.client_id, p.campagne_id, p.canal_principal,
                       c.entreprise AS client_nom,
                       ca.id AS campagne_id_context, ca.nom AS campagne_nom,
                       ct.id AS content_id, ct.type AS contenu_type, ct.sous_type AS contenu_sous_type, ct.nombre_pages_carrousel,
                       ct.sujet AS contenu_sujet, ct.message AS contenu_message, ct.objectif_publication, ct.cible_libre, ct.reseau_cible,
                       ct.statut AS contenu_statut, ct.responsable AS contenu_responsable, ct.persona_id,
                       cc.method AS composition_method, cc.target_audience AS tpack_target, cc.objective AS tpack_objective,
                       cc.problem_need AS tpack_problem, cc.product_offer AS tpack_product, cc.content_format AS tpack_format,
                       cc.call_to_action AS tpack_cta, cc.platform AS tpack_platform, cc.hook_idea AS tpack_hook,
                       cc.priority AS tpack_priority, cc.idea_status AS tpack_status, cc.generated_brief AS tpack_generated_brief,
                       pe.nom_persona AS persona_nom
                FROM livrable_items li
                JOIN plans_mensuels pm ON pm.id = li.plan_mensuel_id
                JOIN projets p ON p.id = li.projet_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN campagnes ca ON ca.id = p.campagne_id
                LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
                LEFT JOIN content_compositions cc ON cc.livrable_item_id = li.id
                LEFT JOIN personas pe ON pe.id = ct.persona_id
                WHERE li.id = :id
                " . (UserScope::isScopedOperationalUser($currentUser) ? " AND EXISTS (SELECT 1 FROM taches_pipeline scope_tp WHERE scope_tp.livrable_item_id = li.id AND scope_tp.auteur_id = :scope_user_id AND scope_tp.statut <> 'Bloquee')" : '') . "
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $params = ['id' => $deliverableId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        $deliverable = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$deliverable) {
            return null;
        }

        $deliverable['tasks'] = $this->getDeliverableTasks($deliverableId, $currentUser);
        if (UserScope::isScopedOperationalUser($currentUser) && empty($deliverable['tasks'])) {
            return null;
        }
        $deliverable['taskMap'] = [];
        foreach ($deliverable['tasks'] as $task) {
            $deliverable['taskMap'][$task['type_tache']] = $task;
        }
        $deliverable['invalidation_history'] = $this->getDeliverableInvalidationHistory($deliverableId);
        $deliverable['content_ready'] = $this->isContentReady($deliverable);
        $deliverable['brief'] = $this->getBriefByDeliverableId($deliverableId, $deliverable);
        return $deliverable;
    }

    public function getContentWorkspace($deliverableId, array $currentUser = null) {
        return $this->getDeliverableWorkspace($deliverableId, $currentUser);
    }

    public function saveContentWorkspace($deliverableId, array $monthlyPayload, array $contentPayload) {
        $workspace = $this->getDeliverableWorkspace($deliverableId);
        if (!$workspace) {
            throw new RuntimeException('Livrable introuvable pour la fiche contenu.');
        }

        $planUpdate = $this->db->prepare("UPDATE plans_mensuels
            SET contexte_mois = :contexte_mois,
                objectif_mois = :objectif_mois,
                temps_forts_mois = :temps_forts_mois
            WHERE id = :id");
        $planUpdate->execute([
            'contexte_mois' => $monthlyPayload['contexte_mois'],
            'objectif_mois' => $monthlyPayload['objectif_mois'],
            'temps_forts_mois' => $monthlyPayload['temps_forts_mois'],
            'id' => $workspace['plan_mensuel_id']
        ]);

        $contentId = (int) ($workspace['content_id'] ?? 0);
        if ($contentId > 0) {
            $stmt = $this->db->prepare("UPDATE contenus
                SET campagne_id = :campagne_id,
                    persona_id = :persona_id,
                    sujet = :sujet,
                    message = :message,
                    objectif_publication = :objectif_publication,
                    cible_libre = :cible_libre,
                    reseau_cible = :reseau_cible,
                    sous_type = :sous_type,
                    nombre_pages_carrousel = :nombre_pages_carrousel,
                    responsable = :responsable
                WHERE id = :id");
            $stmt->execute([
                'campagne_id' => $contentPayload['campagne_id'],
                'persona_id' => $contentPayload['persona_id'],
                'sujet' => $contentPayload['sujet'],
                'message' => $contentPayload['message'],
                'objectif_publication' => $contentPayload['objectif_publication'],
                'cible_libre' => $contentPayload['cible_libre'],
                'reseau_cible' => $contentPayload['reseau_cible'],
                'sous_type' => $contentPayload['sous_type'],
                'nombre_pages_carrousel' => $contentPayload['nombre_pages_carrousel'],
                'responsable' => $contentPayload['responsable'],
                'id' => $contentId,
            ]);
            $this->saveContentComposition($workspace, $contentPayload);
            $this->updateDeliverablePublicationSchedule($deliverableId, $workspace['plan_mensuel_id'], $contentPayload['date_prevue']);
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO contenus
            (campagne_id, persona_id, projet_id, plan_mensuel_id, livrable_item_id, type, sous_type, nombre_pages_carrousel, sujet, message, objectif_publication, cible_libre, reseau_cible, statut, responsable)
            VALUES
            (:campagne_id, :persona_id, :projet_id, :plan_mensuel_id, :livrable_item_id, :type, :sous_type, :nombre_pages_carrousel, :sujet, :message, :objectif_publication, :cible_libre, :reseau_cible, :statut, :responsable)");
        $stmt->execute([
            'campagne_id' => $contentPayload['campagne_id'],
            'persona_id' => $contentPayload['persona_id'],
            'projet_id' => $workspace['projet_id'],
            'plan_mensuel_id' => $workspace['plan_mensuel_id'],
            'livrable_item_id' => $workspace['id'],
            'type' => $workspace['type_livrable'] === 'Video' ? 'Video' : 'Visuel',
            'sous_type' => $contentPayload['sous_type'],
            'nombre_pages_carrousel' => $contentPayload['nombre_pages_carrousel'],
            'sujet' => $contentPayload['sujet'],
            'message' => $contentPayload['message'],
            'objectif_publication' => $contentPayload['objectif_publication'],
            'cible_libre' => $contentPayload['cible_libre'],
            'reseau_cible' => $contentPayload['reseau_cible'],
            'statut' => 'Strategique defini',
            'responsable' => $contentPayload['responsable']
        ]);

        $this->saveContentComposition($workspace, $contentPayload);
        $this->updateDeliverablePublicationSchedule($deliverableId, $workspace['plan_mensuel_id'], $contentPayload['date_prevue']);
    }

        private function saveContentComposition(array $workspace, array $payload) {
        $stmt = $this->db->prepare("INSERT INTO content_compositions (livrable_item_id, projet_id, client_id, method, target_audience, objective, problem_need, product_offer, content_format, call_to_action, platform, hook_idea, priority, idea_status, generated_brief) VALUES (:livrable_item_id,:projet_id,:client_id,:method,:target_audience,:objective,:problem_need,:product_offer,:content_format,:call_to_action,:platform,:hook_idea,:priority,:idea_status,:generated_brief) ON DUPLICATE KEY UPDATE method=VALUES(method),target_audience=VALUES(target_audience),objective=VALUES(objective),problem_need=VALUES(problem_need),product_offer=VALUES(product_offer),content_format=VALUES(content_format),call_to_action=VALUES(call_to_action),platform=VALUES(platform),hook_idea=VALUES(hook_idea),priority=VALUES(priority),idea_status=VALUES(idea_status),generated_brief=VALUES(generated_brief)");
        $stmt->execute(['livrable_item_id'=>(int)$workspace['id'],'projet_id'=>(int)$workspace['projet_id'],'client_id'=>(int)$workspace['client_id'],'method'=>$payload['composition_method'] ?: 'TPACK','target_audience'=>$payload['tpack_target'],'objective'=>$payload['tpack_objective'],'problem_need'=>$payload['tpack_problem'],'product_offer'=>$payload['tpack_product'],'content_format'=>$payload['tpack_format'],'call_to_action'=>$payload['tpack_cta'],'platform'=>$payload['tpack_platform'],'hook_idea'=>$payload['tpack_hook'],'priority'=>$payload['tpack_priority'] ?: 'Moyenne','idea_status'=>$payload['tpack_status'] ?: 'A discuter','generated_brief'=>$payload['tpack_generated_brief']]);
    }
public function getPlanScheduledPublicationDates($planId, $excludeDeliverableId = null) {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return [];
        }

        $sql = "SELECT li.date_prevue, COUNT(*) AS total
            FROM livrable_items li
            WHERE li.plan_mensuel_id = :plan_id
              AND li.date_prevue IS NOT NULL
              AND li.date_prevue <> ''";
        $params = ['plan_id' => $planId];
        if ((int) $excludeDeliverableId > 0) {
            $sql .= ' AND li.id <> :deliverable_id';
            $params['deliverable_id'] = (int) $excludeDeliverableId;
        }
        $sql .= ' GROUP BY li.date_prevue ORDER BY li.date_prevue ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveDeliverableFiles($deliverableId, array $deliverableData) {
        $stmt = $this->db->prepare("UPDATE livrable_items
            SET sous_type = :sous_type,
                nombre_pages = :nombre_pages,
                pieces_jointes = :pieces_jointes,
                statut = :statut
            WHERE id = :id");
        $stmt->execute([
            'sous_type' => $deliverableData['sous_type'],
            'nombre_pages' => $deliverableData['nombre_pages'],
            'pieces_jointes' => json_encode($deliverableData['pieces_jointes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'statut' => $deliverableData['statut'],
            'id' => $deliverableId
        ]);
    }

    public function updateBriefFiles($deliverableId, array $files) {
        $stmt = $this->db->prepare('UPDATE briefs SET pieces_jointes = :pieces_jointes WHERE livrable_item_id = :deliverable_id');
        $stmt->execute([
            'pieces_jointes' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'deliverable_id' => $deliverableId
        ]);
    }

    public function updateTaskFiles($taskId, array $files) {
        $stmt = $this->db->prepare('UPDATE taches_pipeline SET fichiers_livres = :files WHERE id = :id');
        $stmt->execute([
            'files' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'id' => $taskId
        ]);
    }

    public function syncDeliverableStatus($deliverableId) {
        $stmt = $this->db->prepare('SELECT tp.type_tache, tp.statut, tp.plan_mensuel_id, li.type_livrable FROM taches_pipeline tp JOIN livrable_items li ON li.id = tp.livrable_item_id WHERE tp.livrable_item_id = :id');
        $stmt->execute(['id' => $deliverableId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($tasks)) {
            return;
        }

        $taskStatuses = [];
        $planId = null;
        $type = null;
        foreach ($tasks as $task) {
            $taskStatuses[$task['type_tache']] = $task['statut'];
            $planId = $task['plan_mensuel_id'];
            $type = $task['type_livrable'];
        }

        $status = 'Planifie';
        if (($taskStatuses['Publication'] ?? null) === 'Terminee') {
            $status = 'Publie';
        } else {
            $requiredReadyStages = $type === 'Video'
                ? ['Script', 'Tournage', 'Montage', 'Validation interne', 'Validation client']
                : ['Brief', 'Production', 'Validation interne', 'Validation client'];

            $allReady = true;
            foreach ($requiredReadyStages as $stage) {
                if (($taskStatuses[$stage] ?? null) !== 'Terminee') {
                    $allReady = false;
                    break;
                }
            }

            if ($allReady) {
                $status = 'Pret';
            } else {
                foreach ($taskStatuses as $taskStatus) {
                    if (in_array($taskStatus, ['En cours', 'Terminee'], true)) {
                        $status = 'En production';
                        break;
                    }
                }
            }
        }

        $update = $this->db->prepare('UPDATE livrable_items SET statut = :statut WHERE id = :id');
        $update->execute(['statut' => $status, 'id' => $deliverableId]);

        if ($planId) {
            $this->syncPlanCounters((int) $planId);
        }
    }

    private function syncPlanCounters($planId) {
        $stmt = $this->db->prepare("SELECT
                SUM(CASE WHEN type_livrable = 'Video' AND statut IN ('Pret', 'Publie') THEN 1 ELSE 0 END) AS videos_livres,
                SUM(CASE WHEN type_livrable = 'Visuel' AND statut IN ('Pret', 'Publie') THEN 1 ELSE 0 END) AS visuels_livres,
                SUM(CASE WHEN statut IN ('Pret', 'Publie') THEN 1 ELSE 0 END) AS livrables_livres,
                SUM(CASE WHEN statut = 'Publie' THEN 1 ELSE 0 END) AS publies,
                COUNT(*) AS livrables_prevus
            FROM livrable_items WHERE plan_mensuel_id = :id");
        $stmt->execute(['id' => $planId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $status = 'Planifie';
        $total = (int) ($summary['livrables_prevus'] ?? 0);
        $done = (int) ($summary['livrables_livres'] ?? 0);
        if ($done > 0 && $done < $total) {
            $status = 'Partiel';
        }
        if ($total > 0 && $done >= $total) {
            $status = 'Termine';
        }

        $update = $this->db->prepare('UPDATE plans_mensuels SET videos_livres = :videos_livres, visuels_livres = :visuels_livres, livrables_livres = :livrables_livres, statut = :statut WHERE id = :id');
        $update->execute([
            'videos_livres' => (int) ($summary['videos_livres'] ?? 0),
            'visuels_livres' => (int) ($summary['visuels_livres'] ?? 0),
            'livrables_livres' => (int) ($summary['livrables_livres'] ?? 0),
            'statut' => $status,
            'id' => $planId
        ]);
    }

    public function saveTaskWorkflow($taskId, array $payload) {
        $stmt = $this->db->prepare("UPDATE taches_pipeline
            SET statut = :statut,
                notes = :notes,
                fichiers_livres = :fichiers_livres,
                validation_decision = :validation_decision,
                note_sur_10 = :note_sur_10,
                validation_commentaire = :validation_commentaire,
                publication_reseaux = :publication_reseaux
            WHERE id = :id");
        $stmt->execute([
            'statut' => $payload['statut'],
            'notes' => $payload['notes'],
            'fichiers_livres' => $payload['fichiers_livres'],
            'validation_decision' => $payload['validation_decision'],
            'note_sur_10' => isset($payload['note_sur_10']) ? $payload['note_sur_10'] : null,
            'validation_commentaire' => $payload['validation_commentaire'],
            'publication_reseaux' => $payload['publication_reseaux'],
            'id' => $taskId
        ]);
    }

    public function markCreativeTaskAsInvalidForDeliverable($deliverableId) {
        $deliverableId = (int) $deliverableId;
        if ($deliverableId <= 0) {
            return;
        }

        $stmt = $this->db->prepare("UPDATE taches_pipeline
            SET statut = 'Annulee'
            WHERE livrable_item_id = :deliverable_id
              AND type_tache IN ('Montage', 'Production')
              AND statut <> 'Annulee'");
        $stmt->execute(['deliverable_id' => $deliverableId]);
    }

    public function getDeliverableInvalidationHistory($deliverableId) {
        $deliverableId = (int) $deliverableId;
        if ($deliverableId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT vdl.created_at,
                vdl.comment,
                vdl.source,
                tp.type_tache AS validation_type
            FROM validation_decision_logs vdl
            LEFT JOIN taches_pipeline tp ON tp.id = vdl.task_id
            WHERE vdl.deliverable_item_id = :deliverable_id
              AND vdl.decision = 'Non valide'
            ORDER BY vdl.created_at DESC, vdl.id DESC");
        $stmt->execute(['deliverable_id' => $deliverableId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function reassignTask($taskId, $newAuthorId) {
        $taskId = (int) $taskId;
        $newAuthorId = (int) $newAuthorId;
        if ($taskId <= 0 || $newAuthorId <= 0) {
            throw new RuntimeException('Tache ou auteur invalide pour la reactivation.');
        }

        $stmt = $this->db->prepare("UPDATE taches_pipeline SET auteur_id = :auteur_id WHERE id = :id");
        $stmt->execute(['auteur_id' => $newAuthorId, 'id' => $taskId]);
        if ($stmt->rowCount() <= 0) {
            $check = $this->db->prepare('SELECT auteur_id FROM taches_pipeline WHERE id = :id LIMIT 1');
            $check->execute(['id' => $taskId]);
            $currentAuthor = (int) $check->fetchColumn();
            if ($currentAuthor !== $newAuthorId) {
                throw new RuntimeException('La reattribution n a pas pu etre enregistree.');
            }
        }

        return [
            'ok' => true,
            'task_id' => $taskId,
            'new_author_id' => $newAuthorId
        ];
    }

    public function invalidateTaskById($taskId) {
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            return;
        }

        $stmt = $this->db->prepare("UPDATE taches_pipeline
            SET statut = 'Annulee'
            WHERE id = :id");
        $stmt->execute(['id' => $taskId]);
    }

    public function invalidatePlanTaskByType($planId, $taskType) {
        $planId = (int) $planId;
        $taskType = trim((string) $taskType);
        if ($planId <= 0 || $taskType === '') {
            return;
        }

        $stmt = $this->db->prepare("UPDATE taches_pipeline
            SET statut = 'Annulee'
            WHERE plan_mensuel_id = :plan_id
              AND type_tache = :task_type");
        $stmt->execute([
            'plan_id' => $planId,
            'task_type' => $taskType,
        ]);
    }

    public function syncPlanCalendarTaskStatus($planId) {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return null;
        }

        $taskStmt = $this->db->prepare("SELECT id, statut
            FROM taches_pipeline
            WHERE plan_mensuel_id = :plan_id
              AND type_tache = 'Calendrier'
            LIMIT 1");
        $taskStmt->execute(['plan_id' => $planId]);
        $calendarTask = $taskStmt->fetch(PDO::FETCH_ASSOC);
        if (!$calendarTask) {
            return null;
        }

        $deliverableStmt = $this->db->prepare("SELECT li.id,
                pm.objectif_mois,
                pm.temps_forts_mois,
                p.campagne_id,
                ct.sujet AS contenu_sujet,
                ct.message AS contenu_message,
                ct.objectif_publication,
                ct.cible_libre,
                ct.persona_id,
                ct.reseau_cible
            FROM livrable_items li
            JOIN plans_mensuels pm ON pm.id = li.plan_mensuel_id
            JOIN projets p ON p.id = li.projet_id
            LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
            WHERE li.plan_mensuel_id = :plan_id");
        $deliverableStmt->execute(['plan_id' => $planId]);
        $deliverables = $deliverableStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($deliverables)) {
            return [
                'id' => (int) $calendarTask['id'],
                'status' => $calendarTask['statut'],
                'became_completed' => false,
            ];
        }

        $allReady = true;
        foreach ($deliverables as $deliverable) {
            if (!$this->isContentReady($deliverable)) {
                $allReady = false;
                break;
            }
        }

        $currentStatus = (string) ($calendarTask['statut'] ?? 'A faire');
        $nextStatus = $currentStatus;

        if ($allReady) {
            $nextStatus = 'Terminee';
        } elseif (in_array($currentStatus, ['Bloquee', 'A faire'], true)) {
            $nextStatus = 'En cours';
        }

        if ($nextStatus !== $currentStatus) {
            $stmt = $this->db->prepare("UPDATE taches_pipeline
                SET statut = :statut
                WHERE id = :id");
            $stmt->execute([
                'statut' => $nextStatus,
                'id' => $calendarTask['id'],
            ]);
        }

        return [
            'id' => (int) $calendarTask['id'],
            'status' => $nextStatus,
            'became_completed' => $currentStatus !== 'Terminee' && $nextStatus === 'Terminee',
        ];
    }

    public function saveDeliverableBrief($deliverableId, array $brief, array $deliverableData) {
        $existing = $this->getBriefByDeliverableId($deliverableId);
        $brief['contenu_id'] = $this->resolveBriefContentId($deliverableId, $brief, $deliverableData);
        $serializedPages = json_encode($brief['pages_carrousel'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $serializedFiles = json_encode($brief['pieces_jointes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($existing) {
            $stmt = $this->db->prepare("UPDATE briefs SET
                contenu_id = :contenu_id,
                nature_brief = :nature_brief,
                format_livrable = :format_livrable,
                nombre_pages_carrousel = :nombre_pages_carrousel,
                pdf_requis = :pdf_requis,
                source_requis = :source_requis,
                titre_brief = :titre_brief,
                details_message = :details_message,
                informations_complementaires = :informations_complementaires,
                cta = :cta,
                recommandation_design = :recommandation_design,
                description_publication = :description_publication,
                hook_video = :hook_video,
                plan_script = :plan_script,
                pages_carrousel = :pages_carrousel,
                texte_script = :texte_script,
                instructions_visuelles = :instructions_visuelles,
                format = :format,
                statut = :statut,
                responsable = :responsable,
                pieces_jointes = :pieces_jointes
                WHERE id = :id");
            $stmt->execute([
                'contenu_id' => $brief['contenu_id'],
                'nature_brief' => $brief['nature_brief'],
                'format_livrable' => $brief['format_livrable'],
                'nombre_pages_carrousel' => $brief['nombre_pages_carrousel'],
                'pdf_requis' => $brief['pdf_requis'],
                'source_requis' => $brief['source_requis'],
                'titre_brief' => $brief['titre_brief'],
                'details_message' => $brief['details_message'],
                'informations_complementaires' => $brief['informations_complementaires'],
                'cta' => $brief['cta'],
                'recommandation_design' => $brief['recommandation_design'],
                'description_publication' => $brief['description_publication'],
                'hook_video' => $brief['hook_video'],
                'plan_script' => $brief['plan_script'],
                'pages_carrousel' => $serializedPages,
                'texte_script' => $brief['texte_script'],
                'instructions_visuelles' => $brief['instructions_visuelles'],
                'format' => $brief['format'],
                'statut' => $brief['statut'],
                'responsable' => $brief['responsable'],
                'pieces_jointes' => $serializedFiles,
                'id' => $existing['id']
            ]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO briefs
                (contenu_id, livrable_item_id, nature_brief, format_livrable, nombre_pages_carrousel, pdf_requis, source_requis, titre_brief, details_message, informations_complementaires, cta, recommandation_design, description_publication, hook_video, plan_script, pages_carrousel, texte_script, instructions_visuelles, format, statut, responsable, pieces_jointes)
                VALUES
                (:contenu_id, :livrable_item_id, :nature_brief, :format_livrable, :nombre_pages_carrousel, :pdf_requis, :source_requis, :titre_brief, :details_message, :informations_complementaires, :cta, :recommandation_design, :description_publication, :hook_video, :plan_script, :pages_carrousel, :texte_script, :instructions_visuelles, :format, :statut, :responsable, :pieces_jointes)");
            $stmt->execute([
                'contenu_id' => $brief['contenu_id'],
                'livrable_item_id' => $deliverableId,
                'nature_brief' => $brief['nature_brief'],
                'format_livrable' => $brief['format_livrable'],
                'nombre_pages_carrousel' => $brief['nombre_pages_carrousel'],
                'pdf_requis' => $brief['pdf_requis'],
                'source_requis' => $brief['source_requis'],
                'titre_brief' => $brief['titre_brief'],
                'details_message' => $brief['details_message'],
                'informations_complementaires' => $brief['informations_complementaires'],
                'cta' => $brief['cta'],
                'recommandation_design' => $brief['recommandation_design'],
                'description_publication' => $brief['description_publication'],
                'hook_video' => $brief['hook_video'],
                'plan_script' => $brief['plan_script'],
                'pages_carrousel' => $serializedPages,
                'texte_script' => $brief['texte_script'],
                'instructions_visuelles' => $brief['instructions_visuelles'],
                'format' => $brief['format'],
                'statut' => $brief['statut'],
                'responsable' => $brief['responsable'],
                'pieces_jointes' => $serializedFiles
            ]);
        }

        $deliverableStmt = $this->db->prepare("UPDATE livrable_items
            SET sous_type = :sous_type,
                nombre_pages = :nombre_pages,
                pieces_jointes = :pieces_jointes
            WHERE id = :id");
        $deliverableStmt->execute([
            'sous_type' => $deliverableData['sous_type'],
            'nombre_pages' => $deliverableData['nombre_pages'],
            'pieces_jointes' => json_encode($deliverableData['pieces_jointes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'id' => $deliverableId
        ]);
    }

    public function createPublicationEntry($taskId, array $payload) {
        $task = $this->getTaskWorkspace($taskId);
        if (!$task || empty($task['campagne_id']) || empty($task['livrable_item_id'])) {
            throw new RuntimeException('Impossible de creer une publication sans campagne ou livrable associe.');
        }

        $contenuId = $this->findContentIdForDeliverable($task['livrable_item_id'], $task['campagne_id']);

        $stmt = $this->db->prepare("INSERT INTO calendrier_contenus
            (campagne_id, contenu_id, date_publication, heure_publication, canal, statut, note)
            VALUES (:campagne_id, :contenu_id, :date_publication, :heure_publication, :canal, :statut, :note)");
        $stmt->execute([
            'campagne_id' => $task['campagne_id'],
            'contenu_id' => $contenuId,
            'date_publication' => $payload['date_publication'],
            'heure_publication' => $payload['heure_publication'],
            'canal' => $payload['canal'],
            'statut' => 'Planifie',
            'note' => $payload['note']
        ]);
    }

    public function savePublicationEntry($taskId, array $payload, $entryId = null) {
        $task = $this->getTaskWorkspace($taskId);
        if (!$task) {
            throw new RuntimeException('Tache de publication introuvable.');
        }

        // Keep publication workflow usable even when no campaign is attached.
        if (empty($task['campagne_id'])) {
            return 0;
        }

        $contentId = (int) ($task['content_id'] ?? 0);
        if ($contentId <= 0) {
            $contentId = $this->ensureContentForTask($task);
            $task['content_id'] = $contentId;
        }

        $entryId = (int) $entryId;
        if ($entryId <= 0) {
            $existing = $this->getLatestPublicationEntry((int) $task['content_id']);
            $entryId = (int) ($existing['id'] ?? 0);
        }

        if ($entryId > 0) {
            $stmt = $this->db->prepare("UPDATE calendrier_contenus
                SET date_publication = :date_publication,
                    heure_publication = :heure_publication,
                    canal = :canal,
                    statut = :statut,
                    note = :note
                WHERE id = :id");
            $stmt->execute([
                'date_publication' => $payload['date_publication'],
                'heure_publication' => $payload['heure_publication'],
                'canal' => $payload['canal'],
                'statut' => $payload['statut'],
                'note' => $payload['note'],
                'id' => $entryId,
            ]);
            $this->notifyPublicationApi($task, $payload, $entryId);
            return $entryId;
        }

        $stmt = $this->db->prepare("INSERT INTO calendrier_contenus
            (campagne_id, contenu_id, date_publication, heure_publication, canal, statut, note)
            VALUES (:campagne_id, :contenu_id, :date_publication, :heure_publication, :canal, :statut, :note)");
        $stmt->execute([
            'campagne_id' => $task['campagne_id'],
            'contenu_id' => $task['content_id'],
            'date_publication' => $payload['date_publication'],
            'heure_publication' => $payload['heure_publication'],
            'canal' => $payload['canal'],
            'statut' => $payload['statut'],
            'note' => $payload['note'],
        ]);

        $createdId = (int) $this->db->lastInsertId();
        $this->notifyPublicationApi($task, $payload, $createdId);
        return $createdId;
    }

    private function ensureContentForTask(array $task) {
        $deliverableId = (int) ($task['livrable_item_id'] ?? 0);
        if ($deliverableId <= 0) {
            throw new RuntimeException('Impossible de gerer la publication sans livrable associe.');
        }

        $contentId = $this->findContentIdForDeliverable($deliverableId, (int) ($task['campagne_id'] ?? 0));
        if ($contentId > 0) {
            return $contentId;
        }

        $insertStmt = $this->db->prepare("INSERT INTO contenus
            (campagne_id, persona_id, projet_id, plan_mensuel_id, livrable_item_id, type, sous_type, nombre_pages_carrousel, sujet, message, objectif_publication, cible_libre, reseau_cible, statut, responsable)
            VALUES (:campagne_id, :persona_id, :projet_id, :plan_mensuel_id, :livrable_item_id, :type, :sous_type, :nombre_pages, :sujet, :message, :objectif_publication, :cible_libre, :reseau_cible, :statut, :responsable)");
        $insertStmt->execute([
            'campagne_id' => (int) ($task['campagne_id'] ?? 0),
            'persona_id' => !empty($task['persona_id']) ? (int) $task['persona_id'] : null,
            'projet_id' => !empty($task['projet_id']) ? (int) $task['projet_id'] : null,
            'plan_mensuel_id' => !empty($task['plan_mensuel_id']) ? (int) $task['plan_mensuel_id'] : null,
            'livrable_item_id' => $deliverableId,
            'type' => (($task['type_livrable'] ?? '') === 'Video') ? 'Video' : 'Visuel',
            'sous_type' => (string) ($task['sous_type'] ?? ''),
            'nombre_pages' => max(1, (int) ($task['nombre_pages'] ?? 1)),
            'sujet' => (string) ($task['livrable_titre'] ?? 'Publication'),
            'message' => '',
            'objectif_publication' => '',
            'cible_libre' => '',
            'reseau_cible' => (string) ($task['reseau_cible'] ?? $task['canal_principal'] ?? ''),
            'statut' => 'En production',
            'responsable' => '',
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function updateDeliverablePublicationSchedule($deliverableId, $planId, $scheduledDate) {
        $deliverableId = (int) $deliverableId;
        $planId = (int) $planId;
        $scheduledDate = trim((string) $scheduledDate);

        if ($deliverableId <= 0 || $planId <= 0 || $scheduledDate === '') {
            return;
        }

        $deliverableStmt = $this->db->prepare('UPDATE livrable_items SET date_prevue = :date_prevue WHERE id = :id');
        $deliverableStmt->execute([
            'date_prevue' => $scheduledDate,
            'id' => $deliverableId,
        ]);

        $taskStmt = $this->db->prepare("UPDATE taches_pipeline
            SET deadline = :deadline
            WHERE livrable_item_id = :deliverable_id
              AND type_tache = 'Publication'");
        $taskStmt->execute([
            'deadline' => $scheduledDate,
            'deliverable_id' => $deliverableId,
        ]);
    }

    public function createContentResultEntry($taskId, array $payload) {
        $task = $this->getTaskWorkspace($taskId);
        if (!$task || empty($task['content_id'])) {
            throw new RuntimeException('Impossible d enregistrer un resultat sans contenu associe.');
        }

        $reseauCollecte = trim((string) ($payload['reseau_collecte'] ?? ''));
        if ($reseauCollecte === '' && !empty($payload['metric_snapshot'])) {
            $snapshot = json_decode((string) $payload['metric_snapshot'], true);
            if (is_array($snapshot)) {
                $reseauCollecte = strtolower(trim((string) ($snapshot['reseau'] ?? '')));
            }
        }

        $existingId = 0;
        if ($this->hasResultNetworkColumn() && $reseauCollecte !== '') {
            $findStmt = $this->db->prepare("SELECT id FROM contenu_resultats
                WHERE task_id = :task_id
                  AND date_collecte = :date_collecte
                  AND reseau_collecte = :reseau_collecte
                ORDER BY id DESC
                LIMIT 1");
            $findStmt->execute([
                'task_id' => $taskId,
                'date_collecte' => $payload['date_collecte'],
                'reseau_collecte' => $reseauCollecte,
            ]);
            $existingId = (int) ($findStmt->fetchColumn() ?: 0);
        }

        if ($existingId > 0) {
            if ($this->hasResultNetworkColumn()) {
                $updateStmt = $this->db->prepare("UPDATE contenu_resultats
                    SET periode_label = :periode_label,
                        valeur_cle = :valeur_cle,
                        metric_snapshot = :metric_snapshot,
                        note = :note,
                        reseau_collecte = :reseau_collecte
                    WHERE id = :id");
                $updateStmt->execute([
                    'id' => $existingId,
                    'periode_label' => $payload['periode_label'],
                    'valeur_cle' => $payload['valeur_cle'],
                    'metric_snapshot' => $payload['metric_snapshot'],
                    'note' => $payload['note'],
                    'reseau_collecte' => $reseauCollecte,
                ]);
            } else {
                $updateStmt = $this->db->prepare("UPDATE contenu_resultats
                    SET periode_label = :periode_label,
                        valeur_cle = :valeur_cle,
                        metric_snapshot = :metric_snapshot,
                        note = :note
                    WHERE id = :id");
                $updateStmt->execute([
                    'id' => $existingId,
                    'periode_label' => $payload['periode_label'],
                    'valeur_cle' => $payload['valeur_cle'],
                    'metric_snapshot' => $payload['metric_snapshot'],
                    'note' => $payload['note'],
                ]);
            }

            $this->notifyKpiApi($task, $payload, $existingId);
            return $existingId;
        }

        if ($this->hasResultNetworkColumn()) {
            $stmt = $this->db->prepare("INSERT INTO contenu_resultats
                (contenu_id, task_id, date_collecte, periode_label, valeur_cle, metric_snapshot, note, reseau_collecte)
                VALUES (:contenu_id, :task_id, :date_collecte, :periode_label, :valeur_cle, :metric_snapshot, :note, :reseau_collecte)");
            $stmt->execute([
                'contenu_id' => $task['content_id'],
                'task_id' => $taskId,
                'date_collecte' => $payload['date_collecte'],
                'periode_label' => $payload['periode_label'],
                'valeur_cle' => $payload['valeur_cle'],
                'metric_snapshot' => $payload['metric_snapshot'],
                'note' => $payload['note'],
                'reseau_collecte' => $reseauCollecte,
            ]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO contenu_resultats
                (contenu_id, task_id, date_collecte, periode_label, valeur_cle, metric_snapshot, note)
                VALUES (:contenu_id, :task_id, :date_collecte, :periode_label, :valeur_cle, :metric_snapshot, :note)");
            $stmt->execute([
                'contenu_id' => $task['content_id'],
                'task_id' => $taskId,
                'date_collecte' => $payload['date_collecte'],
                'periode_label' => $payload['periode_label'],
                'valeur_cle' => $payload['valeur_cle'],
                'metric_snapshot' => $payload['metric_snapshot'],
                'note' => $payload['note'],
            ]);
        }

        $createdId = (int) $this->db->lastInsertId();
        $this->notifyKpiApi($task, $payload, $createdId);
        return $createdId;
    }

    public function createPublicValidationLink($planId, $createdBy = null, array $deliverableIds = [], $expiryDays = 45) {
        $planId = (int) $planId;
        if ($planId <= 0) {
            throw new RuntimeException('Plan mensuel invalide pour le lien public.');
        }

        $expiryDays = (int) $expiryDays;
        if ($expiryDays <= 0) {
            $expiryDays = 45;
        }
        if ($expiryDays > 365) {
            $expiryDays = 365;
        }

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days'));
        $stmt = $this->db->prepare('INSERT INTO public_validation_links (plan_mensuel_id, token, created_by, expires_at) VALUES (:plan_id, :token, :created_by, :expires_at)');
        $stmt->execute([
            'plan_id' => $planId,
            'token' => $token,
            'created_by' => !empty($createdBy) ? (int) $createdBy : null,
            'expires_at' => $expiresAt,
        ]);

        $linkId = (int) $this->db->lastInsertId();
        $readyMap = [];
        foreach ($this->getReadyDeliverablesForClientValidation($planId) as $item) {
            $readyMap[(int) ($item['deliverable_id'] ?? 0)] = true;
        }

        $insertItem = $this->db->prepare('INSERT INTO public_validation_link_items (link_id, deliverable_item_id) VALUES (:link_id, :deliverable_id)');
        foreach ($deliverableIds as $deliverableId) {
            $deliverableId = (int) $deliverableId;
            if ($deliverableId <= 0 || !isset($readyMap[$deliverableId])) {
                continue;
            }
            $insertItem->execute([
                'link_id' => $linkId,
                'deliverable_id' => $deliverableId,
            ]);
        }

        return $token;
    }

    public function getPublicValidationLinksByPlan($planId) {
        $stmt = $this->db->prepare("SELECT pvl.*, u.nom AS created_by_name,
                (SELECT COUNT(*) FROM public_validation_link_items i WHERE i.link_id = pvl.id) AS selected_deliverables
            FROM public_validation_links pvl
            LEFT JOIN users u ON u.id = pvl.created_by
            WHERE pvl.plan_mensuel_id = :plan_id
            ORDER BY pvl.date_creation DESC");
        $stmt->execute(['plan_id' => (int) $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revokePublicValidationLink($linkId) {
        $stmt = $this->db->prepare('UPDATE public_validation_links SET revoked_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => (int) $linkId]);
    }

    public function getReadyDeliverablesForClientValidation($planId) {
        $stmt = $this->db->prepare("SELECT li.id AS deliverable_id, li.titre, li.type_livrable, li.date_prevue,
                tvc.id AS task_id, tvc.statut, tvc.validation_decision, tvc.note_sur_10,
                COALESCE(ct.reseau_cible, p.canal_principal, 'Non defini') AS canal
            FROM livrable_items li
            JOIN projets p ON p.id = li.projet_id
            JOIN taches_pipeline tvi ON tvi.livrable_item_id = li.id AND tvi.type_tache = 'Validation interne'
            JOIN taches_pipeline tvc ON tvc.livrable_item_id = li.id AND tvc.type_tache = 'Validation client'
            LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
            WHERE li.plan_mensuel_id = :plan_id
              AND tvi.statut = 'Terminee'
              AND tvc.statut <> 'Bloquee'
            ORDER BY li.date_prevue ASC, li.numero_ordre ASC");
        $stmt->execute(['plan_id' => (int) $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPublicValidationWorkspace($token) {
        $stmt = $this->db->prepare("SELECT pvl.id, pvl.plan_mensuel_id, pvl.expires_at,
            pvl.revoked_at,
                pm.periode_mois, pm.index_mois,
                p.id AS projet_id, p.nom AS projet_nom,
                c.entreprise AS client_nom, c.email AS client_email
            FROM public_validation_links pvl
            JOIN plans_mensuels pm ON pm.id = pvl.plan_mensuel_id
            JOIN projets p ON p.id = pm.projet_id
            JOIN clients c ON c.id = p.client_id
            WHERE pvl.token = :token
            LIMIT 1");
        $stmt->execute(['token' => (string) $token]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$link) {
            return null;
        }

        if (!empty($link['expires_at']) && strtotime((string) $link['expires_at']) < time()) {
            return null;
        }
        if (!empty($link['revoked_at'])) {
            return null;
        }

        $selectedStmt = $this->db->prepare('SELECT deliverable_item_id FROM public_validation_link_items WHERE link_id = :link_id');
        $selectedStmt->execute(['link_id' => (int) $link['id']]);
        $selectedIds = array_map('intval', $selectedStmt->fetchAll(PDO::FETCH_COLUMN));

        $items = $this->getReadyDeliverablesForClientValidation((int) $link['plan_mensuel_id']);
        if (!empty($selectedIds)) {
            $selectedLookup = array_fill_keys($selectedIds, true);
            $items = array_values(array_filter($items, static function ($item) use ($selectedLookup) {
                return isset($selectedLookup[(int) ($item['deliverable_id'] ?? 0)]);
            }));
        }

        foreach ($items as &$item) {
            $item['files'] = $this->collectPublicDeliverableFiles((int) ($item['deliverable_id'] ?? 0));
        }

        $link['items'] = $items;
        return $link;
    }

    public function applyPublicValidationDecision($token, $taskId, $decision, $comment, $score = null) {
        $workspace = $this->getPublicValidationWorkspace($token);
        if (!$workspace) {
            throw new RuntimeException('Lien public invalide ou expire.');
        }

        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            throw new RuntimeException('Tache de validation client invalide.');
        }
        $allowedTaskIds = array_map(static function ($item) {
            return (int) ($item['task_id'] ?? 0);
        }, (array) ($workspace['items'] ?? []));
        if (!in_array($taskId, $allowedTaskIds, true)) {
            throw new RuntimeException('Ce livrable n est pas expose par ce lien public.');
        }

        $decision = trim((string) $decision);
        if (!in_array($decision, ['Valide', 'Non valide'], true)) {
            throw new RuntimeException('Decision invalide.');
        }

        if ($score !== null && $score !== '') {
            $score = (int) $score;
            if ($score < 0 || $score > 10) {
                throw new RuntimeException('La note client doit etre comprise entre 0 et 10.');
            }
        } else {
            $score = null;
        }

        $comment = trim((string) $comment);
        $this->logValidationDecision($workspace, $taskId, $decision, $comment, 'public');

        $stmt = $this->db->prepare("UPDATE taches_pipeline
            SET validation_decision = :decision,
                note_sur_10 = :score,
                validation_commentaire = :comment,
                statut = :statut
            WHERE id = :id
              AND type_tache = 'Validation client'");
        $stmt->execute([
            'decision' => $decision,
            'score' => $score,
            'comment' => $comment,
            'statut' => $decision === 'Valide' ? 'Terminee' : 'En cours',
            'id' => $taskId,
        ]);

        if ($decision === 'Valide') {
            PipelineService::unlockChildren($taskId);
        }

        if ($decision === 'Non valide') {
            $deliverableStmt = $this->db->prepare('SELECT livrable_item_id FROM taches_pipeline WHERE id = :id LIMIT 1');
            $deliverableStmt->execute(['id' => $taskId]);
            $deliverableId = (int) $deliverableStmt->fetchColumn();
            if ($deliverableId > 0) {
                $this->markCreativeTaskAsInvalidForDeliverable($deliverableId);
                $this->syncDeliverableStatus($deliverableId);
            }
        }

        $submittedItem = null;
        foreach ((array) ($workspace['items'] ?? []) as $item) {
            if ((int) ($item['task_id'] ?? 0) === $taskId) {
                $submittedItem = $item;
                break;
            }
        }

        return [
            'client_name' => (string) ($workspace['client_nom'] ?? ''),
            'client_email' => (string) ($workspace['client_email'] ?? ''),
            'project_name' => (string) ($workspace['projet_nom'] ?? ''),
            'period_label' => date('F Y', strtotime((string) ($workspace['periode_mois'] ?? date('Y-m-01')))),
            'deliverable_title' => (string) ($submittedItem['titre'] ?? 'Livrable'),
            'decision' => $decision,
            'score' => $score,
            'comment' => $comment,
            'task_id' => $taskId,
        ];
    }

    public function recordInternalValidationDecision($taskId, $decision, $comment = '') {
        $taskId = (int) $taskId;
        $decision = trim((string) $decision);
        if ($taskId <= 0 || !in_array($decision, ['Valide', 'Non valide'], true)) {
            return;
        }

        $this->logValidationDecision([], $taskId, $decision, trim((string) $comment), 'internal');
    }

    public function getPublicationCalendarByPlan($planId) {
        $stmt = $this->db->prepare("SELECT li.date_prevue,
                COALESCE(ct.reseau_cible, p.canal_principal, 'Non defini') AS canal,
                COUNT(*) AS total
            FROM livrable_items li
            JOIN projets p ON p.id = li.projet_id
            LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
            WHERE li.plan_mensuel_id = :plan_id
              AND li.date_prevue IS NOT NULL
              AND li.date_prevue <> ''
            GROUP BY li.date_prevue, COALESCE(ct.reseau_cible, p.canal_principal, 'Non defini')
            ORDER BY li.date_prevue ASC, canal ASC");
        $stmt->execute(['plan_id' => (int) $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPublicationCalendarItemsByPlan($planId) {
        $stmt = $this->db->prepare("SELECT li.id AS deliverable_id,
                li.titre,
                li.type_livrable,
                li.date_prevue,
                COALESCE(ct.reseau_cible, p.canal_principal, 'Non defini') AS canal,
                tp.id AS publication_task_id
            FROM livrable_items li
            JOIN projets p ON p.id = li.projet_id
            LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
            LEFT JOIN taches_pipeline tp ON tp.livrable_item_id = li.id AND tp.type_tache = 'Publication'
            WHERE li.plan_mensuel_id = :plan_id
              AND li.date_prevue IS NOT NULL
              AND li.date_prevue <> ''
            ORDER BY li.date_prevue ASC, li.numero_ordre ASC, li.id ASC");
        $stmt->execute(['plan_id' => (int) $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function moveDeliverablePublicationDate($deliverableId, $newDate) {
        $deliverableId = (int) $deliverableId;
        $newDate = trim((string) $newDate);
        if ($deliverableId <= 0 || $newDate === '') {
            throw new RuntimeException('Livrable ou date invalide.');
        }

        $stmt = $this->db->prepare('SELECT plan_mensuel_id FROM livrable_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $deliverableId]);
        $planId = (int) $stmt->fetchColumn();
        if ($planId <= 0) {
            throw new RuntimeException('Plan mensuel introuvable pour ce livrable.');
        }

        $this->updateDeliverablePublicationSchedule($deliverableId, $planId, $newDate);
    }

    public function getApiEventLogs($projectId, $limit = 30, array $filters = []) {
        TenantGuard::assertProject((int)$projectId);
        $limit = max(1, min(200, (int) $limit));
        $sql = "SELECT *
            FROM api_event_logs
            WHERE project_id = :project_id";
        $params = ['project_id' => (int) $projectId];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'success') {
            $sql .= ' AND success = 1';
        } elseif ($status === 'failure') {
            $sql .= ' AND success = 0';
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $sql .= ' AND integration = :integration';
            $params['integration'] = $type;
        }

        $fromDate = trim((string) ($filters['from'] ?? ''));
        if ($fromDate !== '') {
            $sql .= ' AND DATE(created_at) >= :from_date';
            $params['from_date'] = $fromDate;
        }

        $toDate = trim((string) ($filters['to'] ?? ''));
        if ($toDate !== '') {
            $sql .= ' AND DATE(created_at) <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGlobalApiEventLogs($page = 1, $perPage = 40, array $filters = [], array $currentUser = null) {
        $page = max(1, (int) $page);
        $perPage = max(10, min(200, (int) $perPage));
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT log.*, p.nom AS projet_nom, c.entreprise AS client_nom
            FROM api_event_logs log
            LEFT JOIN projets p ON p.id = log.project_id
            LEFT JOIN clients c ON c.id = p.client_id
            LEFT JOIN taches_pipeline tp ON tp.id = log.task_id
            WHERE 1=1";
        $params = [];

        $accessScope=AgencyAccessPolicy::clientSqlScope('c','projects','api_logs');
        $sql.=' AND '.$accessScope['sql'];
        $params=array_merge($params,$accessScope['params']);

        if (UserScope::isScopedOperationalUser($currentUser)) {
            $sql .= ' AND (' . $this->buildProjectScopeCondition('p', 'tp') . ')';
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'success') {
                $sql .= ' AND log.success = 1';
            } elseif ($filters['status'] === 'failure') {
                $sql .= ' AND log.success = 0';
            }
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND log.integration = :integration';
            $params['integration'] = (string) $filters['type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(log.created_at) >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(log.created_at) <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }

        $sql .= ' ORDER BY log.created_at DESC, log.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countGlobalApiEventLogs(array $filters = [], array $currentUser = null) {
        $sql = "SELECT COUNT(*)
            FROM api_event_logs log
            LEFT JOIN projets p ON p.id = log.project_id
            LEFT JOIN clients c ON c.id = p.client_id
            LEFT JOIN taches_pipeline tp ON tp.id = log.task_id
            WHERE 1=1";
        $params = [];

        $accessScope=AgencyAccessPolicy::clientSqlScope('c','projects','api_logs_count');
        $sql.=' AND '.$accessScope['sql'];
        $params=array_merge($params,$accessScope['params']);

        if (UserScope::isScopedOperationalUser($currentUser)) {
            $sql .= ' AND (' . $this->buildProjectScopeCondition('p', 'tp') . ')';
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'success') {
                $sql .= ' AND log.success = 1';
            } elseif ($filters['status'] === 'failure') {
                $sql .= ' AND log.success = 0';
            }
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND log.integration = :integration';
            $params['integration'] = (string) $filters['type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(log.created_at) >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(log.created_at) <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getSiblingTaskNavigation($taskId, $taskType, $planId, array $currentUser = null) {
        $taskId = (int) $taskId;
        $planId = (int) $planId;
        $taskType = trim((string) $taskType);
        if ($taskId <= 0 || $planId <= 0 || $taskType === '') {
            return ['previous' => null, 'next' => null];
        }

        $sql = "SELECT tp.id, li.numero_ordre, li.type_livrable
            FROM taches_pipeline tp
            LEFT JOIN livrable_items li ON li.id = tp.livrable_item_id
            WHERE tp.plan_mensuel_id = :plan_id
              AND tp.type_tache = :task_type";
        $params = ['plan_id' => $planId, 'task_type' => $taskType];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $sql .= " AND tp.auteur_id = :scope_user_id AND tp.statut <> 'Bloquee'";
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $sql .= " ORDER BY FIELD(li.type_livrable, 'Video', 'Visuel'), li.numero_ordre ASC, tp.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $taskIds = array_values(array_map(static function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $rows));
        $index = array_search($taskId, $taskIds, true);

        return [
            'previous' => ($index !== false && $index > 0) ? (int) $taskIds[$index - 1] : null,
            'next' => ($index !== false && $index < count($taskIds) - 1) ? (int) $taskIds[$index + 1] : null,
        ];
    }

    public function getSiblingContentNavigation($deliverableId, $planId, array $currentUser = null) {
        $deliverableId = (int) $deliverableId;
        $planId = (int) $planId;
        if ($deliverableId <= 0 || $planId <= 0) {
            return ['previous' => null, 'next' => null];
        }

        $sql = "SELECT li.id
            FROM livrable_items li
            WHERE li.plan_mensuel_id = :plan_id";
        $params = ['plan_id' => $planId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $sql .= " AND EXISTS (SELECT 1 FROM taches_pipeline tp WHERE tp.livrable_item_id = li.id AND tp.auteur_id = :scope_user_id AND tp.statut <> 'Bloquee')";
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $sql .= " ORDER BY FIELD(li.type_livrable, 'Video', 'Visuel'), li.numero_ordre ASC, li.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $ids = array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        $index = array_search($deliverableId, $ids, true);

        return [
            'previous' => ($index !== false && $index > 0) ? (int) $ids[$index - 1] : null,
            'next' => ($index !== false && $index < count($ids) - 1) ? (int) $ids[$index + 1] : null,
        ];
    }

    public function getAllClientsSimple() {
        $scope=AgencyAccessPolicy::clientSqlScope('c','projects','calendar_client_options');
        $stmt=$this->db->prepare('SELECT c.id,c.entreprise FROM clients c WHERE '.$scope['sql'].' ORDER BY c.entreprise ASC');
        $stmt->execute($scope['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPersonaOptionsByClient($clientId, $selectedPersonaId = 0) {
        $clientId = (int) $clientId;
        $selectedPersonaId = (int) $selectedPersonaId;

        $options = [];
        if ($clientId > 0) {
            $stmt = $this->db->prepare('SELECT id, nom_persona FROM personas WHERE client_id = :client_id ORDER BY nom_persona ASC');
            $stmt->execute(['client_id' => $clientId]);
            $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        return $options;
    }

    public function createDocumentationFile(array $payload) {
        $stmt = $this->db->prepare("INSERT INTO documentation_files
            (client_id, titre, categorie, fichier_path, fichier_nom, date_document, created_by)
            VALUES (:client_id, :titre, :categorie, :fichier_path, :fichier_nom, :date_document, :created_by)");
        $stmt->execute([
            'client_id' => !empty($payload['client_id']) ? (int) $payload['client_id'] : null,
            'titre' => trim((string) ($payload['titre'] ?? 'Document')),
            'categorie' => trim((string) ($payload['categorie'] ?? 'General')),
            'fichier_path' => (string) ($payload['fichier_path'] ?? ''),
            'fichier_nom' => (string) ($payload['fichier_nom'] ?? ''),
            'date_document' => trim((string) ($payload['date_document'] ?? '')) ?: null,
            'created_by' => !empty($payload['created_by']) ? (int) $payload['created_by'] : null,
        ]);
    }

    public function getDocumentationFiles(array $filters = [], $page = 1, $perPage = 25) {
        $sql = "SELECT df.*, c.entreprise AS client_nom, u.nom AS created_by_nom
            FROM documentation_files df
            LEFT JOIN clients c ON c.id = df.client_id
            LEFT JOIN users u ON u.id = df.created_by
            WHERE 1=1";
        $params = [];

        if (!empty($filters['client_id'])) {
            $sql .= ' AND df.client_id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(df.created_at) >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(df.created_at) <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }

        $sql .= ' ORDER BY df.created_at DESC, df.id DESC';
        $page = max(1, (int) $page);
        $perPage = max(5, min(100, (int) $perPage));
        $offset = ($page - 1) * $perPage;
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDocumentationFiles(array $filters = []) {
        $sql = "SELECT COUNT(*) FROM documentation_files df WHERE 1=1";
        $params = [];
        if (!empty($filters['client_id'])) {
            $sql .= ' AND df.client_id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(df.created_at) >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(df.created_at) <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getRealisations(array $filters = [], $page = 1, $perPage = 25) {
        $sql = "SELECT li.id AS deliverable_id, li.titre, li.date_prevue, li.type_livrable, li.plan_mensuel_id,
                p.id AS projet_id, p.nom AS projet_nom,
                c.id AS client_id, c.entreprise AS client_nom,
                tvc.id AS validation_task_id, tvc.id AS validation_client_task_id,
                tvi.id AS validation_interne_task_id,
                tvc.validation_decision AS validation_client_decision, tvc.note_sur_10,
                tvi.validation_decision AS validation_interne_decision
            FROM livrable_items li
            JOIN projets p ON p.id = li.projet_id
            JOIN clients c ON c.id = p.client_id
            LEFT JOIN taches_pipeline tvc ON tvc.livrable_item_id = li.id AND tvc.type_tache = 'Validation client'
            LEFT JOIN taches_pipeline tvi ON tvi.livrable_item_id = li.id AND tvi.type_tache = 'Validation interne'
            WHERE (
                (li.pieces_jointes IS NOT NULL AND li.pieces_jointes NOT IN ('', '[]', 'null'))
                OR EXISTS (
                    SELECT 1 FROM taches_pipeline tp2
                    WHERE tp2.livrable_item_id = li.id
                      AND tp2.fichiers_livres IS NOT NULL
                      AND tp2.fichiers_livres NOT IN ('', '[]', 'null')
                )
                OR EXISTS (
                    SELECT 1 FROM briefs b2
                    WHERE b2.livrable_item_id = li.id
                      AND b2.pieces_jointes IS NOT NULL
                      AND b2.pieces_jointes NOT IN ('', '[]', 'null')
                )
            )";
        $params = [];

        if (!empty($filters['client_id'])) {
            $sql .= ' AND c.id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND li.date_prevue >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND li.date_prevue <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }

        $validationInterne = trim((string) ($filters['validation_interne'] ?? ''));
        if ($validationInterne === 'Valide' || $validationInterne === 'Non valide') {
            $sql .= ' AND tvi.validation_decision = :vi_decision';
            $params['vi_decision'] = $validationInterne;
        } elseif ($validationInterne === 'En attente') {
            $sql .= " AND (tvi.validation_decision IS NULL OR tvi.validation_decision = '')";
        }

        $validationClient = trim((string) ($filters['validation_client'] ?? ''));
        if ($validationClient === 'Valide' || $validationClient === 'Non valide') {
            $sql .= ' AND tvc.validation_decision = :vc_decision';
            $params['vc_decision'] = $validationClient;
        } elseif ($validationClient === 'En attente') {
            $sql .= " AND (tvc.validation_decision IS NULL OR tvc.validation_decision = '')";
        }

        $sort = trim((string) ($filters['sort'] ?? 'date_desc'));
        if ($sort === 'note_desc') {
            $sql .= ' ORDER BY (tvc.note_sur_10 IS NULL) ASC, tvc.note_sur_10 DESC, li.date_prevue DESC';
        } elseif ($sort === 'note_asc') {
            $sql .= ' ORDER BY (tvc.note_sur_10 IS NULL) ASC, tvc.note_sur_10 ASC, li.date_prevue DESC';
        } elseif ($sort === 'date_asc') {
            $sql .= ' ORDER BY li.date_prevue ASC, c.entreprise ASC, p.nom ASC';
        } else {
            $sql .= ' ORDER BY li.date_prevue DESC, c.entreprise ASC, p.nom ASC';
        }

        $page = max(1, (int) $page);
        $perPage = max(5, min(100, (int) $perPage));
        $offset = ($page - 1) * $perPage;
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['files'] = $this->collectPublicDeliverableFiles((int) ($row['deliverable_id'] ?? 0));
        }

        return $rows;
    }

    public function countRealisations(array $filters = []) {
        $sql = "SELECT COUNT(*) FROM livrable_items li
            JOIN projets p ON p.id = li.projet_id
            JOIN clients c ON c.id = p.client_id
            LEFT JOIN taches_pipeline tvc ON tvc.livrable_item_id = li.id AND tvc.type_tache = 'Validation client'
            LEFT JOIN taches_pipeline tvi ON tvi.livrable_item_id = li.id AND tvi.type_tache = 'Validation interne'
            WHERE (
                (li.pieces_jointes IS NOT NULL AND li.pieces_jointes NOT IN ('', '[]', 'null'))
                OR EXISTS (
                    SELECT 1 FROM taches_pipeline tp2
                    WHERE tp2.livrable_item_id = li.id
                      AND tp2.fichiers_livres IS NOT NULL
                      AND tp2.fichiers_livres NOT IN ('', '[]', 'null')
                )
                OR EXISTS (
                    SELECT 1 FROM briefs b2
                    WHERE b2.livrable_item_id = li.id
                      AND b2.pieces_jointes IS NOT NULL
                      AND b2.pieces_jointes NOT IN ('', '[]', 'null')
                )
            )";
        $params = [];

        if (!empty($filters['client_id'])) {
            $sql .= ' AND c.id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND li.date_prevue >= :from_date';
            $params['from_date'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND li.date_prevue <= :to_date';
            $params['to_date'] = (string) $filters['to'];
        }

        $validationInterne = trim((string) ($filters['validation_interne'] ?? ''));
        if ($validationInterne === 'Valide' || $validationInterne === 'Non valide') {
            $sql .= ' AND tvi.validation_decision = :vi_decision';
            $params['vi_decision'] = $validationInterne;
        } elseif ($validationInterne === 'En attente') {
            $sql .= " AND (tvi.validation_decision IS NULL OR tvi.validation_decision = '')";
        }

        $validationClient = trim((string) ($filters['validation_client'] ?? ''));
        if ($validationClient === 'Valide' || $validationClient === 'Non valide') {
            $sql .= ' AND tvc.validation_decision = :vc_decision';
            $params['vc_decision'] = $validationClient;
        } elseif ($validationClient === 'En attente') {
            $sql .= " AND (tvc.validation_decision IS NULL OR tvc.validation_decision = '')";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function generatePublicLinksBySelectedDeliverables(array $deliverableIds, $createdBy = null, $expiryDays = 45) {
        $deliverableIds = array_values(array_unique(array_map('intval', $deliverableIds)));
        if (empty($deliverableIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($deliverableIds), '?'));
        $stmt = $this->db->prepare("SELECT li.id, li.plan_mensuel_id, c.entreprise AS client_nom
            FROM livrable_items li
            JOIN projets p ON p.id = li.projet_id
            JOIN clients c ON c.id = p.client_id
            WHERE li.id IN ($placeholders)");
        $stmt->execute($deliverableIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byPlan = [];
        foreach ($rows as $row) {
            $planId = (int) ($row['plan_mensuel_id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }
            if (!isset($byPlan[$planId])) {
                $byPlan[$planId] = [
                    'deliverable_ids' => [],
                    'client_nom' => (string) ($row['client_nom'] ?? 'Client'),
                ];
            }
            $byPlan[$planId]['deliverable_ids'][] = (int) ($row['id'] ?? 0);
        }

        $createdLinks = [];
        foreach ($byPlan as $planId => $group) {
            $token = $this->createPublicValidationLink((int) $planId, $createdBy, $group['deliverable_ids'], $expiryDays);
            $createdLinks[] = [
                'plan_id' => (int) $planId,
                'client_nom' => (string) ($group['client_nom'] ?? 'Client'),
                'url' => route_url('/public-validation/index/' . $token),
                'deliverables_count' => count($group['deliverable_ids']),
            ];
        }

        return $createdLinks;
    }

    public function getDeliverableFilesForBundle(array $deliverableIds) {
        $deliverableIds = array_values(array_unique(array_map('intval', $deliverableIds)));
        $bundle = [];
        foreach ($deliverableIds as $deliverableId) {
            if ($deliverableId <= 0) {
                continue;
            }
            $workspace = $this->getDeliverableWorkspace($deliverableId);
            if (!$workspace) {
                continue;
            }
            $title = (string) ($workspace['titre'] ?? ('livrable-' . $deliverableId));
            foreach ($this->collectPublicDeliverableFiles($deliverableId) as $file) {
                $bundle[] = [
                    'deliverable_id' => $deliverableId,
                    'deliverable_title' => $title,
                    'name' => (string) ($file['name'] ?? 'fichier'),
                    'path' => (string) ($file['path'] ?? ''),
                ];
            }
        }
        return $bundle;
    }

    public function getExportableCalendars(array $filters = []) {
        $sql = "SELECT pm.id AS plan_id, pm.periode_mois, pm.index_mois,
                       p.nom AS projet_nom, p.id AS projet_id,
                       c.id AS client_id, c.entreprise AS client_nom
                FROM plans_mensuels pm
                JOIN projets p ON p.id = pm.projet_id
                JOIN clients c ON c.id = p.client_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['client_id'])) {
            $sql .= ' AND c.id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND pm.periode_mois >= :from_month';
            $params['from_month'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND pm.periode_mois <= :to_month';
            $params['to_month'] = (string) $filters['to'];
        }

        $sql .= ' ORDER BY pm.periode_mois DESC, c.entreprise ASC, p.nom ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCalendarExportRows(array $planIds, $includeScripts = false) {
        $planIds = array_values(array_unique(array_map('intval', $planIds)));
        if (empty($planIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($planIds), '?'));
        $sql = "SELECT c.entreprise AS client, p.nom AS projet, pm.periode_mois,
                       li.titre, li.type_livrable, li.date_prevue,
                       COALESCE(ct.reseau_cible, p.canal_principal, '') AS reseau,
                  ct.sujet, ct.message,
                   ct.objectif_publication AS impact_global,
                       b.texte_script, b.plan_script, b.details_message
                FROM livrable_items li
                JOIN plans_mensuels pm ON pm.id = li.plan_mensuel_id
                JOIN projets p ON p.id = li.projet_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
                LEFT JOIN briefs b ON b.livrable_item_id = li.id
                WHERE li.plan_mensuel_id IN ($placeholders)
                ORDER BY pm.periode_mois ASC, p.nom ASC, li.numero_ordre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($planIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$includeScripts) {
            foreach ($rows as &$row) {
                $row['texte_script'] = '';
                $row['plan_script'] = '';
            }
        }

        return $rows;
    }

    public function getScriptsExportRows(array $planIds) {
        $rows = $this->getCalendarExportRows($planIds, true);
        $scripts = [];

        foreach ($rows as $row) {
            $textScript = trim((string) ($row['texte_script'] ?? ''));
            $planScript = trim((string) ($row['plan_script'] ?? ''));
            $legacyDetails = trim((string) ($row['details_message'] ?? ''));
            $scriptContent = $textScript !== ''
                ? $textScript
                : ($planScript !== '' ? $planScript : $legacyDetails);

            // Scripts export should prioritize video scripts and keep only rows with actual script content.
            if ((string) ($row['type_livrable'] ?? '') !== 'Video' || $scriptContent === '') {
                continue;
            }

            $row['script_contenu'] = $scriptContent;
            $scripts[] = $row;
        }

        return $scripts;
    }

    public function getReportsExportData(array $planIds) {
        $rows = $this->getCalendarExportRows($planIds, true);

        $global = [
            'total_contenus' => count($rows),
            'videos' => 0,
            'visuels' => 0,
            'unknown_type' => 0,
            'with_message' => 0,
            'with_script' => 0,
            'scheduled' => 0,
            'network_count' => 0,
            'message_rate' => 0,
            'script_rate' => 0,
        ];
        $networkSet = [];
        foreach ($rows as $row) {
            $type = (string) ($row['type_livrable'] ?? '');
            if ($type === 'Video') {
                $global['videos']++;
            } elseif ($type === 'Visuel') {
                $global['visuels']++;
            } else {
                $global['unknown_type']++;
            }

            if (trim((string) ($row['message'] ?? '')) !== '') {
                $global['with_message']++;
            }
            if (trim((string) ($row['plan_script'] ?? '')) !== '' || trim((string) ($row['texte_script'] ?? '')) !== '') {
                $global['with_script']++;
            }

            $scheduledDate = trim((string) ($row['date_prevue'] ?? ''));
            if ($scheduledDate !== '') {
                $global['scheduled']++;
            }

            $network = trim((string) ($row['reseau'] ?? ''));
            if ($network !== '') {
                $networkSet[$network] = true;
            }
        }
        $global['network_count'] = count($networkSet);
        $global['message_rate'] = $global['total_contenus'] > 0 ? round(($global['with_message'] / $global['total_contenus']) * 100, 1) : 0;
        $global['script_rate'] = $global['videos'] > 0 ? round(($global['with_script'] / $global['videos']) * 100, 1) : 0;

        $byPublication = [];
        $byNetwork = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row['titre'] ?? 'Sans titre'));
            $network = trim((string) ($row['reseau'] ?? 'Non defini'));
            $type = trim((string) ($row['type_livrable'] ?? 'Inconnu'));

            $byPublication[] = [
                'titre' => $title,
                'type_livrable' => $type,
                'reseau' => $network,
                'date_prevue' => trim((string) ($row['date_prevue'] ?? '')),
                'impact_global' => trim((string) ($row['impact_global'] ?? '')),
                'message' => trim((string) ($row['message'] ?? '')),
                'script_contenu' => trim((string) ($row['texte_script'] ?? '')) !== ''
                    ? trim((string) ($row['texte_script'] ?? ''))
                    : trim((string) ($row['plan_script'] ?? '')),
            ];

            if (!isset($byNetwork[$network])) {
                $byNetwork[$network] = [
                    'reseau' => $network,
                    'total' => 0,
                    'videos' => 0,
                    'visuels' => 0,
                    'with_message' => 0,
                ];
            }
            $byNetwork[$network]['total']++;
            if ($type === 'Video') {
                $byNetwork[$network]['videos']++;
            } else {
                $byNetwork[$network]['visuels']++;
            }
            if (trim((string) ($row['message'] ?? '')) !== '') {
                $byNetwork[$network]['with_message']++;
            }
        }

        $recommendations = [];
        if ($global['total_contenus'] > 0 && $global['with_message'] < $global['total_contenus']) {
            $recommendations[] = 'Completer les messages manquants pour les publications sans copywriting.';
        }
        if ($global['videos'] > 0 && $global['with_script'] < $global['videos']) {
            $recommendations[] = 'Finaliser les scripts video restants avant la production.';
        }
        if (empty($byNetwork)) {
            $recommendations[] = 'Verifier le reseau cible des contenus pour equilibrer la diffusion.';
        } else {
            $largestNetwork = null;
            foreach ($byNetwork as $networkStats) {
                if ($largestNetwork === null || (int) $networkStats['total'] > (int) ($largestNetwork['total'] ?? 0)) {
                    $largestNetwork = $networkStats;
                }
            }
            if ($largestNetwork !== null && count($byNetwork) > 1) {
                $recommendations[] = 'Le reseau ' . (string) ($largestNetwork['reseau'] ?? 'principal') . ' concentre le plus de contenus: envisager un meilleur mix cross-canal.';
            }
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Plan editorial bien renseigne: maintenir le rythme et suivre les retours performance par reseau.';
        }

        return [
            'global' => $global,
            'by_publication' => $byPublication,
            'by_network' => array_values($byNetwork),
            'recommendations' => $recommendations,
            'items' => $rows,
        ];
    }

    private function getValidationFirstPassRate($planId = null, $projectScopeSql = '', array $projectScopeParams = []) {
        $params = [];
        $planCondition = '';

        if ((int) $planId > 0) {
            $planCondition = ' AND task.plan_mensuel_id = :plan_id';
            $params['plan_id'] = (int) $planId;
        }

        $scopeCondition = '';
        if ($projectScopeSql !== '') {
            $scopeCondition = str_replace('tp.', 'task.', $projectScopeSql);
            $params = array_merge($params, $projectScopeParams);
        }

        $sql = "SELECT first_logs.decision, COUNT(*) AS total
            FROM (
                SELECT vdl.task_id, MIN(vdl.created_at) AS first_at
                FROM validation_decision_logs vdl
                JOIN taches_pipeline task ON task.id = vdl.task_id
                JOIN projets p ON p.id = task.projet_id
                JOIN clients c ON c.id = p.client_id
                WHERE 1=1" . $planCondition . $scopeCondition . "
                GROUP BY vdl.task_id
            ) first_decisions
            JOIN validation_decision_logs first_logs
              ON first_logs.task_id = first_decisions.task_id
             AND first_logs.created_at = first_decisions.first_at
            GROUP BY first_logs.decision";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        $validCount = 0;
        foreach ($rows as $row) {
            $count = (int) ($row['total'] ?? 0);
            $total += $count;
            if ((string) ($row['decision'] ?? '') === 'Valide') {
                $validCount += $count;
            }
        }

        return $total > 0 ? round(($validCount / $total) * 100, 1) : 0;
    }

    private function getInvalidationRatioByMonth($projectId = null, $planId = null, $limit = 6, $projectScopeSql = '', array $projectScopeParams = []) {
        $limit = max(1, min(24, (int) $limit));
        $params = [];

        $sql = "SELECT DATE_FORMAT(vdl.created_at, '%Y-%m') AS month_key,
                       SUM(CASE WHEN vdl.decision = 'Non valide' THEN 1 ELSE 0 END) AS invalid_count,
                       COUNT(*) AS total_count
                FROM validation_decision_logs vdl
                JOIN taches_pipeline tp ON tp.id = vdl.task_id
                JOIN projets p ON p.id = tp.projet_id
                JOIN clients c ON c.id = p.client_id
                WHERE 1=1";

        if ((int) $projectId > 0) {
            $sql .= ' AND tp.projet_id = :project_id';
            $params['project_id'] = (int) $projectId;
        }
        if ((int) $planId > 0) {
            $sql .= ' AND tp.plan_mensuel_id = :plan_id';
            $params['plan_id'] = (int) $planId;
        }
        if ($projectScopeSql !== '') {
            $sql .= str_replace('tp.', 'tp.', $projectScopeSql);
            $params = array_merge($params, $projectScopeParams);
        }

        $sql .= " GROUP BY DATE_FORMAT(vdl.created_at, '%Y-%m')
                  ORDER BY month_key DESC
                  LIMIT " . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach (array_reverse($rows) as $row) {
            $total = (int) ($row['total_count'] ?? 0);
            $invalid = (int) ($row['invalid_count'] ?? 0);
            $result[] = [
                'month' => (string) ($row['month_key'] ?? ''),
                'ratio' => $total > 0 ? round(($invalid / $total) * 100, 1) : 0,
                'invalid_count' => $invalid,
                'total_count' => $total,
            ];
        }

        return $result;
    }

    private function logValidationDecision(array $workspace, $taskId, $decision, $comment, $source) {
        $taskStmt = $this->db->prepare('SELECT plan_mensuel_id, projet_id, livrable_item_id FROM taches_pipeline WHERE id = :id LIMIT 1');
        $taskStmt->execute(['id' => (int) $taskId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare("INSERT INTO validation_decision_logs
            (task_id, plan_mensuel_id, project_id, deliverable_item_id, source, decision, comment)
            VALUES (:task_id, :plan_id, :project_id, :deliverable_id, :source, :decision, :comment)");
        $stmt->execute([
            'task_id' => (int) $taskId,
            'plan_id' => !empty($task['plan_mensuel_id']) ? (int) $task['plan_mensuel_id'] : (int) ($workspace['plan_mensuel_id'] ?? 0),
            'project_id' => !empty($task['projet_id']) ? (int) $task['projet_id'] : (int) ($workspace['projet_id'] ?? 0),
            'deliverable_id' => !empty($task['livrable_item_id']) ? (int) $task['livrable_item_id'] : null,
            'source' => (string) $source,
            'decision' => (string) $decision,
            'comment' => (string) $comment,
        ]);
    }

    private function collectPublicDeliverableFiles($deliverableId) {
        $workspace = $this->getDeliverableWorkspace((int) $deliverableId);
        if (!$workspace) {
            return [];
        }

        $files = $this->collectDeliverableAssetFiles($workspace);
        $unique = [];
        $seen = [];
        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || isset($seen[$path])) {
                continue;
            }

            $extension = strtolower((string) pathinfo((string) ($file['name'] ?? $path), PATHINFO_EXTENSION));
            if (in_array($extension, ['psd', 'psb'], true)) {
                continue;
            }

            $seen[$path] = true;
            $unique[] = $file;
        }
        return $unique;
    }

    private function notifyPublicationApi(array $task, array $payload, $entryId) {
        $selectedSocialAccount = $this->resolveClientSocialAccountForPublication(
            (int) ($task['client_id'] ?? 0),
            (string) ($payload['canal'] ?? '')
        );

        $apiPayload = [
            'entry_id' => (int) $entryId,
            'task_id' => (int) ($task['id'] ?? 0),
            'project_id' => (int) ($task['projet_id'] ?? 0),
            'client_id' => (int) ($task['client_id'] ?? 0),
            'deliverable_id' => (int) ($task['livrable_item_id'] ?? 0),
            'canal' => (string) ($payload['canal'] ?? ''),
            'date_publication' => (string) ($payload['date_publication'] ?? ''),
            'heure_publication' => (string) ($payload['heure_publication'] ?? ''),
            'statut' => (string) ($payload['statut'] ?? ''),
            'note' => (string) ($payload['note'] ?? ''),
            'selected_social_account' => $selectedSocialAccount,
        ];
        $result = ExternalPublicationService::pushPublication($apiPayload);
        $this->logApiEvent('publication', 'publication_push', $result, [
            'project_id' => (int) ($task['projet_id'] ?? 0),
            'task_id' => (int) ($task['id'] ?? 0),
            'payload' => $apiPayload,
        ]);
    }

    private function notifyKpiApi(array $task, array $payload, $entryId) {
        $apiPayload = [
            'entry_id' => (int) $entryId,
            'task_id' => (int) ($task['id'] ?? 0),
            'project_id' => (int) ($task['projet_id'] ?? 0),
            'deliverable_id' => (int) ($task['livrable_item_id'] ?? 0),
            'date_collecte' => (string) ($payload['date_collecte'] ?? ''),
            'periode_label' => (string) ($payload['periode_label'] ?? ''),
            'valeur_cle' => (string) ($payload['valeur_cle'] ?? ''),
            'metric_snapshot' => (string) ($payload['metric_snapshot'] ?? ''),
            'note' => (string) ($payload['note'] ?? ''),
        ];
        $result = ExternalPublicationService::pushKpiCollection($apiPayload);
        $this->logApiEvent('kpi', 'kpi_collection_push', $result, [
            'project_id' => (int) ($task['projet_id'] ?? 0),
            'task_id' => (int) ($task['id'] ?? 0),
            'payload' => $apiPayload,
        ]);
    }

    private function logApiEvent($integration, $eventType, array $result, array $context) {
        $stmt = $this->db->prepare("INSERT INTO api_event_logs
            (integration, event_type, project_id, task_id, success, status_code, payload_json, response_body, error_message)
            VALUES (:integration, :event_type, :project_id, :task_id, :success, :status_code, :payload_json, :response_body, :error_message)");
        $stmt->execute([
            'integration' => (string) $integration,
            'event_type' => (string) $eventType,
            'project_id' => !empty($context['project_id']) ? (int) $context['project_id'] : null,
            'task_id' => !empty($context['task_id']) ? (int) $context['task_id'] : null,
            'success' => !empty($result['sent']) ? 1 : 0,
            'status_code' => isset($result['status']) ? (int) $result['status'] : null,
            'payload_json' => json_encode((array) ($context['payload'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_body' => (string) ($result['response'] ?? ''),
            'error_message' => (string) ($result['reason'] ?? ''),
        ]);
    }

    private function resolveClientSocialAccountForPublication($clientId, $channel) {
        $clientId = (int) $clientId;
        if ($clientId <= 0) {
            return null;
        }

        $network = $this->normalizePublicationNetwork($channel);
        if ($network === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id, reseau, compte_label, identifiant_compte, page_id, page_nom, access_token, refresh_token, is_default
            FROM client_social_accounts
            WHERE client_id = :client_id
              AND reseau = :reseau
              AND statut = 'Actif'
            ORDER BY is_default DESC, updated_at DESC, id DESC
            LIMIT 1");
        $stmt->execute([
            'client_id' => $clientId,
            'reseau' => $network,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'network' => (string) ($row['reseau'] ?? ''),
            'label' => (string) ($row['compte_label'] ?? ''),
            'account_identifier' => (string) ($row['identifiant_compte'] ?? ''),
            'page_id' => (string) ($row['page_id'] ?? ''),
            'page_name' => (string) ($row['page_nom'] ?? ''),
            'access_token' => CryptoService::decrypt((string) ($row['access_token'] ?? '')),
            'refresh_token' => CryptoService::decrypt((string) ($row['refresh_token'] ?? '')),
            'is_default' => !empty($row['is_default']) ? 1 : 0,
        ];
    }

    public function getClientSocialAccountPreview($clientId, $channel) {
        $selected = $this->resolveClientSocialAccountForPublication((int) $clientId, (string) $channel);
        if (!$selected) {
            return [];
        }

        return [
            'id' => (int) ($selected['id'] ?? 0),
            'reseau' => (string) ($selected['network'] ?? ''),
            'reseau_label' => ucfirst((string) ($selected['network'] ?? '')),
            'compte_label' => (string) ($selected['label'] ?? ''),
            'identifiant_compte' => (string) ($selected['account_identifier'] ?? ''),
            'page_nom' => (string) ($selected['page_name'] ?? ''),
            'is_default' => !empty($selected['is_default']) ? 1 : 0,
        ];
    }

    private function normalizePublicationNetwork($channel) {
        $normalized = strtolower(trim((string) $channel));
        if ($normalized === '') {
            return '';
        }

        if (strpos($normalized, 'facebook') !== false || strpos($normalized, 'meta') !== false) {
            return 'facebook';
        }
        if (strpos($normalized, 'linkedin') !== false) {
            return 'linkedin';
        }
        if (strpos($normalized, 'instagram') !== false || strpos($normalized, 'insta') !== false) {
            return 'instagram';
        }
        if (strpos($normalized, 'tiktok') !== false || strpos($normalized, 'tik tok') !== false) {
            return 'tiktok';
        }
        if (strpos($normalized, 'youtube') !== false || strpos($normalized, 'yt') !== false) {
            return 'youtube';
        }
        if (strpos($normalized, 'whatsapp') !== false || strpos($normalized, 'wa') !== false) {
            return 'whatsapp';
        }

        return '';
    }

    private function getStrategyStats($clientId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM offres WHERE client_id = :client_id) AS offres_total,
                    (SELECT COUNT(*) FROM personas WHERE client_id = :client_id) AS personas_total,
                    (SELECT COUNT(*) FROM messages_marketing mm JOIN personas p ON p.id = mm.persona_id WHERE p.client_id = :client_id) AS messages_total,
                    (SELECT COUNT(*) FROM campagnes WHERE client_id = :client_id) AS campagnes_total";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['client_id' => $clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolveBriefContentId($deliverableId, array $brief, array $deliverableData) {
        $explicitContentId = (int) ($brief['contenu_id'] ?? 0);
        if ($explicitContentId > 0) {
            return $explicitContentId;
        }

        $context = $this->getDeliverableBriefContext($deliverableId);
        if (!$context) {
            throw new RuntimeException('Impossible de retrouver le contexte du livrable pour enregistrer le script.');
        }

        $existingContentId = (int) ($context['brief_contenu_id'] ?? 0);
        if ($existingContentId <= 0) {
            $existingContentId = (int) ($context['linked_contenu_id'] ?? 0);
        }
        if ($existingContentId > 0) {
            return $existingContentId;
        }

        $campaignId = (int) ($context['campagne_id'] ?? 0);

        $deliverableType = (string) ($context['type_livrable'] ?? '');
        $contentType = $deliverableType === 'Video' ? 'Video' : 'Visuel';

        if ($campaignId > 0) {
            $matchStmt = $this->db->prepare('SELECT id FROM contenus WHERE campagne_id = :campagne_id AND type = :type ORDER BY id ASC LIMIT 1');
            $matchStmt->execute([
                'campagne_id' => $campaignId,
                'type' => $contentType
            ]);
            $matchedId = (int) $matchStmt->fetchColumn();
            if ($matchedId > 0) {
                return $matchedId;
            }
        }

        $personaId = (int) ($context['persona_id_context'] ?? 0);
        if ($personaId <= 0) {
            $personaStmt = $this->db->prepare('SELECT persona_cible FROM campagnes WHERE id = :id LIMIT 1');
            $personaStmt->execute(['id' => $campaignId]);
            $personaId = (int) $personaStmt->fetchColumn();
        }
        if ($personaId <= 0) {
            $fallbackPersonaStmt = $this->db->prepare('SELECT id FROM personas WHERE client_id = :client_id ORDER BY id ASC LIMIT 1');
            $fallbackPersonaStmt->execute(['client_id' => (int) ($context['client_id'] ?? 0)]);
            $personaId = (int) $fallbackPersonaStmt->fetchColumn();
        }
        if ($personaId <= 0) {
            throw new RuntimeException('Aucun persona disponible pour creer automatiquement le contenu du script.');
        }

        $insertStmt = $this->db->prepare("INSERT INTO contenus
            (campagne_id, persona_id, projet_id, plan_mensuel_id, livrable_item_id, type, sous_type, nombre_pages_carrousel, sujet, message, objectif_publication, cible_libre, reseau_cible, statut, responsable)
            VALUES (:campagne_id, :persona_id, :projet_id, :plan_mensuel_id, :livrable_item_id, :type, :sous_type, :nombre_pages, :sujet, :message, :objectif_publication, :cible_libre, :reseau_cible, :statut, :responsable)");
        $insertStmt->execute([
            'campagne_id' => $campaignId,
            'persona_id' => $personaId,
            'projet_id' => (int) ($context['projet_id'] ?? 0) ?: null,
            'plan_mensuel_id' => (int) ($context['plan_mensuel_id'] ?? 0) ?: null,
            'livrable_item_id' => $deliverableId,
            'type' => $contentType,
            'sous_type' => $deliverableData['sous_type'] !== '' ? $deliverableData['sous_type'] : ($context['sous_type'] ?? null),
            'nombre_pages' => max(1, (int) ($deliverableData['nombre_pages'] ?? $context['nombre_pages'] ?? 1)),
            'sujet' => (string) ($context['titre'] ?? 'Livrable'),
            'message' => trim((string) ($brief['texte_script'] ?? $brief['plan_script'] ?? '')),
            'objectif_publication' => '',
            'cible_libre' => '',
            'reseau_cible' => (string) ($context['canal_principal'] ?? ''),
            'statut' => 'Brief cree',
            'responsable' => trim((string) ($brief['responsable'] ?? ''))
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function getDeliverableBriefContext($deliverableId) {
        $sql = "SELECT li.id, li.type_livrable, li.sous_type, li.nombre_pages, li.titre,
                  p.id AS projet_id, li.plan_mensuel_id, p.client_id, p.campagne_id, p.canal_principal,
                       ca.persona_cible AS persona_id_context,
                  b.contenu_id AS brief_contenu_id,
                  ct.id AS linked_contenu_id
                FROM livrable_items li
                JOIN projets p ON p.id = li.projet_id
                LEFT JOIN campagnes ca ON ca.id = p.campagne_id
                LEFT JOIN briefs b ON b.livrable_item_id = li.id
              LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
                WHERE li.id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $deliverableId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getBriefByDeliverableId($deliverableId, array $fallbackDeliverable = null) {
        $stmt = $this->db->prepare('SELECT * FROM briefs WHERE livrable_item_id = :id LIMIT 1');
        $stmt->execute(['id' => $deliverableId]);
        $brief = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($brief) {
            $brief['pages_carrousel'] = $this->decodeJsonField($brief['pages_carrousel']);
            $brief['pieces_jointes'] = $this->decodeJsonField($brief['pieces_jointes']);
            return $brief;
        }

        if ($fallbackDeliverable === null) {
            return null;
        }

        $isVideo = ($fallbackDeliverable['type_livrable'] ?? '') === 'Video';
        $isCarousel = strcasecmp((string) ($fallbackDeliverable['sous_type'] ?? ''), 'Carrousel') === 0;
        return [
            'contenu_id' => $fallbackDeliverable['content_id'] ?? null,
            'nature_brief' => $isVideo ? 'Script video' : 'Brief visuel',
            'format_livrable' => $fallbackDeliverable['sous_type'] ?: $fallbackDeliverable['type_livrable'],
            'nombre_pages_carrousel' => (int) ($fallbackDeliverable['nombre_pages'] ?: $fallbackDeliverable['nombre_pages_carrousel'] ?: 1),
            'pdf_requis' => $isCarousel ? 1 : 0,
            'source_requis' => $isVideo ? 0 : 1,
            'titre_brief' => $fallbackDeliverable['titre'],
            'details_message' => '',
            'informations_complementaires' => '',
            'cta' => '',
            'recommandation_design' => '',
            'description_publication' => '',
            'hook_video' => '',
            'plan_script' => '',
            'pages_carrousel' => [],
            'texte_script' => '',
            'instructions_visuelles' => '',
            'format' => '',
            'statut' => 'A faire',
            'responsable' => '',
            'pieces_jointes' => []
        ];
    }

    private function decodeJsonField($value) {
        if (empty($value)) {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function findContentIdForDeliverable($deliverableId, $campaignId) {
        $linked = $this->db->prepare('SELECT id FROM contenus WHERE livrable_item_id = :id LIMIT 1');
        $linked->execute(['id' => $deliverableId]);
        $linkedId = $linked->fetchColumn();
        if ($linkedId) {
            return (int) $linkedId;
        }

        $stmt = $this->db->prepare('SELECT contenu_id FROM briefs WHERE livrable_item_id = :id LIMIT 1');
        $stmt->execute(['id' => $deliverableId]);
        $contentId = $stmt->fetchColumn();
        if ($contentId) {
            return (int) $contentId;
        }

        $fallback = $this->db->prepare('SELECT id FROM contenus WHERE campagne_id = :campagne_id ORDER BY id ASC LIMIT 1');
        $fallback->execute(['campagne_id' => $campaignId]);
        return (int) $fallback->fetchColumn();
    }

    private function isContentReady(array $row) {
        $hasMonthlyContext = !empty($row['campagne_id']) || trim((string) ($row['objectif_mois'] ?? '')) !== '';
        $hasKeyDates = trim((string) ($row['temps_forts_mois'] ?? '')) !== '';
        $hasContentInfo = trim((string) ($row['contenu_sujet'] ?? '')) !== ''
            && trim((string) ($row['objectif_publication'] ?? '')) !== ''
            && trim((string) ($row['contenu_message'] ?? '')) !== ''
            && (((int) ($row['persona_id'] ?? 0) > 0) || trim((string) ($row['cible_libre'] ?? '')) !== '')
            && trim((string) ($row['reseau_cible'] ?? '')) !== '';

        return $hasMonthlyContext && $hasKeyDates && $hasContentInfo;
    }

    private function getProject($projectId, array $currentUser = null) {
        $sql = "SELECT p.*, c.entreprise, ca.nom AS campagne_nom,
                       u1.nom AS charge_compte_nom,
                                             u4.nom AS charge_clientele_nom,
                       u2.nom AS cm_nom,
                                             u3.nom AS createur_nom,
                                             u5.nom AS cadreur_nom,
                                             u6.nom AS videaste_nom,
                                             u7.nom AS designer_nom
                FROM projets p
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN campagnes ca ON ca.id = p.campagne_id
                LEFT JOIN users u1 ON u1.id = p.charge_compte_id
                                LEFT JOIN users u4 ON u4.id = p.charge_clientele_id
                LEFT JOIN users u2 ON u2.id = p.cm_id
                LEFT JOIN users u3 ON u3.id = p.createur_id
                                LEFT JOIN users u5 ON u5.id = p.cadreur_id
                                LEFT JOIN users u6 ON u6.id = p.videaste_id
                                LEFT JOIN users u7 ON u7.id = p.designer_id
                WHERE p.id = :id
                                " . (UserScope::isScopedOperationalUser($currentUser) ? " AND (p.createur_id = :scope_user_id OR p.cadreur_id = :scope_user_id OR p.videaste_id = :scope_user_id OR p.designer_id = :scope_user_id OR p.cm_id = :scope_user_id OR p.charge_compte_id = :scope_user_id OR p.charge_clientele_id = :scope_user_id OR EXISTS (SELECT 1 FROM taches_pipeline scope_tp WHERE scope_tp.projet_id = p.id AND scope_tp.auteur_id = :scope_user_id))" : '') . "
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $params = ['id' => $projectId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function getMonthTasks($planId, array $currentUser = null) {
        $sql = "SELECT tp.*,
                       u.nom AS auteur_nom
                FROM taches_pipeline tp
                LEFT JOIN users u ON u.id = tp.auteur_id
                                WHERE tp.plan_mensuel_id = :plan_id
                                    AND tp.livrable_item_id IS NULL
                                    " . (UserScope::isScopedOperationalUser($currentUser) ? " AND tp.auteur_id = :scope_user_id AND tp.statut <> 'Bloquee'" : '') . "
                ORDER BY tp.ordre_pipeline ASC, tp.deadline ASC";
        $stmt = $this->db->prepare($sql);
        $params = ['plan_id' => $planId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        return $this->filterVisibleTasks($stmt->fetchAll(), $currentUser);
    }

    private function getDeliverablesForPlan($planId, array $currentUser = null) {
        $sql = "SELECT li.*
                FROM livrable_items li
                WHERE li.plan_mensuel_id = :plan_id
                " . (UserScope::isScopedOperationalUser($currentUser) ? " AND EXISTS (SELECT 1 FROM taches_pipeline scope_tp WHERE scope_tp.livrable_item_id = li.id AND scope_tp.auteur_id = :scope_user_id AND scope_tp.statut <> 'Bloquee')" : '') . "
                ORDER BY FIELD(li.type_livrable, 'Video', 'Visuel'), li.numero_ordre ASC";
        $stmt = $this->db->prepare($sql);
        $params = ['plan_id' => $planId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        $deliverables = $stmt->fetchAll();

        foreach ($deliverables as &$deliverable) {
            $deliverable['tasks'] = $this->getDeliverableTasks($deliverable['id'], $currentUser);
            if (UserScope::isScopedOperationalUser($currentUser) && empty($deliverable['tasks'])) {
                continue;
            }
            $deliverable = array_merge($deliverable, $this->getDeliverableContentSummary($deliverable['id']));
            $assetFiles = $this->collectDeliverableAssetFiles($deliverable);
            $deliverable['preview_files'] = $this->extractPreviewFiles($assetFiles);
            $deliverable['progress'] = $this->buildDeliverableProgress($deliverable['tasks']);
            $deliverable['missing_assets'] = $this->buildMissingAssetSummary($deliverable, $assetFiles);
            $deliverable['content_ready'] = $this->isContentReady($deliverable);
        }

        if (UserScope::isScopedOperationalUser($currentUser)) {
            $deliverables = array_values(array_filter($deliverables, static function ($deliverable) {
                return !empty($deliverable['tasks']);
            }));
        }

        return $deliverables;
    }

    private function getDeliverableTasks($deliverableId, array $currentUser = null) {
        $sql = "SELECT tp.*, u.nom AS auteur_nom
                FROM taches_pipeline tp
                LEFT JOIN users u ON u.id = tp.auteur_id
                WHERE tp.livrable_item_id = :deliverable_id
                " . (UserScope::isScopedOperationalUser($currentUser) ? " AND tp.auteur_id = :scope_user_id AND tp.statut <> 'Bloquee'" : '') . "
                ORDER BY tp.ordre_pipeline ASC, tp.deadline ASC";
        $stmt = $this->db->prepare($sql);
        $params = ['deliverable_id' => $deliverableId];
        if (UserScope::isScopedOperationalUser($currentUser)) {
            $params['scope_user_id'] = UserScope::userId($currentUser);
        }
        $stmt->execute($params);
        return $this->filterVisibleTasks($stmt->fetchAll(), $currentUser);
    }

    private function getDeliverableContentSummary($deliverableId) {
        $stmt = $this->db->prepare("SELECT ct.id AS content_id,
                                           ct.sujet AS contenu_sujet,
                                           ct.message AS contenu_message,
                                           ct.objectif_publication,
                                           ct.cible_libre,
                                           ct.reseau_cible,
                                           ct.statut AS contenu_statut,
                                           ct.responsable AS contenu_responsable,
                                           ct.persona_id,
                                           pe.nom_persona AS persona_nom,
                                           pm.contexte_mois,
                                           pm.objectif_mois,
                                           pm.temps_forts_mois
                                    FROM livrable_items li
                                    JOIN plans_mensuels pm ON pm.id = li.plan_mensuel_id
                                    LEFT JOIN contenus ct ON ct.livrable_item_id = li.id
                                    LEFT JOIN personas pe ON pe.id = ct.persona_id
                                    WHERE li.id = :id
                                    LIMIT 1");
        $stmt->execute(['id' => $deliverableId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function getContentPublicationEntries($contentId) {
        $stmt = $this->db->prepare("SELECT *
            FROM calendrier_contenus
            WHERE contenu_id = :content_id
            ORDER BY COALESCE(date_publication, '9999-12-31') DESC, id DESC");
        $stmt->execute(['content_id' => $contentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getLatestPublicationEntry($contentId) {
        $stmt = $this->db->prepare("SELECT *
            FROM calendrier_contenus
            WHERE contenu_id = :content_id
            ORDER BY COALESCE(date_publication, '9999-12-31') DESC, id DESC
            LIMIT 1");
        $stmt->execute(['content_id' => $contentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getContentResultEntries($contentId) {
        if ($this->hasResultNetworkColumn()) {
            $stmt = $this->db->prepare("SELECT cr.*
                FROM contenu_resultats cr
                INNER JOIN (
                    SELECT MAX(id) AS latest_id
                    FROM contenu_resultats
                    WHERE contenu_id = :content_id
                    GROUP BY date_collecte, COALESCE(reseau_collecte, '')
                ) latest ON latest.latest_id = cr.id
                ORDER BY cr.date_collecte DESC, cr.id DESC");
            $stmt->execute(['content_id' => $contentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare("SELECT *
            FROM contenu_resultats
            WHERE contenu_id = :content_id
            ORDER BY date_collecte DESC, id DESC");
        $stmt->execute(['content_id' => $contentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hasResultNetworkColumn() {
        if ($this->hasResultNetworkColumnCache !== null) {
            return (bool) $this->hasResultNetworkColumnCache;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM contenu_resultats LIKE 'reseau_collecte'");
            $this->hasResultNetworkColumnCache = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            $this->hasResultNetworkColumnCache = false;
        }

        return (bool) $this->hasResultNetworkColumnCache;
    }

    private function buildProjectScopeCondition($projectAlias, $taskAlias) {
        return sprintf(
            '(%1$s.auteur_id = :scope_user_id OR %2$s.createur_id = :scope_user_id OR %2$s.cadreur_id = :scope_user_id OR %2$s.videaste_id = :scope_user_id OR %2$s.designer_id = :scope_user_id OR %2$s.cm_id = :scope_user_id OR %2$s.charge_compte_id = :scope_user_id OR %2$s.charge_clientele_id = :scope_user_id)',
            $taskAlias,
            $projectAlias
        );
    }

    private function filterVisibleTasks(array $tasks, array $currentUser = null) {
        if (!UserScope::isScopedOperationalUser($currentUser)) {
            return $tasks;
        }

        return array_values(array_filter($tasks, static function ($task) use ($currentUser) {
            return ($task['statut'] ?? null) !== 'Bloquee'
                && UserScope::canAccessTaskType($currentUser, $task['type_tache'] ?? null);
        }));
    }

    private function resolveDefaultProjectMonth(array $plans, $selectedMonth) {
        if ($selectedMonth === '') {
            return null;
        }

        if ($selectedMonth !== null && $selectedMonth !== '') {
            foreach ($plans as $plan) {
                if (($plan['periode_mois'] ?? null) === $selectedMonth) {
                    return $selectedMonth;
                }
            }
        }

        if (empty($plans)) {
            return null;
        }

        $currentMonth = date('Y-m-01');
        $futureMonths = [];
        $pastMonths = [];

        foreach ($plans as $plan) {
            $month = (string) ($plan['periode_mois'] ?? '');
            if ($month === '') {
                continue;
            }
            if ($month >= $currentMonth) {
                $futureMonths[] = $month;
            } else {
                $pastMonths[] = $month;
            }
        }

        sort($futureMonths);
        rsort($pastMonths);

        if (!empty($futureMonths)) {
            return $futureMonths[0];
        }

        return $pastMonths[0] ?? $plans[count($plans) - 1]['periode_mois'];
    }

    private function resolveGlobalPublicationStage(array $row) {
        // Helpers for state checking
        $isActive = function($status) {
            return $status !== '' && $status !== null && $status !== 'Terminee' && $status !== 'Bloquee';
        };
        $isTerminated = function($status) {
            return $status === 'Terminee';
        };

        // 1. Check if overdue (contenu en retard = rouge)
        $dateStr = (string) ($row['date_prevue'] ?? '');
        $isLate = $dateStr !== '' 
            && strtotime($dateStr) < time()
            && !$isTerminated($row['publication_statut'] ?? '');
        if ($isLate) {
            return ['key' => 'cal-retard', 'label' => 'Contenu retard'];
        }

        // 2. Published (publié = vert clair)
        if ($isTerminated($row['publication_statut'] ?? '')) {
            return ['key' => 'cal-publie', 'label' => 'Publié'];
        }

        // 3. Validation in progress (validation en attente = bleu clair)
        if ($isActive($row['validation_interne_statut'] ?? '') 
            || $isActive($row['validation_client_statut'] ?? '')) {
            return ['key' => 'cal-validation-attente', 'label' => 'Validation en attente'];
        }

        // 4. Publication awaiting (publication en attente = bleu)
        $pubStatus = (string) ($row['publication_statut'] ?? '');
        if ($pubStatus === 'A faire' || $pubStatus === 'En cours') {
            return ['key' => 'cal-publication-attente', 'label' => 'Publication en attente'];
        }

        // 5. Video-specific workflow (Video)
        if (($row['type_livrable'] ?? '') === 'Video') {
            // Montage in progress (montage en attente = jaune)
            if ($isActive($row['montage_statut'] ?? '')) {
                return ['key' => 'cal-montage-attente', 'label' => 'Montage en attente'];
            }
            // Tournage in progress (tournage en attente = orange-jaune)
            if ($isActive($row['tournage_statut'] ?? '')) {
                return ['key' => 'cal-tournage-attente', 'label' => 'Tournage en attente'];
            }
            // Script in progress (brief en attente = orange, as first step for videos)
            if ($isActive($row['script_statut'] ?? '')) {
                return ['key' => 'cal-brief-attente', 'label' => 'Script en attente'];
            }
            // First collection done (première collecte = vert)
            if ($isTerminated($row['script_statut'] ?? '')) {
                return ['key' => 'cal-premiere-collecte', 'label' => 'Première collecte effectuée'];
            }
        } else {
            // Visual-specific workflow (Visuel)
            // Production in progress (production en attente = jaune)
            if ($isActive($row['production_statut'] ?? '')) {
                return ['key' => 'cal-production-attente', 'label' => 'Production en attente'];
            }
            // Brief in progress (brief en attente = orange)
            if ($isActive($row['brief_statut'] ?? '')) {
                return ['key' => 'cal-brief-attente', 'label' => 'Brief en attente'];
            }
            // First collection done (première collecte = vert)
            if ($isTerminated($row['brief_statut'] ?? '')) {
                return ['key' => 'cal-premiere-collecte', 'label' => 'Première collecte effectuée'];
            }
        }

        // 6. Form incomplete (fiche non remplis = gris)
        return ['key' => 'cal-non-rempli', 'label' => 'Fiche non remplie'];
    }

    private function buildDeliverableProgress(array $tasks) {
        $total = count($tasks);
        $done = 0;
        $active = 0;
        $blocked = 0;
        $currentStage = null;

        foreach ($tasks as $task) {
            $status = $task['statut'] ?? '';
            if ($status === 'Terminee') {
                $done++;
            }
            if ($status === 'En cours') {
                $active++;
                if ($currentStage === null) {
                    $currentStage = $task['type_tache'] ?? null;
                }
            }
            if ($status === 'Bloquee') {
                $blocked++;
            }
        }

        if ($currentStage === null) {
            foreach ($tasks as $task) {
                if (($task['statut'] ?? '') !== 'Terminee') {
                    $currentStage = $task['type_tache'] ?? null;
                    break;
                }
            }
        }

        $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return [
            'total' => $total,
            'done' => $done,
            'active' => $active,
            'blocked' => $blocked,
            'percent' => $percent,
            'current_stage' => $currentStage
        ];
    }

    private function extractPreviewFiles(array $files) {
        $previews = [];
        $seen = [];
        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $extension = strtolower(pathinfo((string) ($file['name'] ?? $path), PATHINFO_EXTENSION));
            $role = $this->resolvePreviewRole($file['name'] ?? $path, $extension);
            $previews[] = [
                'name' => $file['name'] ?? basename($path),
                'path' => $path,
                'extension' => $extension,
                'kind' => $this->resolvePreviewKind($extension),
                'role' => $role,
                'role_label' => $role === 'source' ? 'SOURCE' : 'EXPORT'
            ];
            $seen[$path] = true;
            if (count($previews) >= 6) {
                break;
            }
        }

        return $previews;
    }

    private function collectDeliverableAssetFiles(array $deliverable) {
        $sources = [];
        $deliverableFiles = $this->decodeJsonField($deliverable['pieces_jointes'] ?? '[]');
        if (!empty($deliverableFiles)) {
            $sources[] = $deliverableFiles;
        }

        if (!empty($deliverable['brief']['pieces_jointes']) && is_array($deliverable['brief']['pieces_jointes'])) {
            $sources[] = $deliverable['brief']['pieces_jointes'];
        } elseif (!empty($deliverable['id'])) {
            $brief = $this->getBriefByDeliverableId((int) $deliverable['id']);
            if (!empty($brief['pieces_jointes']) && is_array($brief['pieces_jointes'])) {
                $sources[] = $brief['pieces_jointes'];
            }
        }

        foreach (($deliverable['tasks'] ?? []) as $task) {
            $taskFiles = $this->decodeJsonField($task['fichiers_livres'] ?? '[]');
            if (!empty($taskFiles)) {
                $sources[] = $taskFiles;
            }
        }

        $files = [];
        $seen = [];
        foreach ($sources as $bucket) {
            foreach ($bucket as $file) {
                $path = (string) ($file['path'] ?? '');
                if ($path === '' || isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $files[] = $file;
            }
        }

        return $files;
    }

    private function buildMissingAssetSummary(array $deliverable, array $files) {
        $extensions = array_map(static function ($file) {
            return strtolower(pathinfo((string) ($file['name'] ?? $file['path'] ?? ''), PATHINFO_EXTENSION));
        }, $files);

        $items = [];
        if (($deliverable['type_livrable'] ?? '') === 'Video') {
            $videoCount = count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['mp4', 'mov', 'webm'], true);
            }));
            if ($videoCount < 2) {
                $items[] = '2 versions video';
            }
        } elseif (strcasecmp((string) ($deliverable['sous_type'] ?? ''), 'Carrousel') === 0) {
            $pageCount = max(1, (int) ($deliverable['nombre_pages'] ?? 1));
            $imageCount = count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true);
            }));
            if ($imageCount < $pageCount) {
                $items[] = ($pageCount - $imageCount) . ' export(s) page';
            }
            if (!in_array('pdf', $extensions, true)) {
                $items[] = 'PDF';
            }
            if (count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['psd', 'psb'], true);
            })) === 0) {
                $items[] = 'PSD/PSB';
            }
        } else {
            if (!in_array('png', $extensions, true) && !in_array('jpg', $extensions, true) && !in_array('jpeg', $extensions, true) && !in_array('webp', $extensions, true)) {
                $items[] = 'Export image';
            }
            if (count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['psd', 'psb'], true);
            })) === 0) {
                $items[] = 'PSD/PSB';
            }
        }

        return [
            'count' => count($items),
            'items' => $items,
            'complete' => count($items) === 0
        ];
    }

    private function resolvePreviewKind($extension) {
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return 'image';
        }
        if (in_array($extension, ['mp4', 'mov', 'webm'], true)) {
            return 'video';
        }
        if ($extension === 'pdf') {
            return 'pdf';
        }
        return 'file';
    }

    private function resolvePreviewRole($fileName, $extension) {
        $fileName = strtolower((string) $fileName);
        if (in_array($extension, ['psd', 'psb', 'zip', 'doc', 'docx', 'ai'], true)) {
            return 'source';
        }
        if (str_contains($fileName, 'source') || str_contains($fileName, 'master') || str_contains($fileName, 'raw')) {
            return 'source';
        }
        return 'export';
    }
}
