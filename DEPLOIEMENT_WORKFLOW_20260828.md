# Livraison workflow compact — 28 août 2026

## Périmètre

Mois persistant, fiche compacte et brief intégré, calendrier des dates occupées,
cadence hebdomadaire et révisions futures conservatrices. Les contenus déjà
travaillés et les mois antérieurs sont préservés. Les exceptions ne sont pas supprimées.

Les tests locaux ne constituent pas une recette visuelle ni une recette de production.
La connexion Browser a échoué dans l'environnement Windows. La nomenclature
configurable et les suggestions de dates togolaises ne font pas partie de ce lot.

## Mise en production cPanel

1. Sauvegarder les fichiers et la base existants avec cPanel. Conserver le `.env`,
   les fichiers `config/instance*`, les uploads et les sauvegardes de production.
2. Dans Git Version Control, mettre à jour le dépôt depuis `origin/main`, puis
   déployer le commit de cette livraison. Si le répertoire servi est lui-même
   le dépôt Git, vérifier d'abord `git status --short`, puis utiliser uniquement
   `git pull --ff-only`. Ne pas forcer un dépôt modifié.
3. Dans le terminal, depuis `/home/c2268453c/public_html/strax` :

```sh
php scripts/database_deploy.php
```

La migration attendue pour ce lot est `20260828_017_editorial_cadence.sql`.
Si d'autres migrations ou une erreur de checksum apparaissent, arrêter et examiner.
Vérifier dans la base que le tenant historique `jaxe-ops` correspond bien à Jaxe :
la migration lui rattache les abonnements historiques dont le tenant est vide.
Ne jamais importer la base locale ni rejouer `install.sql`.

4. Appliquer après contrôle :

```sh
php scripts/database_deploy.php --apply
php scripts/database_deploy.php
```

Le script crée une sauvegarde avant les migrations en attente. Conserver le chemin
affiché. La seconde vérification doit annoncer zéro migration en attente.
L'application peut aussi lancer les migrations lors de sa première requête en
production : effectuer cette opération pendant une fenêtre sans utilisateurs.

## Recette obligatoire

- Connexion : retrouver les clients, projets et abonnements existants.
- Sélectionner un mois futur, naviguer entre calendrier, matrice, réalisations et publication.
- Ouvrir une fiche : progression, champs compacts, réseau et jours occupés.
- Charger et enregistrer son brief sans sortir de la fiche ; tester un compte en lecture seule.
- Sur un projet de test, modifier la cadence à partir d'un mois futur. Vérifier
  l'intégrité du mois courant et d'un contenu déjà rédigé, puis resynchroniser.
- Contrôler le rendu mobile, les fichiers et l'absence d'erreurs serveur.

Ne pas lancer les scripts de régression sur la base de production : ils sont
destinés à la base locale de développement. Aucune publication Meta n'est requise.

## Retour arrière

En cas de problème, arrêter les modifications utilisateurs et redéployer le code
précédent depuis une copie contrôlée. La migration est additive : ne pas supprimer
ses colonnes ni restaurer une ancienne base sans comparer les données créées depuis
la sauvegarde. Les règles et contenus existants doivent rester conservés.
