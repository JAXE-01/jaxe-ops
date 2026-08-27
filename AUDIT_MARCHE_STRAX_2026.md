# Audit produit et veille concurrentielle — Strax

**Date :** 26 août 2026  
**Périmètre :** dépôt `jaxe-ops`, marché ivoirien/francophone et concurrence internationale.  
**Hypothèse géographique :** « national » désigne la Côte d’Ivoire, compte tenu du contexte du projet et des acteurs identifiés.  

## Verdict exécutif

Strax n’est plus un simple calendrier éditorial. Le produit possède déjà les briques d’un **système d’exploitation de la production de contenu** : stratégie, matrice d’idées, clients et projets, production, validation, calendrier, publication multicanale et KPI.

Le marché est cependant encombré. À l’international, Planable, Agorapulse, Hootsuite, Swello, Kontentino, Buffer, Sendible et SocialPilot couvrent déjà très bien planification, collaboration, publication et analyse. En Côte d’Ivoire, SocialBoost CI se présente déjà comme une plateforme de gestion de contenus, validations et publications ; Postvoro et Polpo affichent aussi des promesses très proches.

La proposition « tout-en-un pour gérer les réseaux sociaux » n’est donc **ni unique ni défendable**.

L’avantage critique de Strax doit devenir :

> **Le premier Content Operations OS francophone qui transforme la stratégie d’une marque en portefeuille de contenus traçables, fait collaborer client et agence sans transférer la propriété des données, puis apprend des résultats pour recommander le prochain cycle éditorial.**

Autrement dit, Strax doit gagner **avant la publication** — là où se prennent les décisions — puis fermer la boucle après publication. Il ne faut pas essayer de battre immédiatement Hootsuite ou Agorapulse sur le nombre de connecteurs et la profondeur de leur inbox.

## État actuel du produit

### Forces vérifiées dans le dépôt

- Couverture fonctionnelle étendue : clients, offres, personas, campagnes, briefs, projets, tâches, calendrier, livrables, validations, reporting et publication.
- Fondation SaaS récente : tenants, organisations, memberships, relations agence-client et autorisations révocables.
- Test d’isolation exécuté avec succès : résolution du tenant, accès au client propre, refus d’un client étranger, refus inter-utilisateur et backfill complet.
- Protection CSRF centralisée dans le contrôleur de base.
- Studio de matrice : dimensions configurables, banque d’idées, validation et synchronisation vers le calendrier.
- Publication multicanale : comptes sociaux, OAuth Meta, file de publication, tentatives et journal d’événements.
- Inscription publique, vérification d’email et récupération de mot de passe.
- 144 fichiers PHP vérifiés : **0 erreur de syntaxe**.
- Déploiement de base plus robuste qu’à l’origine : migrations ordonnées, checksums, verrouillage et sauvegarde.

### Faiblesses techniques et produit

#### Critiques avant commercialisation large

1. **Le schéma peut encore se synchroniser automatiquement par défaut.** `AUTO_SYNC_SCHEMA` reste à `true` si la configuration ne le surcharge pas. En production, il doit être explicitement désactivé et les migrations exécutées uniquement par le pipeline de déploiement.
2. **Des fichiers clients existent encore sous `public/uploads`.** Même si un contrôleur sécurisé protège certains aperçus, les originaux placés sous le webroot restent une surface de fuite. Il faut migrer vers un stockage privé, avec téléchargement autorisé et URLs signées temporaires.
3. **Couverture de tests insuffisante.** Le test d’isolation de base passe, mais il n’existe pas encore de suite automatisée systématique couvrant chaque CRUD, chaque rôle, chaque relation croisée et chaque transition de publication.
4. **Dépendances et qualité non industrialisées.** `composer.json` ne déclare que Dompdf 2.x ; aucune version PHP, aucun framework de tests, aucune analyse statique et aucun contrôle automatique de vulnérabilités ne sont déclarés.
5. **Architecture très concentrée.** `CalendrierModel.php` pèse environ 153 Ko, `task.php` 95 Ko et `CalendrierController.php` 83 Ko. Ces composants deviennent des points de régression et ralentiront l’ajout de connecteurs et de workflows.
6. **Le périmètre de publication est encore étroit.** Meta est amorcé, mais l’avantage concurrentiel exige au minimum LinkedIn, TikTok et YouTube, avec gestion réelle des capacités, erreurs, webhooks, renouvellement des jetons et métriques.

#### Faiblesses commerciales

