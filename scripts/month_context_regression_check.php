<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
function monthCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$db=Database::getConnection();$user=$db->query("SELECT * FROM users WHERE role='Admin' AND statut='Actif' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$_SESSION['user']=$user;TenantContext::clear();$tenant=TenantGuard::tenantId();
WorkingMonth::resolve('2030-02');monthCheck(WorkingMonth::resolve()==='2030-02','Month not retained');WorkingMonth::resolve('invalid');monthCheck(WorkingMonth::resolve()==='2030-02','Invalid month accepted');
$q=$db->prepare('SELECT p.id,p.client_id FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=? LIMIT 1');$q->execute([$tenant]);$project=$q->fetch(PDO::FETCH_ASSOC);
$db->beginTransaction();
try{
 $ids=[];$q=$db->prepare("INSERT INTO social_publications(tenant_id,client_id,project_id,master_title,master_caption,publish_mode,scheduled_at,approval_status,created_by) VALUES(?,?,?,'TEST month rollback','Test','Scheduled',?,'Draft',?)");
 foreach(['2030-02-01 00:00:00','2030-02-28 23:59:59','2030-03-01 00:00:00'] as $date){$q->execute([$tenant,$project['client_id'],$project['id'],$date,$user['id']]);$ids[]=(int)$db->lastInsertId();}
 $dashboard=(new SocialPublishingModel())->dashboardData('2030-02');$visible=array_map('intval',array_column($dashboard['publications'],'id'));
 monthCheck(in_array($ids[0],$visible,true)&&in_array($ids[1],$visible,true)&&!in_array($ids[2],$visible,true),'Publication month boundaries incorrect');
 $bad=false;try{(new SocialPublishingModel())->dashboardData('2030-13');}catch(RuntimeException $e){$bad=true;}monthCheck($bad,'Invalid month query accepted');
 $currentUser=$user;$_SERVER['REQUEST_URI']='/jaxe-ops/index.php/calendrier/contenu/409';ob_start();require dirname(__DIR__).'/app/views/calendrier/month-context-bar.php';$html=ob_get_clean();
 monthCheck(str_contains($html,'2030-02')&&str_contains($html,route_url('/calendrier')),'Detail navigation must open calendar');
 echo "OK: persistent month, invalid values rejected, publication month boundaries, safe detail navigation. Fixtures rolled back.\n";
}finally{$db->rollBack();}
