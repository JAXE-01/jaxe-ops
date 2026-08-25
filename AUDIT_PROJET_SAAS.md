# Audit de Jaxe Ops — design, UX/UI, SaaS, intégrations et composition de contenu

Date : 24 août 2026  
Périmètre : code PHP/MVC, schéma MySQL, sécurité applicative, parcours, design system CSS et matrice `MATRICE_GENERATION_CONTENU_TPACK.xlsx`.

## Synthèse exécutive

Jaxe Ops est déjà un socle métier sérieux : clients, offres, personas, campagnes, contenus, briefs, projets, calendrier, pipeline, validation, reporting, comptes sociaux et rôles sont présents. Il ne faut pas repartir de zéro.

En revanche, l'application n'est pas encore un SaaS multi-tenant. Elle gère plusieurs clients dans une même instance d'agence, mais les utilisateurs et réglages restent globaux. Il manque une frontière d'organisation explicite et systématique dans la base, les requêtes, les autorisations, les fichiers et les intégrations.

La trajectoire recommandée est :

1. sécuriser et stabiliser le socle ;
2. introduire le multi-tenant et les comptes d'entreprise ;
3. refondre les parcours et le design autour d'un espace de travail par client ;
4. transformer publication et collecte en connecteurs asynchrones ;
5. ajouter un moteur de composition configurable inspiré de la matrice T-PACK.

## Constats prioritaires

### P0 — Bloquants avant mise en SaaS

1. **Absence de modèle tenant/organisation.** La table `users` ne porte ni `tenant_id` ni `organization_id`. Les données sont rattachées à `clients`, mais un client n'est pas une frontière de sécurité SaaS. Un administrateur ou une requête insuffisamment filtrée peut traverser toutes les données de l'instance.

2. **Protection CSRF absente.** Aucun mécanisme CSRF n'a été trouvé, alors que de nombreux formulaires modifient les données. Certaines suppressions utilisent encore des liens GET (`/.../delete/{id}`), ce qui est particulièrement risqué.

3. **Identifiant administrateur par défaut documenté et réinjecté par le schéma.** Le README publie le compte par défaut et `install.sql` met à jour son hash à chaque synchronisation. En production, cela peut rétablir un accès connu. Le bootstrap doit créer un premier administrateur via une procédure d'installation à usage unique.

4. **Synchronisation automatique du schéma au chargement HTTP.** `SchemaSynchronizer::syncIfNeeded()` est appelé sur chaque point d'entrée et `AUTO_SYNC_SCHEMA` vaut `true` par défaut. En SaaS, les migrations doivent être exécutées par le déploiement, avec verrou, journalisation, sauvegarde et rollback opérationnel.

5. **Fichiers clients sous le répertoire public.** Les médias et documents se trouvent dans `public/uploads`. Une URL devinable ou divulguée peut contourner les autorisations applicatives. Les originaux doivent être stockés hors webroot ou dans un stockage objet privé, puis servis via URL signée/contrôleur autorisé.

6. **Publication externe synchrone et non résiliente.** `ExternalPublicationService` effectue un `curl` direct avec timeout de 15 secondes. Il n'y a ni file de travaux, ni idempotence, ni retries, ni journal métier, ni circuit breaker, ni validation stricte des destinations webhook.

### P1 — Importants pour la qualité et l'exploitation

1. **Rôles sérialisés en texte.** `secondary_roles` est un CSV et certains scripts utilisent `LIKE '%Role%'`. Il faut des tables `roles`, `permissions`, `user_roles` et des rôles tenant-scoped.

2. **Autorisations dispersées.** Le socle `PermissionModel` est utile, mais le filtrage métier repose aussi sur `UserScope` et de nombreuses conditions SQL locales. La future isolation doit être centralisée et impossible à oublier.

3. **Architecture concentrée.** `CalendrierModel.php` contient plusieurs milliers de lignes et cumule lecture, workflow, publication, documents, KPI et permissions. Cela augmente le risque de régression et rend les connecteurs difficiles à tester.

