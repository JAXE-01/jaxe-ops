<?php
class EmailNotificationService {
    public static function sendAccountVerification($email, $company, $url) {
        $email=trim((string)$email);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $message="Bienvenue sur Strax.\n\nConfirmez le compte de ".(string)$company." en ouvrant ce lien valable 24 heures :\n".(string)$url."\n\nSi vous n etes pas a l origine de cette demande, ignorez ce message.";
        return StraxMailTransport::send([$email],'Confirmez votre compte Strax',$message,(string)$url,'Confirmer mon compte');
    }
    public static function sendTeamInvitation($email,$name,$organization,$url,$existing=false) {
        $message="Bonjour ".trim((string)$name).",\n\nVous êtes invité(e) à rejoindre ".trim((string)$organization)." sur Strax.\nOuvrez ce lien valable 48 heures :\n".(string)$url."\n\n".($existing?'Votre mot de passe actuel restera inchangé.':'Vous choisirez votre mot de passe pendant l activation.')."\n\nSi vous n attendiez pas cette invitation, ignorez ce message.";
        return StraxMailTransport::send([(string)$email],'Invitation à rejoindre '.trim((string)$organization).' sur Strax',$message,(string)$url,'Accepter l’invitation');
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
        return StraxMailTransport::send($recipients,(string)$subject,(string)$message);
    }
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
