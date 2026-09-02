# Strax : mesure de performance et connexions

État au 2 septembre 2026. Ce document distingue les corrections livrées des fonctions à construire. Les autorisations restent soumises aux validations de chaque plateforme.

## Livré dans ce correctif

- Pictogrammes vectoriels œil, pouce, commentaire, partage, clic, enregistrement et lien externe. Dessins locaux utilisant les conventions des interfaces sociales, pas des glyphes Unicode ni des fichiers propriétaires Meta.
- PDF : conversion des alphabets décoratifs en lettres ordinaires, accents conservés, coupure explicite des mots longs, pictogrammes en SVG. Les rares symboles non couverts sont représentés par un libellé ou un code Unicode entre crochets, jamais par une case vide. Les textes enregistrés ne sont pas modifiés.
- OAuth Meta demande aussi `instagram_manage_insights`, même si la configuration existante contient une ancienne liste de scopes. Cela ne confère pas la permission sans validation Meta et consentement.
- Instagram : vues, portée, likes, commentaires, partages et enregistrements selon ce que l’API retourne. Les enregistrements deviennent sélectionnables dans les rapports ; les Reels ont leur propre format. Un compteur absent n’est plus transformé en zéro.

Les adaptateurs TikTok, LinkedIn et Google ne sont PAS encore implémentés. Les nouvelles vues analytiques ci-dessous sont un plan de construction, pas des fonctionnalités déjà disponibles.

## Comptes développeur et autorisations à préparer

Ne pas transmettre de secrets dans une conversation ni les versionner. Conserver les secrets d’application côté serveur ; chaque client autorise ses comptes via OAuth. Une clé API seule ne donne pas accès aux statistiques privées.

### Meta / Instagram

