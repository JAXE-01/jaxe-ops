<?php
class StraxMailTemplate {
    public static function render(string $title,string $message,?string $actionUrl=null,string $actionLabel='Ouvrir Strax'): string {
        $e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $button='';
        if($actionUrl&&filter_var($actionUrl,FILTER_VALIDATE_URL)&&strtolower((string)parse_url($actionUrl,PHP_URL_SCHEME))==='https'){
            $button='<table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#193653" style="border-radius:10px"><a href="'.$e($actionUrl).'" style="display:inline-block;padding:16px 24px;color:#ffffff;font-weight:bold;text-decoration:none">'.$e($actionLabel).'</a></td></tr></table>';
        }
        $paragraphs='';foreach(preg_split('/\r?\n\s*\r?\n/',$message) as $paragraph){$paragraphs.='<p style="margin:0 0 20px;line-height:1.7;overflow-wrap:anywhere;word-break:break-word">'.nl2br($e($paragraph)).'</p>';}
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($title).'</title></head><body style="margin:0;padding:0;background:#f3f6fa;color:#334155;font-family:Arial,Helvetica,sans-serif"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 12px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px"><tr><td style="padding:24px 28px;background:#10243c;border-radius:16px 16px 0 0"><span style="color:#ffffff;font-size:26px;font-weight:bold;letter-spacing:-1px">Strax<span style="color:#8ab7e1">.</span></span><div style="color:#c8d7e7;font-size:13px;margin-top:7px">Votre espace de collaboration éditoriale</div></td></tr><tr><td bgcolor="#ffffff" style="padding:32px 28px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 16px 16px"><h1 style="margin:0 0 24px;font-size:24px;line-height:1.3;color:#10243c">'.$e($title).'</h1><div style="font-size:15px">'.$paragraphs.'</div>'.$button.'</td></tr><tr><td style="padding:24px 24px 8px;text-align:center;font-size:12px;line-height:1.7;color:#64748b">Strax · Un message lié à votre compte ou à votre espace de travail.<br>Ne partagez jamais vos liens d’accès ou votre mot de passe.</td></tr></table></td></tr></table></body></html>';
    }
    public static function mime(string $message,string $html,string $boundary): string {
        $part=static fn($type,$body)=>'--'.$boundary."\r\nContent-Type: ".$type."; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
        return $part('text/plain',$message).$part('text/html',$html).'--'.$boundary."--\r\n";
    }
}
