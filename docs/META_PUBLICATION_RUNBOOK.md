# Publication Meta — procédure de production

## Configuration requise

- Domaine de l’app Meta : `strax.jaxecommunication.com`
- URI OAuth exacte : `https://strax.jaxecommunication.com/index.php/social-oauth/callback`
- Permissions demandées : `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, `instagram_content_publish`
- La personne qui connecte une Page doit disposer de la tâche Facebook `CREATE_CONTENT`.
- Instagram doit être un compte professionnel lié à la Page Facebook sélectionnée.

Les utilisateurs ayant un rôle dans l’application Meta peuvent tester avant l’obtention de l’accès avancé. L’ouverture à tous les clients exige ensuite le Contrôle app et l’approbation des permissions par Meta.

## Déploiement

1. Déployer le code et conserver le `.env` de production hors de Git.
2. Exécuter `php scripts/database_deploy.php` puis vérifier qu’aucune migration n’est en attente.
3. Exécuter `php scripts/meta_readiness_check.php`.
4. Reconnecter les anciennes destinations : seules celles dont les permissions ont réellement été relues par Meta sont publiables.

## File programmée

Configurer dans cPanel une tâche cron chaque minute. Vérifier d’abord le chemin PHP avec `which php`, puis utiliser :

```cron
* * * * * /CHEMIN/VERS/php /home/c2268453c/public_html/strax/scripts/process_social_queue.php 20 >> /home/c2268453c/public_html/strax/storage/logs/social-queue.log 2>&1
```

## Recette sans risque

1. Créer une connexion Meta pour le client de test.
2. Dans l’écran OAuth, cocher uniquement la Page de test et son Instagram lié.
3. Créer une publication immédiate avec une image JPEG publique en HTTPS.
4. Envoyer en validation puis approuver.
5. Vérifier le lien distant, l’identifiant externe et l’historique d’exécution.
6. Pour Facebook, utiliser l’action de suppression distante si le test doit être retiré. Pour Instagram, supprimer manuellement depuis Instagram tant que l’API ne fournit pas cette opération dans le flux retenu.

## Diagnostic

- `scripts/meta_readiness_check.php` : configuration et volumes, sans afficher les secrets.
- `scripts/meta_publication_regression_check.php` : transitions de file en base, sans appel Meta.
- Une erreur Meta est conservée avec son code, sous-code et caractère temporaire pour rendre les reprises explicites.