4. **Couverture de tests limitée.** Les scripts présents sont surtout des contrôles d'intégrité liés à une base existante ; l'un d'eux écrit même une publication de test. Il manque une suite isolée et reproductible couvrant authentification, permissions, séparation des tenants, transitions et API.

5. **Dépendances minimales mais vieillissantes.** Composer ne déclare que Dompdf 2.x. Il faut contrôler les vulnérabilités, verrouiller la version PHP, ajouter analyse statique, tests et règles de qualité au pipeline CI.

6. **Secrets et jetons.** Le chiffrement AES-CBC + HMAC est raisonnable comme compatibilité locale, mais le fallback `change-me-in-production` et le retour du texte clair en cas d'échec de chiffrement ne conviennent pas à un SaaS. En production, l'application doit refuser de démarrer sans clé valide et utiliser un gestionnaire de secrets/KMS.

## Audit design et UX/UI

### Points positifs

- Un design system existe déjà via variables CSS, typographies, couleurs, états, navigation desktop/mobile et focus visibles.
- Le layout est responsive et prévoit navigation mobile, loader, toasts et raccourcis clavier.
- Les écrans couvrent largement le cycle opérationnel réel, ce qui fournit une bonne base de recherche UX.

### Problèmes observés dans le code

- La feuille CSS dépasse 2 000 lignes et mélange socle, composants, pages et correctifs successifs. Le coût de changement visuel est élevé.
- La navigation principale expose beaucoup de modules au même niveau. Elle reflète la base de données plus que les tâches quotidiennes.
- Plusieurs vues contiennent encore des styles inline, des routes absolues historiques et des formulaires spécifiques en parallèle du CRUD commun.
- La densité d'information descend parfois à `0.60rem–0.78rem`, trop petite pour une utilisation confortable et accessible.
- Le bandeau supérieur fixe, les nombreux pills et les effets translucides consomment de l'espace utile sans toujours renforcer la hiérarchie.
- Les libellés et accents sont parfois normalisés sans accents (`Parametres`, `Realisations`, etc.), ce qui réduit la perception de finition.
- Les confirmations destructives reposent surtout sur `confirm()` et ne donnent ni résumé de l'impact ni possibilité métier d'annulation.

### Nouvelle architecture d'information recommandée

La navigation doit suivre cinq intentions :

1. **Accueil** — ce qui nécessite mon attention, échéances, validations, incidents de publication ;
2. **Produire** — idées, planning, briefs, production, validation ;
3. **Publier** — calendrier éditorial, files de publication, erreurs et reprises ;
4. **Mesurer** — KPI, tendances, comparaison aux objectifs et recommandations ;
5. **Administrer** — entreprise, équipes, clients/marques, intégrations, facturation et sécurité.

Un sélecteur permanent `Entreprise > Marque/Client > Projet` doit remplacer les filtres dispersés. Les pages doivent conserver ce contexte dans l'URL et dans toutes les actions.

### Design system cible

- Tokens séparés : couleurs, typographie, espacements, rayons, ombres, z-index et breakpoints.
- Composants stables : bouton, champ, select, combobox, table, badge, card, tabs, drawer, modal, empty state, skeleton, toast et stepper.
- Taille minimale de texte de travail : 14 px ; corps recommandé : 15–16 px.
- Contraste WCAG AA, focus cohérent, zones tactiles de 44 px et navigation clavier complète.
- Une seule hiérarchie visuelle : fond neutre, cartes moins translucides, accent réservé aux actions et états.
- Tables transformées en vues métier : filtres enregistrés, colonnes configurables, actions en lot, pagination et états vides explicatifs.

## Architecture SaaS cible

### Modèle de données

Créer au minimum :

- `tenants` : compte SaaS et frontière de sécurité ;
- `organizations` : entreprise cliente, éventuellement égale au tenant dans l'offre simple ;
- `brands` ou `workspaces` : marque/client opérationnel ;
- `memberships` : lien user ↔ tenant/organisation, statut et rôle ;
- `roles`, `permissions`, `role_permissions`, `membership_roles` ;
- `subscriptions`, `plans`, `usage_counters`, `invoices` ou identifiants du prestataire de paiement ;
- `audit_logs` : acteur, tenant, action, ressource, avant/après, IP et date ;
- `oauth_connections`, `social_accounts`, `publication_jobs`, `publication_attempts`, `metric_sync_jobs`, `metric_snapshots`.

