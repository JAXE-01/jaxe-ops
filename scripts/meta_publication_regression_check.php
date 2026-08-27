<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require dirname(__DIR__) . '/config/config.php';

$pdo=Database::getConnection();$connectionId=0;$publicationId=0;$exitCode=0;
try {
    $user=$pdo->query("SELECT * FROM users WHERE statut='Actif' ORDER BY FIELD(role,'Admin','SuperAdmin') DESC,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$user) throw new RuntimeException('Aucun utilisateur actif pour le test.');
    $_SESSION['user']=['id'=>(int)$user['id'],'nom'=>(string)$user['nom'],'email'=>(string)$user['email'],'role'=>(string)$user['role'],'secondary_roles'=>(string)($user['secondary_roles']??''),'roles'=>UserRoles::extractRoles($user)];
    TenantContext::clear();$tenant=TenantContext::resolveForUser($_SESSION['user']);$tenantId=(int)($tenant['tenant_id']??$tenant['id']??0);if(!$tenantId)throw new RuntimeException('Tenant de test introuvable.');$organization=OrganizationContext::forUser($_SESSION['user']);$organizationId=(int)($organization['id']??$organization['organization_id']??0)?:null;
    $clientStmt=$pdo->prepare("SELECT c.id FROM clients c WHERE c.tenant_id=:tenant AND c.statut='Actif' AND EXISTS (SELECT 1 FROM projets p WHERE p.client_id=c.id) ORDER BY c.id LIMIT 1");$clientStmt->execute(['tenant'=>$tenantId]);$clientId=(int)$clientStmt->fetchColumn();if(!$clientId)throw new RuntimeException('Aucun client actif dans le tenant de test.');
    $external='regression-'.bin2hex(random_bytes(8));$insert=$pdo->prepare("INSERT INTO social_connections(tenant_id,organization_id,client_id,provider,account_label,external_account_id,account_type,access_token_encrypted,status,scopes_json,connected_by,last_validated_at) VALUES(:tenant,:organization,:client,'facebook','Meta regression temporaire',:external,'Page',:token,'Connected',:scopes,:user,NOW())");
    $insert->execute(['tenant'=>$tenantId,'organization'=>$organizationId,'client'=>$clientId,'external'=>$external,'token'=>CryptoService::encrypt('not-a-real-token'),'scopes'=>json_encode(['pages_show_list','pages_read_engagement','pages_manage_posts']),'user'=>(int)$user['id']]);$connectionId=(int)$pdo->lastInsertId();
    $testProject=(int)$pdo->query('SELECT id FROM projets WHERE client_id='.(int)$clientId.' ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO social_connection_projects(tenant_id,connection_id,project_id,created_by) VALUES(?,?,?,?)')->execute([$tenantId,$connectionId,$testProject,(int)$user['id']]);
    $model=new SocialPublishingModel();$publicationId=$model->createPublication(['client_id'=>$clientId,'project_id'=>(int)$pdo->query('SELECT id FROM projets WHERE client_id='.(int)$clientId.' ORDER BY id LIMIT 1')->fetchColumn(),'connection_ids'=>[$connectionId],'master_title'=>'Regression Meta','master_caption'=>'Publication technique non envoyée au réseau.','publish_mode'=>'Scheduled','scheduled_at'=>date('Y-m-d H:i:s',time()+86400)],[],(int)$user['id']);
    $model->submit($publicationId,(int)$user['id']);$model->approve($publicationId,(int)$user['id']);
    $status=$pdo->query('SELECT status FROM social_publication_targets WHERE publication_id='.(int)$publicationId)->fetchColumn();if($status!=='Queued')throw new RuntimeException('Statut attendu Queued, reçu '.(string)$status);
    $model->cancel($publicationId,(int)$user['id']);$status=$pdo->query('SELECT status FROM social_publication_targets WHERE publication_id='.(int)$publicationId)->fetchColumn();if($status!=='Cancelled')throw new RuntimeException('Statut attendu Cancelled, reçu '.(string)$status);
    echo "OK: Draft -> Pending -> Approved/Queued -> Cancelled, sans appel Meta.\n";
} catch(Throwable $exception) { fwrite(STDERR,get_class($exception).': '.$exception->getMessage().PHP_EOL); $exitCode=1; }
finally { if($publicationId){$pdo->prepare('DELETE FROM social_publications WHERE id=:id')->execute(['id'=>$publicationId]);}if($connectionId){$pdo->prepare('DELETE FROM social_connections WHERE id=:id')->execute(['id'=>$connectionId]);} }

exit($exitCode);
