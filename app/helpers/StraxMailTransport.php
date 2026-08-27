<?php
/** SMTP transport shared by invitations, verification and password recovery. */
class StraxMailTransport {
    public static function send(array $recipients,string $subject,string $message,?string $actionUrl=null,string $actionLabel='Ouvrir Strax'): bool {
        $socket=null;$stage='configuration';$reference=bin2hex(random_bytes(8));
        try {
            $recipients=array_values(array_unique(array_filter(array_map('trim',$recipients),static fn($v)=>filter_var($v,FILTER_VALIDATE_EMAIL))));
            $from=trim((string)MAIL_FROM_EMAIL);
            if(!$recipients||!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('invalid_address');
            $domain=substr(strrchr($from,'@'),1);
            $encoded=static fn($v)=>'=?UTF-8?B?'.base64_encode(preg_replace('/[\r\n]+/',' ',(string)$v)).'?=';
            $boundary='strax_'.bin2hex(random_bytes(16));
            $headers=['Date: '.date(DATE_RFC2822),'Message-ID: <'.$reference.'@'.$domain.'>','From: '.$encoded(MAIL_FROM_NAME).' <'.$from.'>','To: '.implode(', ',$recipients),'Subject: '.$encoded($subject),'MIME-Version: 1.0','Content-Type: multipart/alternative; boundary="'.$boundary.'"'];
            $payload=implode("\r\n",$headers)."\r\n\r\n".StraxMailTemplate::mime($message,StraxMailTemplate::render($subject,$message,$actionUrl,$actionLabel),$boundary);
            if(trim((string)SMTP_HOST)==='')throw new RuntimeException('smtp_not_configured');
            $secure=strtolower(trim((string)SMTP_SECURE));
            if(!in_array($secure,['tls','ssl'],true))throw new RuntimeException('encrypted_smtp_required');
            $stage='connection';$timeout=max(3,(int)SMTP_TIMEOUT);
            $socket=@stream_socket_client(($secure==='ssl'?'ssl://':'').trim((string)SMTP_HOST).':'.(int)SMTP_PORT,$errno,$error,$timeout,STREAM_CLIENT_CONNECT);
            if(!$socket)throw new RuntimeException('connection_failed');stream_set_timeout($socket,$timeout);
            self::expect($socket,[220]);$stage='ehlo';self::command($socket,'EHLO '.$domain,[250]);
            if($secure==='tls'){$stage='tls';self::command($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('tls_failed');self::command($socket,'EHLO '.$domain,[250]);}
            if(trim((string)SMTP_USERNAME)!==''){$stage='authentication';self::command($socket,'AUTH LOGIN',[334]);self::command($socket,base64_encode(SMTP_USERNAME),[334]);self::command($socket,base64_encode(SMTP_PASSWORD),[235]);}
            $stage='sender';self::command($socket,'MAIL FROM:<'.$from.'>',[250]);
            $stage='recipient';foreach($recipients as$recipient)self::command($socket,'RCPT TO:<'.$recipient.'>',[250,251]);
            $stage='message';self::command($socket,'DATA',[354]);self::command($socket,$payload.'.',[250]);
            error_log('[strax-mail] reference='.$reference.' result=smtp_accepted');
            // Acceptance is final even if the server disconnects during QUIT.
            try{self::command($socket,'QUIT',[221]);}catch(Throwable $ignored){}
            return true;
        }catch(Throwable $e){error_log('[strax-mail] reference='.$reference.' result=failed stage='.$stage.' reason='.$e->getMessage());return false;}
        finally{if(is_resource($socket))fclose($socket);}
    }
    private static function command($socket,string $command,array $codes): void {
        $bytes=$command."\r\n";$offset=0;
        while($offset<strlen($bytes)){$written=fwrite($socket,substr($bytes,$offset));if(!$written)throw new RuntimeException('write_failed');$offset+=$written;}
        self::expect($socket,$codes);
    }
    private static function expect($socket,array $codes): void {
        for($i=0;$i<100;$i++){$line=fgets($socket,4096);if($line===false)throw new RuntimeException('response_timeout_or_closed');if(isset($line[3])&&$line[3]==='-')continue;$code=(int)substr($line,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('smtp_'.$code);return;}
        throw new RuntimeException('response_too_long');
    }
}
