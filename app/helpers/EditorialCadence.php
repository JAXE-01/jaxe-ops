<?php
class EditorialCadence {
 public static function normalize(array $rows):array {
  if(count($rows)>20)throw new RuntimeException('Maximum 20 règles hebdomadaires.');
  $rules=[];
  foreach($rows as $r){
   if(trim((string)($r['label']??''))==='')continue;
   $day=(int)($r['day']??0);$every=(int)($r['every']??1);$phase=(int)($r['phase']??0);$type=(string)($r['type']??'');
   if($day<1||$day>7||!in_array($every,[1,2],true)||$phase<0||$phase>=$every||!in_array($type,['Video','Visuel'],true))throw new RuntimeException('Règle de publication invalide.');
   $rules[]=['day'=>$day,'every'=>$every,'phase'=>$phase,'type'=>$type,'label'=>mb_substr(trim($r['label']),0,160),'format'=>mb_substr(trim((string)($r['format']??'')),0,80)];
  }
  return $rules;
 }
 public static function dates(array $rules,string $start,string $end,string $month):array {
  $anchor=(new DateTimeImmutable($start))->modify('monday this week');
  $first=new DateTimeImmutable(substr($month,0,7).'-01');$last=$first->modify('last day of this month');$out=['Video'=>[],'Visuel'=>[]];
  for($date=$first;$date<=$last;$date=$date->modify('+1 day')){
   $iso=$date->format('Y-m-d');if($iso<$start||$iso>$end)continue;
   $weeks=(int)floor((int)$anchor->diff($date)->format('%r%a')/7);
   foreach($rules as$r)if((int)$date->format('N')===$r['day']&&$weeks%$r['every']===$r['phase'])$out[$r['type']][]=$r+['date'=>$iso];
  }
  return $out;
 }
 public static function save(PDO $db,int $id,array $data):void {
  if(!isset($data['cadence_present']))return;
  CadenceRevision::save($db,$id,$data,self::normalize((array)($data['cadence']??[])));
 }
}