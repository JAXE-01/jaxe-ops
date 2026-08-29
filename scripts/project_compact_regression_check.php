<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
set_error_handler(static function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);return false;});
$db=Database::getConnection();
$user=$db->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();
$q=$db->prepare('SELECT p.id FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=? LIMIT 1');$q->execute([TenantGuard::tenantId()]);$id=(int)$q->fetchColumn();
$calendar=(new CalendrierModel())->getProjectCalendar($id,null,$user);
if(!$calendar||!$calendar['plans'])throw new RuntimeException('Projet local avec calendrier requis');
$selectedPlanId=(int)$calendar['plans'][0]['id'];$boardCanManageAsCC=true;$boardCurrentUserId=(int)$user['id'];
$boardReassignmentOptions=['video'=>[$user['id']=>$user['nom']],'creative'=>[$user['id']=>$user['nom']]];
$previousCalendarUrl='/previous';$nextCalendarUrl='/next';
ob_start();require dirname(__DIR__).'/app/views/calendrier/projet.php';$html=ob_get_clean();
function projectCompactCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
projectCompactCheck(!str_contains($html,'project-board-intro'),'Duplicate project hero');
projectCompactCheck(!str_contains($html,'Charge de communication:'),'Assignment banner present');
projectCompactCheck(substr_count($html,'calendar-icon-action')===7,'Project actions missing');
projectCompactCheck(str_contains($html,'monthly-tasks-extra'),'Monthly tasks inaccessible');
projectCompactCheck(str_contains($html,'data-label="Production vidéo"'),'Video stages not grouped');
projectCompactCheck(str_contains($html,'task-cell production-card')&&!str_contains($html,'production-substage">Tournage'),'Video task links lost');
projectCompactCheck(substr_count($html,'<form ')===substr_count($html,'</form>'),'Unbalanced forms');
projectCompactCheck(str_contains($html,'name="expiry_days"'),'Expiry input missing');
foreach($calendar['plans'] as $p)foreach($p['deliverables'] as $d)foreach($d['tasks'] as $t){projectCompactCheck(str_contains($html,htmlspecialchars(route_url('/calendrier/task/'.$t['id']))),'Task link missing');}
// Fragment requests render the same grouped cells without the page wrapper.
ob_start();require dirname(__DIR__).'/app/views/calendrier/projet_board.php';$fragment=ob_get_clean();
projectCompactCheck(str_contains($fragment,'data-label="Production vidéo"'),'Fragment grouping mismatch');
echo "OK: compact project actions, grouped video stages, task links, forms and AJAX fragment. No production mutation, no visual assertion.\n";
