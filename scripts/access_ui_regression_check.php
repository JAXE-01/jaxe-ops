<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/config.php';
try{
    $pdo=Database::getConnection();$user=$pdo->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$user)throw new RuntimeException('Administrateur local requis.');
    $_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_URI']='/index.php/platform';
    $controller=new PlatformController();ob_start();try{$controller->index();$html=ob_get_contents();}finally{ob_end_clean();}
    if(!str_contains($html,'Comptes de la plateforme')||str_contains($html,'class="nav-label">Matrice de'))throw new RuntimeException('Navigation SaaS incohérente.');
    if(!preg_match('~<form[^>]+method="post"[^>]+action="[^"]*/logout"[^>]*>\s*<input[^>]+name="_csrf_token"~',$html))throw new RuntimeException('Déconnexion POST/CSRF absente.');
    $_SESSION['workspace_mode']='agency';$controller=new Controller();$model=new SocialPublishingModel();$data=$model->dashboardData();
    ob_start();try{$controller->render('social-publishing/index',array_merge($data,['clients'=>$model->clients(),'projects'=>$model->projects(),'providers'=>SocialPublishingModel::PROVIDERS,'canManage'=>false,'canApprove'=>false]));$html=ob_get_contents();}finally{ob_end_clean();}
    if(str_contains($html,'id="composeDialog"')||str_contains($html,'data-open-compose '))throw new RuntimeException('Composition visible en lecture seule.');
    echo "OK: supervision SaaS, déconnexion POST avec CSRF, composition masquée en lecture seule.\n";
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
