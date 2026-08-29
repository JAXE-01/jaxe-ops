# Interface et validations — 29 août 2026

## Corrections locales

- Fiche contenu : actions compactes avec intitulés accessibles, métadonnées moins hautes.
- Script intégré : plan et texte côte à côte sur desktop, une colonne sur mobile.
- Tableau projet : colonne Type redondante masquée (groupes vidéo/visuel), date plus étroite, état de fiche empilé pour éviter les collisions.
- Contexte de production : titre de section dédoublonné, titre livrable non répété si identique, cartes vides masquées et panneaux moins hauts.

## Règles de validation

Paramètres → Types / workflow : validation interne et client par défaut de l’entreprise.
Création / modification du projet : héritage ou règles personnalisées.

Les règles sont stockées dans `app_settings`, avec clés isolées par entreprise, projet et contenu. Aucun changement de schéma requis. Chaque nouveau contenu conserve sa politique. Les contenus existants gardent leurs étapes historiques ; modifier les préférences ne les valide pas et ne supprime aucune tâche.

Les dépendances de publication et le calcul « prêt » utilisent les étapes requises. Les contrôles d’accès et l’approbation de publication sociale restent inchangés.

## Vérifications

- Test transactionnel des quatre combinaisons, vidéo et visuel : réussi, transaction annulée.
- Dépendances de publication et stabilité après changement de règles : réussies.
- Refus d’un projet introuvable : réussi.
- Régressions cadence future et rendu contenu : réussies.

## Limite de l’audit

Audit des captures et du code réalisé. L’audit visuel interactif complet n’est **pas validé** : le navigateur échoue au démarrage sur les permissions Windows (`apply deny-read ACLs`). Il reste à vérifier le rendu réel à 1440 px, 1024 px et mobile, les infobulles au clavier, les formulaires intégrés et la sélection des règles dans l’interface. Aucun déploiement effectué.
