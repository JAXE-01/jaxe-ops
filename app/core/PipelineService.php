<?php
class PipelineService {
    public static function syncProject($projectId) {
        $pdo = Database::getConnection();
        $project = self::getProject($pdo, $projectId);
        if (!$project) {
            return;
        }

        $cadenceHistory=CadenceRevision::decode((string)($project['publication_rules']??''));
        $hasCadenceHistory=!empty($cadenceHistory['revisions']);
        $firstRevision=$hasCadenceHistory?min(array_keys($cadenceHistory['revisions'])):null;
        if(!$hasCadenceHistory)self::cleanupLegacyTasks($pdo, $project['id']);

        $periods = self::buildMonthlyPeriods($project['date_debut'], $project['date_fin'], $project['duree_mois'] ?? null);
        if(!$hasCadenceHistory)self::pruneExtraPlans($pdo, $project['id'], $periods);
        if(!$hasCadenceHistory)self::ensureProjectOnboarding($pdo, $project);

        foreach ($periods as $index => $period) {
            if($hasCadenceHistory && ($period->format('Y-m')<$firstRevision || $period->format('Y-m')<date('Y-m')))continue;
            unset($project['_cadence']);
            $rules=CadenceRevision::rules((string)($project['publication_rules']??''),$period->format('Y-m'));
            if($rules){$project['_cadence']=EditorialCadence::dates($rules,$project['date_debut'],$project['date_fin'],$period->format('Y-m'));$project['quota_videos_mensuel']=count($project['_cadence']['Video']);$project['quota_visuels_mensuel']=count($project['_cadence']['Visuel']);}
            $planId = self::ensureMonthlyPlan($pdo, $project, $period, $index + 1);
            $monthWithArticle = self::monthLabelWithArticle($period);
            $strategyId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => null,
                'parent_task_id' => null,
                'titre' => 'Strategie du mois ' . $monthWithArticle,
                'type_tache' => 'Strategie',
                'auteur_id' => $project['charge_compte_id'],
                'statut' => 'A faire',
                'deadline' => self::formatDate(clone $period, -10),
                'ordre_pipeline' => 1,
                'notes' => 'Definir l angle editorial, les objectifs et les sujets du mois.'
            ]);

