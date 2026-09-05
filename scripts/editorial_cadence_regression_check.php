<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
function cadenceAssert($ok,$why){if(!$ok)throw new RuntimeException($why);}
$rules=EditorialCadence::normalize([
 ['day'=>1,'type'=>'Visuel','label'=>'Début de semaine','format'=>'Image'],
 ['day'=>3,'type'=>'Visuel','label'=>'Éducation','format'=>'Carrousel'],
 ['day'=>5,'every'=>2,'phase'=>0,'type'=>'Video','label'=>'Démo'],
 ['day'=>5,'every'=>2,'phase'=>1,'type'=>'Video','label'=>'Divertissement']
]);
$sept=EditorialCadence::dates($rules,'2026-09-01','2026-10-31','2026-09');
$oct=EditorialCadence::dates($rules,'2026-09-01','2026-10-31','2026-10');
cadenceAssert(count($sept['Visuel'])===9&&count($sept['Video'])===4,'September counts');
cadenceAssert(count($oct['Video'])===5&&$oct['Video'][0]['label']==='Démo','Five Fridays and continuous alternation');
$compactRules=EditorialCadence::normalize([
 ['active'=>1,'day'=>2,'time'=>'08:30','type'=>'Visuel','frequency'=>'weekly','label'=>'Conseil'],
 ['active'=>1,'day'=>2,'time'=>'17:45','type'=>'Video','frequency'=>'biweekly','label'=>'Coulisses'],
 ['active'=>1,'day'=>4,'time'=>'12:00','type'=>'Visuel','frequency'=>'monthly','label'=>'Offre du mois'],
]);
$compactDates=EditorialCadence::dates($compactRules,'2026-09-01','2026-10-31','2026-09');
cadenceAssert($compactDates['Visuel'][0]['time']==='08:30','Default time was not retained');
cadenceAssert(count(array_filter($compactDates['Visuel'],static fn($slot)=>$slot['label']==='Offre du mois'))===1,'Monthly rule must create one slot');
cadenceAssert(count(array_filter(array_merge($compactDates['Video'],$compactDates['Visuel']),static fn($slot)=>$slot['date']==='2026-09-01'))===2,'Same-day slots must be preserved');
$db=Database::getConnection();
try{
 $u=$db->query("SELECT * FROM users WHERE role='Admin' AND statut='Actif' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$_SESSION['user']=$u;$_SESSION['user']['roles']=UserRoles::extractRoles($u);TenantContext::clear();$tenant=TenantGuard::tenantId();
 WorkingMonth::resolve('2026-10');cadenceAssert(WorkingMonth::resolve()==='2026-10','Month not retained');WorkingMonth::resolve('2026-99');cadenceAssert(WorkingMonth::resolve()==='2026-10','Invalid month stored');
 $q=$db->prepare('SELECT p.* FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=:tenant LIMIT 1');$q->execute(['tenant'=>$tenant]);$project=$q->fetch(PDO::FETCH_ASSOC);cadenceAssert($project,'Local project required');unset($project['id']);
 $project=array_merge($project,['nom'=>'TEST cadence rollback','date_debut'=>'2026-09-01','date_fin'=>'2026-10-31','duree_mois'=>2,'publication_rules'=>json_encode($rules)]);
 $db->beginTransaction();$cols=array_keys($project);$db->prepare('INSERT INTO projets (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')')->execute(array_values($project));$id=(int)$db->lastInsertId();
 PipelineService::syncProject($id);
 $q=$db->prepare('SELECT type_livrable,date_prevue,titre,sous_type FROM livrable_items WHERE projet_id=:id ORDER BY date_prevue');$q->execute(['id'=>$id]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
 cadenceAssert(count($items)===26,'Unexpected generated total');
 foreach($items as$item){$day=(int)(new DateTimeImmutable($item['date_prevue']))->format('N');cadenceAssert($item['type_livrable']==='Video'?$day===5:in_array($day,[1,3]),'Wrong weekday');}
 PipelineService::syncProject($id);$q->execute(['id'=>$id]);cadenceAssert($items===$q->fetchAll(PDO::FETCH_ASSOC),'Resync changed/duplicated slots');
 $rejected=false;try{EditorialCadence::save($db,$id,['cadence_present'=>1,'cadence'=>[]]);}catch(RuntimeException$e){$rejected=true;}cadenceAssert($rejected,'Existing calendar silently rewritten');
 $options=(new ProjectModel())->getRelationOptions('abonnement');$q=$db->prepare("SELECT id FROM abonnements WHERE tenant_id=:tenant");$q->execute(['tenant'=>$tenant]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));foreach(array_keys($options)as$key)cadenceAssert(in_array((int)$key,$allowed,true),'Foreign subscription visible');
 $db->rollBack();echo "OK: weekly dates, alternating Fridays, 5-week month, resync, existing-calendar protection, working month and subscription isolation. Fixtures rolled back.\n";
}finally{if($db->inTransaction())$db->rollBack();}
