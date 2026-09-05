<?php
class EditorialCadence {
 public static function normalize(array $rows):array {
  if(count($rows)>20)throw new RuntimeException('Maximum 20 règles hebdomadaires.');
  $rules=[];
  foreach($rows as $r){
   if(array_key_exists('active',$r)&&empty($r['active']))continue;
   $day=(int)($r['day']??0);$frequency=(string)($r['frequency']??(((int)($r['every']??1)===2)?'biweekly':'weekly'));$type=(string)($r['type']??'');$time=trim((string)($r['time']??'09:00'));
   if($day<1||$day>7||!in_array($frequency,['weekly','biweekly','monthly'],true)||!in_array($type,['Video','Visuel'],true)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time))throw new RuntimeException('Créneau de publication invalide.');
   $phase=$frequency==='biweekly'?max(0,min(1,(int)($r['phase']??0))):0;
   $rules[]=['day'=>$day,'every'=>$frequency==='biweekly'?2:1,'phase'=>$phase,'frequency'=>$frequency,'time'=>$time,'type'=>$type,'label'=>mb_substr(trim((string)($r['label']??'')),0,160),'format'=>mb_substr(trim((string)($r['format']??'')),0,80)];
  }
  return $rules;
 }
 public static function dates(array $rules,string $start,string $end,string $month):array {
  $anchor=(new DateTimeImmutable($start))->modify('monday this week');
  $first=new DateTimeImmutable(substr($month,0,7).'-01');$last=$first->modify('last day of this month');$out=['Video'=>[],'Visuel'=>[]];
  for($date=$first;$date<=$last;$date=$date->modify('+1 day')){
   $iso=$date->format('Y-m-d');if($iso<$start||$iso>$end)continue;
   $weeks=(int)floor((int)$anchor->diff($date)->format('%r%a')/7);
   foreach($rules as$r){$frequency=(string)($r['frequency']??(((int)($r['every']??1)===2)?'biweekly':'weekly'));$matches=$frequency==='monthly'?((int)$date->format('j')<=7):($weeks%(int)($r['every']??1)===(int)($r['phase']??0));if((int)$date->format('N')===(int)$r['day']&&$matches)$out[$r['type']][]=$r+['date'=>$iso];}
  }
  return $out;
 }
 public static function save(PDO $db,int $id,array $data):void {
  if(!isset($data['cadence_present']))return;
  CadenceRevision::save($db,$id,$data,self::normalize((array)($data['cadence']??[])));
 }
}
