# Étape 1 — Contrôles et durcissement du socle

## Modifications locales

- Apache : interdiction du listing, des fichiers de configuration/SQL/journaux, des répertoires internes et des scripts exécutables dans les uploads. Les images publiques ne sont pas déplacées.
- Dompdf : passage de 2.0.8 à 3.1.6 et verrou Composer mis à jour. Audit Composer : aucune alerte connue au moment du contrôle.
- Tableau de bord opérationnel : filtre explicite sur l’entreprise active pour les tâches, corrections et projets affectés. Une affectation utilisateur seule ne suffit plus à faire remonter une tâche d’une autre entreprise.
- Diagnostic CLI `scripts/release_preflight.php` sans modification du schéma ni affichage de secrets.

## Vérifications réalisées

- Génération PDF en mémoire : UTF-8, tableau, A4 paysage. Ce test ne remplace pas une vérification visuelle de tous les exports métier.
- Requêtes du tableau de bord exécutées pour neuf profils existants ; contrôle de l’appartenance des tâches opérationnelles à l’entreprise active.
- HTTP HEAD local : accueil et favicon 200 ; `.env`, configuration et scripts 403.
- HTTP HEAD production : accueil et connexion 200 ; `.env` 403 ; `composer.json` 200. Aucun contenu de fichier sensible n’a été téléchargé.
- Diagnostic local : migrations à jour, clé de chiffrement renseignée, debug désactivé, SMTP chiffré. L’auto-synchronisation du schéma reste activée dans la configuration **locale** ; sa désactivation doit être confirmée en production.

## Déploiement après commit et push

Conserver une sauvegarde de la base, du `.env` et des uploads. Dans le répertoire Strax :

```bash
git pull --ff-only origin main
php composer.phar install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php scripts/release_preflight.php
```

Utiliser `composer install` si Composer est installé globalement et si le PHAR n’est pas présent. Ne pas exécuter `composer update` en production : respecter le verrou livré.

Ce lot n’ajoute aucune migration. Le diagnostic doit confirmer l’état des migrations antérieures et `AUTO_SYNC_SCHEMA=false` en production. Ne pas modifier la clé de chiffrement existante : les jetons déjà stockés en dépendent.

## Toujours requis avant ouverture générale

- Sauvegarde/restauration testée sur une copie isolée.
- Stockage privé des documents clients avec accès contrôlés ; le blocage d’exécution PHP des uploads ne rend pas les images privées.
- Tests réels en production des rôles, invitations, réinitialisations et publication sur la Page de test.
- Diagnostic des en-têtes Gmail pour SPF/DKIM/DMARC et délivrabilité.
- Audit exhaustif des autorisations au-delà du tableau de bord.

## Lot UX suivant

Navigation par intentions, accueil « Aujourd’hui », contexte conservé et lisibilité. Aucun de ces changements UX n’est présenté comme livré dans ce lot de sécurité.
