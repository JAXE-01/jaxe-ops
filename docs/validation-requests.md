# Demandes groupées de validation

## Disponible

Bouton « Envoyer en validation » dans les pages de tâches Production, Montage et Validation interne.

- Production / Montage : rappel au responsable de chaque tâche de validation interne.
- Validation interne : rappel au responsable de chaque tâche de validation client.
- Périmètre : même projet et même calendrier mensuel que la page ouverte ; les profils opérationnels ne transmettent que leurs propres productions.
- Seules les étapes sources terminées et les validations ouvertes sont retenues. La transmission vers la validation client exige une décision interne « Valide ».
- Les dépendances réelles du pipeline sont respectées. Les contenus exclus, annulés, déjà validés ou encore bloqués sont ignorés.
- Un e-mail par destinataire avec les liens des tâches à traiter. Les tâches sans responsable actif ou sans e-mail valide et les échecs d’envoi sont signalés.
- L’historique est enregistré sur les tâches destinataires dans workflow_progress_reports. Le bouton peut servir à une nouvelle relance ; il ne modifie aucune décision de validation.
- Le transport SMTP existant doit être configuré. Aucun envoi réel pendant les tests.

Vérification : php scripts/validation_request_regression_check.php (SQLite en mémoire, transport simulé).

## Étape suivante demandée — à implémenter

- Rappel par e-mail toutes les 24 h, regroupé par utilisateur selon ses tâches en charge.
- Validation automatique des tâches de validation en attente depuis plus de 48 h.
- Liens de validation client partagés par e-mail, WhatsApp ou SMS.
- Prévoir les groupes WhatsApp et des rappels internes via Slack et SMS.

Pour la règle des 48 h, définir explicitement le point de départ (mise à disposition de la validation) et les étapes concernées avant activation. Les possibilités des fournisseurs WhatsApp, notamment les groupes, devront être vérifiées lors de l’intégration. Ces automatismes et canaux supplémentaires ne sont pas activés par ce changement.
