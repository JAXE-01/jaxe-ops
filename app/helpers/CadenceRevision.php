<?php
/** Append-only monthly cadence history. Existing work is never deleted. */
class CadenceRevision {
    public static function decode(?string $json): array {
        $value=json_decode((string)$json,true);
        if(!is_array($value))return ['base'=>[],'revisions'=>[]];
        if(isset($value['revisions']))return $value;
        return ['base'=>$value,'revisions'=>[]];
    }
    public static function rules(?string $json,string $month): array {
        $history=self::decode($json);$rules=$history['base'];
        $revisions=$history['revisions'];ksort($revisions);
        foreach($revisions as $effective=>$revision)if($effective<=substr($month,0,7))$rules=$revision['rules'];
        return $rules;
    }
    public static function latest(?string $json): array {return self::rules($json,'9999-12');}
    public static function hasHistory(PDO $db,int $projectId):bool {
        $q=$db->prepare('SELECT publication_rules FROM projets WHERE id=?');$q->execute([$projectId]);
        return !empty(self::decode((string)$q->fetchColumn())['revisions']);
    }
    public static function save(PDO $db,int $id,array $data,array $rules):void {
        if(!$db->inTransaction())throw new RuntimeException('La révision doit être enregistrée dans une transaction.');
        $q=$db->prepare('SELECT * FROM projets WHERE id=? FOR UPDATE');$q->execute([$id]);$project=$q->fetch(PDO::FETCH_ASSOC);
        if(!$project)throw new RuntimeException('Projet introuvable.');
        $raw=(string)($project['publication_rules']??'');
        // Même sans changement visuel des règles, rejouer la répartition permet
        // de réparer les calendriers historiques mal ordonnés.
        $q=$db->prepare('SELECT * FROM plans_mensuels WHERE projet_id=? ORDER BY periode_mois FOR UPDATE');$q->execute([$id]);$plans=$q->fetchAll(PDO::FETCH_ASSOC);
        if(!$plans){$db->prepare('UPDATE projets SET publication_rules=? WHERE id=?')->execute([$rules?json_encode($rules,JSON_UNESCAPED_UNICODE):null,$id]);return;}
        $effective=(string)($data['cadence_effective_month']??'');
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$effective)||$effective<date('Y-m'))throw new RuntimeException('Choisissez le mois courant ou un mois futur pour modifier la cadence.');
        if($effective<substr($project['date_debut'],0,7)||$effective>substr($project['date_fin'],0,7))throw new RuntimeException('Le mois de révision doit appartenir à la durée du projet.');
        if(!$rules)throw new RuntimeException('Conservez au moins un rendez-vous pour une révision de cadence.');
        if(empty($data['cadence_confirm_future']))throw new RuntimeException('Confirmez la révision des mois futurs et la conservation des contenus personnalisés.');
        $history=self::decode($raw);
        // La nouvelle règle devient la référence à partir du mois choisi.
        // Les révisions ultérieures sont remplacées afin d'éviter des calendriers contradictoires.
        foreach(array_keys($history['revisions']) as $month)if($month>=$effective)unset($history['revisions'][$month]);
        $stats=['moved'=>0,'preserved'=>0,'extra'=>0];
        // Les reports provenant d'anciens plans ne doivent pas polluer le nouveau mois.
        // On conserve uniquement ceux qui ont réellement été publiés.
        $carry=$db->prepare("SELECT li.* FROM livrable_items li JOIN plans_mensuels pm ON pm.id=li.plan_mensuel_id WHERE pm.projet_id=? AND pm.periode_mois<? AND li.date_prevue>=? AND COALESCE(li.statut,'') NOT IN ('Publie','Annule','Exclu') FOR UPDATE");
        $carry->execute([$id,$effective.'-01',$effective.'-01']);
        foreach($carry->fetchAll(PDO::FETCH_ASSOC)as$item){if(!self::isUntouched($db,$item,['date'=>$item['date_prevue'],'label'=>$item['titre'],'format'=>(string)$item['sous_type']],$project))continue;$db->prepare("UPDATE livrable_items SET statut='Annule' WHERE id=?")->execute([$item['id']]);$db->prepare("UPDATE taches_pipeline SET statut='Annulee' WHERE livrable_item_id=? AND statut<>'Terminee'")->execute([$item['id']]);$stats['extra']++;}
        foreach($plans as $plan){
            $month=substr($plan['periode_mois'],0,7);if($month<$effective)continue;
            $oldRules=self::rules($raw,$month);
            $oldSlots=$oldRules?EditorialCadence::dates($oldRules,$project['date_debut'],$project['date_fin'],$month):null;
            $newSlots=EditorialCadence::dates($rules,$project['date_debut'],$project['date_fin'],$month);
            $q=$db->prepare('SELECT * FROM livrable_items WHERE plan_mensuel_id=? ORDER BY type_livrable,numero_ordre FOR UPDATE');$q->execute([$plan['id']]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item){
                $type=$item['type_livrable'];$index=(int)$item['numero_ordre'];$slot=$newSlots[$type][$index-1]??null;
                if(!$slot){if(self::isUntouched($db,$item,['date'=>$item['date_prevue'],'label'=>$item['titre'],'format'=>(string)$item['sous_type']],$project)){$db->prepare("UPDATE livrable_items SET statut='Annule' WHERE id=?")->execute([$item['id']]);$db->prepare("UPDATE taches_pipeline SET statut='Annulee' WHERE livrable_item_id=? AND statut<>'Terminee'")->execute([$item['id']]);}$stats['extra']++;continue;}
                $old=$oldSlots[$type][$index-1]??null;
                if(!$old)$old=['date'=>$item['date_prevue'],'label'=>$item['titre'],'format'=>(string)$item['sous_type']];
                if(!self::isUntouched($db,$item,$old,$project)){$stats['preserved']++;continue;}
                $label=trim((string)($slot['label']??''));$title=$label!==''?$label:self::defaultTitle($type,$month,$index);
                $db->prepare('UPDATE livrable_items SET date_prevue=?,titre=?,sous_type=? WHERE id=?')->execute([$slot['date'],$title,$slot['format']?:null,$item['id']]);
                if($label!==''||!empty($slot['format']))$db->prepare("UPDATE contenus SET sujet=CASE WHEN ?<>'' THEN ? ELSE sujet END,sous_type=COALESCE(?,sous_type) WHERE livrable_item_id=?")->execute([$label,$label,$slot['format']?:null,$item['id']]);
                $delta=(int)(new DateTimeImmutable($old['date']))->diff(new DateTimeImmutable($slot['date']))->format('%r%a');
                $db->prepare('UPDATE taches_pipeline SET deadline=DATE_ADD(deadline, INTERVAL ? DAY) WHERE livrable_item_id=?')->execute([$delta,$item['id']]);
                $stats['moved']++;
            }
        }
        $history['events'][]=['effective_month'=>$effective,'rules'=>$rules,'at'=>date(DATE_ATOM),'user_id'=>(int)($_SESSION['user']['id']??0),'summary'=>$stats];
        $history['revisions'][$effective]=['rules'=>$rules,'at'=>date(DATE_ATOM),'user_id'=>(int)($_SESSION['user']['id']??0),'summary'=>$stats];
        $db->prepare('UPDATE projets SET publication_rules=? WHERE id=?')->execute([json_encode($history,JSON_UNESCAPED_UNICODE),$id]);
    }
    public static function alignUntouchedItem(PDO $db,int $itemId,array $slot,array $project): bool {
        $q=$db->prepare('SELECT * FROM livrable_items WHERE id=?');$q->execute([$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);
        if(!$item)return false;
        $old=['date'=>(string)$item['date_prevue'],'label'=>(string)$item['titre'],'format'=>(string)$item['sous_type']];
        if(!self::isUntouched($db,$item,$old,$project))return false;
        $newDate=(string)$slot['date'];$slotLabel=trim((string)($slot['label']??''));$newLabel=$slotLabel!==''?$slotLabel:self::defaultTitle((string)$item['type_livrable'],substr($newDate,0,7),(int)$item['numero_ordre']);$newFormat=(string)($slot['format']??'');
        if($old['date']===$newDate&&$old['label']===$newLabel&&$old['format']===$newFormat)return true;
        $db->prepare('UPDATE livrable_items SET date_prevue=?,titre=?,sous_type=? WHERE id=?')->execute([$newDate,$newLabel,$newFormat?:null,$itemId]);
        if($slotLabel!==''||$newFormat!=='')$db->prepare("UPDATE contenus SET sujet=CASE WHEN ?<>'' THEN ? ELSE sujet END,sous_type=COALESCE(?,sous_type) WHERE livrable_item_id=?")->execute([$slotLabel,$slotLabel,$newFormat?:null,$itemId]);
        $delta=(int)(new DateTimeImmutable($old['date']))->diff(new DateTimeImmutable($newDate))->format('%r%a');
        $db->prepare('UPDATE taches_pipeline SET deadline=DATE_ADD(deadline, INTERVAL ? DAY) WHERE livrable_item_id=?')->execute([$delta,$itemId]);
        return true;
    }
    private static function isUntouched(PDO $db,array $item,array $old,array $project):bool {
        // A cadence revision reschedules every item in scope, including work already
        // started. Only an effectively published item is immutable.
        if((string)($item['statut']??'')==='Publie')return false;
        $q=$db->prepare("SELECT 1 FROM contenus c JOIN social_publications sp ON sp.content_id=c.id JOIN social_publication_targets spt ON spt.publication_id=sp.id WHERE c.livrable_item_id=? AND spt.status='Published' LIMIT 1");
        $q->execute([(int)$item['id']]);
        if($q->fetchColumn())return false;
        $q=$db->prepare("SELECT 1 FROM contenus c JOIN calendrier_contenus cc ON cc.contenu_id=c.id WHERE c.livrable_item_id=? AND cc.statut='Publie' LIMIT 1");
        $q->execute([(int)$item['id']]);
        return !$q->fetchColumn();
    }
    private static function defaultTitle(string$type,string$month,int$index):string {
        $names=[1=>'Janvier',2=>'Fevrier',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Aout',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Decembre'];
        $number=(int)substr($month,5,2);return sprintf('%s %s #%d',$type,$names[$number]??$month,$index);
    }
}
