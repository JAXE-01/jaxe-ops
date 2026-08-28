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
        if(self::latest($raw)===$rules)return;
        $q=$db->prepare('SELECT * FROM plans_mensuels WHERE projet_id=? ORDER BY periode_mois FOR UPDATE');$q->execute([$id]);$plans=$q->fetchAll(PDO::FETCH_ASSOC);
        if(!$plans){$db->prepare('UPDATE projets SET publication_rules=? WHERE id=?')->execute([$rules?json_encode($rules,JSON_UNESCAPED_UNICODE):null,$id]);return;}
        $effective=(string)($data['cadence_effective_month']??'');
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$effective)||$effective<=date('Y-m'))throw new RuntimeException('Choisissez un mois strictement futur pour modifier une cadence existante.');
        if($effective<substr($project['date_debut'],0,7)||$effective>substr($project['date_fin'],0,7))throw new RuntimeException('Le mois de révision doit appartenir à la durée du projet.');
        if(!$rules)throw new RuntimeException('Conservez au moins un rendez-vous pour une révision de cadence.');
        if(empty($data['cadence_confirm_future']))throw new RuntimeException('Confirmez la révision des mois futurs et la conservation des contenus personnalisés.');
        $history=self::decode($raw);
        // A new revision must not silently supersede a separately scheduled revision.
        foreach(array_keys($history['revisions']) as $month)if($month>$effective)throw new RuntimeException('Une révision ultérieure existe déjà. Modifiez la dernière révision en premier.');
        $stats=['moved'=>0,'preserved'=>0,'extra'=>0];
        foreach($plans as $plan){
            $month=substr($plan['periode_mois'],0,7);if($month<$effective)continue;
            $oldRules=self::rules($raw,$month);
            $oldSlots=$oldRules?EditorialCadence::dates($oldRules,$project['date_debut'],$project['date_fin'],$month):null;
            $newSlots=EditorialCadence::dates($rules,$project['date_debut'],$project['date_fin'],$month);
            $q=$db->prepare('SELECT * FROM livrable_items WHERE plan_mensuel_id=? ORDER BY type_livrable,numero_ordre FOR UPDATE');$q->execute([$plan['id']]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item){
                $type=$item['type_livrable'];$index=(int)$item['numero_ordre'];$slot=$newSlots[$type][$index-1]??null;
                if(!$slot){$stats['extra']++;continue;}
                $old=$oldSlots[$type][$index-1]??null;
                if(!$old || !self::isUntouched($db,$item,$old,$project)){$stats['preserved']++;continue;}
                $db->prepare('UPDATE livrable_items SET date_prevue=?,titre=?,sous_type=? WHERE id=?')->execute([$slot['date'],$slot['label'],$slot['format']?:null,$item['id']]);
                $db->prepare('UPDATE contenus SET sujet=?,sous_type=? WHERE livrable_item_id=?')->execute([$slot['label'],$slot['format']?:null,$item['id']]);
                $delta=(int)(new DateTimeImmutable($old['date']))->diff(new DateTimeImmutable($slot['date']))->format('%r%a');
                $db->prepare('UPDATE taches_pipeline SET deadline=DATE_ADD(deadline, INTERVAL ? DAY) WHERE livrable_item_id=?')->execute([$delta,$item['id']]);
                $stats['moved']++;
            }
        }
        $history['events'][]=['effective_month'=>$effective,'rules'=>$rules,'at'=>date(DATE_ATOM),'user_id'=>(int)($_SESSION['user']['id']??0),'summary'=>$stats];
        $history['revisions'][$effective]=['rules'=>$rules,'at'=>date(DATE_ATOM),'user_id'=>(int)($_SESSION['user']['id']??0),'summary'=>$stats];
        $db->prepare('UPDATE projets SET publication_rules=? WHERE id=?')->execute([json_encode($history,JSON_UNESCAPED_UNICODE),$id]);
    }
    private static function isUntouched(PDO $db,array $item,array $old,array $project):bool {
        if($item['statut']!=='Planifie'||$item['date_prevue']!==$old['date']||$item['titre']!==$old['label']||(string)$item['sous_type']!==$old['format']||(string)$item['canal']!==(string)$project['canal_principal'])return false;
        if(!in_array(trim((string)$item['pieces_jointes']),['','[]','null'],true)||(int)$item['nombre_pages']>1)return false;
        $q=$db->prepare('SELECT * FROM contenus WHERE livrable_item_id=? FOR UPDATE');$q->execute([$item['id']]);$content=$q->fetch(PDO::FETCH_ASSOC);
        if(!$content||$content['sujet']!==$old['label']||$content['statut']!=='Strategique defini'||!empty($content['persona_id']))return false;
        if((string)$content['sous_type']!==$old['format']||(int)$content['nombre_pages_carrousel']>1)return false;
        foreach(['message','objectif_publication','cible_libre','responsable'] as $key)if(trim((string)$content[$key])!=='')return false;
        if((string)$content['reseau_cible']!==(string)$project['canal_principal'])return false;
        $q=$db->prepare('SELECT * FROM taches_pipeline WHERE livrable_item_id=? FOR UPDATE');$q->execute([$item['id']]);$tasks=$q->fetchAll(PDO::FETCH_ASSOC);
        $expectedNotes=[
            'Script'=>'Rediger le script, l intention editoriale et les indications de tournage.',
            'Brief'=>'Produire le brief detaille du visuel, ses formats et ses livrables attendus.',
            'Tournage'=>'Realiser la captation selon le script valide.',
            'Montage'=>'Monter la video, integrer habillage et version finale pour validation.',
            'Production'=>'Produire le visuel et preparer exports ainsi que source PSD/PSB si necessaire.',
            'Validation interne'=>$item['type_livrable']==='Video'?'Verifier script, montage, habillage et conformite avant envoi client.':'Verifier la coherence strategique, le branding et la qualite avant envoi client.',
            'Validation client'=>'Envoyer au client, recueillir les retours et valider la version finale.',
            'Publication'=>'Publier le contenu selon le calendrier valide.',
            'Collecte KPI'=>'Collecter les performances 14 jours apres la publication.'
        ];
        $taskOffsets=['Script'=>-6,'Brief'=>-6,'Tournage'=>-5,'Montage'=>-3,'Production'=>-3,'Validation interne'=>-2,'Validation client'=>-1,'Publication'=>0,'Collecte KPI'=>14];
        foreach($tasks as $task){
            if(!isset($taskOffsets[$task['type_tache']]) || $task['deadline']!==(new DateTimeImmutable($old['date']))->modify(sprintf('%+d days',$taskOffsets[$task['type_tache']]))->format('Y-m-d'))return false;
            if((string)$task['notes']!==($expectedNotes[$task['type_tache']]??null))return false;
            if(!in_array($task['statut'],['A faire','Bloquee'],true)||!in_array($task['validation_decision'],[null,'','En attente'],true)||$task['note_sur_10']!==null||trim((string)$task['validation_commentaire'])!==''||!empty(json_decode((string)$task['fichiers_livres'],true))||!empty(json_decode((string)$task['publication_reseaux'],true)))return false;
        }
        // Any linked brief, matrix idea, validation, publication or result protects the item.
        $columns=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('livrable_item_id','synced_deliverable_id','contenu_id','content_id','task_id','tache_id')")->fetchAll(PDO::FETCH_ASSOC);
        foreach($columns as $column){
            $table=$column['TABLE_NAME'];$key=$column['COLUMN_NAME'];
            if(in_array($table,['contenus','taches_pipeline'],true)||!preg_match('/^[a-zA-Z0-9_]+$/',$table))continue;
            $ids=in_array($key,['task_id','tache_id'],true)?array_column($tasks,'id'):[in_array($key,['contenu_id','content_id'],true)?$content['id']:$item['id']];
            if(!$ids)continue;
            $q=$db->prepare('SELECT 1 FROM `'.$table.'` WHERE `'.$key.'` IN ('.implode(',',array_fill(0,count($ids),'?')).') LIMIT 1');$q->execute($ids);if($q->fetchColumn())return false;
        }
        return true;
    }
}
