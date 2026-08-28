# Fiche contenu unifiée — livraison et suite

## Livré dans ce lot
- Progression éditoriale visible en tête pour éditeur et lecteur, avec HTML de secours sans JavaScript.
- Références issues des matrices du client, sélection explicite si plusieurs matrices ; valeurs déjà enregistrées conservées.
- Affichage Essentiel / Tout afficher mémorisé dans le navigateur. Ce réglage ne change aucun droit ni statut.
- Compatibilités produit/cible dans la matrice, génération et affectation contrôlées.

## Prochaine intégration : même contenu, plusieurs modes de travail
1. Mode regroupé : calendrier + brief/script éditables dans un espace, via les services de sauvegarde existants. Sauvegardes partielles indépendantes, statut enregistré visible, aucun formulaire imbriqué.
2. Mode par étapes : conception, brief/script, production, validation responsable, validation client optionnelle pour agence, publication, résultats. Masquer les actions non autorisées côté interface ET les refuser côté serveur.
3. Configuration par entreprise/projet : présélections Solo et Équipe, regroupements d'affichage personnels. Le responsable configure les validations ; un utilisateur ne peut pas supprimer une validation pour contourner les droits. Appliquer les changements de workflow aux futurs contenus, sans effacer les tâches ou validations historiques.
4. Synchronisation réelle : relier la tâche Publication à un travail de file sociale et ses destinations, puis à l'identifiant distant. Approuvé/En file n'est pas Publié. Relier la collecte KPI au succès réel de chaque destination, avec relevés J+N idempotents. Ne pas marquer une publication terminée sur simple programmation.

Ces quatre intégrations ne sont pas encore livrées. La vue actuelle ne fusionne pas les formulaires calendrier et brief.

## Recette avant activation en production
- Vérifier mobile/desktop avec un éditeur, lecteur, responsable et client d'agence.
- Vérifier sauvegarde simultanée, erreurs réseau, perte de session, validations et absence de double publication.
- Contrôler direction@jaxe-tech.com via scripts/diagnose_direction_access.php sur le serveur (lecture seule).
- Sauvegarder la base et les fichiers, mettre à jour le code sans écraser .env/uploads, exécuter scripts/database_deploy.php puis --apply uniquement après contrôle du dry-run.
- Tester la migration sur une copie et conserver le journal ; ne pas réconcilier arbitrairement des empreintes.
- La connexion navigateur étant indisponible dans cette session, la recette visuelle et en ligne reste à faire.
