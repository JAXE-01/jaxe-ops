<?php
class CalendrierValidationException extends RuntimeException {
    private $fieldErrors;

    public function __construct($message, array $fieldErrors = [], $code = 0, Throwable $previous = null) {
        parent::__construct($message, (int) $code, $previous);
        $this->fieldErrors = $fieldErrors;
    }

    public function getFieldErrors() {
        return $this->fieldErrors;
    }
}

class CalendrierController extends Controller {
    private $calendrierModel;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('calendar.view');
        $this->calendrierModel = new CalendrierModel();
    }

    public function index() {
        $currentUser = $this->currentUser();
        $settingsModel = new SettingsModel();
        $monthFilter = trim((string) ($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
            $monthFilter = date('Y-m');
        }
        $filters = [
            'client_id' => trim((string) ($_GET['client_id'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'completion_min' => trim((string) ($_GET['completion_min'] ?? '')),
            'completion_max' => trim((string) ($_GET['completion_max'] ?? '')),
            'month' => $monthFilter,
            'group_by_client' => trim((string) ($_GET['group_by_client'] ?? '0')),
        ];

        $this->render('calendrier/index', [
            'pageTitle' => 'Pilotage projets',
            'projects' => $this->calendrierModel->getProjectsOverview($currentUser, $filters),
            'clients' => $this->calendrierModel->getAllClientsSimple(),
            'filters' => $filters,
            'globalStats' => $this->calendrierModel->getGlobalCalendarStats($currentUser),
            'globalMonthCalendar' => $this->calendrierModel->getGlobalPublicationCalendar($monthFilter, $filters['client_id'] ?? '', $currentUser),
            'calendarColorScheme' => $settingsModel->getCalendarColorScheme(),
            'openGlobalCalendar' => false,
            'showGlobalCalendar' => false,
            'showProjectsPilotage' => true,
        ]);
    }

    public function client($clientId) {
        $currentUser = $this->currentUser();
        $client = $this->calendrierModel->getClient($clientId);
        if (!$client) {
            $this->flash('error', 'Client introuvable.');
            $this->redirect('/calendrier');
        }

        $projects = $this->calendrierModel->getClientProjects($clientId, $currentUser);
        $projectCalendarStats = $this->calendrierModel->getClientProjectsCalendarStats($clientId, $currentUser);
        if (UserScope::isScopedOperationalUser($currentUser) && empty($projects)) {
            $this->flash('error', 'Vous n\'avez pas acces a ce client.');
            $this->redirect('/calendrier');
        }

        $this->render('calendrier/client', [
            'pageTitle' => 'Calendriers de ' . $client['entreprise'],
            'client' => $client,
            'projects' => $projects,
            'projectCalendarStats' => $projectCalendarStats
        ]);
    }

    public function projet($projectId) {
        $currentUser = $this->currentUser();
        $hasMonthFilter = array_key_exists('month', $_GET);
        $selectedMonth = trim((string) ($_GET['month'] ?? ''));
        $calendar = $this->calendrierModel->getProjectCalendar($projectId, $hasMonthFilter ? $selectedMonth : null, $currentUser);
        if (!$calendar) {
            $this->flash('error', 'Projet introuvable ou non accessible.');
            $this->redirect('/calendrier');
        }

        $projectReturnUrl = route_url('/calendrier/projet/' . (int) $projectId);
        if (!empty($calendar['selectedMonth'])) {
            $projectReturnUrl .= '?month=' . urlencode((string) $calendar['selectedMonth']);
        }

        if ($this->isPost() && (string) ($_POST['manager_action'] ?? '') === 'reassign_task') {
            $this->requirePermission('calendar.view');

            try {
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $newAuthorId = (int) ($_POST['auteur_id'] ?? 0);
                $task = $this->calendrierModel->getTaskWorkspace($taskId);
                if (!$task || (int) ($task['projet_id'] ?? 0) !== (int) $projectId) {
                    throw new RuntimeException('Tache introuvable pour ce projet.');
                }

                if (!$this->canManageBoardTask($currentUser, $task)) {
                    throw new RuntimeException('Seul le charge de com ou le responsable de cette tache peut modifier le responsable.');
                }

                $options = $this->getTaskReassignmentOptions($task);
                if ($newAuthorId <= 0 || !isset($options[(string) $newAuthorId])) {
                    throw new RuntimeException('Selectionnez un responsable valide pour cette tache.');
                }

                $this->calendrierModel->reassignTask($taskId, $newAuthorId);
                $this->flash('success', 'Responsable de la tache mis a jour.');
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
            }

            header('Location: ' . $projectReturnUrl);
            exit;
        }

        if ($this->isPost() && (string) ($_POST['manager_action'] ?? '') === 'move_publication_date') {
            $this->requirePermission('calendar.view');

            try {
                $deliverableId = (int) ($_POST['deliverable_id'] ?? 0);
                $targetDate = trim((string) ($_POST['new_date_prevue'] ?? ''));
                if ($deliverableId <= 0 || $targetDate === '') {
                    throw new RuntimeException('Selectionnez un contenu et une date valides.');
                }

                $deliverable = $this->calendrierModel->getDeliverableWorkspace($deliverableId, $currentUser);
                if (!$deliverable || (int) ($deliverable['projet_id'] ?? 0) !== (int) $projectId) {
                    throw new RuntimeException('Livrable introuvable pour ce projet.');
                }

                if (!$this->canManageBoardDeliverableDate($currentUser, $deliverable)) {
                    throw new RuntimeException('Seul le charge de com ou un responsable de ce livrable peut modifier la date.');
                }

                $this->calendrierModel->moveDeliverablePublicationDate($deliverableId, $targetDate);

                if ($this->isAjaxRequest()) {
                    $this->respondJson(['ok' => true, 'message' => 'Date de publication mise a jour.']);
                    return;
                }

                $this->flash('success', 'Date de publication mise a jour.');
            } catch (Throwable $exception) {
                if ($this->isAjaxRequest()) {
                    $this->respondJson(['ok' => false, 'message' => $exception->getMessage()], 400);
                    return;
                }

                $this->flash('error', $exception->getMessage());
            }

            header('Location: ' . $projectReturnUrl);
            exit;
        }

        $selectedPlanId = !empty($calendar['plans'][0]['id']) ? (int) $calendar['plans'][0]['id'] : 0;
        $readyDeliverablesForPublicValidation = $selectedPlanId > 0
            ? $this->calendrierModel->getReadyDeliverablesForClientValidation($selectedPlanId)
            : [];
        $publicValidationLinks = $selectedPlanId > 0
            ? $this->calendrierModel->getPublicValidationLinksByPlan($selectedPlanId)
            : [];
        $calendarStatsByPlan = $this->calendrierModel->getProjectStatsByCalendars((int) $projectId);

        $previousCalendarUrl = null;
        $nextCalendarUrl = null;
        $availableMonths = array_values((array) ($calendar['availableMonths'] ?? []));
        $resolvedMonth = (string) ($calendar['selectedMonth'] ?? '');

        if (!empty($availableMonths) && $resolvedMonth !== '') {
            $monthValues = array_values(array_map(static function ($month) {
                return (string) ($month['value'] ?? '');
            }, $availableMonths));
            $currentIndex = array_search($resolvedMonth, $monthValues, true);
            if ($currentIndex !== false) {
                if ($currentIndex > 0 && !empty($monthValues[$currentIndex - 1])) {
                    $previousCalendarUrl = route_url('/calendrier/projet/' . (int) $projectId) . '?month=' . urlencode((string) $monthValues[$currentIndex - 1]);
                }
                if ($currentIndex < count($monthValues) - 1 && !empty($monthValues[$currentIndex + 1])) {
                    $nextCalendarUrl = route_url('/calendrier/projet/' . (int) $projectId) . '?month=' . urlencode((string) $monthValues[$currentIndex + 1]);
                }
            }
        }

        $viewData = [
            'pageTitle' => 'Calendrier projet',
            'calendar' => $calendar,
            'showAllPipelineStages' => !UserScope::isScopedOperationalUser($currentUser),
            'selectedPlanId' => $selectedPlanId,
            'readyDeliverablesForPublicValidation' => $readyDeliverablesForPublicValidation,
            'publicValidationLinks' => $publicValidationLinks,
            'calendarStatsByPlan' => $calendarStatsByPlan,
            'previousCalendarUrl' => $previousCalendarUrl,
            'nextCalendarUrl' => $nextCalendarUrl,
            'boardCurrentUserId' => UserScope::userId($currentUser),
            'boardCanManageAsCC' => $this->canManageBoardActions($currentUser),
            'boardReassignmentOptions' => $this->can('calendar.view') ? [
                'creative' => (new SettingsModel())->getUserOptionsByRoles(['Admin', 'Createur', 'Designer', 'CC']),
                'video' => (new SettingsModel())->getUserOptionsByRoles(['Admin', 'Cadreur', 'Videaste', 'CC']),
                'validation_client' => (new SettingsModel())->getUserOptionsByRoles(['Admin', 'Clientele', 'CC']),
                'validation_interne' => (new SettingsModel())->getUserOptionsByRoles(['Admin', 'CC']),
                'publication' => (new SettingsModel())->getUserOptionsByRoles(['Admin', 'CM', 'CC']),
                'generic' => (new SettingsModel())->getUserOptionsByRoles(['Admin', 'CC', 'Createur', 'Designer', 'Cadreur', 'Videaste', 'CM', 'Clientele']),
            ] : [],
        ];

        if ($this->isAjaxRequest() && ((string) ($_GET['fragment'] ?? '') === 'board')) {
            extract($viewData);
            ob_start();
            require __DIR__ . '/../views/calendrier/projet_board.php';
            echo ob_get_clean();
            exit;
        }

        $this->render('calendrier/projet', $viewData);
    }

    public function publicationCalendar($projectId) {
        $projectId = (int) $projectId;
        $currentUser = $this->currentUser();
        $hasMonthFilter = array_key_exists('month', $_GET);
        $selectedMonth = trim((string) ($_GET['month'] ?? ''));
        $calendar = $this->calendrierModel->getProjectCalendar($projectId, $hasMonthFilter ? $selectedMonth : null, $currentUser);
        if (!$calendar || empty($calendar['plans'][0])) {
            $this->flash('error', 'Calendrier publication introuvable.');
            $this->redirect('/calendrier');
        }

        $plan = $calendar['plans'][0];
        $currentViewUrl = route_url('/calendrier/publicationCalendar/' . $projectId) . (!empty($plan['periode_mois']) ? '?month=' . urlencode((string) $plan['periode_mois']) : '');

        if ($this->isPost() && (string) ($_POST['manager_action'] ?? '') === 'move_publication_date') {
            $this->requirePermission('calendar.view');
            $targetDate = trim((string) ($_POST['new_date_prevue'] ?? ''));
            $deliverableId = (int) ($_POST['deliverable_id'] ?? 0);

            if ($this->isAjaxRequest()) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                try {
                    if ($deliverableId <= 0 || $targetDate === '') {
                        throw new RuntimeException('Selectionnez un contenu et une date valides.');
                    }
                    $this->calendrierModel->moveDeliverablePublicationDate($deliverableId, $targetDate);
                    $this->respondJson(['ok' => true, 'message' => 'Date de publication mise a jour.']);
                } catch (Throwable $exception) {
                    $this->respondJson(['ok' => false, 'message' => $exception->getMessage()], 400);
                }
                return;
            }

            try {
                if ($deliverableId <= 0 || $targetDate === '') {
                    throw new RuntimeException('Selectionnez un contenu et une date valides.');
                }

                $this->calendrierModel->moveDeliverablePublicationDate($deliverableId, $targetDate);
                $this->flash('success', 'Date de publication mise a jour.');
            } catch (Throwable $exception) {
                $this->flash('error', $exception->getMessage());
            }

            header('Location: ' . $currentViewUrl);
            exit;
        }

        $entries = $this->calendrierModel->getPublicationCalendarByPlan((int) $plan['id']);
        $items = $this->calendrierModel->getPublicationCalendarItemsByPlan((int) $plan['id']);
        $byDate = [];
        foreach ($entries as $entry) {
            $date = (string) ($entry['date_prevue'] ?? '');
            if ($date === '') {
                continue;
            }
            if (!isset($byDate[$date])) {
                $byDate[$date] = ['total' => 0, 'channels' => []];
            }
            $count = (int) ($entry['total'] ?? 0);
            $canal = (string) ($entry['canal'] ?? 'Non defini');
            $byDate[$date]['total'] += $count;
            $byDate[$date]['channels'][$canal] = ($byDate[$date]['channels'][$canal] ?? 0) + $count;
        }

        $itemsByDate = [];
        foreach ($items as $item) {
            $date = (string) ($item['date_prevue'] ?? '');
            if ($date === '') {
                continue;
            }
            if (!isset($itemsByDate[$date])) {
                $itemsByDate[$date] = [];
            }
            $itemsByDate[$date][] = $item;
        }

        $this->render('calendrier/publication-calendar', [
            'pageTitle' => 'Calendrier publication',
            'project' => $calendar['project'],
            'plan' => $plan,
            'eventsByDate' => $byDate,
            'itemsByDate' => $itemsByDate,
            'canManageCalendar' => $this->can('calendar.view'),
            'projectBoardUrl' => route_url('/calendrier/projet/' . $projectId) . (!empty($plan['periode_mois']) ? '?month=' . urlencode((string) $plan['periode_mois']) : ''),
            'returnTo' => route_url('/calendrier/projet/' . $projectId) . (!empty($plan['periode_mois']) ? '?month=' . urlencode((string) $plan['periode_mois']) : ''),
        ]);
    }

    public function createPublicValidationLink($planId = null) {
        $planId = (int) ($planId ?? ($_POST['plan_id'] ?? 0));
        $returnTo = trim((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? route_url('/calendrier')));
        if ($planId <= 0) {
            $this->flash('error', 'Plan invalide pour le lien client.');
            header('Location: ' . $returnTo);
            exit;
        }

        try {
            $deliverableIds = array_values(array_filter(array_map('intval', (array) ($_POST['deliverable_ids'] ?? []))));
            $expiryDays = (int) ($_POST['expiry_days'] ?? 45);
            $token = $this->calendrierModel->createPublicValidationLink($planId, (int) (($this->currentUser()['id'] ?? 0)), $deliverableIds, $expiryDays);
            $url = route_url('/public-validation/index/' . $token);
            $this->flash('success', 'Lien public genere: ' . $url);
        } catch (Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        header('Location: ' . $returnTo);
        exit;
    }

    public function revokePublicValidationLink($linkId) {
        $linkId = (int) $linkId;
        $returnTo = trim((string) ($_GET['return_to'] ?? route_url('/calendrier')));
        if ($linkId <= 0) {
            $this->flash('error', 'Lien invalide.');
            header('Location: ' . $returnTo);
            exit;
        }

        try {
            $this->calendrierModel->revokePublicValidationLink($linkId);
            $this->flash('success', 'Lien public revoque.');
        } catch (Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        header('Location: ' . $returnTo);
        exit;
    }

    public function contenu($deliverableId) {
        $currentUser = $this->currentUser();
        $isInlineAutosave = $this->isPost() && $this->isAjaxRequest() && !empty($_POST['autosave_mode']);
        $workspace = $this->calendrierModel->getContentWorkspace($deliverableId, $currentUser);
        if (!$workspace) {
            $this->flash('error', 'Fiche contenu introuvable ou non accessible.');
            $this->redirect('/calendrier');
        }

        $this->assertWorkspaceCapability($workspace,'content',$this->isPost());
        $returnTo = $this->resolveCalendarReturn($workspace['projet_id'], $workspace['periode_mois'] ?? null);
        $campaignOptions = (new CrudModel(ModuleRegistry::get('campagne')))->getRelationOptions('campagne');
        $personaOptions = $this->calendrierModel->getPersonaOptionsByClient(
            (int) ($workspace['client_id'] ?? 0),
            (int) ($workspace['persona_id'] ?? 0)
        );
        $contentObjectiveOptions = (new SettingsModel())->getContentObjectiveOptions();
        $canEdit = $this->canManageContentSetup($currentUser);
        $canManagerInvalidate = $this->canManagerInvalidateContent($currentUser);
        $monthStart = !empty($workspace['periode_mois']) ? date('Y-m-01', strtotime((string) $workspace['periode_mois'])) : '';
        $monthEnd = !empty($workspace['periode_mois']) ? date('Y-m-t', strtotime((string) $workspace['periode_mois'])) : '';
        $scheduledPublicationDates = $this->calendrierModel->getPlanScheduledPublicationDates((int) ($workspace['plan_mensuel_id'] ?? 0), (int) $deliverableId);
        $contentNavigation = $this->calendrierModel->getSiblingContentNavigation((int) $deliverableId, (int) ($workspace['plan_mensuel_id'] ?? 0), $currentUser);
        $briefTaskType = (($workspace['type_livrable'] ?? '') === 'Video') ? 'Script' : 'Brief';
        $briefTaskId = null;
        foreach ((array) ($workspace['tasks'] ?? []) as $taskRow) {
            if ((string) ($taskRow['type_tache'] ?? '') === $briefTaskType) {
                $briefTaskId = (int) ($taskRow['id'] ?? 0);
                break;
            }
        }

        if ($this->isPost()) {
            if ((string) ($_POST['manager_action'] ?? '') === 'invalidate_content') {
                if (!$canManagerInvalidate) {
                    $this->flash('error', 'Seul un manager peut invalider la fiche contenu.');
                    header('Location: ' . $returnTo);
                    exit;
                }

                try {
                    $this->calendrierModel->invalidatePlanTaskByType((int) ($workspace['plan_mensuel_id'] ?? 0), 'Calendrier');
                    PipelineService::syncContentReadinessForPlan((int) ($workspace['plan_mensuel_id'] ?? 0));
                    PipelineService::syncContentStatusByDeliverable((int) $deliverableId);
                    if ($isInlineAutosave) {
                        $this->respondJson(['ok' => true, 'autosaved' => true, 'message' => 'Fiche invalidee.', 'at' => date('H:i:s')]);
                    }
                    $this->flash('success', 'Fiche contenu marquee comme non valide.');
                    header('Location: ' . $returnTo);
                    exit;
                } catch (Throwable $exception) {
                    if ($isInlineAutosave) {
                        $this->respondJson(['ok' => false, 'message' => $exception->getMessage()], 422);
                    }
                    $this->flash('error', $exception->getMessage());
                    $workspace = array_merge($workspace, $_POST);
                }
            }

            if (!$canEdit) {
                $this->flash('error', 'Seul le charge de communication peut completer la fiche contenu.');
                header('Location: ' . $returnTo);
                exit;
            }

            try {
                [$monthlyPayload, $contentPayload] = $this->buildContentWorkspacePayload($workspace);
                $this->calendrierModel->saveContentWorkspace((int) $deliverableId, $monthlyPayload, $contentPayload);
                $calendarTaskSync = $this->calendrierModel->syncPlanCalendarTaskStatus((int) $workspace['plan_mensuel_id']);
                if (!empty($calendarTaskSync['became_completed']) && !empty($calendarTaskSync['id'])) {
                    PipelineService::unlockChildren((int) $calendarTaskSync['id']);
                }
                PipelineService::syncContentReadinessForPlan((int) $workspace['plan_mensuel_id']);
                PipelineService::syncContentStatusByDeliverable((int) $deliverableId);
                if ($isInlineAutosave) {
                    $this->respondJson(['ok' => true, 'autosaved' => true, 'message' => 'Brouillon enregistre.', 'at' => date('H:i:s')]);
                }
                $this->flash('success', 'Fiche contenu mise a jour.');
                header('Location: ' . $returnTo);
                exit;
            } catch (Throwable $exception) {
                if ($isInlineAutosave) {
                    $this->respondJson(['ok' => false, 'message' => $exception->getMessage()], 422);
                }
                $this->flash('error', $exception->getMessage());
                $workspace = array_merge($workspace, $_POST);
            }
        }

        $this->render('calendrier/contenu', [
            'pageTitle' => 'Fiche contenu',
            'workspace' => $workspace,
            'returnTo' => $returnTo,
            'campaignOptions' => $campaignOptions,
            'personaOptions' => $personaOptions,
            'contentObjectiveOptions' => $contentObjectiveOptions,
            'canEditContentSetup' => $canEdit,
            'canManagerInvalidate' => $canManagerInvalidate,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'scheduledPublicationDates' => $scheduledPublicationDates,
            'previousContentUrl' => !empty($contentNavigation['previous']) ? route_url('/calendrier/contenu/' . (int) $contentNavigation['previous']) : null,
            'nextContentUrl' => !empty($contentNavigation['next']) ? route_url('/calendrier/contenu/' . (int) $contentNavigation['next']) : null,
            'briefEditUrl' => $briefTaskId > 0 ? route_url('/calendrier/task/' . $briefTaskId) : null
        ]);
    }

    public function task($taskId) {
        $isInlineAutosave = $this->isPost() && $this->isAjaxRequest() && !empty($_POST['autosave_mode']);
        $inlineErrors = [];
        $canManageTaskActions = $this->canManageBoardActions($this->currentUser());
        $task = $this->calendrierModel->getTaskWorkspace($taskId, $this->currentUser());
        if (!$task) {
            $this->flash('error', 'Tache introuvable ou non accessible.');
            $this->redirect('/calendrier');
        }

        $this->assertWorkspaceCapability($task,$this->capabilityForTask($task),$this->isPost()||isset($_GET['remove_brief_file'])||isset($_GET['remove_file']));
        $task = $this->hydrateTournageTaskFields($task);

        $this->guardClienteleTaskAccess($task);

        $returnTo = $this->resolveCalendarReturn($task['projet_id'], $task['periode_mois'] ?? null);

        if (isset($_GET['remove_brief_file']) && in_array((string) ($task['type_tache'] ?? ''), ['Brief', 'Script'], true) && !empty($task['deliverable'])) {
            $this->removeBriefFile($task['deliverable'], (int) $_GET['remove_brief_file'], $returnTo);
        }

        if (isset($_GET['remove_file'])) {
            $this->removeTaskFile($task, (int) $_GET['remove_file'], $returnTo);
        }

        if ($this->isPost()) {
            try {
                if ((string) ($_POST['manager_action'] ?? '') === 'create_public_validation_link') {
                    if (($task['type_tache'] ?? '') !== 'Validation client') {
                        throw new RuntimeException('La generation de lien public est reservee a la validation client.');
                    }

                    $canGenerateFromTask = $canManageTaskActions || $this->can('calendar.view') || $this->isClienteleUser();
                    if (!$canGenerateFromTask) {
                        throw new RuntimeException('Vous n avez pas la permission de generer un lien public.');
                    }

                    $planId = (int) ($task['plan_mensuel_id'] ?? 0);
                    if ($planId <= 0) {
                        throw new RuntimeException('Plan mensuel introuvable pour cette tache.');
                    }

                    $deliverableIds = array_values(array_filter(array_map('intval', (array) ($_POST['deliverable_ids'] ?? []))));
                    if (empty($deliverableIds) && !empty($task['livrable_item_id'])) {
                        $deliverableIds = [(int) $task['livrable_item_id']];
                    }
                    $expiryDays = (int) ($_POST['expiry_days'] ?? 45);
                    $token = $this->calendrierModel->createPublicValidationLink($planId, (int) (($this->currentUser()['id'] ?? 0)), $deliverableIds, $expiryDays);
                    $url = route_url('/public-validation/index/' . $token);
                    $this->flash('success', 'Lien public genere: ' . $url);
                    header('Location: ' . route_url('/calendrier/task/' . (int) $taskId));
                    exit;
                }

                if ((string) ($_POST['manager_action'] ?? '') === 'reassign_task') {
                    if (!$canManageTaskActions) {
                        throw new RuntimeException('Vous n avez pas la permission de reattribuer cette tache.');
                    }

                    $newAuthorId = (int) ($_POST['auteur_id'] ?? 0);
                    $options = $this->getTaskReassignmentOptions($task);
                    if ($newAuthorId <= 0 || !isset($options[(string) $newAuthorId])) {
                        throw new RuntimeException('Selectionnez un responsable valide pour cette reattribution.');
                    }

                    $this->calendrierModel->reassignTask((int) $taskId, $newAuthorId);
                    $this->flash('success', 'Responsable de la tache mis a jour.');
                    header('Location: ' . route_url('/calendrier/task/' . (int) $taskId));
                    exit;
                }

                if ($this->requestExceededPostMaxSize()) {
                    throw new RuntimeException('Le fichier selectionne depasse la taille maximale acceptee par le serveur (' . $this->getPhpUploadLimitLabel() . '). Augmente upload_max_filesize et post_max_size dans PHP/XAMPP pour charger ce fichier.');
                }

                if (in_array((string) ($task['type_tache'] ?? ''), ['Brief', 'Script'], true) && !empty($task['livrable_item_id'])) {
                    if ((string) ($_POST['manager_action'] ?? '') === 'invalidate_brief') {
                        if (!$this->canManagerInvalidateContent($this->currentUser())) {
                            throw new RuntimeException('Seul un manager peut invalider le brief.');
                        }

                        $this->calendrierModel->invalidateTaskById((int) $taskId);
                        $this->calendrierModel->syncDeliverableStatus((int) $task['livrable_item_id']);
                        PipelineService::syncContentStatusByDeliverable((int) $task['livrable_item_id']);
                        $this->flash('success', 'Brief marque comme non valide.');
                        header('Location: ' . $returnTo);
                        exit;
                    }

                    $deliverable = $task['deliverable'] ?? $this->calendrierModel->getDeliverableWorkspace((int) $task['livrable_item_id'], $this->currentUser());
                    if (!$deliverable) {
                        throw new RuntimeException('Livrable introuvable pour cette tache.');
                    }

                    [$briefPayload, $deliverablePayload, $taskPayload] = $this->buildDeliverablePayload($deliverable, ($task['type_tache'] ?? '') === 'Script');
                    $this->calendrierModel->saveDeliverableBrief((int) $deliverable['id'], $briefPayload, $deliverablePayload);
                    if ($taskPayload !== null) {
                        $this->calendrierModel->saveTaskWorkflow((int) $taskPayload['id'], $taskPayload['update']);
                        if ($taskPayload['update']['statut'] === 'Terminee') {
                            PipelineService::unlockChildren((int) $taskPayload['id']);
                        }
                    }
                    if ((string) ($briefPayload['statut'] ?? '') === 'Valide') {
                        PipelineService::unlockChildren((int) $taskId);
                    }
                    $this->calendrierModel->syncDeliverableStatus((int) $deliverable['id']);
                    PipelineService::syncContentStatusByDeliverable((int) $deliverable['id']);
                } else {
                    $payload = $this->buildTaskPayload($task);

                    if (($task['type_tache'] ?? '') === 'Publication' && !empty($task['livrable_item_id'])) {
                        $plannedDate = trim((string) ($_POST['date_prevue'] ?? ''));
                        if ($plannedDate !== '') {
                            if (!$canManageTaskActions) {
                                throw new RuntimeException('Seuls Admin, CC et responsables d equipe peuvent modifier la date planifiee.');
                            }
                            $this->calendrierModel->moveDeliverablePublicationDate((int) $task['livrable_item_id'], $plannedDate);
                        }

                        $publicationPayload = $this->buildPublicationEntryPayload($task, $payload);
                        if ($publicationPayload !== null) {
                            $this->calendrierModel->savePublicationEntry((int) $taskId, $publicationPayload['payload'], $publicationPayload['id']);
                        }
                    }

                    if (($task['type_tache'] ?? '') === 'Collecte KPI' && !empty($task['livrable_item_id'])) {
                        $resultPayloads = $this->buildContentResultPayloads();
                        foreach ($resultPayloads as $resultPayload) {
                            $this->calendrierModel->createContentResultEntry((int) $taskId, $resultPayload);
                        }
                    }

                    $this->calendrierModel->saveTaskWorkflow((int) $taskId, $payload);
                    if (in_array((string) ($task['type_tache'] ?? ''), ['Validation interne', 'Validation client'], true)
                        && in_array((string) ($payload['validation_decision'] ?? ''), ['Valide', 'Non valide'], true)) {
                        $this->calendrierModel->recordInternalValidationDecision((int) $taskId, (string) $payload['validation_decision'], (string) ($payload['validation_commentaire'] ?? ''));
                    }
                    if (in_array((string) ($task['type_tache'] ?? ''), ['Validation interne', 'Validation client'], true)
                        && ($payload['validation_decision'] ?? '') === 'Non valide'
                        && !empty($task['livrable_item_id'])) {
                        $this->calendrierModel->markCreativeTaskAsInvalidForDeliverable((int) $task['livrable_item_id']);
                    }
                    if (!empty($task['livrable_item_id'])) {
                        $this->calendrierModel->syncDeliverableStatus((int) $task['livrable_item_id']);
                        PipelineService::syncContentStatusByDeliverable((int) $task['livrable_item_id']);
                    }
                    if ($payload['statut'] === 'Terminee') {
                        PipelineService::unlockChildren((int) $taskId);
                    }
                }

                if (($task['type_tache'] ?? '') === 'Calendrier' && !empty($task['plan_mensuel_id'])) {
                    PipelineService::syncContentReadinessForPlan((int) $task['plan_mensuel_id']);
                }
                $this->flash('success', 'Tache mise a jour.');
                if ($isInlineAutosave) {
                    $this->respondJson(['ok' => true, 'autosaved' => true, 'message' => 'Brouillon enregistre.', 'at' => date('H:i:s')]);
                }
                header('Location: ' . $returnTo);
                exit;
            } catch (Throwable $exception) {
                $fieldErrors = [];
                if ($exception instanceof CalendrierValidationException) {
                    $fieldErrors = $exception->getFieldErrors();
                }
                if ($isInlineAutosave) {
                    $this->respondJson(['ok' => false, 'message' => $exception->getMessage(), 'errors' => $fieldErrors], 422);
                }
                $this->flash('error', $exception->getMessage());
                $task = array_merge($task, $_POST);
                $this->assertWorkspaceCapability($task,$this->capabilityForTask($task),$this->isPost()||isset($_GET['remove_brief_file'])||isset($_GET['remove_file']));
        $task = $this->hydrateTournageTaskFields($task);
                $inlineErrors = $fieldErrors;
            }
        }

        $navigation = $this->calendrierModel->getSiblingTaskNavigation((int) $taskId, (string) ($task['type_tache'] ?? ''), (int) ($task['plan_mensuel_id'] ?? 0), $this->currentUser());
        $previousTaskUrl = !empty($navigation['previous']) ? route_url('/calendrier/task/' . (int) $navigation['previous']) : null;
        $nextTaskUrl = !empty($navigation['next']) ? route_url('/calendrier/task/' . (int) $navigation['next']) : null;
        $selectedSocialAccountPreview = [];
        if ((string) ($task['type_tache'] ?? '') === 'Publication') {
            $selectedSocialAccountPreview = $this->calendrierModel->getClientSocialAccountPreview(
                (int) ($task['client_id'] ?? 0),
                (string) ($_POST['canal'] ?? $task['latest_publication']['canal'] ?? $task['reseau_cible'] ?? $task['canal_principal'] ?? '')
            );
        }
        $canReassignTask = $canManageTaskActions;
        $reassignmentOptions = $canReassignTask ? $this->getTaskReassignmentOptions($task) : [];
        $canGeneratePublicValidationLink = ($task['type_tache'] ?? '') === 'Validation client'
            && ($canManageTaskActions || $this->can('calendar.view') || $this->isClienteleUser());
        $taskPublicValidationLinks = [];
        $taskReadyDeliverablesForPublicValidation = [];
        if ($canGeneratePublicValidationLink && !empty($task['plan_mensuel_id'])) {
            $taskReadyDeliverablesForPublicValidation = $this->calendrierModel->getReadyDeliverablesForClientValidation((int) $task['plan_mensuel_id']);
            $taskPublicValidationLinks = $this->calendrierModel->getPublicValidationLinksByPlan((int) $task['plan_mensuel_id']);
        }
        $requireSecondMontageVideo = $this->isSecondMontageVideoRequired();
        $kpiNetworkConfig = $this->getKpiNetworkConfig();

        $this->render('calendrier/task', [
            'pageTitle' => $task['titre'],
            'task' => $task,
            'returnTo' => $returnTo,
            'canViewFutureContentInfo' => $this->canViewFutureContentInfo($this->currentUser()),
            'phpUploadLimitLabel' => $this->getPhpUploadLimitLabel(),
            'canManagerInvalidate' => $this->canManagerInvalidateContent($this->currentUser()),
            'previousTaskUrl' => $previousTaskUrl,
            'nextTaskUrl' => $nextTaskUrl,
            'selectedSocialAccountPreview' => $selectedSocialAccountPreview,
            'canReassignTask' => $canReassignTask,
            'canManageTaskPlanningDate' => $canManageTaskActions,
            'reassignmentOptions' => $reassignmentOptions,
            'inlineErrors' => $inlineErrors,
            'canGeneratePublicValidationLink' => $canGeneratePublicValidationLink,
            'taskPublicValidationLinks' => $taskPublicValidationLinks,
            'taskReadyDeliverablesForPublicValidation' => $taskReadyDeliverablesForPublicValidation,
            'requireSecondMontageVideo' => $requireSecondMontageVideo,
            'kpiNetworkConfig' => $kpiNetworkConfig,
        ]);
    }

    public function brief($deliverableId) {
        $deliverable = $this->calendrierModel->getDeliverableWorkspace($deliverableId, $this->currentUser());
        if (!$deliverable) {
            $this->flash('error', 'Livrable introuvable ou non accessible.');
            $this->redirect('/calendrier');
        }

        $taskId = $this->resolveDeliverableTaskId($deliverable, 'Brief');
        if ($taskId === null && ($deliverable['type_livrable'] ?? '') === 'Video') {
            $taskId = $this->resolveDeliverableTaskId($deliverable, 'Script');
        }

        $this->flash('success', 'Le brief se gere maintenant depuis la tache du pipeline.');
        $this->redirectToTaskWorkspace($taskId, (int) ($deliverable['projet_id'] ?? 0));
    }

    public function script($deliverableId) {
        $deliverable = $this->calendrierModel->getDeliverableWorkspace($deliverableId, $this->currentUser());
        if (!$deliverable) {
            $this->flash('error', 'Livrable introuvable ou non accessible.');
            $this->redirect('/calendrier');
        }

        $taskId = $this->resolveDeliverableTaskId($deliverable, 'Script');
        if ($taskId === null && ($deliverable['type_livrable'] ?? '') !== 'Video') {
            $taskId = $this->resolveDeliverableTaskId($deliverable, 'Brief');
        }

        $this->flash('success', 'Le script se gere maintenant depuis la tache du pipeline.');
        $this->redirectToTaskWorkspace($taskId, (int) ($deliverable['projet_id'] ?? 0));
    }

    public function livrable($deliverableId) {
        $deliverable = $this->calendrierModel->getDeliverableWorkspace($deliverableId, $this->currentUser());
        if (!$deliverable) {
            $this->flash('error', 'Livrable introuvable ou non accessible.');
            $this->redirect('/calendrier');
        }
        $stage = trim((string) ($_GET['stage'] ?? ''));
        $resolvedStage = $stage !== '' ? $stage : (($deliverable['type_livrable'] ?? '') === 'Video' ? 'Montage' : 'Production');
        $taskId = $this->resolveDeliverableTaskId($deliverable, $resolvedStage);

        $this->flash('success', 'Le livrable se gere maintenant depuis la tache du pipeline.');
        $this->redirectToTaskWorkspace($taskId, (int) ($deliverable['projet_id'] ?? 0));
    }

    public function publication($taskId) {
        $this->flash('success', 'La publication se gere maintenant depuis la tache du pipeline.');
        $this->redirect('/calendrier/task/' . (int) $taskId);
    }

    private function resolveCalendarReturn($projectId, $periodMonth = null) {
        $url = route_url('/calendrier/projet/' . $projectId);
        if (!empty($periodMonth)) {
            $url .= '?month=' . urlencode((string) $periodMonth);
        }
        return $url;
    }

    private function guardClienteleTaskAccess(array $task) {
        if (!$this->isClienteleUser()) {
            return;
        }

        if (!$this->isPost() && !isset($_GET['remove_file'])) {
            return;
        }

        if ((string) ($_POST['manager_action'] ?? '') === 'invalidate_brief' && $this->canManagerInvalidateContent($this->currentUser())) {
            return;
        }

        if (($task['type_tache'] ?? '') === 'Validation client') {
            return;
        }

        $this->flash('error', 'Le role clientele peut modifier uniquement les taches de validation client.');
        $this->redirect($this->resolveCalendarReturn($task['projet_id'], $task['periode_mois'] ?? null));
    }

    private function isClienteleUser() {
        return UserRoles::hasRole($this->currentUser(), 'Clientele');
    }

    private function canManageContentSetup(array $user = null) {
        return UserRoles::hasAnyRole($user, ['Admin', 'CC', 'CM']);
    }

    private function canManagerInvalidateContent(array $user = null) {
        return UserRoles::hasAnyRole($user, ['Admin', 'Clientele']);
    }

    private function canViewFutureContentInfo(array $user = null) {
        return UserRoles::hasAnyRole($user, ['Admin', 'CC', 'Clientele']);
    }
    private function isSecondMontageVideoRequired() {
        $workflowRulesConfig = (new SettingsModel())->getWorkflowRulesConfig();
        return !empty($workflowRulesConfig['require_second_montage_video']);
    }

    private function getTaskReassignmentOptions(array $task) {
        $roles = $this->getReassignmentRolesForTaskType((string) ($task['type_tache'] ?? ''));
        return (new SettingsModel())->getUserOptionsByRoles($roles);
    }

    private function getReassignmentRolesForTaskType($taskType) {
        $taskType = trim((string) $taskType);
        if (in_array($taskType, ['Brief', 'Script', 'Production'], true)) {
            return ['Admin', 'CC', 'Createur', 'Designer'];
        }
        if (in_array($taskType, ['Tournage', 'Montage'], true)) {
            return ['Admin', 'CC', 'Cadreur', 'Videaste'];
        }
        if ($taskType === 'Validation client') {
            return ['Admin', 'CC', 'Clientele'];
        }
        if ($taskType === 'Validation interne') {
            return ['Admin', 'CC'];
        }
        if (in_array($taskType, ['Publication', 'Interactions', 'Collecte KPI'], true)) {
            return ['Admin', 'CC', 'CM'];
        }

        return ['Admin', 'CC', 'Createur', 'Designer', 'Cadreur', 'Videaste', 'CM', 'Clientele'];
    }

    private function resolveDeliverableTaskId(array $deliverable, $taskType) {
        $taskMap = $deliverable['taskMap'] ?? [];
        $task = $taskMap[$taskType] ?? null;
        return !empty($task['id']) ? (int) $task['id'] : null;
    }

    private function redirectToTaskWorkspace($taskId, $projectId = 0) {
        if ((int) $taskId > 0) {
            $this->redirect('/calendrier/task/' . (int) $taskId);
        }

        if ((int) $projectId > 0) {
            $this->redirect('/calendrier/projet/' . (int) $projectId);
        }

        $this->redirect('/calendrier');
    }

    private function buildContentWorkspacePayload(array $workspace) {
        $monthlyPayload = [
            'contexte_mois' => trim((string) ($_POST['contexte_mois'] ?? $workspace['contexte_mois'] ?? '')),
            'objectif_mois' => trim((string) ($_POST['objectif_mois'] ?? $workspace['objectif_mois'] ?? '')),
            'temps_forts_mois' => trim((string) ($_POST['temps_forts_mois'] ?? $workspace['temps_forts_mois'] ?? ''))
        ];

        $personaId = (int) ($_POST['persona_id'] ?? $workspace['persona_id'] ?? 0);
        $contentPayload = [
            'campagne_id' => !empty($workspace['campagne_id']) ? (int) $workspace['campagne_id'] : null,
            'persona_id' => $personaId > 0 ? $personaId : null,
            'sous_type' => trim((string) ($_POST['sous_type'] ?? $workspace['sous_type'] ?? '')),
            'nombre_pages_carrousel' => max(1, (int) ($_POST['nombre_pages_carrousel'] ?? $workspace['nombre_pages'] ?? 1)),
            'date_prevue' => trim((string) ($_POST['date_prevue'] ?? $workspace['date_prevue'] ?? '')),
            'sujet' => trim((string) ($_POST['sujet'] ?? $workspace['contenu_sujet'] ?? $workspace['titre'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? $workspace['contenu_message'] ?? '')),
            'objectif_publication' => trim((string) ($_POST['objectif_publication'] ?? $workspace['objectif_publication'] ?? '')),
            'cible_libre' => trim((string) ($_POST['cible_libre'] ?? $workspace['cible_libre'] ?? '')),
            'reseau_cible' => trim((string) ($_POST['reseau_cible'] ?? $workspace['reseau_cible'] ?? $workspace['canal'] ?? $workspace['canal_principal'] ?? '')),
            'responsable' => trim((string) ($_POST['responsable'] ?? $workspace['contenu_responsable'] ?? '')),
            'composition_method' => 'TPACK',
            'tpack_target' => trim((string) ($_POST['tpack_target'] ?? $workspace['tpack_target'] ?? '')),
            'tpack_objective' => trim((string) ($_POST['tpack_objective'] ?? $workspace['tpack_objective'] ?? '')),
            'tpack_problem' => trim((string) ($_POST['tpack_problem'] ?? $workspace['tpack_problem'] ?? '')),
            'tpack_product' => trim((string) ($_POST['tpack_product'] ?? $workspace['tpack_product'] ?? '')),
            'tpack_format' => trim((string) ($_POST['tpack_format'] ?? $workspace['tpack_format'] ?? '')),
            'tpack_cta' => trim((string) ($_POST['tpack_cta'] ?? $workspace['tpack_cta'] ?? '')),
            'tpack_platform' => trim((string) ($_POST['tpack_platform'] ?? $workspace['tpack_platform'] ?? '')),
            'tpack_hook' => trim((string) ($_POST['tpack_hook'] ?? $workspace['tpack_hook'] ?? '')),
            'tpack_priority' => trim((string) ($_POST['tpack_priority'] ?? $workspace['tpack_priority'] ?? 'Moyenne')),
            'tpack_status' => trim((string) ($_POST['tpack_status'] ?? $workspace['tpack_status'] ?? 'A discuter')),
            'tpack_generated_brief' => trim((string) ($_POST['tpack_generated_brief'] ?? $workspace['tpack_generated_brief'] ?? ''))
        ];

        return [$monthlyPayload, $contentPayload];
    }

    private function buildTaskPayload(array $task) {
        $status = trim((string) ($_POST['statut'] ?? $task['statut'] ?? 'A faire'));
        $notes = trim((string) ($_POST['notes'] ?? $task['notes'] ?? ''));
        $validationDecision = trim((string) ($_POST['validation_decision'] ?? $task['validation_decision'] ?? 'En attente'));
        $rawScore = trim((string) ($_POST['note_sur_10'] ?? $task['note_sur_10'] ?? ''));
        $score = null;
        if ($rawScore !== '') {
            $score = (int) $rawScore;
            if ($score < 0 || $score > 10) {
                throw $this->taskValidationError('La note doit etre comprise entre 0 et 10.', ['note_sur_10' => 'Entrez une note entre 0 et 10.']);
            }
        }
        $validationComment = trim((string) ($_POST['validation_commentaire'] ?? $task['validation_commentaire'] ?? ''));
        $networks = array_values(array_filter((array) ($_POST['publication_reseaux'] ?? [])));

        if (in_array($task['type_tache'], ['Validation interne', 'Validation client'], true)) {
            if ($validationDecision === 'Valide') {
                $status = 'Terminee';
            } elseif ($validationDecision === 'Non valide') {
                $status = 'En cours';
                if ($validationComment !== '') {
                    $notes = $this->appendInvalidationHistory($notes, (string) ($task['type_tache'] ?? ''), $validationComment);
                }
            }
        }

        if ($task['type_tache'] === 'Publication' && !empty($networks)) {
            $status = 'Terminee';
        }

        if (($task['type_tache'] ?? '') === 'Tournage') {
            $storageDisk = trim((string) ($_POST['tournage_disque'] ?? ''));
            $storageFolder = trim((string) ($_POST['tournage_dossier'] ?? ''));

            if ($status === 'Terminee' && ($storageDisk === '' || $storageFolder === '')) {
                throw $this->taskValidationError(
                    'Pour terminer le tournage, renseigne le disque et le dossier de copie des fichiers.',
                    [
                        'tournage_disque' => $storageDisk === '' ? 'Le disque de copie est requis.' : '',
                        'tournage_dossier' => $storageFolder === '' ? 'Le dossier de copie est requis.' : '',
                    ]
                );
            }

            $notes = $this->composeTournageNotes($storageDisk, $storageFolder, $notes);
        }

        $files = $this->processUploads('fichiers_livres', ['png','jpg','jpeg','pdf','psd','psb','mp4','mov','zip'], $task['fichiers_livres'] ?? null);
        $this->validateTaskFiles($task, $status, $files);
        $this->validateFinalApprovalRequirements($task, $status, $validationDecision);

        return [
            'statut' => $status,
            'notes' => $notes,
            'fichiers_livres' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'validation_decision' => $validationDecision,
            'note_sur_10' => $score,
            'validation_commentaire' => $validationComment,
            'publication_reseaux' => json_encode($networks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];
    }

    private function appendInvalidationHistory($notes, $taskType, $comment) {
        $notes = trim((string) $notes);
        $taskType = trim((string) $taskType);
        $comment = trim((string) $comment);
        if ($comment === '') {
            return $notes;
        }

        $line = '[[INVALIDATION]]' . date('Y-m-d H:i:s') . '|' . $taskType . '|' . str_replace(["\r", "\n"], ' ', $comment);
        return $notes === '' ? $line : ($notes . "\n" . $line);
    }

    private function buildPublicationEntryPayload(array $task, array $taskPayload) {
        $date = trim((string) ($_POST['date_publication'] ?? $task['latest_publication']['date_publication'] ?? ''));
        $time = trim((string) ($_POST['heure_publication'] ?? $task['latest_publication']['heure_publication'] ?? ''));
        $canal = trim((string) ($_POST['canal'] ?? $task['latest_publication']['canal'] ?? $task['reseau_cible'] ?? $task['canal_principal'] ?? ''));
        $note = trim((string) ($_POST['publication_note'] ?? $task['latest_publication']['note'] ?? ''));
        $entryId = (int) ($_POST['publication_entry_id'] ?? $task['latest_publication']['id'] ?? 0);

        if ($date === '' && $time === '' && $canal === '' && $note === '' && $entryId <= 0) {
            return null;
        }

        if ($date === '') {
            throw $this->taskValidationError('La publication attend au minimum une date de publication.', ['date_publication' => 'Renseignez une date de publication.']);
        }

        return [
            'id' => $entryId,
            'payload' => [
                'date_publication' => $date,
                'heure_publication' => $time,
                'canal' => $canal,
                'statut' => $taskPayload['statut'] === 'Terminee' ? 'Publie' : 'Planifie',
                'note' => $note,
            ]
        ];
    }

    private function buildContentResultPayloads() {
        $dateCollecte = trim((string) ($_POST['date_collecte'] ?? ''));
        if ($dateCollecte === '') {
            $dateCollecte = date('Y-m-d');
        }

        $timestamp = strtotime($dateCollecte);
        if ($timestamp === false) {
            $dateCollecte = date('Y-m-d');
            $timestamp = strtotime($dateCollecte);
        }

        $kpiConfig = $this->getKpiNetworkConfig();
        $note = trim((string) ($_POST['result_note'] ?? ''));
        $selectedNetworks = array_values(array_unique(array_filter(array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, (array) ($_POST['kpi_networks'] ?? [])))));

        $rawValuesByNetwork = is_array($_POST['kpi_values'] ?? null) ? $_POST['kpi_values'] : [];

        if (empty($selectedNetworks) && !empty($_POST['kpi_network'])) {
            $selectedNetworks[] = strtolower(trim((string) $_POST['kpi_network']));
        }

        if (empty($selectedNetworks) && $note === '') {
            return [];
        }

        if (empty($selectedNetworks)) {
            throw new RuntimeException('Selectionne au moins un reseau de collecte.');
        }

        $periodeLabel = date('Y-m', (int) $timestamp);
        $payloads = [];

        foreach ($selectedNetworks as $network) {
            $networkMeta = $kpiConfig[$network] ?? null;
            if (!is_array($networkMeta)) {
                continue;
            }

            $rawValues = is_array($rawValuesByNetwork[$network] ?? null) ? $rawValuesByNetwork[$network] : [];
            $kpis = [];
            foreach ((array) ($networkMeta['kpis'] ?? []) as $kpiDef) {
                $name = trim((string) ($kpiDef['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $type = strtolower((string) ($kpiDef['type'] ?? 'integer'));
                $raw = $rawValues[$name] ?? '';
                $value = $type === 'float' ? (float) $raw : (int) $raw;
                if ($type === 'integer') {
                    $value = max(0, (int) $value);
                }

                $kpis[] = [
                    'name' => $name,
                    'label' => (string) ($kpiDef['label'] ?? $name),
                    'type' => $type,
                    'value' => $value,
                ];
            }

            $valeurCle = 'Aucune mesure cle';
            foreach ($kpis as $kpi) {
                if ((float) ($kpi['value'] ?? 0) > 0) {
                    $valeurCle = (string) ($kpi['label'] ?? $kpi['name']) . ': ' . (string) $kpi['value'];
                    break;
                }
            }

            $metricSnapshot = json_encode([
                'reseau' => $network,
                'reseau_label' => (string) ($networkMeta['label'] ?? ucfirst($network)),
                'periode_auto' => $periodeLabel,
                'kpis' => $kpis,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $payloads[] = [
                'date_collecte' => $dateCollecte,
                'periode_label' => $periodeLabel,
                'valeur_cle' => $valeurCle,
                'metric_snapshot' => (string) $metricSnapshot,
                'note' => $note,
                'reseau_collecte' => $network,
            ];
        }

        return $payloads;
    }

    private function getKpiNetworkConfig() {
        return (new SettingsModel())->getKpiNetworksConfig();
    }

    private function buildDeliverablePayload(array $deliverable, $isVideo) {
        $brief = $deliverable['brief'];
        $sousType = trim((string) ($_POST['sous_type'] ?? $deliverable['sous_type'] ?? ''));
        $nombrePages = max(1, (int) ($_POST['nombre_pages_carrousel'] ?? $_POST['nombre_pages'] ?? $brief['nombre_pages_carrousel'] ?? $deliverable['nombre_pages'] ?? 1));
        $pages = [];
        foreach ((array) ($_POST['page_messages'] ?? []) as $index => $message) {
            $pages[] = [
                'index' => (int) $index + 1,
                'message' => trim((string) $message)
            ];
        }

        $briefFiles = $this->processUploads('pieces_jointes', ['png','jpg','jpeg','pdf','psd','psb','doc','docx','txt'], $brief['pieces_jointes'] ?? []);
        $deliverableFiles = $this->processUploads('deliverable_files', ['png','jpg','jpeg','pdf','psd','psb','mp4','mov','zip'], $deliverable['pieces_jointes'] ?? []);

        if (!$isVideo && strcasecmp($sousType, 'Carrousel') === 0) {
            if (count($pages) < $nombrePages) {
                for ($cursor = count($pages); $cursor < $nombrePages; $cursor++) {
                    $pages[] = ['index' => $cursor + 1, 'message' => ''];
                }
            }
        }

        $briefPayload = [
            'contenu_id' => $brief['contenu_id'] ?? $deliverable['content_id'] ?? null,
            'nature_brief' => $isVideo ? 'Script video' : 'Brief visuel',
            'format_livrable' => $sousType !== '' ? $sousType : ($deliverable['type_livrable'] ?? ''),
            'nombre_pages_carrousel' => $nombrePages,
            'pdf_requis' => !$isVideo && strcasecmp($sousType, 'Carrousel') === 0 ? 1 : 0,
            'source_requis' => $isVideo ? 0 : 1,
            'titre_brief' => trim((string) ($_POST['titre_brief'] ?? '')),
            'details_message' => trim((string) ($_POST['details_message'] ?? '')),
            'informations_complementaires' => trim((string) ($_POST['informations_complementaires'] ?? '')),
            'cta' => trim((string) ($_POST['cta'] ?? '')),
            'recommandation_design' => trim((string) ($_POST['recommandation_design'] ?? '')),
            'description_publication' => trim((string) ($_POST['description_publication'] ?? '')),
            'hook_video' => trim((string) ($_POST['hook_video'] ?? '')),
            'plan_script' => trim((string) ($_POST['plan_script'] ?? '')),
            'pages_carrousel' => $pages,
            'texte_script' => trim((string) ($_POST['texte_script'] ?? '')),
            'instructions_visuelles' => trim((string) ($_POST['instructions_visuelles'] ?? '')),
            'format' => trim((string) ($_POST['format'] ?? '')),
            'statut' => trim((string) ($_POST['statut'] ?? 'En cours')),
            'responsable' => trim((string) ($_POST['responsable'] ?? '')),
            'pieces_jointes' => $briefFiles
        ];

        $deliverablePayload = [
            'sous_type' => $sousType,
            'nombre_pages' => $nombrePages,
            'pieces_jointes' => $deliverableFiles
        ];

        $workflowTask = $deliverable['taskMap'][$isVideo ? 'Script' : 'Brief'] ?? null;
        $workflowTaskUpdate = null;
        if ($workflowTask) {
            $taskStatus = $this->mapBriefStatusToTaskStatus((string) ($briefPayload['statut'] ?? 'A faire'));
            $workflowTaskUpdate = [
                'id' => (int) $workflowTask['id'],
                'update' => [
                    'statut' => $taskStatus,
                    'notes' => trim((string) ($briefPayload['plan_script'] ?? $briefPayload['details_message'] ?? $workflowTask['notes'] ?? '')),
                    'fichiers_livres' => $workflowTask['fichiers_livres'] ?? json_encode([]),
                    'validation_decision' => $workflowTask['validation_decision'] ?? 'En attente',
                    'validation_commentaire' => $workflowTask['validation_commentaire'] ?? '',
                    'publication_reseaux' => $workflowTask['publication_reseaux'] ?? json_encode([])
                ]
            ];
        }

        return [$briefPayload, $deliverablePayload, $workflowTaskUpdate];
    }

    private function mapBriefStatusToTaskStatus($briefStatus) {
        if ($briefStatus === 'Valide') {
            return 'Terminee';
        }

        if ($briefStatus === 'En cours') {
            return 'En cours';
        }

        return 'A faire';
    }

    private function processUploads($field, array $allowedExtensions, $existingValue) {
        $existing = $this->decodeFiles($existingValue);
        if (!isset($_FILES[$field])) {
            return $existing;
        }

        $fileBag = $_FILES[$field];
        $names = is_array($fileBag['name']) ? $fileBag['name'] : [$fileBag['name']];
        $tmpNames = is_array($fileBag['tmp_name']) ? $fileBag['tmp_name'] : [$fileBag['tmp_name']];
        $errors = is_array($fileBag['error']) ? $fileBag['error'] : [$fileBag['error']];
        $sizes = is_array($fileBag['size']) ? $fileBag['size'] : [$fileBag['size']];
        $uploaded = [];

        foreach ($names as $index => $name) {
            if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (($errors[$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errorCode = (int) ($errors[$index] ?? UPLOAD_ERR_CANT_WRITE);
                throw new RuntimeException($this->buildUploadErrorMessage((string) $name, $errorCode));
            }

            $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('Format non autorise pour ' . $name . '.');
            }

            $targetDirectory = UPLOADS_PATH . '/calendrier/' . date('Y') . '/' . date('m');
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException('Impossible de creer le dossier des uploads calendrier.');
            }

            $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo((string) $name, PATHINFO_FILENAME));
            $safeBase = trim((string) $safeBase, '-');
            $fileName = uniqid($safeBase . '-', true) . '.' . $extension;
            $targetPath = $targetDirectory . '/' . $fileName;

            if (!move_uploaded_file($tmpNames[$index], $targetPath)) {
                throw new RuntimeException('Le televersement du fichier a echoue.');
            }

            $uploaded[] = [
                'name' => $name,
                'path' => 'calendrier/' . date('Y') . '/' . date('m') . '/' . $fileName,
                'size' => (int) ($sizes[$index] ?? 0),
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }

        return array_values(array_merge($existing, $uploaded));
    }

    private function removeBriefFile(array $deliverable, $index, $returnTo) {
        $files = is_array($deliverable['brief']['pieces_jointes'] ?? null) ? $deliverable['brief']['pieces_jointes'] : [];
        if (!isset($files[$index])) {
            $this->flash('error', 'Fichier introuvable.');
            header('Location: ' . $returnTo);
            exit;
        }

        $this->deletePhysicalFile($files[$index]['path'] ?? '');
        array_splice($files, $index, 1);
        $this->calendrierModel->updateBriefFiles((int) $deliverable['id'], array_values($files));
        $this->flash('success', 'Fichier supprime.');
        $paramToClear = isset($_GET['remove_brief_file']) ? 'remove_brief_file' : 'remove_file';
        header('Location: ' . $this->currentUrlWithoutParam($paramToClear));
        exit;
    }

    private function removeTaskFile(array $task, $index, $returnTo) {
        $files = $this->decodeFiles($task['fichiers_livres'] ?? '[]');
        if (!isset($files[$index])) {
            $this->flash('error', 'Fichier introuvable.');
            header('Location: ' . $returnTo);
            exit;
        }

        $this->deletePhysicalFile($files[$index]['path'] ?? '');
        array_splice($files, $index, 1);
        $this->calendrierModel->updateTaskFiles((int) $task['id'], array_values($files));
        if (!empty($task['livrable_item_id'])) {
            $this->calendrierModel->syncDeliverableStatus((int) $task['livrable_item_id']);
        }
        $this->flash('success', 'Fichier supprime.');
        header('Location: ' . $this->currentUrlWithoutParam('remove_file'));
        exit;
    }

    private function deletePhysicalFile($relativePath) {
        $relativePath = trim((string) $relativePath, '/');
        if ($relativePath === '') {
            return;
        }

        FileTrashService::trashByRelativePath($relativePath, [
            'module_key' => 'calendrier',
            'source_table' => 'calendrier_contenus',
            'source_record_id' => 0,
            'deleted_by' => (int) (($this->currentUser()['id'] ?? 0)),
        ]);
    }

    private function currentUrlWithoutParam($param) {
        $query = $_GET;
        unset($query[$param]);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        foreach (array_filter([PUBLIC_BASE_URL, BASE_URL], static function ($value) { return $value !== ''; }) as $prefix) {
            if (strpos($path, $prefix) === 0) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }
        $path = '/' . ltrim($path, '/');
        $url = (BASE_URL === '' ? '' : BASE_URL) . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    private function decodeFiles($value) {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function composeTournageNotes($disk, $folder, $notes) {
        $disk = trim(preg_replace('/\s+/', ' ', (string) $disk));
        $folder = trim(preg_replace('/\s+/', ' ', (string) $folder));
        $notes = trim((string) $notes);

        $header = "[TOURNAGE_STORAGE]\n"
            . 'disque=' . $disk . "\n"
            . 'dossier=' . $folder . "\n"
            . '[/TOURNAGE_STORAGE]';

        return $notes === '' ? $header : ($header . "\n\n" . $notes);
    }

    private function extractTournageNotesData($rawNotes) {
        $rawNotes = (string) $rawNotes;
        $pattern = '/^\[TOURNAGE_STORAGE\]\Rdisque=(.*)\Rdossier=(.*)\R\[\/TOURNAGE_STORAGE\](?:\R\R)?/';
        if (!preg_match($pattern, $rawNotes, $matches)) {
            return [
                'disque' => '',
                'dossier' => '',
                'notes' => trim($rawNotes),
            ];
        }

        $cleanNotes = trim((string) substr($rawNotes, strlen($matches[0])));
        return [
            'disque' => trim((string) ($matches[1] ?? '')),
            'dossier' => trim((string) ($matches[2] ?? '')),
            'notes' => $cleanNotes,
        ];
    }

    private function hydrateTournageTaskFields(array $task) {
        if (($task['type_tache'] ?? '') !== 'Tournage') {
            return $task;
        }

        $meta = $this->extractTournageNotesData($task['notes'] ?? '');
        if (!isset($task['tournage_disque']) || trim((string) $task['tournage_disque']) === '') {
            $task['tournage_disque'] = $meta['disque'];
        }
        if (!isset($task['tournage_dossier']) || trim((string) $task['tournage_dossier']) === '') {
            $task['tournage_dossier'] = $meta['dossier'];
        }
        $task['notes'] = $meta['notes'];

        return $task;
    }

    private function validateTaskFiles(array $task, $status, array $files) {
        if ($status !== 'Terminee' || empty($task['type_livrable'])) {
            return;
        }

        if (!in_array($task['type_tache'], ['Production', 'Montage'], true)) {
            return;
        }

        $extensions = array_map(static function ($file) {
            return strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        }, $files);
        $taskType = (string) ($task['type_tache'] ?? '');
        $isMontage = $taskType === 'Montage';

        if ($task['type_livrable'] === 'Video') {
            $videoCount = count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['mp4', 'mov'], true);
            }));
            if ($isMontage && $videoCount === 0) {
                throw $this->taskValidationError('Pour terminer la tache Montage, charge au moins les exports video finaux.', ['fichiers_livres' => 'Ajoutez au moins un export video final (MP4/MOV).']);
            }
            if ($isMontage && $this->isSecondMontageVideoRequired() && $videoCount < 2) {
                throw $this->taskValidationError('Pour terminer la tache Montage, charge la version avec musique et la version sans musique.', ['fichiers_livres' => 'Deux exports video sont requis: avec musique et sans musique.']);
            }
            return;
        }

        $hasSource = count(array_filter($extensions, static function ($extension) {
            return in_array($extension, ['psd', 'psb'], true);
        })) > 0;
        if (!$hasSource) {
            throw $this->taskValidationError(
                $isMontage
                    ? 'Pour terminer la tache Montage, ajoute le fichier source PSD ou PSB.'
                    : 'Pour un visuel termine, ajoute le fichier source PSD ou PSB.',
                ['fichiers_livres' => 'Ajoutez le fichier source PSD ou PSB.']
            );
        }

        if (strcasecmp((string) ($task['sous_type'] ?? ''), 'Carrousel') === 0) {
            $imageCount = count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['png', 'jpg', 'jpeg'], true);
            }));
            if ($imageCount < max(1, (int) ($task['nombre_pages'] ?? 1))) {
                throw $this->taskValidationError('Le carrousel attend une image exportee par page.', ['fichiers_livres' => 'Ajoutez une image exportee pour chaque page du carrousel.']);
            }
            if (!in_array('pdf', $extensions, true)) {
                throw $this->taskValidationError('Le carrousel attend aussi un PDF.', ['fichiers_livres' => 'Ajoutez aussi le PDF du carrousel.']);
            }
            return;
        }

        $hasImage = count(array_filter($extensions, static function ($extension) {
            return in_array($extension, ['png', 'jpg', 'jpeg'], true);
        })) > 0;
        if (!$hasImage) {
            throw $this->taskValidationError(
                $isMontage
                    ? 'Pour terminer la tache Montage, ajoute au moins un export PNG/JPG/JPEG.'
                    : 'Pour un visuel termine, ajoute au moins un export PNG/JPG/JPEG.',
                ['fichiers_livres' => 'Ajoutez au moins un export PNG/JPG/JPEG.']
            );
        }
    }

    private function buildUploadErrorMessage($fileName, $errorCode) {
        $label = $fileName !== '' ? $fileName : 'ce fichier';
        $message = 'Le televersement a echoue pour ' . $label . '.';

        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            return $message . ' Le fichier depasse la taille autorisee.';
        }
        if ($errorCode === UPLOAD_ERR_PARTIAL) {
            return $message . ' Le fichier est incomplet, recommence le chargement.';
        }
        if ($errorCode === UPLOAD_ERR_NO_TMP_DIR) {
            return $message . ' Le dossier temporaire du serveur est introuvable.';
        }
        if ($errorCode === UPLOAD_ERR_CANT_WRITE) {
            return $message . ' Le serveur ne peut pas ecrire le fichier sur le disque.';
        }
        if ($errorCode === UPLOAD_ERR_EXTENSION) {
            return $message . ' Une extension PHP a bloque le televersement.';
        }

        return $message;
    }

    private function requestExceededPostMaxSize() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return false;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return false;
        }

        $postMaxSize = $this->parseIniSizeToBytes((string) ini_get('post_max_size'));
        if ($postMaxSize <= 0 || $contentLength <= $postMaxSize) {
            return false;
        }

        return empty($_POST) && empty($_FILES);
    }

    private function getPhpUploadLimitLabel() {
        $uploadMax = trim((string) ini_get('upload_max_filesize'));
        $postMax = trim((string) ini_get('post_max_size'));
        if ($uploadMax === '' && $postMax === '') {
            return 'limite inconnue';
        }

        if ($uploadMax === $postMax || $postMax === '') {
            return $uploadMax;
        }

        if ($uploadMax === '') {
            return $postMax;
        }

        return $uploadMax . ' par fichier, ' . $postMax . ' par requete';
    }

    private function parseIniSizeToBytes($value) {
        $value = trim(strtolower((string) $value));
        if ($value === '') {
            return 0;
        }

        $unit = substr($value, -1);
        $number = (float) $value;
        if ($unit === 'g') {
            return (int) round($number * 1024 * 1024 * 1024);
        }
        if ($unit === 'm') {
            return (int) round($number * 1024 * 1024);
        }
        if ($unit === 'k') {
            return (int) round($number * 1024);
        }

        return (int) round((float) $value);
    }

    private function validateLivrableFiles(array $deliverable, $stage, array $files, $sousType, $pageCount, $status) {
        if ($status !== 'Terminee') {
            return;
        }

        if ($stage === 'Tournage') {
            return;
        }

        $extensions = array_map(static function ($file) {
            return strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        }, $files);

        if (($deliverable['type_livrable'] ?? '') === 'Video') {
            $videoCount = count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['mp4', 'mov'], true);
            }));
            if ($videoCount < 2) {
                throw $this->taskValidationError('Le livrable video attend une version avec musique et une version sans musique pour TikTok.', ['validation_decision' => 'Deux exports video finaux sont requis avant validation client.']);
            }
            return;
        }

        $hasSource = count(array_filter($extensions, static function ($extension) {
            return in_array($extension, ['psd', 'psb'], true);
        })) > 0;
        if (!$hasSource) {
            throw $this->taskValidationError('Le livrable visuel attend un fichier source PSD ou PSB.', ['validation_decision' => 'Le fichier source PSD/PSB est requis avant validation client.']);
        }

        if (strcasecmp($sousType, 'Carrousel') === 0) {
            $imageCount = count(array_filter($extensions, static function ($extension) {
                return in_array($extension, ['png', 'jpg', 'jpeg'], true);
            }));
            if ($imageCount < $pageCount) {
                throw $this->taskValidationError('Le carrousel attend une image exportee par page.', ['validation_decision' => 'Le carrousel doit contenir un export image par page.']);
            }
            if (!in_array('pdf', $extensions, true)) {
                throw $this->taskValidationError('Le carrousel attend aussi un fichier PDF.', ['validation_decision' => 'Ajoutez le PDF du carrousel avant validation client.']);
            }
            return;
        }

        $hasImageExport = count(array_filter($extensions, static function ($extension) {
            return in_array($extension, ['png', 'jpg', 'jpeg'], true);
        })) > 0;
        if (!$hasImageExport) {
            throw $this->taskValidationError('Le livrable visuel attend au minimum un export image (PNG/JPG/JPEG).', ['validation_decision' => 'Ajoutez au moins un export image (PNG/JPG/JPEG) avant validation client.']);
        }
    }

    private function validateFinalApprovalRequirements(array $task, $status, $validationDecision) {
        if (($task['type_tache'] ?? '') !== 'Validation client') {
            return;
        }

        $isFinalApproval = $status === 'Terminee' || $validationDecision === 'Valide';
        if (!$isFinalApproval || empty($task['livrable_item_id'])) {
            return;
        }

        $deliverable = $this->calendrierModel->getDeliverableWorkspace((int) $task['livrable_item_id']);
        if (!$deliverable) {
            throw $this->taskValidationError('Livrable introuvable pour la validation finale.', ['validation_decision' => 'Impossible de valider sans livrable associe.']);
        }

        $files = $this->collectDeliverableFiles($deliverable);
        $pageCount = max(1, (int) ($deliverable['nombre_pages'] ?? 1));
        $sousType = trim((string) ($deliverable['sous_type'] ?? ''));
        $this->validateLivrableFiles($deliverable, 'Validation client', $files, $sousType, $pageCount, 'Terminee');
    }

    private function collectDeliverableFiles(array $deliverable) {
        $files = $this->decodeFiles($deliverable['pieces_jointes'] ?? '[]');
        foreach ((array) ($deliverable['tasks'] ?? []) as $task) {
            $files = array_merge($files, $this->decodeFiles($task['fichiers_livres'] ?? '[]'));
        }

        $seen = [];
        $uniqueFiles = [];
        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $uniqueFiles[] = $file;
        }

        return $uniqueFiles;
    }

    private function taskValidationError($message, array $fieldErrors = []) {
        $cleanErrors = [];
        foreach ($fieldErrors as $field => $errorMessage) {
            $field = trim((string) $field);
            $errorMessage = trim((string) $errorMessage);
            if ($field === '' || $errorMessage === '') {
                continue;
            }
            $cleanErrors[$field] = $errorMessage;
        }

        return new CalendrierValidationException((string) $message, $cleanErrors);
    }

    public function reassignTask($taskId) {
        $this->requirePermission('calendar.view');
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            $this->respondJson(['ok' => false, 'message' => 'Tache invalide.'], 400);
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $task = $this->calendrierModel->getTaskWorkspace($taskId);
        if (!$task) {
            $this->respondJson(['ok' => false, 'message' => 'Tache introuvable.'], 404);
            return;
        }

        if (!$this->canManageBoardTask($this->currentUser(), $task)) {
            $this->respondJson(['ok' => false, 'message' => 'Seul le charge de com ou le responsable de cette tache peut modifier le responsable.'], 403);
            return;
        }

        $newAuthorId = (int) ($_POST['auteur_id'] ?? 0);
        $options = $this->getTaskReassignmentOptions($task);
        if ($newAuthorId <= 0 || !isset($options[(string) $newAuthorId])) {
            $this->respondJson(['ok' => false, 'message' => 'Auteur invalide pour cette tache.'], 400);
            return;
        }

        try {
            $result = $this->calendrierModel->reassignTask($taskId, $newAuthorId);
            $this->respondJson([
                'ok' => true,
                'message' => 'Tache reattribuee avec succes.'
            ] + $result);
        } catch (Throwable $exception) {
            $this->respondJson(['ok' => false, 'message' => 'Erreur lors de la reattribution: ' . $exception->getMessage()], 500);
        }
    }

    private function canManageBoardTask(array $currentUser = null, array $task = null) {
        if ($task === null || empty($task)) {
            return false;
        }

        return $this->canManageBoardActions($currentUser);
    }

    private function canManageBoardDeliverableDate(array $currentUser = null, array $deliverable = null) {
        if ($deliverable === null || empty($deliverable)) {
            return false;
        }

        return $this->canManageBoardActions($currentUser);
    }

    private function canManageBoardActions(array $currentUser = null) {
        if (UserRoles::hasAnyRole($currentUser, ['Admin', 'CC'])) {
            return true;
        }

        if ($this->can('calendar.manage')) {
            return true;
        }

        $secondary = strtolower(trim((string) ($currentUser['secondary_roles'] ?? '')));
        if ($secondary === '') {
            return false;
        }

        return strpos($secondary, 'responsable') !== false && strpos($secondary, 'equipe') !== false;
    }
    private function capabilityForTask(array $task): string {
        $type=(string)($task['type_tache']??'');
        if(in_array($type,['Brief','Script','Calendrier','Production','Tournage','Montage'],true))return 'content';
        if(in_array($type,['Validation interne','Validation client'],true))return 'validation';
        if($type==='Publication')return 'publishing';
        if($type==='Collecte KPI')return 'analytics';
        return 'projects';
    }

    private function assertWorkspaceCapability(array $workspace,string $capability,bool $write=false): void {
        $projectId=(int)($workspace['projet_id']??$workspace['project_id']??0);
        if($projectId<=0)throw new RuntimeException('Projet associe introuvable.');
        $stmt=Database::getConnection()->prepare('SELECT p.*,c.tenant_id,c.organization_id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=:id LIMIT 1');
        $stmt->execute(['id'=>$projectId]);
        AgencyAccessPolicy::assertRecordCapability('projets',$stmt->fetch(PDO::FETCH_ASSOC)?:null,$capability,$write);
        AgencyAccessPolicy::auditAccess('projets',$projectId,$capability,$workspace);
    }
}
