<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
$db=Database::getConnection();
$user=$db->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$user)throw new RuntimeException('Admin local requis');
$_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();
$model=new CalendrierModel();$workspace=$model->getContentWorkspace((int)($argv[1]??409),$user);
if(!$workspace)throw new RuntimeException('Contenu local inaccessible');
$returnTo=route_url('/calendrier');$campaignOptions=[];$personaOptions=[];
foreach([true,false]as$canEditContentSetup){
 ob_start();require dirname(__DIR__).'/app/views/calendrier/contenu.php';$html=ob_get_clean();
 if(substr_count($html,'data-content-completion aria-live')!==1)throw new RuntimeException('Progression absente ou dupliquée');
 if(strpos($html,'data-content-completion aria-live')>strpos($html,'content-general-panel'))throw new RuntimeException('Progression non visible en tête');
 if(str_contains($html,'Kraft bowl')||str_contains($html,'Composition TPACK'))throw new RuntimeException('Références codées en dur');
}
$context=ContentMatrixReferences::load((int)$workspace['client_id']);
foreach($context['matrices']as$m)if((int)$m['client_id']!==(int)$workspace['client_id']||(int)$m['tenant_id']!==TenantGuard::tenantId())throw new RuntimeException('Références hors périmètre');
echo "OK: HTML rendu, progression éditeur/lecteur en tête, références client isolées. Aucun test visuel navigateur effectué.\n";