            $calendarId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => null,
                'parent_task_id' => $strategyId,
                'titre' => 'Calendrier du mois ' . $monthWithArticle,
                'type_tache' => 'Calendrier',
                'auteur_id' => $project['charge_compte_id'],
                'statut' => 'Bloquee',
                'deadline' => self::formatDate(clone $period, -7),
                'ordre_pipeline' => 2,
                'notes' => 'Planifier les publications, valider les canaux et les deadlines du mois.'
            ]);

            $interactionId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => null,
                'parent_task_id' => $calendarId,
                'titre' => 'Gestion interactions du mois ' . $monthWithArticle,
                'type_tache' => 'Interactions',
                'auteur_id' => $project['cm_id'],
                'statut' => 'Bloquee',
                'deadline' => self::formatDate(clone $period, 20),
                'ordre_pipeline' => 8,
                'notes' => 'Assurer les reponses aux commentaires et messages pendant la periode de publication.'
            ]);

            $reportingId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => null,
                'parent_task_id' => $interactionId,
                'titre' => 'Reporting du mois ' . $monthWithArticle,
                'type_tache' => 'Reporting',
                'auteur_id' => $project['charge_compte_id'],
                'statut' => 'Bloquee',
                'deadline' => self::formatDate(clone $period, 26),
                'ordre_pipeline' => 9,
                'notes' => 'Consolider les resultats du mois et preparer la restitution client.'
            ]);

            self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => null,
                'parent_task_id' => $reportingId,
                'titre' => 'Optimisation du mois ' . $monthWithArticle,
                'type_tache' => 'Optimisation',
                'auteur_id' => $project['charge_compte_id'],
                'statut' => 'Bloquee',
                'deadline' => self::formatDate(clone $period, 28),
                'ordre_pipeline' => 10,
                'notes' => 'Ajuster les formats, messages et tests a lancer pour le mois suivant.'
            ]);

            self::ensureDeliverableChain($pdo, $project, $planId, $calendarId, 'Video', (int) $project['quota_videos_mensuel'], $period);
            self::ensureDeliverableChain($pdo, $project, $planId, $calendarId, 'Visuel', (int) $project['quota_visuels_mensuel'], $period);
            if(!$hasCadenceHistory)self::syncContentReadinessForPlan($planId);
            if($hasCadenceHistory){
                $actual=$pdo->prepare("UPDATE plans_mensuels SET videos_prevus=(SELECT COUNT(*) FROM livrable_items WHERE plan_mensuel_id=? AND type_livrable='Video'),visuels_prevus=(SELECT COUNT(*) FROM livrable_items WHERE plan_mensuel_id=? AND type_livrable='Visuel'),livrables_prevus=(SELECT COUNT(*) FROM livrable_items WHERE plan_mensuel_id=?) WHERE id=?");
                $actual->execute([$planId,$planId,$planId,$planId]);
            }
        }

        if(!$hasCadenceHistory)self::reconcileTaskTransitions($pdo, (int) $project['id']);
    }

    public static function unlockChildren($taskId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE taches_pipeline
            SET statut = :new_status
            WHERE parent_task_id = :parent_task_id
              AND statut IN ('Bloquee', 'Annulee')");
        $stmt->execute([
            'new_status' => 'A faire',
            'parent_task_id' => $taskId
        ]);
    }

    public static function syncContentReadinessForPlan($planId) {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return;
        }

        $pdo = Database::getConnection();
        $sql = "SELECT tp.id, tp.statut, tp.type_tache,
                       pm.objectif_mois, pm.temps_forts_mois,
                       p.campagne_id,
                       ct.sujet, ct.message, ct.objectif_publication, ct.cible_libre, ct.persona_id, ct.reseau_cible
                FROM taches_pipeline tp
                JOIN plans_mensuels pm ON pm.id = tp.plan_mensuel_id
                JOIN projets p ON p.id = tp.projet_id
                LEFT JOIN contenus ct ON ct.livrable_item_id = tp.livrable_item_id
                WHERE tp.plan_mensuel_id = :plan_id
                  AND tp.type_tache IN ('Brief', 'Script')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['plan_id' => $planId]);

        $update = $pdo->prepare('UPDATE taches_pipeline SET statut = :statut WHERE id = :id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (in_array((string) $row['statut'], ['En cours', 'Terminee', 'Annulee'], true)) {
                continue;
            }

            $contentReady = self::isContentReady($row);
            $newStatus = $contentReady ? 'A faire' : 'Bloquee';
            if ($newStatus !== (string) $row['statut']) {
                $update->execute([
                    'statut' => $newStatus,
                    'id' => $row['id']
                ]);
            }
        }
    }

    public static function syncContentStatusByDeliverable($deliverableId) {
        $deliverableId = (int) $deliverableId;
        if ($deliverableId <= 0) {
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT ct.id,
                                      tp.type_tache,
                                      tp.statut
                               FROM contenus ct
                               LEFT JOIN taches_pipeline tp ON tp.livrable_item_id = ct.livrable_item_id
                               WHERE ct.livrable_item_id = :deliverable_id");
        $stmt->execute(['deliverable_id' => $deliverableId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return;
        }

        $contentId = (int) ($rows[0]['id'] ?? 0);
        if ($contentId <= 0) {
            return;
        }

        $statusByTask = [];
        foreach ($rows as $row) {
            if (!empty($row['type_tache'])) {
                $statusByTask[$row['type_tache']] = $row['statut'];
            }
        }

        $contentStatus = 'Strategique defini';
        if (($statusByTask['Publication'] ?? '') === 'Terminee') {
            $contentStatus = 'Publie';
        } elseif (($statusByTask['Validation client'] ?? '') === 'Terminee') {
            $contentStatus = 'Finalise';
        } elseif (array_intersect($statusByTask, ['En cours', 'Terminee'])) {
            $contentStatus = 'En production';
        } elseif (($statusByTask['Brief'] ?? '') === 'A faire' || ($statusByTask['Script'] ?? '') === 'A faire') {
            $contentStatus = 'Brief cree';
        }

        $update = $pdo->prepare('UPDATE contenus SET statut = :statut WHERE id = :id');
        $update->execute([
            'statut' => $contentStatus,
            'id' => $contentId
        ]);
    }

    private static function reconcileTaskTransitions(PDO $pdo, int $projectId): void {
        if ($projectId <= 0) {
            return;
        }

        $stmt = $pdo->prepare("UPDATE taches_pipeline child
            JOIN taches_pipeline parent ON parent.id = child.parent_task_id
            SET child.statut = 'A faire'
            WHERE parent.projet_id = :project_id
              AND parent.statut = 'Terminee'
              AND child.statut IN ('Bloquee', 'Annulee')");
        $stmt->execute(['project_id' => $projectId]);
    }

    private static function getProject(PDO $pdo, $projectId) {
        $stmt = $pdo->prepare('SELECT * FROM projets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetch();
    }

    private static function buildMonthlyPeriods($startDate, $endDate, $durationMonths = null) {
        $periods = [];
        $cursor = new DateTime(date('Y-m-01', strtotime($startDate)));
        if ($durationMonths !== null && (int) $durationMonths > 0) {
            for ($index = 0; $index < (int) $durationMonths; $index++) {
                $periods[] = clone $cursor;
                $cursor->modify('+1 month');
            }
            return $periods;
        }

        $end = new DateTime(date('Y-m-01', strtotime($endDate)));

        while ($cursor <= $end) {
            $periods[] = clone $cursor;
            $cursor->modify('+1 month');
        }

        return $periods;
    }

    private static function ensureMonthlyPlan(PDO $pdo, array $project, DateTime $period, $monthIndex) {
        $stmt = $pdo->prepare('SELECT id FROM plans_mensuels WHERE projet_id = :projet_id AND periode_mois = :periode_mois LIMIT 1');
        $stmt->execute([
            'projet_id' => $project['id'],
            'periode_mois' => $period->format('Y-m-01')
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            // Preserve existing workflow state during cadence revisions.
            if (CadenceRevision::hasHistory($pdo, (int) $project['id'])) {
                return (int) $existingId;
            }
            $update = $pdo->prepare('UPDATE plans_mensuels
                SET videos_prevus = :videos_prevus,
                    visuels_prevus = :visuels_prevus,
                    livrables_prevus = :livrables_prevus,
                    statut = :statut
                WHERE id = :id');
            $update->execute([
                'videos_prevus' => $project['quota_videos_mensuel'],
                'visuels_prevus' => $project['quota_visuels_mensuel'],
                'livrables_prevus' => $project['quota_videos_mensuel'] + $project['quota_visuels_mensuel'],
                'statut' => 'Planifie',
                'id' => $existingId
            ]);
            return (int) $existingId;
        }

        $insert = $pdo->prepare('INSERT INTO plans_mensuels
            (projet_id, periode_mois, index_mois, videos_prevus, videos_livres, visuels_prevus, visuels_livres, livrables_prevus, livrables_livres, statut)
            VALUES
            (:projet_id, :periode_mois, :index_mois, :videos_prevus, 0, :visuels_prevus, 0, :livrables_prevus, 0, :statut)');
        $insert->execute([
            'projet_id' => $project['id'],
            'periode_mois' => $period->format('Y-m-01'),
            'index_mois' => $monthIndex,
            'videos_prevus' => $project['quota_videos_mensuel'],
            'visuels_prevus' => $project['quota_visuels_mensuel'],
            'livrables_prevus' => $project['quota_videos_mensuel'] + $project['quota_visuels_mensuel'],
            'statut' => 'Planifie'
        ]);

        return (int) $pdo->lastInsertId();
    }

    private static function pruneExtraPlans(PDO $pdo, $projectId, array $periods) {
        if (empty($periods)) {
            return;
        }

        $placeholders = [];
        $params = ['projet_id' => $projectId];
        foreach ($periods as $index => $period) {
            $key = 'period_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $period->format('Y-m-01');
        }

        $sql = sprintf(
            'DELETE FROM plans_mensuels WHERE projet_id = :projet_id AND periode_mois NOT IN (%s)',
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    private static function ensureProjectOnboarding(PDO $pdo, array $project) {
        $kickoffId = self::ensureTask($pdo, [
            'projet_id' => $project['id'],
            'plan_mensuel_id' => null,
            'livrable_item_id' => null,
            'parent_task_id' => null,
            'titre' => 'Onboarding - kickoff et collecte infos',
            'type_tache' => 'Onboarding',
            'auteur_id' => $project['charge_compte_id'],
            'statut' => 'A faire',
            'deadline' => $project['date_debut'],
            'ordre_pipeline' => 0,
            'notes' => 'Kick-off, collecte des informations client, acces plateformes et cadrage des objectifs.'
        ]);

        self::ensureTask($pdo, [
            'projet_id' => $project['id'],
            'plan_mensuel_id' => null,
            'livrable_item_id' => null,
            'parent_task_id' => $kickoffId,
            'titre' => 'Onboarding - pages et acces',
            'type_tache' => 'Onboarding',
            'auteur_id' => $project['charge_compte_id'],
            'statut' => 'Bloquee',
            'deadline' => self::formatDate(new DateTime($project['date_debut']), 2),
            'ordre_pipeline' => 0,
            'notes' => 'Creation ou optimisation des pages, verification Meta, WhatsApp et autres acces utiles.'
        ]);
    }

    private static function cleanupLegacyTasks(PDO $pdo, $projectId) {
        $stmt = $pdo->prepare("DELETE FROM taches_pipeline
            WHERE projet_id = :projet_id
              AND (type_tache = '' OR (titre LIKE 'Validation %' AND type_tache NOT IN ('Validation interne', 'Validation client'))) ");
        $stmt->execute(['projet_id' => $projectId]);
    }

    private static function getValidationClientOwnerId(array $project) {
        $clienteleId = (int) ($project['charge_clientele_id'] ?? 0);
        if ($clienteleId > 0) {
            return $clienteleId;
        }

        return $project['charge_compte_id'];
    }

    private static function getCreativeOwnerId(array $project) {
        $creativeId = (int) ($project['createur_id'] ?? 0);
        if ($creativeId > 0) {
            return $creativeId;
        }

        return $project['charge_compte_id'];
    }

    private static function getCameraOwnerId(array $project) {
        $cameraId = (int) ($project['cadreur_id'] ?? 0);
        if ($cameraId > 0) {
            return $cameraId;
        }

        return self::getCreativeOwnerId($project);
    }

    private static function getVideoEditorOwnerId(array $project) {
        $editorId = (int) ($project['videaste_id'] ?? 0);
        if ($editorId > 0) {
            return $editorId;
        }

        return self::getCreativeOwnerId($project);
    }

    private static function ensureDeliverableChain(PDO $pdo, array $project, $planId, $calendarId, $type, $quantity, DateTime $period) {
        if ($quantity <= 0) {
            return;
        }

        for ($index = 1; $index <= $quantity; $index++) {
            $itemId = self::ensureDeliverableItem($pdo, $project, $planId, $type, $index, $period);
            $validationPolicy=ValidationPolicy::forContent($pdo,(int)$project['id'],(int)$itemId); self::ensureContentItem($pdo, $project, $planId, $itemId, $type, $index, $period);
            $cadenceDate=null;if(isset($project['_cadence'])){$dateQuery=$pdo->prepare('SELECT date_prevue FROM livrable_items WHERE id=:id');$dateQuery->execute(['id'=>$itemId]);$cadenceDate=$dateQuery->fetchColumn();}
            if ($type === 'Video') {
                $scriptId = self::ensureTask($pdo, [
                    'projet_id' => $project['id'],
                    'plan_mensuel_id' => $planId,
                    'livrable_item_id' => $itemId,
                    'parent_task_id' => $calendarId,
                    'titre' => 'Script video #' . $index,
                    'type_tache' => 'Script',
                    'auteur_id' => self::getCreativeOwnerId($project),
                    'statut' => 'Bloquee',
                    'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-6 days')->format('Y-m-d'):self::formatDate(clone $period, 1 + (($index - 1) * 7)),
                    'ordre_pipeline' => 3,
                    'notes' => 'Rediger le script, l intention editoriale et les indications de tournage.'
                ]);

                $shootId = self::ensureTask($pdo, [
                    'projet_id' => $project['id'],
                    'plan_mensuel_id' => $planId,
                    'livrable_item_id' => $itemId,
                    'parent_task_id' => $scriptId,
                    'titre' => 'Tournage video #' . $index,
                    'type_tache' => 'Tournage',
                    'auteur_id' => self::getCameraOwnerId($project),
                    'statut' => 'Bloquee',
                    'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-5 days')->format('Y-m-d'):self::formatDate(clone $period, 2 + (($index - 1) * 7)),
                    'ordre_pipeline' => 4,
                    'notes' => 'Realiser la captation selon le script valide.'
                ]);

                $productionId = self::ensureTask($pdo, [
                    'projet_id' => $project['id'],
                    'plan_mensuel_id' => $planId,
                    'livrable_item_id' => $itemId,
                    'parent_task_id' => $shootId,
                    'titre' => 'Montage video #' . $index,
                    'type_tache' => 'Montage',
                    'auteur_id' => self::getVideoEditorOwnerId($project),
                    'statut' => 'Bloquee',
                    'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-3 days')->format('Y-m-d'):self::formatDate(clone $period, 4 + (($index - 1) * 7)),
                    'ordre_pipeline' => 5,
                    'notes' => 'Monter la video, integrer habillage et version finale pour validation.'
                ]);
            } else {
                $briefId = self::ensureTask($pdo, [
                    'projet_id' => $project['id'],
                    'plan_mensuel_id' => $planId,
                    'livrable_item_id' => $itemId,
                    'parent_task_id' => $calendarId,
                    'titre' => 'Brief visuel #' . $index,
                    'type_tache' => 'Brief',
                    'auteur_id' => self::getCreativeOwnerId($project),
                    'statut' => 'Bloquee',
                    'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-6 days')->format('Y-m-d'):self::formatDate(clone $period, 1 + (($index - 1) * 7)),
                    'ordre_pipeline' => 3,
                    'notes' => 'Produire le brief detaille du visuel, ses formats et ses livrables attendus.'
                ]);

                $productionId = self::ensureTask($pdo, [
                    'projet_id' => $project['id'],
                    'plan_mensuel_id' => $planId,
                    'livrable_item_id' => $itemId,
                    'parent_task_id' => $briefId,
                    'titre' => 'Production visuel #' . $index,
                    'type_tache' => 'Production',
                    'auteur_id' => self::getCreativeOwnerId($project),
                    'statut' => 'Bloquee',
                    'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-3 days')->format('Y-m-d'):self::formatDate(clone $period, 3 + (($index - 1) * 7)),
                    'ordre_pipeline' => 4,
                    'notes' => 'Produire le visuel et preparer exports ainsi que source PSD/PSB si necessaire.'
                ]);
            }

            $validationInternalId = $productionId; if ($validationPolicy['internal']) { $validationInternalId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => $itemId,
                'parent_task_id' => $productionId,
                'titre' => 'Validation interne ' . strtolower($type) . ' #' . $index,
                'type_tache' => 'Validation interne',
                'auteur_id' => $project['charge_compte_id'],
                'statut' => 'Bloquee',
                'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-2 days')->format('Y-m-d'):self::formatDate(clone $period, ($type === 'Video' ? 5 : 4) + (($index - 1) * 7)),
                'ordre_pipeline' => $type === 'Video' ? 6 : 5,
                'notes' => $type === 'Video'
                    ? 'Verifier script, montage, habillage et conformite avant envoi client.'
                    : 'Verifier la coherence strategique, le branding et la qualite avant envoi client.'
            ]);
            } $validationClientId = $validationInternalId; if ($validationPolicy['client']) { $validationClientId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => $itemId,
                'parent_task_id' => $validationInternalId,
                'titre' => 'Validation client ' . strtolower($type) . ' #' . $index,
                'type_tache' => 'Validation client',
                'auteur_id' => self::getValidationClientOwnerId($project),
                'statut' => 'Bloquee',
                'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('-1 day')->format('Y-m-d'):self::formatDate(clone $period, ($type === 'Video' ? 6 : 5) + (($index - 1) * 7)),
                'ordre_pipeline' => $type === 'Video' ? 7 : 6,
                'notes' => 'Envoyer au client, recueillir les retours et valider la version finale.'
            ]);
            } $publicationId = self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => $itemId,
                'parent_task_id' => $validationClientId,
                'titre' => 'Publication ' . strtolower($type) . ' #' . $index,
                'type_tache' => 'Publication',
                'auteur_id' => $project['cm_id'],
                'statut' => 'Bloquee',
                'deadline' => $cadenceDate?:self::formatDate(clone $period, ($type === 'Video' ? 7 : 6) + (($index - 1) * 7)),
                'ordre_pipeline' => $type === 'Video' ? 8 : 7,
                'notes' => 'Publier le contenu selon le calendrier valide.'
            ]);
            self::ensureTask($pdo, [
                'projet_id' => $project['id'],
                'plan_mensuel_id' => $planId,
                'livrable_item_id' => $itemId,
                'parent_task_id' => $publicationId,
                'titre' => 'Collecte KPI ' . strtolower($type) . ' #' . $index,
                'type_tache' => 'Collecte KPI',
                'auteur_id' => $project['cm_id'],
                'statut' => 'Bloquee',
                'deadline' => $cadenceDate?(new DateTimeImmutable($cadenceDate))->modify('+14 days')->format('Y-m-d'):self::formatDate(clone $period, 20 + (($index - 1) * 7)),
                'ordre_pipeline' => $type === 'Video' ? 9 : 8,
                'notes' => 'Collecter les performances 14 jours apres la publication.'
            ]);
        }
    }

    private static function ensureDeliverableItem(PDO $pdo, array $project, $planId, $type, $index, DateTime $period) {
        $stmt = $pdo->prepare('SELECT id, date_prevue FROM livrable_items WHERE plan_mensuel_id = :plan_mensuel_id AND type_livrable = :type_livrable AND numero_ordre = :numero_ordre LIMIT 1');
        $stmt->execute([
            'plan_mensuel_id' => $planId,
            'type_livrable' => $type,
            'numero_ordre' => $index
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $existingId = (int) ($existing['id'] ?? 0);

        $slot=$project['_cadence'][$type][$index-1]??null;
        $datePrevue = $slot['date']??self::formatDate(clone $period, 5 + (($index - 1) * 7));
        $titre = $slot['label']??sprintf('%s %s #%s', $type, self::monthLabelForTitle($period), $index);

        if ($existingId > 0 && isset($project['_cadence'])) return $existingId;
        if ($existingId > 0) {
            $existingDate = trim((string) ($existing['date_prevue'] ?? ''));
            $dateToKeep = $existingDate !== '' ? $existingDate : $datePrevue;
            $update = $pdo->prepare('UPDATE livrable_items SET titre = :titre, date_prevue = :date_prevue WHERE id = :id');
            $update->execute([
                'titre' => $titre,
                'date_prevue' => $dateToKeep,
                'id' => $existingId
            ]);
            return $existingId;
        }

        $insert = $pdo->prepare('INSERT INTO livrable_items
            (projet_id, plan_mensuel_id, type_livrable, numero_ordre, titre, statut, date_prevue, canal)
            VALUES
            (:projet_id, :plan_mensuel_id, :type_livrable, :numero_ordre, :titre, :statut, :date_prevue, :canal)');
        $insert->execute([
            'projet_id' => $project['id'],
            'plan_mensuel_id' => $planId,
            'type_livrable' => $type,
            'numero_ordre' => $index,
            'titre' => $titre,
            'statut' => 'Planifie',
            'date_prevue' => $datePrevue,
            'canal' => $project['canal_principal']
        ]);

        $createdId=(int)$pdo->lastInsertId();
        if(!empty($slot['format']))$pdo->prepare('UPDATE livrable_items SET sous_type=:format WHERE id=:id')->execute(['format'=>$slot['format'],'id'=>$createdId]);
        return $createdId;
    }

    private static function ensureContentItem(PDO $pdo, array $project, $planId, $deliverableId, $type, $index, DateTime $period) {
        $stmt = $pdo->prepare('SELECT id FROM contenus WHERE livrable_item_id = :livrable_item_id LIMIT 1');
        $stmt->execute(['livrable_item_id' => $deliverableId]);
        $existingId = $stmt->fetchColumn();

        $contentType = $type === 'Video' ? 'Video' : 'Visuel';
        $slot=$project['_cadence'][$type][$index-1]??null; $subject = $slot['label']??sprintf('%s mois %s #%s', $type, date('m/Y', strtotime($period->format('Y-m-01'))), $index);
        $personaId = !empty($project['campagne_id']) ? self::findCampaignPersonaId($pdo, (int) $project['campagne_id']) : null;

        $payload = [
            'campagne_id' => !empty($project['campagne_id']) ? (int) $project['campagne_id'] : null,
            'persona_id' => $personaId,
            'projet_id' => (int) $project['id'],
            'plan_mensuel_id' => (int) $planId,
            'livrable_item_id' => (int) $deliverableId,
            'type' => $contentType,
            'sous_type' => $slot['format']??null,
            'nombre_pages_carrousel' => 1,
            'sujet' => $subject,
            'message' => '',
            'objectif_publication' => '',
            'cible_libre' => '',
            'reseau_cible' => $project['canal_principal'] ?? '',
            'statut' => 'Strategique defini',
            'responsable' => ''
        ];

        if ($existingId && CadenceRevision::hasHistory($pdo,(int)$project['id']))return (int)$existingId;
        if ($existingId) {
            $update = $pdo->prepare('UPDATE contenus
                SET campagne_id = :campagne_id,
                    persona_id = COALESCE(persona_id, :persona_id),
                    projet_id = :projet_id,
                    plan_mensuel_id = :plan_mensuel_id,
                    type = :type,
                    sujet = CASE WHEN sujet IS NULL OR sujet = "" THEN :sujet ELSE sujet END,
                    reseau_cible = CASE WHEN reseau_cible IS NULL OR reseau_cible = "" THEN :reseau_cible ELSE reseau_cible END
                WHERE id = :id');
            $update->execute([
                'campagne_id' => $payload['campagne_id'],
                'persona_id' => $payload['persona_id'],
                'projet_id' => $payload['projet_id'],
                'plan_mensuel_id' => $payload['plan_mensuel_id'],
                'type' => $payload['type'],
                'sujet' => $payload['sujet'],
                'reseau_cible' => $payload['reseau_cible'],
                'id' => $existingId,
            ]);
            return (int) $existingId;
        }

        $insert = $pdo->prepare('INSERT INTO contenus
            (campagne_id, persona_id, projet_id, plan_mensuel_id, livrable_item_id, type, sous_type, nombre_pages_carrousel, sujet, message, objectif_publication, cible_libre, reseau_cible, statut, responsable)
            VALUES
            (:campagne_id, :persona_id, :projet_id, :plan_mensuel_id, :livrable_item_id, :type, :sous_type, :nombre_pages_carrousel, :sujet, :message, :objectif_publication, :cible_libre, :reseau_cible, :statut, :responsable)');
        $insert->execute($payload);

        return (int) $pdo->lastInsertId();
    }

    private static function findCampaignPersonaId(PDO $pdo, $campaignId) {
        if ($campaignId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT persona_cible FROM campagnes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campaignId]);
        $personaId = (int) $stmt->fetchColumn();
        return $personaId > 0 ? $personaId : null;
    }

    private static function isContentReady(array $row) {
        $hasMonthlyContext = (int) ($row['campagne_id'] ?? 0) > 0 || trim((string) ($row['objectif_mois'] ?? '')) !== '';
        $hasKeyDates = trim((string) ($row['temps_forts_mois'] ?? '')) !== '';
        $hasSpecificBrief = trim((string) ($row['sujet'] ?? '')) !== ''
            && trim((string) ($row['objectif_publication'] ?? '')) !== ''
            && trim((string) ($row['message'] ?? '')) !== ''
            && (((int) ($row['persona_id'] ?? 0) > 0) || trim((string) ($row['cible_libre'] ?? '')) !== '')
            && trim((string) ($row['reseau_cible'] ?? '')) !== '';

        return $hasMonthlyContext && $hasKeyDates && $hasSpecificBrief;
    }

    private static function ensureTask(PDO $pdo, array $data) {
                $stmt = $pdo->prepare("SELECT id, statut, auteur_id FROM taches_pipeline
            WHERE projet_id = :projet_id
              AND plan_mensuel_id <=> :plan_mensuel_id
              AND livrable_item_id <=> :livrable_item_id
              AND (
                    titre = :titre
                    OR (
                        type_tache = :type_tache
                        AND NOT (
                            plan_mensuel_id IS NULL
                            AND livrable_item_id IS NULL
                            AND type_tache = 'Onboarding'
                        )
                    )
              )
            LIMIT 1");
        $stmt->execute([
            'projet_id' => $data['projet_id'],
            'plan_mensuel_id' => $data['plan_mensuel_id'],
            'livrable_item_id' => $data['livrable_item_id'],
            'titre' => $data['titre'],
            'type_tache' => $data['type_tache']
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            if(CadenceRevision::hasHistory($pdo,(int)$data['projet_id']))return (int)$existing['id'];
            $currentStatus = $existing['statut'];
            $statusToKeep = in_array($currentStatus, ['En cours', 'Terminee', 'Annulee'], true) ? $currentStatus : $data['statut'];
            $existingAuthorId = (int) ($existing['auteur_id'] ?? 0);
            $authorToKeep = $existingAuthorId > 0 ? $existingAuthorId : $data['auteur_id'];
            $update = $pdo->prepare('UPDATE taches_pipeline
                SET titre = :titre,
                    parent_task_id = :parent_task_id,
                    type_tache = :type_tache,
                    auteur_id = :auteur_id,
                    statut = :statut,
                    deadline = :deadline,
                    ordre_pipeline = :ordre_pipeline,
                    notes = :notes
                WHERE id = :id');
            $update->execute([
                'titre' => $data['titre'],
                'parent_task_id' => $data['parent_task_id'],
                'type_tache' => $data['type_tache'],
                'auteur_id' => $authorToKeep,
                'statut' => $statusToKeep,
                'deadline' => $data['deadline'],
                'ordre_pipeline' => $data['ordre_pipeline'],
                'notes' => $data['notes'],
                'id' => $existing['id']
            ]);
            return (int) $existing['id'];
        }

        $insert = $pdo->prepare('INSERT INTO taches_pipeline
            (projet_id, plan_mensuel_id, livrable_item_id, parent_task_id, titre, type_tache, auteur_id, statut, deadline, ordre_pipeline, notes)
            VALUES
            (:projet_id, :plan_mensuel_id, :livrable_item_id, :parent_task_id, :titre, :type_tache, :auteur_id, :statut, :deadline, :ordre_pipeline, :notes)');
        $insert->execute($data);
        return (int) $pdo->lastInsertId();
    }

    private static function formatDate(DateTime $date, $dayOffset) {
        if ($dayOffset !== 0) {
            $date->modify(($dayOffset > 0 ? '+' : '') . $dayOffset . ' day');
        }
        return $date->format('Y-m-d');
    }

    private static function monthNameFr(DateTime $period) {
        $names = [
            1 => 'janvier',
            2 => 'fevrier',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'aout',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'decembre',
        ];
        $month = (int) $period->format('n');
        return $names[$month] ?? strtolower($period->format('F'));
    }

    private static function monthLabelForTitle(DateTime $period) {
        $name = self::monthNameFr($period);
        return ucfirst($name);
    }

    private static function monthLabelWithArticle(DateTime $period) {
        $name = self::monthNameFr($period);
        $first = strtolower(substr($name, 0, 1));
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y'];
        if (in_array($first, $vowels, true)) {
            return "d'" . $name;
        }

        return 'de ' . $name;
    }
}
