<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
$db=Database::getConnection();$q=$db->prepare("SELECT id,email,role,statut,secondary_roles FROM users WHERE email LIKE :email");$q->execute(['email'=>trim((string)($argv[1]??'direction@jaxe-tech%'))]);
$accounts=$q->fetchAll(PDO::FETCH_ASSOC);if(!$accounts)echo "Aucun compte correspondant dans cette base. Aucun droit modifié.".PHP_EOL;foreach($accounts as$u){$m=$db->prepare('SELECT tm.tenant_id,tm.membership_role,tm.status,o.name FROM tenant_memberships tm LEFT JOIN organizations o ON o.id=tm.organization_id WHERE tm.user_id=:id');$m->execute(['id'=>$u['id']]);$u['memberships']=$m->fetchAll(PDO::FETCH_ASSOC);$u['users_manage']=(new PermissionModel())->userHasPermission($u,'users.manage');echo json_encode($u,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;}
