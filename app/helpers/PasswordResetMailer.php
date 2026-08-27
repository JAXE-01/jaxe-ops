<?php
class PasswordResetMailer {
    public static function send(string $email,string $name,string $url): bool {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $subject='Réinitialisez votre mot de passe Strax';
        $message="Bonjour ".$name.",\n\nUne demande de réinitialisation a été reçue pour votre compte Strax. Ouvrez ce lien valable 30 minutes :\n".$url."\n\nSi vous n êtes pas à l origine de cette demande, ignorez ce message.";
        return StraxMailTransport::send([$email],$subject,$message,$url,'Choisir mon mot de passe');
    }
}
