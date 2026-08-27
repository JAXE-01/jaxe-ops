<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/config.php';
$pdo=Database::getConnection();$id=0;$exitCode=0;
try {
    $user=$pdo->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$user)throw new RuntimeException('Administrateur local requis.');
    $_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();
    $tenant=TenantGuard::tenantId();
    $stmt=$pdo->prepare('SELECT c.id,p.id project_id FROM clients c JOIN projets p ON p.client_id=c.id WHERE c.tenant_id=:tenant LIMIT 1');$stmt->execute(['tenant'=>$tenant]);$client=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$client)throw new RuntimeException('Client avec projet requis.');
    $stmt=$pdo->prepare("INSERT INTO social_connections(tenant_id,client_id,provider,account_label,status) VALUES(:tenant,:client,'facebook','Selection regression temporaire','Pending')");$stmt->execute(['tenant'=>$tenant,'client'=>$client['id']]);$id=(int)$pdo->lastInsertId();
    $key=bin2hex(random_bytes(24));$_SESSION['social_oauth_selection'][$key]=['tenant_id'=>$tenant,'connection_id'=>$id,'created_at'=>time(),'pages'=>[['id'=>'test','name'=>'Page de test locale','access_token'=>'not-rendered']],'scopes'=>[]];
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/index.php/social-oauth/select/'.$key;$_SERVER['HTTP_HOST']='localhost';
    $controller=new SocialOAuthController();ob_start();try{$controller->select($key);$html=ob_get_contents();}finally{ob_end_clean();}
    if(!str_contains($html,'Page de test locale')||!str_contains($html,'projects[facebook_0][]'))throw new RuntimeException('Sélection ou projets absents du rendu.');
    if(str_contains($html,'not-rendered'))throw new RuntimeException('Un jeton a été exposé.');
    $model=new SocialPublishingModel();$model->projects();
    $pdo->beginTransaction();
    $insert=$pdo->prepare('INSERT INTO social_connection_projects(tenant_id,connection_id,project_id,created_by) VALUES(:tenant,:connection,:project,:user)');$insert->execute(['tenant'=>$tenant,'connection'=>$id,'project'=>$client['project_id'],'user'=>$user['id']]);
    $method=new ReflectionMethod(SocialOAuthController::class,'syncConnectionProjects');
    $rejected=false;try{$method->invoke($controller,$id,(int)$client['id'],[PHP_INT_MAX]);}catch(RuntimeException $e){$rejected=true;}
    $pdo->rollBack();if(!$rejected)throw new RuntimeException('Projet étranger accepté.');
    echo "OK: rendu OAuth et projets, jetons absents du HTML, projet invalide refusé.\n";
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);$exitCode=1;}
finally{if($pdo->inTransaction())$pdo->rollBack();if($id)$pdo->prepare('DELETE FROM social_connections WHERE id=:id')->execute(['id'=>$id]);if(isset($key))unset($_SESSION['social_oauth_selection'][$key]);}
exit($exitCode);
