# Publication : destinations, projets et file

## Livraison locale

- Destinations regroupées par client, repliées et recherchables ; placeholders révoqués masqués.
- Sélection OAuth : pages déjà associées au client masquées par défaut, accessibles pour actualiser les droits ; pages d’un autre client non proposées et refusées côté serveur.
- Création/modification de projet : cases à cocher pour ses pages, filtrées sur le client. Associations enregistrées dans la transaction du projet.
- Composition : uniquement les pages explicitement associées au projet et à son client. Contrôle identique côté serveur et dans le worker.
- Diffusion immédiate : champ date masqué, désactivé et vidé ; aucune date demandée.
- File : contenu/adaptations consultables, prochaine tentative, erreur et statut ; exécution ciblée des tâches dues, reprise des échecs. Aucun renvoi manuel d’une tâche déjà en traitement ou publiée.
- L’approbation traite uniquement la publication concernée, pas les autres publications du tenant.
- Correction du contexte tenant hérité du client lors de l’édition des projets.

## Déploiement et données existantes

Aucune nouvelle migration SQL. La table `social_connection_projects` doit déjà exister (migration 20260827_015).
Les anciennes pages non associées à un projet ne sont plus autorisées implicitement pour tous les projets. Après déploiement, ouvrir chaque projet et cocher ses pages. Les associations existantes sont conservées ; aucune réaffectation de client n’est devinée à partir du nom de la page.

Les changements de ce lot sont locaux, non déployés. Le lot de sécurité précédent comprend aussi une mise à jour Composer : suivre `ETAPE_1_SOCLE_20260827.md` pour une livraison commune.

## Vérifications

- `php scripts/social_context_regression_check.php` : associations et rollback, mauvais projet, immédiat sans date, sélection ciblée, isolation tenant, validation/date, protection Processing. Données temporaires supprimées, aucun appel Meta.
- `php scripts/meta_publication_regression_check.php` : cycle brouillon/validation/file/annulation.
- `php scripts/meta_selection_regression_check.php` : rendu OAuth sans jeton exposé et validation projet.
- `node scripts/social_context_ui_check.cjs` : logique du formulaire sur DOM simulé (pas une vérification visuelle).

Le navigateur de test n’a pas démarré (erreur d’environnement). Vérifier visuellement desktop/mobile après livraison. Les droits Meta et le fonctionnement du worker planifié en production ne sont pas certifiés par ces tests locaux.
