<?php
class PdfReportText {
    public static function normalize(string $text): string {
        static $map=null,$coverage=null;
        if($map===null)$map=json_decode(file_get_contents(__DIR__.'/../resources/pdf-unicode-map.json'),true,512,JSON_THROW_ON_ERROR);
        if($coverage===null)$coverage=(static function(){require __DIR__.'/../third_party/tcpdf/fonts/dejavusans.php';return $cw;})();
        // Preserve wording; only normalize decorative alphabets and compatibility forms.
        $text=strtr($text,$map);
        $text=strtr($text,$map); // compose accents after styled-letter conversion
        $text=strtr($text,['🏗'=>'[chantier]','🚀'=>'[lancement]','🔥'=>'[feu]','💡'=>'[idée]','📞'=>'[téléphone]','📍'=>'[lieu]']);
        $text=str_replace(["\u{FE0F}","\u{FE0E}","\u{200D}","\u{2011}"],['','','','-'],$text);
        return preg_replace_callback('/./us',static function($m)use($coverage){
            $code=mb_ord($m[0],'UTF-8');
            if($code<32)return in_array($m[0],["\n","\t"],true)?' ':'';
            return isset($coverage[$code])?$m[0]:'[U+'.strtoupper(dechex($code)).']';
        },$text)??'';
    }
    public static function cell(array $row,string $key): string {
        if($key==='url_publication'){
            $url=ReportPresentation::url($row[$key]??'');
            return $url?'<a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.ReportIcons::pdf($key).'</a>':'-';
        }
        $value=self::normalize(ReportPresentation::value($row,$key));
        // Bound previews and explicitly wrap long unbroken names/URLs for TCPDF.
        if(in_array($key,['publication','publication_titre'],true))$value=mb_strimwidth($value,0,150,'…','UTF-8');
        $limit=in_array($key,['publication','publication_titre'],true)?26:16;
        $tokens=preg_split('/(\s+)/u',$value,-1,PREG_SPLIT_DELIM_CAPTURE);
        return implode('',array_map(static function($token)use($limit){
            $chunks=mb_str_split($token,$limit,'UTF-8');
            return implode('<br />',array_map(static fn($s)=>htmlspecialchars($s,ENT_QUOTES,'UTF-8'),$chunks));
        },$tokens));
    }
}
