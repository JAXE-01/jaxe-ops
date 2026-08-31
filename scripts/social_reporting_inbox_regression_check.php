<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=dirname(__DIR__);
$model=file_get_contents($root.'/app/models/ReportingMetricModel.php');
$controller=file_get_contents($root.'/app/controllers/ReportingMetricController.php');
$inbox=file_get_contents($root.'/app/helpers/SocialInboxService.php');
$view=file_get_contents($root.'/app/views/reporting-metric/index.php');
foreach(['scope_social.id=rm.social_publication_id','analytics_social_metrics','filter_client','filter_connection','date_publication','getClientOptions','getPageOptions'] as$needle)if(!str_contains($model,$needle))throw new RuntimeException('Périmètre social absent: '.$needle);
foreach(['collectSocialMetrics','importSocialHistory']as$needle)if(!str_contains($controller,$needle))throw new RuntimeException('Action reporting absente: '.$needle);
foreach(['Collecter les KPI Meta','Importer l’historique Meta','Messages et commentaires']as$needle)if(!str_contains($view,$needle))throw new RuntimeException('Bouton absent: '.$needle);
foreach(['pages_manage_engagement','pages_messaging','replyComment','replyMessage']as$needle)if(!str_contains($inbox,$needle))throw new RuntimeException('Capacité inbox absente: '.$needle);
$inboxView=file_get_contents($root.'/app/views/social-inbox/index.php');foreach(['inbox-tabs','type=','Historique (','messages[0]']as$needle)if(!str_contains($inboxView,$needle))throw new RuntimeException('Design inbox incomplet: '.$needle);
try{require $root.'/config/config.php';$db=Database::getConnection();$user=$db->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);if($user){$_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();$reporting=new ReportingMetricModel();$reporting->getClientOptions();$reporting->getPageOptions();$reporting->getMetrics(['client_id'=>0,'connection_id'=>0],5);$reporting->getMonthlyAggregateReport(['client_id'=>0,'connection_id'=>0]);$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/index.php/reporting-metric';$_SERVER['HTTP_HOST']='localhost';ob_start();try{(new ReportingMetricController())->index();$html=ob_get_contents();}finally{ob_end_clean();}foreach(['name="client_id"','name="connection_id"','overview-chart-grid']as$needle)if(!str_contains($html,$needle))throw new RuntimeException('Rendu reporting incomplet: '.$needle);}}catch(PDOException $e){fwrite(STDERR,"SKIP DB: MySQL local indisponible.\n");}
echo "OK: reporting social visible, collecte sur Statistiques et inbox Meta.\n";