- Pas de page de prix ni de packaging clair dans le produit public.
- Pas de preuve chiffrée : temps gagné, délai de validation, baisse des retards, volume publié, ROI ou témoignages.
- Promesse publique trop générique ; plusieurs concurrents emploient déjà les mêmes termes.
- La matrice est prometteuse, mais ressemble encore à un formulaire combinatoire. Elle n’apprend pas encore des performances et ne constitue donc pas encore un véritable moat.
- Pas de différenciation forte par secteur (banques, écoles, retail, immobilier, institutions, agences).
- Pas encore d’inbox communautaire, de social listening ou de benchmarking concurrentiel — domaines où les leaders sont très avancés.

## Marché et concurrence

### Contexte ivoirien

Le marché est porteur : la Côte d’Ivoire comptait **13,4 millions d’internautes** et **8,40 millions d’identités actives sur les réseaux sociaux** fin 2025. Ces identités sociales ont progressé de **22,6 % sur un an**, selon [Digital 2026 Côte d’Ivoire — DataReportal](https://datareportal.com/reports/digital-2026-cote-divoire). La demande d’organisation, de gouvernance et de mesure devrait donc croître avec la professionnalisation des équipes.

### Concurrents nationaux ou régionaux directs

| Acteur | Positionnement visible | Force | Faiblesse/opportunité pour Strax |
|---|---|---|---|
| [SocialBoost CI](https://socialboost-ci.com/) | Entreprises, agences et marques en Côte d’Ivoire ; clients, Brand Kit, studio, visuels, calendrier et facturation | Local, français, logiciel + gestion déléguée, discours très adapté au terrain | Encore en programme bêta public ; Strax doit se différencier par la propriété client, la gouvernance agence-client et la boucle stratégie-performance |
| [Postvoro](https://www.postvoro.com/) | IA, calendrier, validation client, analytics | Promesse simple et lisible | Prix encore « à définir » et plateforme apparemment précommerciale ; occasion de prendre de l’avance sur l’exécution et la preuve |
| [Polpo](https://thepolpo.app/) | Copilote des agences social media : contenu, inbox, rapports, multi-clients, automatisation | Très bon cadrage agence et inbox centralisée | Strax peut gagner sur le modèle bilatéral client-propriétaire/agence-révocable et sur l’intelligence stratégique structurée |
| Outils maison (Excel, WhatsApp, Drive, Trello/Notion) | Processus fragmentés mais familiers et peu coûteux | Faible coût perçu, habitudes ancrées | Principal concurrent réel ; Strax doit importer l’existant et prouver un gain en moins de 7 jours |

### Concurrents internationaux

| Catégorie | Acteurs de référence | Ce qu’ils dominent | Espace jouable pour Strax |
|---|---|---|---|
| Collaboration et validation | [Planable](https://planable.io/product/), [Kontentino](https://www.kontentino.com/social-media-approval-tool/) | Aperçus fidèles, commentaires, versions, approbations et UX mature | Stratégie structurée avant création, propriété réversible des données, contexte africain/francophone |
| Suite social media complète | [Agorapulse](https://www.agorapulse.com/pricing/), Hootsuite, Sprout Social | Inbox, listening, publication multi-réseaux, analytics et écosystème | Ne pas les affronter frontalement ; devenir la couche Content Ops qui peut aussi s’intégrer à eux |
| Rapport qualité-prix | [Swello](https://swello.com/fr/tarifs), Buffer, SocialPilot, Metricool | Programmation accessible et adoption simple | Offrir davantage de gouvernance et de méthode métier, pas seulement un scheduler moins cher |
| Agences/white-label | Sendible, Cloud Campaign, Gain | Portails clients, rapports et marque blanche | Portail client propriétaire, continuité lors d’un changement d’agence et preuves d’approbation |

Quelques repères de prix montrent pourquoi une guerre tarifaire pure serait dangereuse : Swello affiche des forfaits annuels équivalant à **19 €, 59 € et 99 € HT/mois**, tandis qu’Agorapulse va de **79 à 149 $ par utilisateur/mois** avant l’offre sur mesure. Planable facture principalement par workspace avec utilisateurs illimités. Strax doit donc tarifer selon la valeur opérationnelle — marques actives, volume de contenus ou clients gérés — et éviter une pénalité par utilisateur qui freine la collaboration client.

## Valeur ajoutée critique de Strax

### 1. La relation agence-client réversible

C’est la différence la plus crédible déjà présente dans l’architecture : le client conserve son espace, son historique et ses actifs ; il accorde un périmètre d’accès à une agence et peut le révoquer. La majorité des outils parlent de « workspace client », mais l’agence en reste souvent l’administrateur de fait.

Cette promesse doit devenir une fonctionnalité visible et contractualisable : propriété des données, journal des accès, export complet, révocation instantanée et continuité lors du changement d’agence.

### 2. La traçabilité de la stratégie jusqu’au résultat

Chaque contenu devrait pouvoir répondre à six questions : pour quelle cible, quel problème, quelle offre, quel objectif, quelle hypothèse créative et quel CTA ? Puis afficher le résultat obtenu. Cette chaîne est plus précieuse qu’un simple calendrier.

### 3. Une méthode locale encodée, pas une IA générique

La matrice T-PACK peut devenir un actif propriétaire si elle combine : référentiels sectoriels locaux, contraintes de canal, langue/ton, données de performance, règles de conformité et recommandations explicables. Un générateur de légendes générique se copie facilement ; une base d’apprentissage structurée par secteur et résultat se copie beaucoup moins.

### 4. Le pont entre direction, marketing, agence et production

Strax peut servir de registre de décision : qui a demandé, produit, modifié, approuvé, publié et mesuré. Cette gouvernance est particulièrement attractive pour les banques, assurances, institutions, écoles et groupes multi-marques.

## Ce qu’il faut ajouter pour dépasser la concurrence

### Priorité 1 — Construire le moat (0 à 3 mois)

1. **Content Intelligence Loop** : rattacher chaque idée à ses dimensions stratégiques, collecter automatiquement ses KPI, comparer aux objectifs et recommander les combinaisons à réutiliser ou arrêter.
2. **Brand Brain par client** : ton, mots interdits, offres, preuves, cibles, concurrents, charte, CTA autorisés et exemples validés. Toutes les suggestions doivent être contrôlées par ce contexte.
3. **Score explicable avant publication** : cohérence stratégique, répétition, couverture des objectifs, conformité de marque et faisabilité par réseau — jamais un score opaque de « viralité ».
4. **Validation sans friction** : lien sécurisé sans compte, commentaires sur visuel/vidéo, comparaison de versions, approbation en un geste via mobile et rappels WhatsApp/email.
5. **Preuve de valeur intégrée** : tableau « heures économisées, délai moyen de validation, taux de publication à l’heure, taux de reprise et performance par pilier ».
6. **Onboarding par import** : importer Excel/CSV, calendrier existant et dossiers Drive ; fournir un premier mois exploitable en moins d’une heure.

### Priorité 2 — Gagner le marché local et régional (3 à 6 mois)

1. **Vertical packs** : modèles, workflows et KPI pour banques/assurances, écoles, immobilier, retail/restauration, institutions et ONG.
2. **Mobile-first et faible bande passante** : PWA, compression des aperçus, brouillons hors ligne et notifications adaptées.
3. **WhatsApp comme interface de validation** : notification, aperçu sécurisé, décision et commentaire renvoyés dans Strax. Respecter les règles officielles de WhatsApp Business.
4. **Facturation locale** : XOF, TVA/configuration locale, Mobile Money et paiement annuel par facture/virement.
5. **Français impeccable puis anglais**, avec possibilité de variantes locales de ton ; éviter de disperser trop tôt l’effort sur de nombreuses langues.
6. **Support et accompagnement certifié** : réseau de partenaires/agences Strax, formation et certification opérationnelle.

### Priorité 3 — Parité internationale ciblée (6 à 12 mois)

1. Connecteurs robustes Meta, LinkedIn, TikTok, YouTube, Threads et Google Business.
2. Inbox unifiée avec assignation et SLA — seulement après stabilisation des connecteurs de publication.
3. Rapports clients automatiques, marque blanche et exports exécutifs.
4. Bibliothèque média privée avec versions, droits d’usage et expiration des assets.
5. API et webhooks ; intégrations Canva, Drive, Dropbox, Slack/Teams, Zapier/Make.
6. SSO, MFA, journaux inviolables, sauvegardes testées, DPA/SLA, politique de rétention et préparation ISO 27001/SOC 2 selon la cible entreprise.

### Paris différenciants à fort potentiel

- **Agency portability** : changer d’agence sans migration et sans perdre l’historique.
- **Content supply chain** : vue du goulot d’étranglement, capacité équipe, retards et coût par contenu.
- **Benchmarks anonymisés par secteur** avec consentement : fréquence, formats et performance relative en Afrique francophone.
- **Détection de fatigue créative** : répétition des hooks, formats, sujets et offres.
- **Conformité sectorielle** : lexiques, mentions obligatoires et validation juridique pour secteurs réglementés.
- **Agent de réunion éditoriale** : prépare l’ordre du jour, les décisions à prendre et le plan mensuel sur la base des résultats précédents.

## Positionnement recommandé

### Catégorie

Ne pas se présenter d’abord comme un « outil de gestion des réseaux sociaux ». La catégorie recommandée est :

> **Plateforme de Content Operations pour agences et marques francophones.**

### Promesse

> **Strax transforme votre stratégie en contenus validés, publiés et mesurés — tout en laissant chaque marque propriétaire de ses données.**

### Trois preuves à afficher

1. De l’objectif au KPI : chaque contenu reste relié à la décision qui l’a créé.
2. Client propriétaire, agence autorisée : collaboration révocable et historique continu.
3. Un mois éditorial prêt plus vite : matrice, validation, production et calendrier dans un même flux.

### Cible initiale

Le meilleur beachhead est constitué des **agences de 3 à 30 personnes gérant 5 à 50 marques**, ainsi que des équipes marketing de groupes multi-marques ayant des validations complexes. Elles ressentent fortement la douleur, peuvent apporter plusieurs workspaces par vente et fournissent rapidement des données d’usage.

## Packaging et modèle économique suggérés

- **Starter — 25 000 à 40 000 XOF/mois** : 2 marques, calendrier, matrice, validation, publication de base.
- **Agency — 75 000 à 150 000 XOF/mois** : 10 à 25 marques, utilisateurs illimités, portail client, marque blanche légère, rapports.
- **Business — sur devis** : organisations multiples, SSO/MFA, conformité, SLA, audit avancé et accompagnement.
- Éviter la facturation par siège pour les approbateurs clients ; elle réduit l’adoption.
- Proposer un service d’onboarding payant et des packs sectoriels, sans transformer le SaaS en agence de production généraliste.

Ces fourchettes doivent être validées par 15 à 20 entretiens et 5 pilotes payants ; elles sont un point de départ, pas une conclusion définitive.

## Plan d’exécution 90 jours

### Jours 1–30 : sécuriser et prouver

- Désactiver l’auto-sync en production et déplacer les uploads hors webroot.
- Ajouter CI, PHPUnit/Pest, PHPStan et tests systématiques d’étanchéité.
- Mesurer les événements clés : idée créée, délai de validation, contenu synchronisé, publication réussie, KPI collecté.
- Refaire la landing page autour de la propriété des données et de la boucle stratégie-performance.
- Recruter 5 agences pilotes et documenter leur processus actuel.

### Jours 31–60 : rendre l’avantage visible

- Livrer Brand Brain, validations mobiles/no-login et tableau de gains opérationnels.
- Relier les dimensions de matrice aux performances historiques.
- Stabiliser Meta et LinkedIn avec observabilité, retries et renouvellement de jetons.
- Publier prix, SLA de base, DPA et page sécurité.

### Jours 61–90 : créer les preuves et la distribution

- Produire trois cas clients chiffrés.
- Lancer deux vertical packs, par exemple agence généraliste et école/formation.
- Mettre en place parrainage agence et programme de partenaires.
- Tester le paiement XOF/Mobile Money et une offre annuelle.
- Fixer les seuils : activation en 24 h, premier calendrier en 7 jours, rétention à 8 semaines et expansion par marque.

## Indicateurs de réussite

- Time-to-value : premier mois éditorial créé en moins de 60 minutes.
- Activation : au moins un client, une matrice, cinq idées et un approbateur dans les 7 jours.
- Délai médian d’approbation réduit d’au moins 40 % chez les pilotes.
- Plus de 90 % des publications planifiées exécutées à l’heure hors panne des plateformes.
- Plus de 70 % des contenus publiés reliés à un objectif et à une hypothèse stratégique.
- Rétention logo à 6 mois supérieure à 85 % pour les agences pilotes.
- Expansion : augmentation du nombre de marques actives par compte.

## Décision finale

Strax a une vraie fenêtre de marché, mais elle est courte. Les concurrents locaux arrivent et les leaders mondiaux ajoutent rapidement IA, approbations et automatisations. Le produit ne gagnera pas par une liste de fonctionnalités plus longue.

Il peut gagner en devenant le meilleur système pour **gouverner et apprendre de toute la chaîne de contenu**, avec trois actifs difficiles à copier :

1. la propriété client et la collaboration agence révocable ;
2. le graphe stratégie → contenu → approbation → résultat ;
3. une intelligence éditoriale locale et sectorielle nourrie par des données structurées.

La priorité absolue est donc de transformer la matrice actuelle en boucle d’apprentissage mesurable, tout en sécurisant le socle et en obtenant rapidement des preuves clients. C’est cette combinaison — gouvernance, méthode propriétaire et performance — qui peut permettre à Strax de dominer d’abord le marché ivoirien, puis l’Afrique francophone, avant une expansion internationale ciblée.

