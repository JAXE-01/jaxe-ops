<?php
/** Manual monthly reminders. Validation decisions remain unchanged. */
class ValidationRequestService {
    private PDO $db;
    public function __construct() { $this->db = Database::getConnection(); }

    public function pending(array $context, array $user): array {
        $sourceType = (string) ($context['type_tache'] ?? '');
        $targetType = ['Production' => 'Validation interne', 'Montage' => 'Validation interne', 'Validation interne' => 'Validation client'][$sourceType] ?? null;
        if (!$targetType || empty($context['plan_mensuel_id']) || empty($context['projet_id'])) {
            throw new RuntimeException('Cette étape ne permet pas un envoi groupé en validation.');
        }
        if (!UserScope::canAccessTaskType($user, $sourceType)) throw new RuntimeException('Étape inaccessible.');
        $project = $this->db->prepare('SELECT p.*,c.tenant_id,c.organization_id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=?');
        $project->execute([(int) $context['projet_id']]);
        AgencyAccessPolicy::assertRecordCapability('projets', $project->fetch(PDO::FETCH_ASSOC) ?: null, $sourceType === 'Validation interne' ? 'validation' : 'content', true);

        // Follow the actual dependency, including customized validation gates.
        $sql = "SELECT target.id,target.titre,target.auteur_id,u.nom,u.email,u.statut user_status,li.titre livrable_titre
                FROM taches_pipeline source
                JOIN taches_pipeline target ON target.parent_task_id=source.id
                    AND target.livrable_item_id=source.livrable_item_id
                    AND target.plan_mensuel_id=source.plan_mensuel_id AND target.projet_id=source.projet_id
                JOIN livrable_items li ON li.id=source.livrable_item_id
                LEFT JOIN users u ON u.id=target.auteur_id
                WHERE source.projet_id=:project AND source.plan_mensuel_id=:plan
                    AND source.type_tache=:source AND source.statut='Terminee'
                    AND target.type_tache=:target AND target.statut IN ('A faire','En cours')
                    AND COALESCE(target.validation_decision,'')<>'Valide'
                    AND COALESCE(li.statut,'') NOT IN ('Annule','Exclu')";
        $params = ['project' => (int) $context['projet_id'], 'plan' => (int) $context['plan_mensuel_id'], 'source' => $sourceType, 'target' => $targetType];
        if ($sourceType === 'Validation interne') $sql .= " AND source.validation_decision='Valide'";
        if (UserScope::isScopedOperationalUser($user)) {
            $sql .= ' AND source.auteur_id=:author';
            $params['author'] = UserScope::userId($user);
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY target.auteur_id,li.numero_ordre,target.id');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function send(array $context, array $user): array {
        $tasks = $this->pending($context, $user);
        $result = ['sent' => 0, 'failed' => 0, 'unassigned' => 0, 'tasks' => count($tasks)];
        $groups = [];
        foreach ($tasks as $task) {
            $email = trim((string) ($task['email'] ?? ''));
            if (($task['user_status'] ?? '') !== 'Actif' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['unassigned']++;
                continue;
            }
            $groups[$email][] = $task;
        }
        $log = $this->db->prepare('INSERT INTO workflow_progress_reports(tenant_id,task_id,sender_id,subject,message,primary_recipient,cc_recipients,delivery_status) VALUES(?,?,?,?,?,?,?,?)');
        foreach ($groups as $email => $items) {
            $subject = 'Demande de validation · ' . ($context['client_nom'] ?? '') . ' · ' . ($context['projet_nom'] ?? '');
            $lines = ['Bonjour ' . ($items[0]['nom'] ?? ''), '', 'Ces contenus sont prêts et attendent votre validation.', 'Calendrier : ' . substr((string) ($context['periode_mois'] ?? ''), 0, 7), 'Demande de : ' . ($user['nom'] ?? ''), ''];
            foreach ($items as $item) {
                $lines[] = $item['livrable_titre'] . ' · ' . $item['titre'];
                $lines[] = route_url('/calendrier/task/' . (int) $item['id']);
                $lines[] = '';
            }
            $body = implode("\n", $lines);
            try {
                $ok = StraxMailTransport::send([$email], $subject, $body, route_url('/calendrier/task/' . (int) $items[0]['id']), 'Ouvrir la validation');
            } catch (Throwable $exception) {
                error_log('[validation-request] ' . $exception->getMessage());
                $ok = false;
            }
            $result[$ok ? 'sent' : 'failed']++;
            foreach ($items as $item) {
                $log->execute([TenantGuard::tenantId(), (int) $item['id'], (int) $user['id'], $subject, $body, $email, '', $ok ? 'Sent' : 'Failed']);
            }
        }
        return $result;
    }
}
