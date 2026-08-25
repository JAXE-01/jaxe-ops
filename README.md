# Jaxe Ops

Application PHP MVC pour la gestion des clients, offres, personas, messages marketing, campagnes, contenus, briefs, calendrier editorial et reporting.

## Installation

1. Placer le projet dans le dossier web (ex: `htdocs/jaxe-ops`).
2. Copier [config/instance.example.php](config/instance.example.php) vers `config/instance.php`.
3. Renseigner les parametres DB dans `config/instance.php`.
4. Ouvrir l'application: le schema principal est synchronise automatiquement depuis [install.sql](install.sql).
5. Ajouter des scripts SQL dans [database/migrations](database/migrations) pour les evolutions incrementales de base.

## Deploiement fluide (ZIP, local vs online)

- `config/config.php` detecte automatiquement l'environnement (`local`/`production`) et charge un fichier d'instance local: `config/instance.php`.
- Un secret applicatif unique est genere automatiquement dans `config/instance.secrets.php` (si absent) et conserve les tokens chiffres stables entre mises a jour.
- Pour deployer une nouvelle version ZIP:
	1. Decompresser la nouvelle version.
	2. Conserver `config/instance.php` et `config/instance.secrets.php` du serveur.
	3. Ouvrir l'application: les migrations SQL non appliquees sont executees automatiquement.

Conseil: ne pas versionner `config/instance.php` ni `config/instance.secrets.php`.

## Mises a jour de base de donnees

- Le fichier [install.sql](install.sql) reste la base de reference initiale (suivi par checksum).
- Les updates de production doivent etre ajoutees dans [database/migrations](database/migrations), avec un nom ordonne, par exemple:
	- `20260416_001_add_index_tasks.sql`
	- `20260416_002_add_social_table.sql`
- Chaque migration est tracee dans la table `schema_migrations` pour eviter les re-executions.

## Compte par defaut

- Email: `admin@jaxe-ops.local`
- Mot de passe: `admin123`

## Modules inclus

- Clients
- Offres
- Personas
- Messages marketing
- Campagnes
- Tunnel de conversion
- Contenus
- Briefs
- Calendrier
- Reportings

## Architecture

- `app/core`: coeur MVC et CRUD generique
- `app/controllers`: controleurs des modules
- `app/models`: modeles specifiques et authentification
- `app/helpers`: registre des modules
- `app/views`: layout, dashboard, login, vues CRUD
- `public/assets`: styles

## Notes

- Les formulaires relationnels utilisent des listes deroulantes alimentees depuis les tables liees.
- Le socle fonctionne avec une authentification simple par session.
- Les anciens fichiers de vues specifiques sont conserves mais le projet utilise maintenant les vues CRUD communes.
- Le schema SQL se synchronise automatiquement au chargement si [install.sql](install.sql) change. Ce comportement est pilote par `AUTO_SYNC_SCHEMA` dans [config/config.php](config/config.php).
- Les sessions sont durcies avec cookies `HttpOnly`, `SameSite` et mode strict.
### Securite des migrations en production

- `install.sql` n'est jamais rejoue en production sur une base existante ; seules les nouvelles migrations sont appliquees.
- Une sauvegarde SQL est creee dans `storage/backups` avant toute migration en attente.
- Les migrations utilisent un verrou MySQL pour eviter deux executions simultanees.
- Une migration appliquee est immuable : un checksum modifie bloque le deploiement et exige une nouvelle migration corrective.
- Dry-run : `php scripts/database_deploy.php`.
- Application manuelle securisee : `php scripts/database_deploy.php --apply`.
- `DB_DUMP_BINARY` peut pointer vers `mysqldump`; un export PDO est utilise automatiquement en secours.
