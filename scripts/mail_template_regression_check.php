<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/helpers/StraxMailTemplate.php';
$message="Bonjour <script>alert(1)</script>\n\nVotre lien expire dans 30 minutes.";
$html=StraxMailTemplate::render('Accès <Strax>',$message,'https://example.com/reset?a=1&b=2','Choisir mon mot de passe');
$checks=[str_contains($html,'&lt;script&gt;'),!str_contains($html,'<script>'),str_contains($html,'https://example.com/reset?a=1&amp;b=2'),!str_contains(StraxMailTemplate::render('Test','Message','javascript:alert(1)'),'href=')];
$mime=StraxMailTemplate::mime($message,$html,'test_boundary');
$checks[]=str_contains($mime,'Content-Type: text/plain');$checks[]=str_contains($mime,'Content-Type: text/html');$checks[]=str_ends_with($mime,"--test_boundary--\r\n");
if(in_array(false,$checks,true)){fwrite(STDERR,"Mail template regression failed\n");exit(1);}echo "OK: HTML échappé, lien HTTPS, alternative texte/HTML et délimiteurs MIME.\n";
