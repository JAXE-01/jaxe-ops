<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
function revisionCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$db=Database::getConnection();
$u=$db->query("SELECT * FROM users WHERE role='Admin' AND statut='Actif' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$_SESSION['user']=$u;TenantContext::clear();
$q=$db->prepare('SELECT p.* FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=? LIMIT 1');$q->execute([TenantGuard::tenantId()]);$project=$q->fetch(PDO::FETCH_ASSOC);
$first=new DateTimeImmutable('first day of this month');$effective=$first->modify('+1 month')->format('Y-m');
$rules=EditorialCadence::normalize([['day'=>1,'type'=>'Visuel','label'=>'Conseil','format'=>'Image'],['day'=>5,'type'=>'Video','label'=>'Démo','format'=>'Vidéo']]);
$revised=$rules;$revised[0]['day']=2;$revised[1]['day']=7;
unset($project['id']);$project=array_merge($project,['nom'=>'TEST cadence revision rollback','date_debut'=>$first->format('Y-m-d'),'date_fin'=>$first->modify('+2 months')->modify('last day of this month')->format('Y-m-d'),'duree_mois'=>3,'campagne_id'=>null,'publication_rules'=>json_encode($rules)]);
$db->beginTransaction();
try{
 $cols=array_keys($project);$db->prepare('INSERT INTO projets (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')')->execute(array_values($project));$id=(int)$db->lastInsertId();
 PipelineService::syncProject($id);
 $snap=function($before)use($db,$id,$effective){$out=[];foreach(['livrable_items','contenus','taches_pipeline'] as $table){$q=$db->prepare("SELECT t.* FROM `$table` t JOIN plans_mensuels p ON p.id=t.plan_mensuel_id WHERE t.projet_id=? AND p.periode_mois ".($before?'<':'>=')." ? ORDER BY t.id");$q->execute([$id,$effective.'-01']);$out[$table]=$q->fetchAll(PDO::FETCH_ASSOC);}return $out;};
 $db->prepare("UPDATE plans_mensuels SET statut='En cours' WHERE projet_id=?")->execute([$id]);
 $planSnapshot=function($before)use($db,$id,$effective){$q=$db->prepare('SELECT * FROM plans_mensuels WHERE projet_id=? AND periode_mois '.($before?'<':'>=').' ? ORDER BY id');$q->execute([$id,$effective.'-01']);return $q->fetchAll(PDO::FETCH_ASSOC);};
 $priorPlans=$planSnapshot(true);
 $past=$snap(true);$future=$snap(false);$protectedId=$future['livrable_items'][0]['id'];
 $db->prepare('UPDATE contenus SET message=? WHERE livrable_item_id=?')->execute(['Message rédigé à conserver',$protectedId]);
 $protected=$future['livrable_items'][0];
 $rejected=false;try{EditorialCadence::save($db,$id,['cadence_present'=>1,'cadence'=>$revised,'cadence_effective_month'=>$first->format('Y-m'),'cadence_confirm_future'=>1]);}catch(RuntimeException $e){$rejected=true;}revisionCheck($rejected,'Current month revision accepted');
 EditorialCadence::save($db,$id,['cadence_present'=>1,'cadence'=>$revised,'cadence_effective_month'=>$effective,'cadence_confirm_future'=>1]);
 PipelineService::syncProject($id);
 revisionCheck($past===$snap(true),'Earlier month changed');
 revisionCheck($priorPlans===$planSnapshot(true),'Earlier monthly plan changed');
 foreach($planSnapshot(false) as $monthlyPlan){revisionCheck($monthlyPlan['statut']==='En cours','Future monthly plan status reset');}
 $q=$db->prepare('SELECT * FROM livrable_items WHERE id=?');$q->execute([$protectedId]);revisionCheck($protected===$q->fetch(PDO::FETCH_ASSOC),'Personalized content moved');
 $q=$db->prepare('SELECT publication_rules FROM projets WHERE id=?');$q->execute([$id]);$history=CadenceRevision::decode((string)$q->fetchColumn());
 revisionCheck($history['revisions'][$effective]['summary']['moved']>0,'No blank slots adapted');
 revisionCheck($history['revisions'][$effective]['summary']['preserved']>0,'Personalized item not reported');
 $snapshot=$snap(false);PipelineService::syncProject($id);revisionCheck($snapshot===$snap(false),'Revision sync not idempotent');
 $q=$db->prepare('SELECT message FROM contenus WHERE livrable_item_id=?');$q->execute([$protectedId]);revisionCheck($q->fetchColumn()==='Message rédigé à conserver','Message overwritten');
 echo "OK: future-only revision, prior month unchanged, personalized content preserved, blank slots adapted, idempotent resync. Transaction rolled back.\n";
}finally{$db->rollBack();}
