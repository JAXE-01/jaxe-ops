<?php
class ContentCompletion {
 public static function requirements(array$d):array{
  $filled=static fn($v)=>trim((string)$v)!=='';
  return [
   ['label'=>'Objectif du mois','done'=>(int)($d['campagne_id']??0)>0||$filled($d['objectif_mois']??'')],
   ['label'=>'Dates clés','done'=>$filled($d['temps_forts_mois']??'')],
   ['label'=>'Sujet / angle','done'=>$filled($d['contenu_sujet']??$d['titre']??'')],
   ['label'=>'Objectif de publication','done'=>$filled($d['objectif_publication']??'')],
   ['label'=>'Message','done'=>$filled($d['contenu_message']??'')],
   ['label'=>'Cible','done'=>(int)($d['persona_id']??0)>0||$filled($d['cible_libre']??'')],
   ['label'=>'Réseau','done'=>$filled($d['reseau_cible']??$d['canal']??$d['canal_principal']??'')]
  ];
 }
}
