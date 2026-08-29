<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
function policyCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$db=Database::getConnection();
$user=$db->query("SELECT * FROM users WHERE role='Admin' AND statut='Actif' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['user']=$user;TenantContext::clear();$tenant=TenantGuard::tenantId();
$q=$db->prepare('SELECT p.* FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=? LIMIT 1');$q->execute([$tenant]);$base=$q->fetch(PDO::FETCH_ASSOC);
if(!$base)throw new RuntimeException('Local project fixture required');
$db->beginTransaction();
try{
 foreach([[0,0],[0,1],[1,0],[1,1]] as [$internal,$client]){
  $project=$base;unset($project['id']);$project['nom']='TEST validation rollback';$project['date_debut']='2027-02-01';$project['date_fin']='2027-02-28';$project['duree_mois']=1;$project['publication_rules']=null;$project['quota_videos_mensuel']=1;$project['quota_visuels_mensuel']=1;
  $cols=array_keys($project);$db->prepare('INSERT INTO projets (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')')->execute(array_values($project));$id=(int)$db->lastInsertId();
  ValidationPolicy::saveProject($id,['validation_policy_present'=>1,'validation_mode'=>'custom','validation_internal'=>$internal,'validation_client'=>$client]);
  PipelineService::syncProject($id);
  $q=$db->prepare('SELECT id FROM livrable_items WHERE projet_id=?');$q->execute([$id]);
  foreach($q->fetchAll(PDO::FETCH_COLUMN) as $item){
   $tasks=$db->prepare('SELECT * FROM taches_pipeline WHERE livrable_item_id=?');$tasks->execute([$item]);$map=[];foreach($tasks->fetchAll(PDO::FETCH_ASSOC) as $t)$map[$t['type_tache']]=$t;
   policyCheck(isset($map['Validation interne'])===(bool)$internal,'Internal gate mismatch');
   policyCheck(isset($map['Validation client'])===(bool)$client,'Client gate mismatch');
   $expected=$client?'Validation client':($internal?'Validation interne':(isset($map['Montage'])?'Montage':'Production'));
   policyCheck((int)$map['Publication']['parent_task_id']===(int)$map[$expected]['id'],'Publication dependency mismatch');
   policyCheck(ValidationPolicy::contentRequires($db,(int)$item,'Validation interne')===(bool)$internal,'Readiness policy mismatch');
  }
  $snap=$db->prepare('SELECT id,parent_task_id,type_tache,statut FROM taches_pipeline WHERE projet_id=? ORDER BY id');$snap->execute([$id]);$before=$snap->fetchAll(PDO::FETCH_ASSOC);
  ValidationPolicy::saveProject($id,['validation_policy_present'=>1,'validation_mode'=>'custom','validation_internal'=>!$internal,'validation_client'=>!$client]);PipelineService::syncProject($id);
  $snap->execute([$id]);policyCheck($before===$snap->fetchAll(PDO::FETCH_ASSOC),'Existing workflow changed');
  $plans=$db->prepare('SELECT id FROM plans_mensuels WHERE projet_id=?');$plans->execute([$id]);foreach($plans->fetchAll(PDO::FETCH_COLUMN) as $plan)(new CalendrierModel())->getReadyDeliverablesForClientValidation((int)$plan);
 }
 $denied=false;try{ValidationPolicy::saveProject(-1,['validation_policy_present'=>1]);}catch(RuntimeException $e){$denied=true;}policyCheck($denied,'Unknown project accepted');
 echo "OK: four validation combinations, video/visual dependencies, immutable existing workflows, readiness and project scope. Rolled back.\n";
}finally{if($db->inTransaction())$db->rollBack();}
