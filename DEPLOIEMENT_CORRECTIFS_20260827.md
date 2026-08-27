# Correctifs Meta, accès et e-mail — 27 août 2026

## Livraison locale (pas encore déployée)

- Correction du retour OAuth : `render()` remplace l’appel inexistant `view()`.
- Sélection explicite des Pages et comptes Instagram, puis des projets du client choisi avant OAuth.
- Enregistrement atomique de la sélection : une erreur annule tout le lot.
- Une destination déjà rattachée à un autre client n’est pas déplacée automatiquement.
- Sans projet coché, la destination reste utilisable dans tous les projets **de ce client** ; le formulaire le précise. Les restrictions explicites sont contrôlées à la création et à l’exécution.
- Reconnexion disponible pour modifier la sélection des projets ; les destinations précédemment connectées mais non cochées ne sont pas révoquées automatiquement.
- Textes adaptés indexés par destination, et non par réseau, pour distinguer plusieurs Pages Facebook.
- Séparation des permissions de création et d’approbation ; composition masquée en lecture seule.
- Déconnexion principale via formulaire POST protégé par CSRF, puis retour à la connexion.
- Vue `/platform` réservée à l’administrateur SaaS, avec liste des espaces et diagnostic des adhésions en lecture seule. Aucun changement du tenant actif pour consulter cette vue.
- Invitations d’organisation réellement envoyées ; une réinvitation ne peut plus déplacer ou désactiver une adhésion existante.
- Transport SMTP commun pour invitations, vérifications de compte et réinitialisations : TLS, en-têtes Date/Message-ID, encodage UTF-8 et diagnostics sans contenu des messages ni identifiants secrets.

## Déploiement

1. Sauvegarder la base et conserver le `.env` et les fichiers uploadés de production.
2. Déployer ensemble le code et la nouvelle migration `20260827_015_social_connection_projects.sql`.
3. Dans le répertoire Strax, exécuter `php scripts/database_deploy.php` pour examiner les migrations, puis `php scripts/database_deploy.php --apply` pour sauvegarder et appliquer. Ne modifier aucune ancienne migration et ne réconcilier aucune empreinte sans examen.
4. Reconnecter Meta, sélectionner des Pages/projets et vérifier le retour. Ne pas publier sur une Page réelle sans choix explicite.
5. Exécuter `php scripts/mail_delivery_check.php destinataire-confirmé@gmail.com`, puis vérifier la réception et les indésirables. Une réponse SMTP_ACCEPTED n’est pas une preuve de livraison en boîte de réception.

## Tests locaux

Les scripts de régression Meta créent puis suppriment leurs propres lignes de test. Ils n’appellent pas Meta et doivent être exécutés sur la base de développement, pas celle de production.

- `php scripts/meta_selection_regression_check.php`
- `php scripts/meta_publication_regression_check.php`
- `php scripts/access_ui_regression_check.php`

Un e-mail de diagnostic a été accepté par le SMTP pour l’adresse administrateur connue. Réception effective à confirmer.

## Reste à finaliser

- Gestion SaaS transverse des rôles/suspensions, distincte de la gestion d’équipe actuelle.
- Accès support temporaire aux espaces clients : motif, durée, journal d’activité, restrictions et sortie explicite. Pas d’usurpation silencieuse activée.
- Audit exhaustif de tous les écrans/profils et vérification visuelle navigateur.
- Diagnostic de délivrabilité Gmail depuis **le serveur de production**, notamment suivi d’envoi cPanel et signature DKIM. La configuration locale seule ne prouve pas celle de production.
- Sélection de plusieurs clients différents au cours d’une seule connexion OAuth : actuellement le client est choisi en amont, et on répète le parcours pour un autre client.
- Vérification HTTP en production et essai de publication sur la Page de test autorisée.
