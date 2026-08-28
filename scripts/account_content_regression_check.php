<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
$db=Database::getConnection();$code=0;
function assertAC($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
 $user=$db->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);assertAC($user,'Admin local requis');$_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();$tenant=TenantGuard::tenantId();$org=OrganizationContext::forUser($user);$_SERVER['REQUEST_METHOD']='GET';
 $db->beginTransaction();
 $hash=password_hash('Test-only-Password-123',PASSWORD_DEFAULT);$q=$db->prepare("INSERT INTO users(nom,email,password,role,statut) VALUES('Regression user',:email,:hash,'Clientele','Actif')");$q->execute(['email'=>'test-'.bin2hex(random_bytes(7)).'@example.invalid','hash'=>$hash]);$id=(int)$db->lastInsertId();
 $q=$db->prepare("INSERT INTO tenant_memberships(tenant_id,organization_id,user_id,membership_role,status,joined_at) VALUES(:tenant,:org,:user,'Member','Suspendu',NOW())");$q->execute(['tenant'=>$tenant,'org'=>$org['id'],'user'=>$id]);$membership=(int)$db->lastInsertId();
 $model=new ManagedUserModel();assertAC($model->getById($id)!==null,'Suspended member not manageable');$model->update($id,['nom'=>'Changed','password'=>'']);$q=$db->prepare('SELECT password FROM users WHERE id=:id');$q->execute(['id'=>$id]);assertAC(hash_equals($hash,$q->fetchColumn()),'Blank password replaced existing hash');
 $team=new TeamInvitationService($user);$team->reactivate($membership);$q=$db->prepare('SELECT status FROM tenant_memberships WHERE id=:id');$q->execute(['id'=>$membership]);assertAC($q->fetchColumn()==='Actif','Reactivation failed');
 $rejected=false;try{$team->reactivate(PHP_INT_MAX);}catch(RuntimeException$e){$rejected=true;}assertAC($rejected,'Foreign membership accepted');
 $token=bin2hex(random_bytes(32));$q=$db->prepare("INSERT INTO team_invitation_tokens(membership_id,token_hash,expires_at,created_by) VALUES(:membership,:hash,DATE_ADD(NOW(),INTERVAL 1 HOUR),:creator)");$q->execute(['membership'=>$membership,'hash'=>hash('sha256',$token),'creator'=>$user['id']]);
 $team->suspend($membership);assertAC(TeamInvitationService::inspect($token)===null,'Suspended invitation still usable');
 $db->rollBack();
 $items=ContentCompletion::requirements(['objectif_mois'=>'x','temps_forts_mois'=>'x','contenu_sujet'=>'x','objectif_publication'=>'x','contenu_message'=>'x','cible_libre'=>'x','reseau_cible'=>'x']);assertAC(count(array_filter($items,fn($x)=>$x['done']))===7,'Completion calculator incorrect');
 $matrix=['product_list'=>['Audit','Photo'],'target_list'=>['SaaS','Restaurant'],'compatibility_json'=>json_encode(['Audit'=>['SaaS'],'Photo'=>['Restaurant']])];
 assertAC(!MatrixCompatibility::allowed($matrix,'Photo','SaaS'),'Invalid product/target accepted');$groups=MatrixCompatibility::pairs($matrix,'Cible',['SaaS']);assertAC(count($groups[0])===1&&$groups[0][0]['product']==='Audit','Generator included invalid pair');
 echo "OK: suspended member management, unchanged blank password, reactivation scope, completion and product/target rules. Transaction rolled back.\n";
}catch(Throwable$e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);$code=1;}finally{if($db->inTransaction())$db->rollBack();}exit($code);
