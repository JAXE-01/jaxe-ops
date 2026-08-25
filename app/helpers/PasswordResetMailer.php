<?php
class PasswordResetMailer {
    public static function send(string $email,string $name,string $url): bool {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $subject='Réinitialisez votre mot de passe Strax';
        $message="Bonjour ".$name.",\n\nUne demande de réinitialisation a été reçue pour votre compte Strax. Ouvrez ce lien valable 30 minutes :\n".$url."\n\nSi vous n êtes pas à l origine de cette demande, ignorez ce message.";
        if(trim((string)SMTP_HOST)==='')return @mail($email,$subject,$message);
        $host=trim((string)SMTP_HOST);$port=max(1,(int)SMTP_PORT);$secure=strtolower(trim((string)SMTP_SECURE));$socket=@stream_socket_client(($secure==='ssl'?'ssl://':'').$host.':'.$port,$errno,$error,max(3,(int)SMTP_TIMEOUT),STREAM_CLIENT_CONNECT);if(!$socket)return false;stream_set_timeout($socket,max(3,(int)SMTP_TIMEOUT));
        try{if(!self::expect($socket,[220]))return false;$domain=(string)($_SERVER['SERVER_NAME']??'localhost');if(!self::command($socket,'EHLO '.$domain,[250]))return false;if($secure==='tls'){if(!self::command($socket,'STARTTLS',[220])||!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)||!self::command($socket,'EHLO '.$domain,[250]))return false;}if(trim((string)SMTP_USERNAME)!==''&&(!self::command($socket,'AUTH LOGIN',[334])||!self::command($socket,base64_encode((string)SMTP_USERNAME),[334])||!self::command($socket,base64_encode((string)SMTP_PASSWORD),[235])))return false;$from=trim((string)MAIL_FROM_EMAIL);if(!filter_var($from,FILTER_VALIDATE_EMAIL)||!self::command($socket,'MAIL FROM:<'.$from.'>',[250])||!self::command($socket,'RCPT TO:<'.$email.'>',[250,251])||!self::command($socket,'DATA',[354]))return false;$safeSubject=preg_replace('/[\r\n]+/',' ',$subject);$body='From: '.trim((string)MAIL_FROM_NAME).' <'.$from.">\r\nTo: <".$email.">\r\nSubject: ".$safeSubject."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$message."\r\n.";if(!self::command($socket,$body,[250]))return false;self::command($socket,'QUIT',[221]);return true;}finally{if(is_resource($socket))fclose($socket);}
    }
    private static function command($socket,string$command,array$codes): bool {fwrite($socket,$command."\r\n");return self::expect($socket,$codes);}
    private static function expect($socket,array$codes): bool {do{$line=fgets($socket,515);if($line===false)return false;}while(isset($line[3])&&$line[3]==='-');return in_array((int)substr($line,0,3),$codes,true);}
}