- [Applications Meta](https://developers.facebook.com/apps/).
- Dans le mode actuellement utilisé par Strax, Instagram passe par Facebook Login : compte Instagram professionnel et Page Facebook associée.
- Accès Insights : `instagram_basic`, `instagram_manage_insights`, `pages_read_engagement`. Strax utilise aussi `pages_show_list` pour découvrir les Pages ; conserver les autres permissions déjà nécessaires à la publication et à la messagerie.
- [Documentation officielle Meta des Insights Instagram](https://www.postman.com/meta/instagram/folder/23987686-f659d7d1-d74c-44e4-9192-9b1e8694c511). Ne pas confondre les scopes `instagram_business_*` du parcours Instagram Login avec ceux du Facebook Login existant.
- Champs serveur existants : `META_CLIENT_ID`, `META_CLIENT_SECRET`.
- Callback déjà implémenté : `https://strax.jaxecommunication.com/index.php/social-oauth/callback`.
- Après approbation, reconnecter Meta, cocher aussi les destinations Instagram, importer leur historique puis relancer la collecte. Ajouter la permission dans le portail seul ne modifie pas les anciens jetons.
- Publicité : [Marketing API / Insights](https://developers.facebook.com/docs/marketing-api/insights/), accès aux comptes publicitaires et permission de lecture `ads_read` selon le produit approuvé. Pas besoin de gérer les annonces pour seulement les analyser.

### TikTok

- [Applications TikTok](https://developers.tiktok.com/apps/), produits Login Kit et Display API.
- Préparer Client key et Client secret. Accès minimal à étudier : `user.info.basic`, `video.list` ; `user.info.stats` pour les statistiques du profil si approuvé.
- [Liste des vidéos v2](https://developers.tiktok.com/doc/tiktok-api-v2-video-list/) : requête paginée des vidéos publiques autorisées. Les champs retournés dépendent des champs/scopes demandés ; ne pas promettre portée ou clics si non fournis.
- [Content Posting API](https://developers.tiktok.com/products/content-posting-api/) séparée si publication requise ; elle nécessite sa propre validation.
- [TikTok API for Business](https://business-api.tiktok.com/portal) pour les données publicitaires, distincte du Display API.

### LinkedIn

- [Applications LinkedIn](https://www.linkedin.com/developers/apps), rattacher une Page entreprise et demander le produit Community Management API.
- Préparer Client ID et Client secret. Pour les Pages : `r_organization_social` et les autorisations de reporting/administration accordées au produit (`rw_organization_admin` selon accès). Demander les droits d’écriture seulement pour la publication ou la réponse.
- [Produits, niveaux d’accès et scopes officiels](https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access). Les autorisations des profils personnels et celles des Pages ne sont pas interchangeables. L’accès complet est soumis à approbation.

### Google

- [Console Google Cloud / identifiants](https://console.cloud.google.com/apis/credentials) : projet, écran de consentement OAuth et client OAuth de type application Web.
- **GA4, priorité pour mesurer l’impact des réseaux sur le site** : activer Analytics Data API, préparer les identifiants des propriétés et autoriser l’identité cliente à les lire. [Démarrage officiel](https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart). Scope lecture `https://www.googleapis.com/auth/analytics.readonly`.
- **Search Console** : activer Search Console API, accès aux propriétés vérifiées. Scope `https://www.googleapis.com/auth/webmasters.readonly`. [Autorisation officielle](https://developers.google.com/webmaster-tools/v1/how-tos/authorizing). Mesure les résultats de recherche Google, pas les clics Facebook.
- **Google Ads** : compte administrateur MCC et [API Center](https://ads.google.com/aw/apicenter), developer token en plus de l’OAuth, IDs des comptes autorisés. [Procédure officielle](https://developers.google.com/google-ads/api/docs/api-policy/developer-token). Scope `https://www.googleapis.com/auth/adwords`, application limitée en interne à la lecture pour ce connecteur analytique.
- **Google Business Profile**, anciennement My Business : [prérequis et demande d’accès](https://developers.google.com/my-business/content/prereqs), puis [Performance API](https://developers.google.com/my-business/reference/performance/rest). Préparer les établissements autorisés et leur identifiant ; scope `https://www.googleapis.com/auth/business.manage`. L’accès API et le consentement restent nécessaires même pour un usage de reporting.

Les callbacks TikTok, LinkedIn et Google seront définis lors de l’implémentation des adaptateurs. Ne pas enregistrer pour eux le callback Meta : il ne traite pas leurs codes OAuth. Les pages légales existantes à vérifier en production sont `/index.php/public/privacy`, `/index.php/public/terms` et `/index.php/public/data-deletion`.

## Vues analytiques à construire

| Lecture | Visualisation et mesure | Prérequis / garde-fous |
| --- | --- | --- |
| Une publication sur plusieurs réseaux | Matrice réseau × KPI, courbes synchronisées à 24 h, 72 h, 7 j | Identifiant éditorial commun, pas simple rapprochement par titre/date |
| Réseau le plus performant | Volumes, médianes par publication, taux d’engagement, clics et conversions | Même période, définition des vues et taille d’audience affichées ; classement selon objectif, pas score universel |
| Meilleurs contenus par réseau | Top vues, commentaires, réactions, partages, enregistrements, clics, engagement | Âge comparable ; afficher les effectifs et la disponibilité des KPI |
| Croissance d’une publication | Valeur cumulée, gains entre relevés, vitesse par heure/jour | Relevés horodatés conservés, fréquence affichée |
| Publications d’une période | Cohortes basées sur la date de publication, courbe moyenne/médiane à âge égal | Séparer période de publication et période d’observation |
| Jours/heures de forte croissance | Carte de chaleur des gains observés par créneau et réseau | Collecte assez fine, fuseau de la Page ; ne pas reconstruire des heures absentes |
| Meilleurs jours/heures pour publier | Performance à 7 j selon créneau de publication | Distinct du créneau de croissance ; contrôler format, sujet, budget et taille d’échantillon |
| Formats et sujets | Matrices format × réseau, thème × KPI, médianes et dispersion | Classification fiable des Reels/carrousels/vidéos/images ; tags de thème validés par l’utilisateur |
| Mois forts | Comparaisons mois-à-mois et année-à-année par Page, client et périmètre autorisé | Plusieurs cycles nécessaires pour parler de saisonnalité ; neutraliser volume publié, budget et changement de portefeuille |
| Croissance croisée | Courbes alignées et corrélations décalées entre réseaux | Rapprochement éditorial explicite ; simultanéité n’est pas preuve d’un effet causal |
| Relations entre KPI et vues | Nuages de points, corrélations sur les gains, analyses par réseau/format | Nombre de paires, incertitude, valeurs extrêmes ; pas de conclusion « les likes causent les vues » |
| Recommandations | Constats chiffrés, hypothèses, tests A/B proposés, suivi des résultats | Distinguer conseil éditorial et causalité démontrée |
| Naturel / sponsorisé | Onglets Total, Organique, Payant et Non ventilé ; dépenses/CPM/CPC/coût par conversion | Source Ads explicite et liaison annonce ↔ publication ; donnée sans ventilation = inconnue, jamais organique par défaut |

Ajouter également : durée de visionnage/rétention si disponible, clics sortants distincts des clics totaux, taux de conversion, visites engagées, coût par lead, fréquence publicitaire, sauvegardes, délai de réponse, sentiment/thèmes des commentaires sous contrôle humain, anomalies de collecte et fraîcheur des données.

## Modèle de données nécessaire

1. Une publication éditoriale commune et ses déclinaisons réseau ; possibilité de relier manuellement les imports avec traçabilité. Ne pas fusionner automatiquement deux contenus parce que leur titre est proche.
2. Un registre par KPI : nom API, définition, unité, réseau, format, cumul ou intervalle, dénominateur d’un taux, version API, statut disponible/indisponible/erreur/non applicable.
3. Une table d’observations immuables : tenant, client, Page, cible réseau, date de publication, date d’observation UTC, fenêtre API, valeur nullable, provenance, ventilation organique/payante/inconnue. Conserver les corrections négatives des plateformes avec un drapeau, pas un clamp silencieux à zéro.
4. Des attributs éditoriaux : format, thème, sujet, objectif, CTA, durée, campagne. Les suggestions automatiques de thème doivent être confirmables/corrigeables.
5. Une projection « dernier relevé » pour l’affichage rapide ; ne jamais additionner plusieurs snapshots cumulatifs pour obtenir un total.
6. Accès limités au tenant et aux clients autorisés. Une vue globale ne signifie pas accès aux autres agences. Les tokens ne vont ni dans les observations ni dans les exports.

Le collecteur actuel remplace les snapshots d’une même journée dans `reporting_metrics`. Il faut d’abord ajouter le journal d’observations avant d’annoncer une analyse horaire. Proposition à valider selon quotas : collecte horaire pendant les 72 premières heures, quotidienne jusqu’à 30 jours, puis espacée. Aucune planification supplémentaire n’est activée par ce document.

Les comptages de personnes uniques ne s’additionnent pas entre publications ou réseaux. Le total moins le payant n’est pas automatiquement une portée organique valide. Les fenêtres d’attribution publicitaires et les définitions de vue doivent accompagner chaque comparaison.

## Lien social → site → Google

Ajouter des UTM stables par publication/déclinaison (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`), sans données personnelles et sans écraser des tags existants sans validation. [Référence Google](https://support.google.com/analytics/answer/10917952?hl=fr).

GA4 fournit la lecture des sessions/conversions attribuées aux campagnes balisées, avec les limites de consentement et de suivi. Search Console aide à observer le trafic de recherche ; Business Profile les interactions locales ; Google Ads les résultats payants. Une hausse SEO ou de demandes d’itinéraire après une publication reste une association temporelle tant qu’un dispositif de mesure ne permet pas d’attribution plus précise.

## Ordre de réalisation proposé

1. Déployer et vérifier Instagram avec un compte réel et une publication de référence ; contrôler les permissions et chaque valeur API.
2. Ajouter le journal d’observations, les définitions des KPI, le rapprochement éditorial et les tags ; migrer les snapshots existants sans inventer d’historique.
3. Construire les comparaisons par réseau, tops et cohortes ; compléter par les cartes horaires quand les données suffisent.
4. Implémenter TikTok et LinkedIn après obtention des produits/scopes ; tests OAuth, rafraîchissement, révocation, pagination et isolation client.
5. Ajouter Meta Ads puis GA4/UTM ; Search Console, Google Ads et Business Profile selon les objectifs des clients.

Critères de validation : zéro réel distinct de l’absence ; pas de doublon cumulatif ; fuseaux explicites ; échantillons faibles signalés ; données payantes non assimilées au naturel ; aucune promesse de causalité fondée seulement sur une corrélation.
