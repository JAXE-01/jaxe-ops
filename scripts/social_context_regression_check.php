<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/config.php';
$db=Database::getConnection();$connections=[];$publications=[];$projects=[];$code=0;
function ensureSocial($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
    $user=$db->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ensureSocial($user,'Administrateur local requis');$_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();$tenant=TenantGuard::tenantId();
    $q=$db->prepare('SELECT p.* FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=:tenant LIMIT 1');$q->execute(['tenant'=>$tenant]);$sample=$q->fetch(PDO::FETCH_ASSOC);ensureSocial($sample,'Projet local requis');
    $client=(int)$sample['client_id'];$sample['configuration_mode']='custom';$sample['nom']='Regression social '.bin2hex(random_bytes(6));unset($sample['id']);
    $projectModel=new ProjectModel();$project=(int)$projectModel->create($sample);$projects[]=$project;
    $project2=(int)$projectModel->create(array_merge($sample,['nom'=>$sample['nom'].' B']));$projects[]=$project2;
    $insert=$db->prepare("INSERT INTO social_connections(tenant_id,client_id,provider,account_label,external_account_id,access_token_encrypted,status,scopes_json,last_validated_at,connected_by) VALUES(:tenant,:client,'facebook','Regression scope',:external,:token,'Connected',:scopes,NOW(),:user)");
    $insert->execute(['tenant'=>$tenant,'client'=>$client,'external'=>'test-'.bin2hex(random_bytes(8)),'token'=>CryptoService::encrypt('fake-never-sent'),'scopes'=>'["pages_manage_posts"]','user'=>$user['id']]);$connection=(int)$db->lastInsertId();$connections[]=$connection;
    $input=['client_id'=>$client,'project_id'=>$project,'connection_ids'=>[$connection],'master_title'=>'Scope test','master_caption'=>'Not sent to Meta','publish_mode'=>'Now'];$model=new SocialPublishingModel();
    $rejected=false;try{$id=$model->createPublication($input,[],(int)$user['id']);$publications[]=$id;}catch(RuntimeException$e){$rejected=true;}ensureSocial($rejected,'Unmapped destination accepted');
    $projectModel->update($project,array_merge($sample,['social_pages_present'=>1,'social_page_ids'=>[$connection]]));ensureSocial(ProjectSocialPages::selected($project)===[$connection],'Project mapping not saved');
    $rejected=false;try{$projectModel->update($project,array_merge($sample,['social_pages_present'=>1,'social_page_ids'=>[PHP_INT_MAX]]));}catch(RuntimeException$e){$rejected=true;}ensureSocial($rejected&&ProjectSocialPages::selected($project)===[$connection],'Invalid assignment not rolled back');
    $rejected=false;try{$id=$model->createPublication(array_merge($input,['project_id'=>$project2]),[],(int)$user['id']);$publications[]=$id;}catch(RuntimeException$e){$rejected=true;}ensureSocial($rejected,'Wrong project destination accepted');
    $publication=$model->createPublication($input,[],(int)$user['id']);$publications[]=$publication;
    $model->submit($publication,(int)$user['id']);$model->approve($publication,(int)$user['id']);
    $q=$db->prepare('SELECT id FROM social_publication_targets WHERE publication_id=:id');$q->execute(['id'=>$publication]);$target=(int)$q->fetchColumn();
    $service=new SocialPublisherService();$claim=new ReflectionMethod(SocialPublisherService::class,'claimNext');
    ensureSocial($claim->invoke($service,$tenant,PHP_INT_MAX,null)===null,'Claim selected another target');
    ensureSocial($claim->invoke($service,$tenant+99999,$target,null)===null,'Claim crossed tenant boundary');
    $db->prepare('UPDATE social_publication_targets SET next_attempt_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=:id')->execute(['id'=>$target]);
    ensureSocial($claim->invoke($service,$tenant,$target,null)===null,'Future publication claimed early');
    $db->prepare('UPDATE social_publication_targets SET next_attempt_at=NOW() WHERE id=:id')->execute(['id'=>$target]);
    $row=$claim->invoke($service,$tenant,$target,$publication);ensureSocial((int)($row['id']??0)===$target,'Due target not claimed');
    ensureSocial($claim->invoke($service,$tenant,$target,null)===null,'Processing target claimed twice');
    $rejected=false;try{$model->retry($target,(int)$user['id']);}catch(RuntimeException$e){$rejected=true;}ensureSocial($rejected,'Processing target manually retried');
    $db->prepare("UPDATE social_publication_targets SET status='Failed' WHERE id=:id")->execute(['id'=>$target]);$model->retry($target,(int)$user['id']);
    $scheduled=$model->createPublication(array_merge($input,['publish_mode'=>'Scheduled','scheduled_at'=>date('Y-m-d H:i:s',time()+86400)]),[],(int)$user['id']);$publications[]=$scheduled;
    $q->execute(['id'=>$scheduled]);$waiting=(int)$q->fetchColumn();ensureSocial($claim->invoke($service,$tenant,$waiting,null)===null,'Unapproved target claimed');
    echo "OK: explicit project mappings, rollback, no-date immediate mode, project isolation, targeted queue, schedule/approval guards and processing protection. No Meta calls.\n";
}catch(Throwable$e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);$code=1;}
finally{
 if($db->inTransaction())$db->rollBack();
 foreach($publications as$id)$db->prepare('DELETE FROM social_publications WHERE id=:id')->execute(['id'=>$id]);
 foreach($connections as$id)$db->prepare('DELETE FROM social_connections WHERE id=:id')->execute(['id'=>$id]);
 foreach($projects as$id)$db->prepare('DELETE FROM projets WHERE id=:id')->execute(['id'=>$id]);
}
exit($code);
