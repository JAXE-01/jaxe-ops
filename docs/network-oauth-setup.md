# Connexions TikTok, LinkedIn et YouTube

Cette version relie les comptes autorisés. Elle ne collecte pas encore les publications ou statistiques de ces réseaux et ne publie pas de contenu. Meta reste inchangé.

## Configuration serveur

Conserver les six identifiants dans le `.env` serveur (voir `.env.example`). Une `APP_ENCRYPTION_KEY` stable est obligatoire : ne pas remplacer la clé existante, sous peine de rendre les anciens jetons illisibles. Aucun secret ne doit entrer dans Git ou dans une capture.

Enregistrer exactement ces URI dans les consoles développeur :

| Réseau | URI autorisée |
| --- | --- |
| TikTok | `https://strax.jaxecommunication.com/index.php/network-oauth/callback/tiktok` |
| LinkedIn | `https://strax.jaxecommunication.com/index.php/network-oauth/callback/linkedin` |
| YouTube | `https://strax.jaxecommunication.com/index.php/network-oauth/callback/youtube` |

Les variables facultatives `TIKTOK_REDIRECT_URI`, `LINKEDIN_REDIRECT_URI`, `YOUTUBE_REDIRECT_URI` permettent de changer ces URI pour un autre déploiement HTTPS. Ne pas utiliser un hôte différent de celui de la session Strax.

- TikTok : Login Kit Web et Display API ; `user.info.basic`, `video.list`. Sandbox ou application approuvée selon le compte utilisé.
- LinkedIn : produit **Sign In with LinkedIn using OpenID Connect**, scopes `openid profile`. Connexion du profil personnel uniquement, sans demande d’adresse e-mail. Les Pages et leurs Insights nécessiteront l’adaptateur Community Management et l’approbation correspondante : ces scopes ne donnent pas accès aux Pages.
- YouTube : YouTube Data API v3 et YouTube Analytics API, client OAuth Web, scopes `youtube.readonly` et `yt-analytics.readonly` (préfixe `https://www.googleapis.com/auth/`). En mode test, ajouter le compte Google aux utilisateurs test. Le consentement doit retourner une seule chaîne ; reconnecter chaque chaîne séparément.

## Utilisation et limites

Publication sociale → Destinations → « Connecter TikTok, LinkedIn ou YouTube » → client et libellé → Préparer → Connecter. Après consentement, le nom réel du compte est enregistré. Une connexion existante ne peut pas être remplacée par un autre compte et un compte déjà rattaché ne peut pas être transféré implicitement à un autre client.

Les jetons sont chiffrés. Le renouvellement est manuel via « Renouveler l’accès » quand un refresh token existe. LinkedIn peut ne pas en délivrer selon le produit : utiliser « Actualiser les droits ». Pas de tâche de renouvellement automatique dans ce lot. Les collecteurs devront utiliser ce service avant d’effectuer leurs appels.

## Vérification

`php scripts/test-network-oauth.php` : tests simulés des paramètres, scopes, état, identité et chiffrement.

`php scripts/test-network-oauth-storage.php` : sauvegarde, renouvellement, doublons, changement de client et isolation tenant ; exige une base MariaDB jetable via `STRAX_SQL_TEST_DSN`, utilise uniquement une table temporaire.

`php scripts/social-connectors-readiness.php` : présence des variables et URI, sans secrets ; ce diagnostic ne prouve pas la validité des identifiants.

Le test réel exige le déploiement, les URI enregistrées et le consentement du propriétaire. Vérifier le nom du compte et le retour dans Strax. Aucune validation réelle n’a été effectuée pendant le développement local.

Sources : [TikTok OAuth](https://developers.tiktok.com/doc/oauth-user-access-token-management/), [LinkedIn OIDC](https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2), [Google OAuth Web](https://developers.google.com/identity/protocols/oauth2/web-server).
