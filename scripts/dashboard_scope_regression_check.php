<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/config.php';
try{
 $pdo=Database::getConnection();$users=$pdo->query("SELECT u.* FROM users u WHERE u.statut='Actif' AND EXISTS(SELECT 1 FROM tenant_memberships tm WHERE tm.user_id=u.id AND tm.status='Actif')")->fetchAll(PDO::FETCH_ASSOC);$tested=0;
 foreach($users as$user){$_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();$tenant=TenantGuard::tenantId();$model=new DashboardModel();$model->getOverviewStats($_SESSION['user']);$model->getProjectsByType($_SESSION['user']);$model->getCurrentMonthPlans($_SESSION['user']);$rows=array_merge($model->getUpcomingDeadlines($_SESSION['user']),$model->getDelayedTasks($_SESSION['user']));$model->getPhilsFocus($_SESSION['user']);
 if(UserScope::isScopedOperationalUser($_SESSION['user']))foreach($rows as$row){$q=$pdo->prepare('SELECT c.tenant_id FROM taches_pipeline tp JOIN projets p ON p.id=tp.projet_id JOIN clients c ON c.id=p.client_id WHERE tp.id=:id');$q->execute(['id'=>$row['id']]);if((int)$q->fetchColumn()!==$tenant)throw new RuntimeException('Tâche hors entreprise active.');}
 $tested++;}
 if(!$tested)throw new RuntimeException('Aucun profil testé.');echo "OK: tableau de bord, $tested profils existants, aucun résultat opérationnel hors entreprise active.\n";
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
