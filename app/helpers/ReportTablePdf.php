<?php
class ReportTablePdf {
    public static function html(string $title,array $rows,array $columns): string {
        $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        // Keep a readable font: additional metrics go into another table, not narrower cells.
        $identity=array_values(array_intersect($columns,['date_publication','mois','page_nom','publication','publication_titre']));
        $metrics=array_values(array_diff($columns,$identity));
        $bands=array_chunk($metrics,max(1,10-count($identity))) ?: [[]];
        $html='<html><head><meta charset="UTF-8"><style>body{font-family:dejavusans;color:#23364d;font-size:9pt}h1{font-size:18pt;color:#183453}h2{font-size:11pt}th{background-color:#eaf0f5;color:#23364d;font-weight:bold}td{border-bottom:0.4pt solid #dce4ed}a{color:#246597;text-decoration:none}</style></head><body><h1>'.$esc($title).'</h1><p style="color:#687b90">STRAX / RAPPORT DE PERFORMANCE · '.count($rows).' ligne(s)<br>↓ Publication importée · — Donnée indisponible · ◉ Vues · ♡ Réactions · ▤ Commentaires · ↗ Partages / lien · ↖ Clics</p>';
        foreach($bands as $index=>$band) {
            $fields=array_merge($identity,$band);
            $weights=[];
            foreach($fields as $field) $weights[$field]=in_array($field,['publication','publication_titre'],true)?4:(in_array($field,['date_publication','date_collecte','page_nom'],true)?2:($field==='url_publication'?0.6:1.25));
            $total=array_sum($weights);
            if(count($bands)>1) $html.='<h2>Tableau '.($index+1).' / '.count($bands).'</h2>';
            $html.='<table width="100%" cellpadding="6" cellspacing="0"><thead><tr>';
            foreach($fields as $field) $html.='<th width="'.round($weights[$field]/$total*100,3).'%">'.$esc(ReportPresentation::icon($field)).'</th>';
            $html.='</tr></thead><tbody>';
            foreach($rows as $i=>$row) {
                $html.='<tr nobr="true" bgcolor="'.($i%2?'#f5f8fb':'#ffffff').'">';
                foreach($fields as $field) $html.='<td width="'.round($weights[$field]/$total*100,3).'%">'.ReportPresentation::cell($row,$field).'</td>';
                $html.='</tr>';
            }
            if(!$rows) $html.='<tr><td colspan="'.count($fields).'">Aucune donnée pour cette sélection.</td></tr>';
            $html.='</tbody></table><br>';
        }
        return $html.'</body></html>';
    }
}