Toutes les tables métier doivent porter un `tenant_id` non nul. Les relations client/projet restent utiles, mais ne remplacent jamais le filtre tenant.

### Isolation

- Résoudre le tenant depuis la session et l'URL, jamais depuis un champ POST libre.
- Appliquer le tenant dans un repository/query builder central et dans les contraintes uniques.
- Vérifier les relations croisées : un `projet_id`, `client_id`, `persona_id` ou `social_account_id` doit appartenir au même tenant.
- Stockage de fichiers préfixé par tenant, accès privé, quotas et antivirus.
- Tests automatiques “tenant A ne voit/modifie jamais tenant B” sur chaque ressource.

### Comptes et rôles

- Super-admin plateforme séparé des admins d'entreprise.
- Invitations par email, expiration, acceptation et révocation.
- Rôles types : Owner, Admin, Account manager, Creator, Reviewer, Analyst, Client approver.
- Permissions personnalisables par entreprise si l'offre le prévoit.
- MFA pour les comptes privilégiés, récupération sécurisée et journal des sessions.

## Publication sociale et collecte de données

### Principe

Conserver une interface commune, mais développer un adaptateur par réseau : `MetaConnector`, `LinkedInConnector`, `TikTokConnector`, `YouTubeConnector`. Chaque adaptateur déclare ses capacités : texte, image, vidéo, carrousel, programmation native, limites, métriques et statut de traitement.

### Flux recommandé

1. L'utilisateur valide un contenu et ses variantes par réseau.
2. L'application crée un `publication_job` idempotent.
3. Un worker récupère le média privé, valide les contraintes du réseau et publie.
4. Chaque tentative est journalisée ; les erreurs temporaires sont rejouées avec backoff.
5. Un webhook réseau ou un poller met à jour l'identifiant externe et le statut final.
6. Des jobs séparés collectent les métriques selon les fenêtres autorisées par chaque API.

### Exigences

- OAuth 2.0 avec `state`/PKCE quand disponible ; chiffrement et rotation des jetons ; scopes minimaux.
- Files asynchrones, idempotency keys, dead-letter queue, reprises manuelles et alertes.
- Consentement, politique de conservation et suppression des connexions.
- Normalisation des métriques sans perdre le payload brut : impressions, reach, views, watch time, clicks, engagement, followers et conversions.
- Horodatage, fuseau du compte, devise, fraîcheur de la donnée et provenance visibles dans l'UI.
- Les webhooks génériques actuels peuvent rester comme connecteur d'automatisation, mais pas comme cœur de publication.

## Moteur de composition inspiré de la matrice T-PACK

### Ce que contient la matrice

La matrice combine six dimensions principales : **cible, objectif, problème/besoin, produit, format et appel à l'action**. Elle génère ensuite une phrase de brief, alimente une banque d'idées avec priorité/statut, puis sélectionne dix vidéos dans un plan mensuel équilibré par objectif.

Les référentiels ajoutent les plateformes. Les formats incluent notamment avant/après, démonstration, conseil rapide, storytelling, humour, témoignage, comparaison, test produit, erreur à éviter et coulisses.

### Fonctionnalité à intégrer

Créer un module **Studio d'idées** piloté par données :

- référentiels propres à chaque tenant : audiences, objectifs, tensions, offres/produits, formats, CTA, plateformes et séries ;
- générateur combinatoire avec règles de compatibilité et exclusions ;
- modèles de composition versionnés, par exemple `Cible × Problème × Preuve × Format × CTA` ;
- score par adéquation stratégique, nouveauté, effort, réutilisabilité et potentiel de conversion ;
- banque d'idées avec doublons détectés, priorité, statut, propriétaire et historique ;
- transformation en brief, contenu, livrable ou emplacement du calendrier en un clic ;
- plan mensuel avec quotas/pondérations par objectif et canal ;
- variantes par réseau : hook, longueur, ton, format, CTA et contraintes média ;
- boucle d'apprentissage : comparer la composition aux KPI réels sans laisser un score automatique remplacer la validation humaine.

