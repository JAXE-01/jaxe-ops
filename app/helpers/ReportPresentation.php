<?php
/** Shared allow-list for screen and export columns. */
class ReportPresentation {
    public static function models(): array {return ['publication'=>'Par publication','monthly'=>'Synthèse mensuelle','individual'=>'Historique des relevés'];}
    public static function tables(array $query): array {
        return is_array($query['tables']??null)?array_values(array_intersect(array_keys(self::models()),array_filter($query['tables'],'is_string'))):array_keys(self::models());
    }
    public static function sortRows(array $rows,string $model,array $filters): array {
        $key=explode(' ',self::sortSql($model,$filters))[0];
        $direction=($filters['direction']??'desc')==='asc'?1:-1;
        usort($rows,static function($a,$b)use($key,$direction){
            $left=$a[$key]??null;$right=$b[$key]??null;
            if($left===null||$right===null)return ($left===null)<=>($right===null);
            return ($left<=>$right)*$direction ?: (($b['id']??0)<=>($a['id']??0));
        });return $rows;
    }
    public static function fields(string $model): array {
        $kpi=['vues'=>'Vues','likes'=>'Réactions','commentaires'=>'Commentaires','partages'=>'Partages','clics'=>'Clics','impressions'=>'Impressions','couverture'=>'Portée'];
        if($model==='monthly') {
            $fields=['mois'=>'Mois','page_nom'=>'Page / compte','plateforme'=>'Réseau','publications'=>'Publications'];
            foreach($kpi as $key=>$label) $fields[$key.'_total']=$label;
            return $fields+['vues_moyenne'=>'Vues moy.','ctr_moyen'=>'CTR','engagement_rate_moyen'=>'Engagement'];
        }
        return ['date_publication'=>'Date','page_nom'=>'Page / compte',($model==='publication'?'publication':'publication_titre')=>'Publication','content_type'=>'Format','url_publication'=>'Lien']+$kpi+['ctr'.($model==='publication'?'_moyen':'')=>'CTR','engagement_rate'.($model==='publication'?'_moyen':'')=>'Engagement']+($model==='individual'?['date_collecte'=>'Relevé le']:[]);
    }
    public static function columns(string $model,array $query): array {
        $allowed=self::fields($model); $requested=$query['columns'][$model]??null;
        if(!is_array($requested)) return array_slice(array_keys($allowed),0,$model==='monthly'?9:10);
        return array_values(array_intersect(array_keys($allowed),array_filter($requested,'is_string'))) ?: [array_key_first($allowed)];
    }
    public static function type(string $value): string {
        return ['photo'=>'image','image'=>'image','video'=>'video','video_inline'=>'video','album'=>'carousel','carousel_album'=>'carousel','link'=>'link','share'=>'link','status'=>'text','text'=>'text'][$value]??'unknown';
    }
    public static function typeSql(): string {
        return 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(rm.kpi_payload),rm.kpi_payload,"{}"),"$._content_type")),"unknown")';
    }
    public static function label(string $key): string {
        foreach(['individual','publication','monthly'] as $model) if(isset(self::fields($model)[$key])) return self::fields($model)[$key];
        return ucfirst(str_replace('_',' ',$key));
    }
    public static function icon(string $key): string {
        return ['vues'=>'◉','likes'=>'♡','commentaires'=>'▤','partages'=>'↗','clics'=>'↖','url_publication'=>'↗'][str_replace('_total','',$key)]??self::label($key);
    }
    public static function url($value): string {
        $value=trim((string)$value);
        return filter_var($value,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true)?$value:'';
    }
    public static function value(array $row,string $key): string {
        $value=$row[$key]??null;
        if($key==='content_type') return ['image'=>'Image','video'=>'Vidéo','carousel'=>'Carrousel','link'=>'Lien','text'=>'Texte'][$value??'unknown']??'Non renseigné';
        if(in_array($key,['publication','publication_titre'],true)&&preg_match('/^Import (Facebook|Instagram)\s*[·-]/u',(string)$value)) return '↓ '.mb_strimwidth(trim((string)($row['publication_caption']??''))?:'Publication',0,95,'…');
        if($value===null||$value==='') return '—';
        if(in_array($key,['publication','publication_titre'],true)&&!empty($row['social_publication_id'])) return '↑ '.(string)$value;
        if($key==='date_publication') return date('d/m/Y',strtotime((string)$value));
        if(is_numeric($value)) return number_format((float)$value,str_contains($key,'ctr')||str_contains($key,'rate')||str_contains($key,'moyenne')?2:0,',',' ').(str_contains($key,'ctr')||str_contains($key,'rate')?' %':'');
        return (string)$value;
    }
    public static function cell(array $row,string $key): string {
        if($key==='url_publication') {
            $url=self::url($row[$key]??'');
            return $url?'<a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'" target="_blank" rel="noopener noreferrer" title="Ouvrir la publication" aria-label="Ouvrir la publication">↗</a>':'—';
        }
        return htmlspecialchars(self::value($row,$key),ENT_QUOTES,'UTF-8');
    }
    public static function sortSql(string $model,array $filters): string {
        $key=(string)($filters['sort']??'');
        if(!isset(self::fields($model)[$key])||$key==='url_publication') $key=$model==='monthly'?'mois':'date_publication';
        return $key.' IS NULL ASC, '.$key.(($filters['direction']??'desc')==='asc'?' ASC':' DESC');
    }
}
