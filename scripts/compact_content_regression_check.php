<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/config.php';
function compactCheck($ok,$why){if(!$ok)throw new RuntimeException($why);}
$db=Database::getConnection();$user=$db->query("SELECT * FROM users WHERE role='Admin' AND statut='Actif' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$_SESSION['user']=$user;TenantContext::clear();
$model=new CalendrierModel();$workspace=$model->getContentWorkspace((int)($argv[1]??409),$user);compactCheck((bool)$workspace,'Local content fixture required');
$returnTo=route_url('/calendrier');$personaOptions=[];$campaignOptions=[];$briefEditUrl=route_url('/calendrier/task/1');
$monthStart=substr($workspace['periode_mois'],0,7).'-01';$scheduledPublicationDates=[['date_prevue'=>substr($monthStart,0,7).'-05','total'=>2]];
foreach([true,false] as $canEditContentSetup){
 ob_start();require dirname(__DIR__).'/app/views/calendrier/contenu.php';$html=ob_get_clean();
 compactCheck(str_contains($html,'<select name="reseau_cible"'),'Network dropdown absent');
 compactCheck(strpos($html,'name="temps_forts_mois"')<strpos($html,'name="contexte_mois"')&&strpos($html,'name="contexte_mois"')<strpos($html,'name="objectif_mois"'),'Monthly field order wrong');
 compactCheck(strpos($html,'id="inline-content-brief"')>strpos($html,'</form>'),'Brief nested in content form');
 compactCheck(str_contains($html,'class="date-occupancy"'),'Mini calendar absent');
 compactCheck($canEditContentSetup===str_contains($html,'data-select-content-date='),'Date shortcuts editable in reader mode');
 compactCheck(substr_count($html,'<form ')===substr_count($html,'</form>'),'Unbalanced forms');
}
echo "OK: compact HTML, monthly ordering, network dropdown, inline brief outside form, reader date controls. Visual layout not evaluated.\n";