### Modèle minimal

- `composition_dimensions`
- `composition_options`
- `composition_templates`
- `composition_template_dimensions`
- `composition_rules`
- `content_ideas`
- `content_idea_values`
- `content_variants`
- `content_scores`

Les valeurs doivent être configurables par tenant et reliées, quand pertinent, aux tables existantes `personas`, `offres`, `campagnes`, `contenus` et `plans_mensuels`.

## Roadmap recommandée

### Phase 0 — Sécurisation et cadrage (1 à 2 semaines)

- Supprimer identifiants par défaut, GET destructifs et fallback de clé.
- Ajouter CSRF, rate limiting login, politiques d'upload et en-têtes de sécurité.
- Désactiver la migration automatique en production.
- Mettre le projet sous Git/CI, figer l'environnement PHP et créer une base de test.
- Cartographier les parcours et définir les rôles SaaS.

### Phase 1 — Fondation multi-tenant (3 à 5 semaines)

- Migrations `tenants`, organisations/workspaces et memberships.
- Backfill de toutes les données existantes dans un tenant initial.
- Scoping central, contraintes croisées, audit logs et tests d'étanchéité.
- Invitations, changement de tenant, gestion des équipes et permissions.

### Phase 2 — Refonte UX/UI (3 à 5 semaines, parallélisable après le socle)

- Recherche rapide avec 5 à 8 utilisateurs représentatifs.
- Nouvelle architecture d'information et design system modulaire.
- Refonte prioritaire : onboarding, accueil, sélection du contexte, planning, tâche, validation et publication.
- Tests clavier/mobile, contrastes et tests d'utilisabilité.

### Phase 3 — Studio de composition (2 à 4 semaines)

- Référentiels tenant-scoped et import de la logique T-PACK.
- Générateur, banque d'idées, scoring et plan mensuel.
- Conversion idée → brief → calendrier et variantes réseau.

### Phase 4 — Connecteurs et analytics (4 à 8 semaines par vagues)

- Infrastructure OAuth, queue, workers, logs et modèle de métriques.
- Vague 1 : Meta (Facebook/Instagram) et LinkedIn.
- Vague 2 : TikTok et YouTube.
- Dashboard de fraîcheur, erreurs, reprises, coûts et performance.

### Phase 5 — Commercialisation SaaS (2 à 4 semaines)

- Plans, quotas, facturation, emails transactionnels et onboarding self-service.
- Sauvegardes/restauration, observabilité, alertes, politique de données et support.
- Revue sécurité, charge, disponibilité et procédure d'incident.

## Critères de réussite

- Aucun test de traversée inter-tenant ne réussit.
- 100 % des mutations sont protégées par autorisation + CSRF et aucune suppression n'utilise GET.
- Une publication ne bloque jamais une requête web et chaque tentative est traçable/rejouable.
- Un nouvel utilisateur peut créer son entreprise, inviter son équipe, connecter un réseau et planifier un contenu sans assistance.
- Le temps pour passer d'une idée à un contenu planifié diminue d'au moins 40 % lors des tests utilisateurs.
- Les données KPI affichent source, compte, période, fuseau et date de dernière synchronisation.

## Vérifications effectuées et limites

- Inventaire de l'architecture et lecture des composants centraux.
- Analyse du schéma, des rôles, permissions, comptes sociaux, publication, configuration et chiffrement.
- Validation syntaxique de tous les fichiers PHP hors dépendances : aucun échec.
- Analyse structurelle des trois feuilles de la matrice Excel et de ses formules.
- L'interface n'a pas pu être rendue dans le navigateur intégré à cause d'un blocage de l'isolation Windows. Les constats UX/UI sont donc fondés sur le code des vues, le layout et le CSS ; une revue visuelle et des tests utilisateurs restent indispensables avant de figer le nouveau design.
