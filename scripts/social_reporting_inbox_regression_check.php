<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=dirname(__DIR__);
$model=file_get_contents($root.'/app/models/ReportingMetricModel.php');
$controller=file_get_contents($root.'/app/controllers/ReportingMetricController.php');
$inbox=file_get_contents($root.'/app/helpers/SocialInboxService.php');
$view=file_get_contents($root.'/app/views/reporting-metric/index.php');
foreach(['scope_social.id=rm.social_publication_id','analytics_social_metrics'] as$needle)if(!str_contains($model,$needle))throw new RuntimeException('Périmètre social absent: '.$needle);
foreach(['collectSocialMetrics','importSocialHistory']as$needle)if(!str_contains($controller,$needle))throw new RuntimeException('Action reporting absente: '.$needle);
foreach(['Collecter les KPI Meta','Importer l’historique Meta','Messages et commentaires']as$needle)if(!str_contains($view,$needle))throw new RuntimeException('Bouton absent: '.$needle);
foreach(['pages_manage_engagement','pages_messaging','replyComment','replyMessage']as$needle)if(!str_contains($inbox,$needle))throw new RuntimeException('Capacité inbox absente: '.$needle);
echo "OK: reporting social visible, collecte sur Statistiques et inbox Meta.\n";
