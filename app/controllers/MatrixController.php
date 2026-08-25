<?php
class MatrixController extends Controller {
    private $pdo;
    private $tenantId;
    private $organizationId;

    public function __construct() {
        parent::__construct();
        $this->requirePermission('content.view');
        $tenant = TenantContext::resolveForUser($this->currentUser());
        if (!$tenant) { throw new RuntimeException('Aucune entreprise active.'); }
        $this->tenantId = (int) $tenant['id'];
        $this->organizationId = (int) ($tenant['organization_id'] ?? 0);
        $this->pdo = Database::getConnection();
    }

    public function index() {
        $clients = $this->accessibleClients();
        $clientIds = array_map('intval', array_column($clients, 'id'));
        $clientId = (int) ($_REQUEST['client_id'] ?? ($clientIds[0] ?? 0));
        if ($clientId > 0 && !in_array($clientId, $clientIds, true)) { throw new RuntimeException('Client inaccessible.'); }
        $projects = $this->projectsForClient($clientId);
        $projectId = (int) ($_REQUEST['project_id'] ?? ($projects[0]['id'] ?? 0));
        if ($projectId > 0 && !in_array($projectId, array_map('intval', array_column($projects, 'id')), true)) { throw new RuntimeException('Projet inaccessible.'); }
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_REQUEST['month'] ?? '')) ? (string) $_REQUEST['month'] : date('Y-m');

        if ($this->isPost()) {
            try { $this->handleAction($clientId, $projectId, $month); }
            catch (Throwable $e) { $this->flash('error', $e->getMessage()); }
            $this->redirect('/matrix?client_id=' . $clientId . '&project_id=' . $projectId . '&month=' . urlencode($month) . (!empty($_POST['matrix_id']) ? '&matrix_id=' . (int) $_POST['matrix_id'] : ''));
        }

        $matrices = $this->matricesForClient($clientId);
        $matrixId = (int) ($_GET['matrix_id'] ?? ($matrices[0]['id'] ?? 0));
        $matrix = $this->findMatrix($matrixId, $clientId);
        $ideas = $matrix ? $this->ideasForContext($matrixId, $projectId, $month) : [];
        $this->render('matrix/workspace', ['pageTitle'=>'Matrice de creation','clients'=>$clients,'projects'=>$projects,'clientId'=>$clientId,'projectId'=>$projectId,'month'=>$month,'matrices'=>$matrices,'matrix'=>$matrix,'ideas'=>$ideas]);
    }

    private function handleAction($clientId, $projectId, $month) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create_matrix') { $this->createMatrix($clientId); return; }
        $matrixId = (int) ($_POST['matrix_id'] ?? 0);
        $matrix = $this->findMatrix($matrixId, $clientId);
        if (!$matrix) { throw new RuntimeException('Matrice introuvable pour ce client.'); }
        if ($action === 'update_matrix') { $this->updateMatrix($matrix); return; }
        if ($action === 'clone_matrix') { $this->cloneMatrix($matrix); return; }
        if ($action === 'delete_matrix') { $this->deleteMatrix($matrix); return; }
        if ($action === 'generate_combinations') { $this->generateCombinations($matrix, $projectId, $month); return; }
        if ($action === 'update_idea') { $this->updateIdea($matrix); return; }
        if ($action === 'delete_ideas') { $this->deleteIdeas($matrixId, (array) ($_POST['idea_ids'] ?? [])); return; }
        if ($action === 'assign_ideas') { $this->assignIdeas($matrix, $projectId); return; }
        if ($action === 'add_idea') { $this->addIdea($matrix, $projectId, $month); return; }
        if ($action === 'validate_ideas') { $this->setIdeaStatus($matrixId, (array) ($_POST['idea_ids'] ?? []), 'Validee'); return; }
        if ($action === 'discard_ideas') { $this->setIdeaStatus($matrixId, (array) ($_POST['idea_ids'] ?? []), 'Ecartee'); return; }
        if ($action === 'sync_ideas') { $this->syncIdeas($matrix, $projectId, $month); return; }
        throw new RuntimeException('Action Matrice inconnue.');
    }

    private function updateMatrix(array $matrix) {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') { throw new RuntimeException('Le nom de la matrice est obligatoire.'); }
        $payload = [];
        foreach (['target','objective','problem','product','format','cta','platform'] as $key) {
            $payload[$key] = $this->lines($_POST[$key.'_options'] ?? '', $matrix[$key.'_list'] ?? []);
        }
        $type = in_array($_POST['default_deliverable_type'] ?? '', ['Video','Visuel'], true) ? $_POST['default_deliverable_type'] : 'Video';
        $stmt = $this->pdo->prepare('UPDATE content_matrices SET name=:name,description=:description,target_options=:targets,objective_options=:objectives,problem_options=:problems,product_options=:products,format_options=:formats,cta_options=:ctas,platform_options=:platforms,default_deliverable_type=:type,default_format=:default_format WHERE id=:id AND tenant_id=:tenant');
        $stmt->execute(['name'=>$name,'description'=>trim((string)($_POST['description']??'')),'targets'=>json_encode($payload['target'],JSON_UNESCAPED_UNICODE),'objectives'=>json_encode($payload['objective'],JSON_UNESCAPED_UNICODE),'problems'=>json_encode($payload['problem'],JSON_UNESCAPED_UNICODE),'products'=>json_encode($payload['product'],JSON_UNESCAPED_UNICODE),'formats'=>json_encode($payload['format'],JSON_UNESCAPED_UNICODE),'ctas'=>json_encode($payload['cta'],JSON_UNESCAPED_UNICODE),'platforms'=>json_encode($payload['platform'],JSON_UNESCAPED_UNICODE),'type'=>$type,'default_format'=>trim((string)($_POST['default_format']??'')),'id'=>(int)$matrix['id'],'tenant'=>$this->tenantId]);
        $this->flash('success', 'Configuration de la matrice mise a jour.');
    }

    private function cloneMatrix(array $matrix) {
        $stmt = $this->pdo->prepare("INSERT INTO content_matrices (tenant_id,owner_organization_id,client_id,name,description,target_options,objective_options,problem_options,product_options,format_options,cta_options,platform_options,default_deliverable_type,default_format,created_by) SELECT tenant_id,owner_organization_id,client_id,CONCAT(name, ' - copie'),description,target_options,objective_options,problem_options,product_options,format_options,cta_options,platform_options,default_deliverable_type,default_format,:user FROM content_matrices WHERE id=:id AND tenant_id=:tenant");
        $stmt->execute(['user'=>(int)($this->currentUser()['id']??0)?:null,'id'=>(int)$matrix['id'],'tenant'=>$this->tenantId]);
        $_POST['matrix_id'] = (int) $this->pdo->lastInsertId();
        $this->flash('success', 'Matrice dupliquee. Vous pouvez maintenant adapter la copie.');
    }
    private function createMatrix($clientId) {
        if ($clientId <= 0) { throw new RuntimeException('Selectionnez un client.'); }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') { throw new RuntimeException('Le nom de la matrice est obligatoire.'); }
        $defaults = $this->defaultReferences();
        $payload = [];
        foreach (['target','objective','problem','product','format','cta','platform'] as $key) { $payload[$key] = $this->lines($_POST[$key.'_options'] ?? '', $defaults[$key]); }
        $stmt=$this->pdo->prepare("INSERT INTO content_matrices (tenant_id,owner_organization_id,client_id,name,description,target_options,objective_options,problem_options,product_options,format_options,cta_options,platform_options,default_deliverable_type,default_format,created_by) VALUES (:tenant,:org,:client,:name,:description,:targets,:objectives,:problems,:products,:formats,:ctas,:platforms,:type,:default_format,:user)");
        $stmt->execute(['tenant'=>$this->tenantId,'org'=>$this->organizationId?:null,'client'=>$clientId,'name'=>$name,'description'=>trim((string)($_POST['description']??'')),'targets'=>json_encode($payload['target'],JSON_UNESCAPED_UNICODE),'objectives'=>json_encode($payload['objective'],JSON_UNESCAPED_UNICODE),'problems'=>json_encode($payload['problem'],JSON_UNESCAPED_UNICODE),'products'=>json_encode($payload['product'],JSON_UNESCAPED_UNICODE),'formats'=>json_encode($payload['format'],JSON_UNESCAPED_UNICODE),'ctas'=>json_encode($payload['cta'],JSON_UNESCAPED_UNICODE),'platforms'=>json_encode($payload['platform'],JSON_UNESCAPED_UNICODE),'type'=>in_array($_POST['default_deliverable_type']??'', ['Video','Visuel'],true)?$_POST['default_deliverable_type']:'Video','default_format'=>trim((string)($_POST['default_format']??'')),'user'=>(int)($this->currentUser()['id']??0)?:null]);
        $_POST['matrix_id']=(int)$this->pdo->lastInsertId(); $this->flash('success','Matrice creee pour le client.');
    }

    private function addIdea(array $matrix, $projectId, $month) {
        if ($projectId<=0) throw new RuntimeException('Selectionnez un projet.');
        TenantGuard::assertProject($projectId); $this->assertProjectClient($projectId,(int)$matrix['client_id']);
        $hook=trim((string)($_POST['hook_idea']??'')); if($hook==='') throw new RuntimeException('L accroche ou le titre est obligatoire.');
        $type=in_array($_POST['deliverable_type']??'', ['Video','Visuel'],true)?$_POST['deliverable_type']:$matrix['default_deliverable_type'];
        $brief=trim((string)($_POST['generated_brief']??'')); if($brief==='') $brief=$this->buildBrief($_POST); $script=trim((string)($_POST['script_content']??''));
        $stmt=$this->pdo->prepare("INSERT INTO matrix_ideas (matrix_id,tenant_id,client_id,projet_id,target_month,target_audience,objective,problem_need,product_offer,creative_format,deliverable_type,platform,hook_idea,call_to_action,generated_brief,script_content,priority,created_by) VALUES (:matrix,:tenant,:client,:project,:month,:target,:objective,:problem,:product,:format,:type,:platform,:hook,:cta,:brief,:script,:priority,:user)");
        $stmt->execute(['matrix'=>(int)$matrix['id'],'tenant'=>$this->tenantId,'client'=>(int)$matrix['client_id'],'project'=>$projectId,'month'=>$month.'-01','target'=>trim((string)($_POST['target_audience']??'')),'objective'=>trim((string)($_POST['objective']??'')),'problem'=>trim((string)($_POST['problem_need']??'')),'product'=>trim((string)($_POST['product_offer']??'')),'format'=>trim((string)($_POST['creative_format']??$matrix['default_format'])),'type'=>$type,'platform'=>trim((string)($_POST['platform']??'')),'hook'=>$hook,'cta'=>trim((string)($_POST['call_to_action']??'')),'brief'=>$brief,'script'=>$script,'priority'=>in_array($_POST['priority']??'', ['Haute','Moyenne','Basse'],true)?$_POST['priority']:'Moyenne','user'=>(int)($this->currentUser()['id']??0)?:null]);
        $this->flash('success','Idee ajoutee a la banque.');
    }

    private function setIdeaStatus($matrixId,array $ids,$status) {
        $ids=array_values(array_filter(array_map('intval',$ids))); if(!$ids) throw new RuntimeException('Selectionnez au moins une idee.');
        $marks=implode(',',array_fill(0,count($ids),'?')); $stmt=$this->pdo->prepare("UPDATE matrix_ideas SET status=? WHERE matrix_id=? AND tenant_id=? AND status<>'Synchronisee' AND id IN ($marks)");
        $stmt->execute(array_merge([$status,$matrixId,$this->tenantId],$ids)); $this->flash('success',count($ids).' idee(s) mise(s) a jour.');
    }

    private function deleteMatrix(array $matrix) {
        $this->pdo->prepare("UPDATE content_matrices SET status='Archived' WHERE id=:id AND tenant_id=:tenant")->execute(['id'=>(int)$matrix['id'],'tenant'=>$this->tenantId]);
        $_POST['matrix_id'] = 0;
        $this->flash('success', 'Matrice supprimee de la bibliotheque. Les contenus deja synchronises sont conserves.');
    }

    private function deleteIdeas($matrixId, array $ids) {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) { throw new RuntimeException('Selectionnez au moins une idee a supprimer.'); }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM matrix_ideas WHERE matrix_id=? AND tenant_id=? AND synced_deliverable_id IS NULL AND id IN ($marks)");
        $stmt->execute(array_merge([$matrixId, $this->tenantId], $ids));
        $this->flash('success', $stmt->rowCount().' idee(s) supprimee(s) de la banque.');
    }

    private function updateIdea(array $matrix) {
        $ideaId = (int) ($_POST['idea_id'] ?? 0);
        if ($ideaId <= 0) { throw new RuntimeException('Idee introuvable.'); }
        $hook = trim((string) ($_POST['hook_idea'] ?? ''));
        if ($hook === '') { throw new RuntimeException('Le titre de l idee est obligatoire.'); }
        $type = in_array($_POST['deliverable_type'] ?? '', ['Video','Visuel'], true) ? $_POST['deliverable_type'] : 'Video';
        $brief = trim((string) ($_POST['generated_brief'] ?? ''));
        if ($brief === '') { $brief = $this->buildBrief($_POST); }
        $sql = "UPDATE matrix_ideas SET target_audience=:target,objective=:objective,problem_need=:problem,product_offer=:product,creative_format=:format,deliverable_type=:type,platform=:platform,hook_idea=:hook,call_to_action=:cta,generated_brief=:brief,script_content=:script,priority=:priority,status=:status WHERE id=:id AND matrix_id=:matrix AND tenant_id=:tenant AND synced_deliverable_id IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['target'=>trim((string)($_POST['target_audience']??'')),'objective'=>trim((string)($_POST['objective']??'')),'problem'=>trim((string)($_POST['problem_need']??'')),'product'=>trim((string)($_POST['product_offer']??'')),'format'=>trim((string)($_POST['creative_format']??'')),'type'=>$type,'platform'=>trim((string)($_POST['platform']??'')),'hook'=>$hook,'cta'=>trim((string)($_POST['call_to_action']??'')),'brief'=>$brief,'script'=>trim((string)($_POST['script_content']??'')),'priority'=>in_array($_POST['priority']??'', ['Haute','Moyenne','Basse'],true)?$_POST['priority']:'Moyenne','status'=>in_array($_POST['status']??'', ['Brouillon','Validee','Ecartee'],true)?$_POST['status']:'Brouillon','id'=>$ideaId,'matrix'=>(int)$matrix['id'],'tenant'=>$this->tenantId]);
        $this->flash('success', 'Idee, brief et script mis a jour.');
    }

    private function generateCombinations(array $matrix, $projectId, $month) {
        if ($projectId <= 0) { throw new RuntimeException('Selectionnez un projet.'); }
        $this->assertProjectClient($projectId, (int)$matrix['client_id']);
        $anchorType = ($_POST['anchor_type'] ?? '') === 'Cible' ? 'Cible' : 'Produit';
        $anchorList = $anchorType === 'Produit' ? $matrix['product_list'] : $matrix['target_list'];
        $anchorValues = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['anchor_values'] ?? [])))));
        if (!$anchorValues) { throw new RuntimeException('Selectionnez au moins une valeur principale.'); }
        foreach ($anchorValues as $selectedAnchor) {
            if (!in_array($selectedAnchor, $anchorList, true)) { throw new RuntimeException('Une valeur principale selectionnee est invalide.'); }
        }
        $count = max(1, min(30, (int)($_POST['combination_count'] ?? 6)));
        $withScript = !empty($_POST['with_script']);
        $insert = $this->pdo->prepare("INSERT INTO matrix_ideas (matrix_id,tenant_id,client_id,projet_id,target_month,target_audience,objective,problem_need,product_offer,creative_format,deliverable_type,platform,hook_idea,call_to_action,generated_brief,script_content,generation_mode,anchor_type,anchor_value,priority,created_by) VALUES (:matrix,:tenant,:client,:project,:month,:target,:objective,:problem,:product,:format,:type,:platform,:hook,:cta,:brief,:script,'Combinaison',:anchor_type,:anchor_value,'Moyenne',:user)");
        for ($i=0; $i<$count; $i++) {
            $anchorValue = $anchorValues[$i % count($anchorValues)];
            $target = $anchorType === 'Cible' ? $anchorValue : ($matrix['target_list'][$i % max(1,count($matrix['target_list']))] ?? 'Audience cible');
            $product = $anchorType === 'Produit' ? $anchorValue : ($matrix['product_list'][$i % max(1,count($matrix['product_list']))] ?? 'Offre principale');
            $objective = $matrix['objective_list'][$i % max(1,count($matrix['objective_list']))] ?? 'Visibilite';
            $problem = $matrix['problem_list'][($i+1) % max(1,count($matrix['problem_list']))] ?? 'Besoin a preciser';
            $format = $matrix['format_list'][($i+2) % max(1,count($matrix['format_list']))] ?? $matrix['default_format'];
            $cta = $matrix['cta_list'][($i+3) % max(1,count($matrix['cta_list']))] ?? 'Contactez-nous';
            $platform = $matrix['platform_list'][$i % max(1,count($matrix['platform_list']))] ?? 'Multi-canal';
            $hook = ($i+1).'. '.$product.' : comment aider '.$target.' a resoudre « '.$problem.' » ?';
            $data=['hook_idea'=>$hook,'target_audience'=>$target,'product_offer'=>$product,'problem_need'=>$problem,'creative_format'=>$format,'objective'=>$objective,'call_to_action'=>$cta];
            $brief=$this->buildBrief($data);
            $script=$withScript ? "ACCROCHE : $hook\n\nDEVELOPPEMENT : Presenter le probleme, illustrer la solution avec $product et donner une preuve concrete.\n\nCONCLUSION : $cta" : '';
            $insert->execute(['matrix'=>(int)$matrix['id'],'tenant'=>$this->tenantId,'client'=>(int)$matrix['client_id'],'project'=>$projectId,'month'=>$month.'-01','target'=>$target,'objective'=>$objective,'problem'=>$problem,'product'=>$product,'format'=>$format,'type'=>$matrix['default_deliverable_type'],'platform'=>$platform,'hook'=>$hook,'cta'=>$cta,'brief'=>$brief,'script'=>$script,'anchor_type'=>$anchorType,'anchor_value'=>$anchorValue,'user'=>(int)($this->currentUser()['id']??0)?:null]);
        }
        $this->flash('success', $count.' combinaisons reparties sur '.count($anchorValues).' valeur(s) principale(s).');
    }

    private function assignIdeas(array $matrix, $projectId) {
        if ($projectId <= 0) { throw new RuntimeException('Selectionnez un projet.'); }
        TenantGuard::assertProject($projectId); $this->assertProjectClient($projectId,(int)$matrix['client_id']);
        $ids=array_values(array_filter(array_map('intval',(array)($_POST['idea_ids']??[]))));
        if(!$ids) throw new RuntimeException('Selectionnez les idees a affecter.');
        $start=preg_match('/^\d{4}-\d{2}$/',(string)($_POST['start_month']??''))?(string)$_POST['start_month']:date('Y-m');
        $spread=max(1,min(12,(int)($_POST['spread_months']??1)));
        PipelineService::syncProject($projectId);
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$this->pdo->prepare("SELECT * FROM matrix_ideas WHERE matrix_id=? AND tenant_id=? AND projet_id=? AND synced_deliverable_id IS NULL AND id IN ($marks) ORDER BY FIELD(priority,'Haute','Moyenne','Basse'),id");
        $stmt->execute(array_merge([(int)$matrix['id'],$this->tenantId,$projectId],$ids)); $ideas=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($ideas)!==count($ids)) throw new RuntimeException('Une idee selectionnee a deja ete affectee ou n est plus disponible.');
        $planIds=[]; for($m=0;$m<$spread;$m++){ $month=(new DateTime($start.'-01'))->modify('+'.$m.' month')->format('Y-m-01'); $q=$this->pdo->prepare('SELECT id FROM plans_mensuels WHERE projet_id=:project AND periode_mois=:month');$q->execute(['project'=>$projectId,'month'=>$month]);$planIds[$month]=(int)$q->fetchColumn();if(!$planIds[$month])throw new RuntimeException('Le mois '.substr($month,0,7).' est hors de la periode du projet.'); }
        $this->pdo->beginTransaction(); $touched=[];
        try { foreach($ideas as $index=>$idea){$targetMonth=(new DateTime($start.'-01'))->modify('+'.($index%$spread).' month')->format('Y-m-01');$planId=$planIds[$targetMonth];$slot=$this->pdo->prepare("SELECT li.id FROM livrable_items li LEFT JOIN matrix_ideas mi ON mi.synced_deliverable_id=li.id WHERE li.plan_mensuel_id=:plan AND li.type_livrable=:type AND mi.id IS NULL ORDER BY li.numero_ordre LIMIT 1 FOR UPDATE");$slot->execute(['plan'=>$planId,'type'=>$idea['deliverable_type']]);$deliverableId=(int)$slot->fetchColumn();if(!$deliverableId)throw new RuntimeException('Capacite '.$idea['deliverable_type'].' insuffisante pour '.substr($targetMonth,0,7).'. Aucune idee n a ete affectee.');$message=trim((string)$idea['generated_brief']);if(trim((string)$idea['script_content'])!=='')$message.="\n\nSCRIPT\n".$idea['script_content'];$this->pdo->prepare("UPDATE livrable_items SET titre=:title,sous_type=:format,canal=:platform,statut='Planifie' WHERE id=:id")->execute(['title'=>$idea['hook_idea'],'format'=>$idea['creative_format'],'platform'=>$idea['platform'],'id'=>$deliverableId]);$this->pdo->prepare("UPDATE contenus SET sujet=:subject,message=:message,objectif_publication=:objective,cible_libre=:target,reseau_cible=:platform,sous_type=:format WHERE livrable_item_id=:id")->execute(['subject'=>$idea['hook_idea'],'message'=>$message,'objective'=>$idea['objective'],'target'=>$idea['target_audience'],'platform'=>$idea['platform'],'format'=>$idea['creative_format'],'id'=>$deliverableId]);$this->pdo->prepare("INSERT INTO content_compositions (livrable_item_id,projet_id,client_id,method,target_audience,objective,problem_need,product_offer,content_format,call_to_action,platform,hook_idea,priority,idea_status,generated_brief) VALUES (:deliverable,:project,:client,'MATRIX',:target,:objective,:problem,:product,:format,:cta,:platform,:hook,:priority,'Planifiee',:brief) ON DUPLICATE KEY UPDATE generated_brief=VALUES(generated_brief),idea_status='Planifiee'")->execute(['deliverable'=>$deliverableId,'project'=>$projectId,'client'=>$matrix['client_id'],'target'=>$idea['target_audience'],'objective'=>$idea['objective'],'problem'=>$idea['problem_need'],'product'=>$idea['product_offer'],'format'=>$idea['creative_format'],'cta'=>$idea['call_to_action'],'platform'=>$idea['platform'],'hook'=>$idea['hook_idea'],'priority'=>$idea['priority'],'brief'=>$message]);$this->pdo->prepare("UPDATE matrix_ideas SET status='Synchronisee',target_month=:month,synced_deliverable_id=:deliverable WHERE id=:id")->execute(['month'=>$targetMonth,'deliverable'=>$deliverableId,'id'=>$idea['id']]);$touched[$planId]=true;} $this->pdo->commit(); foreach(array_keys($touched)as$planId)PipelineService::syncContentReadinessForPlan($planId);$this->flash('success',count($ideas).' idee(s) repartie(s) sur '.$spread.' mois et retirees de la banque.');}catch(Throwable$e){$this->pdo->rollBack();throw$e;}
    }
    private function syncIdeas(array $matrix,$projectId,$month) {
        TenantGuard::assertProject($projectId); $this->assertProjectClient($projectId,(int)$matrix['client_id']); PipelineService::syncProject($projectId);
        $stmt=$this->pdo->prepare('SELECT id FROM plans_mensuels WHERE projet_id=:project AND periode_mois=:month LIMIT 1'); $stmt->execute(['project'=>$projectId,'month'=>$month.'-01']); $planId=(int)$stmt->fetchColumn();
        if($planId<=0) throw new RuntimeException('Ce mois ne fait pas partie de la periode du projet.');
        $stmt=$this->pdo->prepare("SELECT * FROM matrix_ideas WHERE matrix_id=:matrix AND projet_id=:project AND target_month=:month AND status='Validee' AND synced_deliverable_id IS NULL ORDER BY FIELD(priority,'Haute','Moyenne','Basse'),id"); $stmt->execute(['matrix'=>$matrix['id'],'project'=>$projectId,'month'=>$month.'-01']); $ideas=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$ideas) throw new RuntimeException('Aucune idee validee a synchroniser.');
        $this->pdo->beginTransaction();
        try {
            foreach($ideas as $idea){
                $slot=$this->pdo->prepare("SELECT li.id FROM livrable_items li LEFT JOIN matrix_ideas mi ON mi.synced_deliverable_id=li.id WHERE li.plan_mensuel_id=:plan AND li.type_livrable=:type AND mi.id IS NULL ORDER BY li.numero_ordre LIMIT 1 FOR UPDATE"); $slot->execute(['plan'=>$planId,'type'=>$idea['deliverable_type']]); $deliverableId=(int)$slot->fetchColumn();
                if($deliverableId<=0) throw new RuntimeException('Quota '.$idea['deliverable_type'].' insuffisant pour synchroniser toutes les idees. Augmentez le quota du projet ou reduisez la selection.');
                $this->pdo->prepare("UPDATE livrable_items SET titre=:title,sous_type=:format,canal=:platform,statut='Planifie' WHERE id=:id")->execute(['title'=>$idea['hook_idea'],'format'=>$idea['creative_format'],'platform'=>$idea['platform'],'id'=>$deliverableId]);
                $this->pdo->prepare("UPDATE contenus SET sujet=:subject,message=:message,objectif_publication=:objective,cible_libre=:target,reseau_cible=:platform,sous_type=:format WHERE livrable_item_id=:id")->execute(['subject'=>$idea['hook_idea'],'message'=>$idea['generated_brief'],'objective'=>$idea['objective'],'target'=>$idea['target_audience'],'platform'=>$idea['platform'],'format'=>$idea['creative_format'],'id'=>$deliverableId]);
                $this->pdo->prepare("INSERT INTO content_compositions (livrable_item_id,projet_id,client_id,method,target_audience,objective,problem_need,product_offer,content_format,call_to_action,platform,hook_idea,priority,idea_status,generated_brief) VALUES (:deliverable,:project,:client,'MATRIX',:target,:objective,:problem,:product,:format,:cta,:platform,:hook,:priority,'Planifiee',:brief) ON DUPLICATE KEY UPDATE target_audience=VALUES(target_audience),objective=VALUES(objective),problem_need=VALUES(problem_need),product_offer=VALUES(product_offer),content_format=VALUES(content_format),call_to_action=VALUES(call_to_action),platform=VALUES(platform),hook_idea=VALUES(hook_idea),priority=VALUES(priority),idea_status='Planifiee',generated_brief=VALUES(generated_brief)")->execute(['deliverable'=>$deliverableId,'project'=>$projectId,'client'=>$matrix['client_id'],'target'=>$idea['target_audience'],'objective'=>$idea['objective'],'problem'=>$idea['problem_need'],'product'=>$idea['product_offer'],'format'=>$idea['creative_format'],'cta'=>$idea['call_to_action'],'platform'=>$idea['platform'],'hook'=>$idea['hook_idea'],'priority'=>$idea['priority'],'brief'=>$idea['generated_brief']]);
                $this->pdo->prepare("UPDATE matrix_ideas SET status='Synchronisee',synced_deliverable_id=:deliverable WHERE id=:id")->execute(['deliverable'=>$deliverableId,'id'=>$idea['id']]);
            }
            $this->pdo->commit(); PipelineService::syncContentReadinessForPlan($planId); $this->flash('success',count($ideas).' idee(s) synchronisee(s) avec le calendrier client.');
        } catch(Throwable $e){$this->pdo->rollBack();throw $e;}
    }

    private function accessibleClients(){
        $projectsScope=AgencyAccessPolicy::clientSqlScope('c','projects','matrix_projects');
        $contentScope=AgencyAccessPolicy::clientSqlScope('c','content','matrix_content');
        $sql='SELECT DISTINCT c.id,c.nom,c.entreprise,c.relationship_mode,c.tenant_id,c.organization_id,c.managed_by_organization_id FROM clients c WHERE '.$projectsScope['sql'].' AND '.$contentScope['sql'].' ORDER BY c.entreprise,c.nom';
        $stmt=$this->pdo->prepare($sql);$stmt->execute(array_merge($projectsScope['params'],$contentScope['params']));
        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC),static fn($row)=>AgencyAccessPolicy::canAccessRecord('clients',$row,'projects')&&AgencyAccessPolicy::canAccessRecord('clients',$row,'content')));
    }
    private function projectsForClient($clientId){
        if($clientId<=0)return[];
        TenantGuard::assertClient((int)$clientId);
        $stmt=$this->pdo->prepare('SELECT p.id,p.nom,p.date_debut,p.date_fin,p.quota_videos_mensuel,p.quota_visuels_mensuel,p.tenant_id,p.client_id,p.beneficiary_organization_id FROM projets p WHERE p.client_id=:client ORDER BY p.nom');
        $stmt->execute(['client'=>$clientId]);
        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC),static fn($row)=>AgencyAccessPolicy::canAccessRecord('projets',$row,'projects')));
    }    private function matricesForClient($clientId){if($clientId<=0)return[];$stmt=$this->pdo->prepare("SELECT * FROM content_matrices WHERE tenant_id=:tenant AND client_id=:client AND status='Active' ORDER BY updated_at DESC");$stmt->execute(['tenant'=>$this->tenantId,'client'=>$clientId]);return$stmt->fetchAll(PDO::FETCH_ASSOC);}
    private function findMatrix($id,$clientId){if($id<=0)return null;$stmt=$this->pdo->prepare("SELECT * FROM content_matrices WHERE id=:id AND tenant_id=:tenant AND client_id=:client AND status='Active' LIMIT 1");$stmt->execute(['id'=>$id,'tenant'=>$this->tenantId,'client'=>$clientId]);$m=$stmt->fetch(PDO::FETCH_ASSOC);if(!$m)return null;foreach(['target','objective','problem','product','format','cta','platform']as$key){$m[$key.'_list']=json_decode((string)$m[$key.'_options'],true)?:[];}return$m;}
    private function ideasForContext($matrixId,$projectId,$month){$stmt=$this->pdo->prepare('SELECT * FROM matrix_ideas WHERE matrix_id=:matrix AND tenant_id=:tenant AND projet_id=:project AND synced_deliverable_id IS NULL ORDER BY FIELD(priority,"Haute","Moyenne","Basse"),id DESC');$stmt->execute(['matrix'=>$matrixId,'tenant'=>$this->tenantId,'project'=>$projectId]);return$stmt->fetchAll(PDO::FETCH_ASSOC);}
    private function assertProjectClient($projectId,$clientId){$stmt=$this->pdo->prepare('SELECT 1 FROM projets WHERE id=:project AND client_id=:client');$stmt->execute(['project'=>$projectId,'client'=>$clientId]);if(!$stmt->fetchColumn())throw new RuntimeException('Le projet ne correspond pas au client selectionne.');}
    private function lines($value,array$fallback){$rows=array_values(array_unique(array_filter(array_map('trim',preg_split('/\R/',(string)$value)))));return$rows?:$fallback;}
    private function buildBrief(array$p){return trim((string)($p['hook_idea']??'')).'. Pour '.trim((string)($p['target_audience']??'la cible')).' : montrer comment '.trim((string)($p['product_offer']??'le produit')).' repond au besoin « '.trim((string)($p['problem_need']??'a preciser')).' » au format '.trim((string)($p['creative_format']??'a definir')).', afin de renforcer '.trim((string)($p['objective']??'l objectif')).'. CTA : '.trim((string)($p['call_to_action']??'a definir')).'.';}
    private function defaultReferences(){return['target'=>['Restaurants','Agroalimentaire','Marques locales','Grand public'],'objective'=>['Visibilite','Notoriete','Autorite','Confiance','Conversion','Engagement'],'problem'=>['Presentation peu professionnelle','Mauvaise conservation','Produit peu visible','Manque de credibilite'],'product'=>['Produit principal','Offre de service','Nouveaute','Promotion'],'format'=>['Avant / apres','Demonstration','Conseil rapide','Storytelling','Temoignage','Comparaison','Test produit','Coulisses'],'cta'=>['Demandez conseil','Ecrivez-nous','Visitez notre espace','Enregistrez ce contenu','Partagez'],'platform'=>['Facebook','Instagram','TikTok','LinkedIn','YouTube','Multi-canal']];}
}
