<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/config/config.php';
$recipient=strtolower(trim((string)($argv[1]??'')));
$configured=[
    'host'=>trim((string)SMTP_HOST)!=='',
    'port'=>(int)SMTP_PORT,
    'secure'=>(string)SMTP_SECURE,
    'username'=>trim((string)SMTP_USERNAME)!=='',
    'password'=>trim((string)SMTP_PASSWORD)!=='',
    'from_valid'=>filter_var((string)MAIL_FROM_EMAIL,FILTER_VALIDATE_EMAIL)!==false,
];
echo json_encode(['smtp'=>$configured],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
if($recipient===''){echo "Configuration vérifiée. Pour tester la remise: php scripts/mail_delivery_check.php adresse@gmail.com\n";exit(0);}
if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Adresse destinataire invalide.\n");exit(2);}
$sent=StraxMailTransport::send([$recipient],'Test de distribution Strax','Ceci est un test de distribution SMTP. Aucun mot de passe ni accès à votre compte ne sera modifié.');
echo $sent?"SMTP_ACCEPTED: le serveur SMTP a accepté le message. Vérifiez réception et spam.\n":"SMTP_REJECTED: consultez le journal PHP/SMTP et les paramètres DNS SPF, DKIM et DMARC.\n";
exit($sent?0:1);
