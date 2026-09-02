<?php
/** Familiar social UI symbols, drawn locally; no dependency on Unicode icon fonts. */
class ReportIcons {
    private const FILES=['vues'=>'views','likes'=>'reactions','commentaires'=>'comments','partages'=>'shares','clics'=>'clicks','url_publication'=>'external','sauvegardes'=>'saves'];
    public static function file(string $key): ?string {
        $name=self::FILES[str_replace('_total','',$key)]??null;
        return $name?dirname(__DIR__,2).'/public/assets/icons/kpi/'.$name.'.svg':null;
    }
    public static function web(string $key): string {
        $file=self::file($key);
        return $file?file_get_contents($file):htmlspecialchars(ReportPresentation::label($key),ENT_QUOTES,'UTF-8');
    }
    public static function pdf(string $key): string {
        $file=self::file($key);
        return $file?'<img src="'.htmlspecialchars(str_replace('\\','/',$file),ENT_QUOTES,'UTF-8').'" width="13" height="13" />':htmlspecialchars(ReportPresentation::label($key),ENT_QUOTES,'UTF-8');
    }
}
