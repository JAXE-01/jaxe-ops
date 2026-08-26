<?php
class EmailNotificationService {
    public static function sendAccountVerification($email, $company, $url) {
        $email=trim((string)$email);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $message="Bienvenue sur Strax.\n\nConfirmez le compte de ".(string)$company." en ouvrant ce lien valable 24 heures :\n".(string)$url."\n\nSi vous n etes pas a l origine de cette demande, ignorez ce message.";
        return self::sendMail([$email],'Confirmez votre compte Strax',$message);
    }
    public static function sendTeamInvitation($email,$name,$organization,$url,$existing=false) {
        $message="Bonjour ".trim((string)$name).",\n\nVous êtes invité(e) à rejoindre ".trim((string)$organization)." sur Strax.\nOuvrez ce lien valable 48 heures :\n".(string)$url."\n\n".($existing?'Votre mot de passe actuel restera inchangé.':'Vous choisirez votre mot de passe pendant l activation.')."\n\nSi vous n attendiez pas cette invitation, ignorez ce message.";
        return self::sendMail([(string)$email],'Invitation à rejoindre '.trim((string)$organization).' sur Strax',$message);
    }
    public static function sendPublicValidationNotifications(array $context) {
        $clientEmail = trim((string) ($context['client_email'] ?? ''));
        $internalRecipients = self::parseRecipients((string) VALIDATION_NOTIFICATION_EMAILS);

        $subject = 'Validation client recue - ' . (string) ($context['project_name'] ?? 'Projet');
        $message = self::buildValidationMessage($context);

        $result = [
            'client_sent' => false,
            'internal_sent' => false,
        ];

        if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            $result['client_sent'] = self::sendMail([$clientEmail], $subject, $message);
        }

        if (!empty($internalRecipients)) {
            $result['internal_sent'] = self::sendMail($internalRecipients, $subject, $message);
        }

        return $result;
    }

    private static function parseRecipients($csv) {
        $parts = array_map('trim', explode(',', (string) $csv));
        $valid = [];
        foreach ($parts as $email) {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }
        return array_values(array_unique($valid));
    }

    private static function sendMail(array $recipients, $subject, $message) {
        $recipients=array_values(array_unique(array_filter($recipients,static fn($email)=>filter_var($email,FILTER_VALIDATE_EMAIL))));
        if(empty($recipients))return false;
        if(defined('SMTP_HOST')&&trim((string)SMTP_HOST)!=='')return self::sendSmtp($recipients,$subject,$message);
        $fromEmail=trim((string)MAIL_FROM_EMAIL);$fromName=trim((string)MAIL_FROM_NAME);
        $headers=['MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8'];
        if(filter_var($fromEmail,FILTER_VALIDATE_EMAIL)){$display=preg_replace('/[\r\n]+/',' ',($fromName!==''?$fromName:$fromEmail));$headers[]='From: '.$display.' <'.$fromEmail.'>';$headers[]='Reply-To: '.$fromEmail;}
        return @mail(implode(',',$recipients),preg_replace('/[\r\n]+/',' ',(string)$subject),(string)$message,implode("\r\n",$headers));
    }

    private static function sendSmtp(array $recipients,$subject,$message): bool {
        $host=trim((string)SMTP_HOST);$port=max(1,(int)SMTP_PORT);$secure=strtolower(trim((string)SMTP_SECURE));$transport=$secure==='ssl'?'ssl://':'';
        $socket=@stream_socket_client($transport.$host.':'.$port,$errno,$error,max(3,(int)SMTP_TIMEOUT),STREAM_CLIENT_CONNECT);
        if(!$socket)return false;stream_set_timeout($socket,max(3,(int)SMTP_TIMEOUT));
        try{
            if(!self::smtpExpect($socket,[220]))return false;
            $domain=(string)($_SERVER['SERVER_NAME']??'localhost');if(!self::smtpCommand($socket,'EHLO '.$domain,[250]))return false;
            if($secure==='tls'){if(!self::smtpCommand($socket,'STARTTLS',[220]))return false;if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))return false;if(!self::smtpCommand($socket,'EHLO '.$domain,[250]))return false;}
            $username=trim((string)SMTP_USERNAME);if($username!==''){if(!self::smtpCommand($socket,'AUTH LOGIN',[334])||!self::smtpCommand($socket,base64_encode($username),[334])||!self::smtpCommand($socket,base64_encode((string)SMTP_PASSWORD),[235]))return false;}
            $from=trim((string)MAIL_FROM_EMAIL);if(!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
            if(!self::smtpCommand($socket,'MAIL FROM:<'.$from.'>',[250]))return false;foreach($recipients as$recipient){if(!self::smtpCommand($socket,'RCPT TO:<'.$recipient.'>',[250,251]))return false;}
            if(!self::smtpCommand($socket,'DATA',[354]))return false;
            $name=preg_replace('/[\r\n]+/',' ',trim((string)MAIL_FROM_NAME));$safeSubject=preg_replace('/[\r\n]+/',' ',(string)$subject);
            $body="From: ".($name!==''?$name.' ':'').'<'.$from.">\r\nTo: ".implode(', ',$recipients)."\r\nSubject: ".$safeSubject."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\n.","\n..",str_replace(["\r\n","\r"],"\n",(string)$message))."\r\n.";
            if(!self::smtpCommand($socket,$body,[250]))return false;self::smtpCommand($socket,'QUIT',[221]);return true;
        }finally{if(is_resource($socket))fclose($socket);}
    }
    private static function smtpCommand($socket,string$command,array$codes): bool {fwrite($socket,$command."\r\n");return self::smtpExpect($socket,$codes);}
    private static function smtpExpect($socket,array$codes): bool {$response='';do{$line=fgets($socket,515);if($line===false)return false;$response.=$line;}while(isset($line[3])&&$line[3]==='-');return in_array((int)substr($response,0,3),$codes,true);}
    private static function buildValidationMessage(array $context) {
        $lines = [];
        $lines[] = 'Une validation client vient d etre soumise.';
        $lines[] = '';
        $lines[] = 'Client: ' . (string) ($context['client_name'] ?? 'N/A');
        $lines[] = 'Projet: ' . (string) ($context['project_name'] ?? 'N/A');
        $lines[] = 'Mois: ' . (string) ($context['period_label'] ?? 'N/A');
        $lines[] = 'Livrable: ' . (string) ($context['deliverable_title'] ?? 'N/A');
        $lines[] = 'Decision: ' . (string) ($context['decision'] ?? 'N/A');
        $lines[] = 'Commentaire: ' . (string) ($context['comment'] ?? '');
        $lines[] = 'Date: ' . date('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = 'Lien public: ' . (string) ($context['public_url'] ?? '');

        return implode("\n", $lines);
    }
}
