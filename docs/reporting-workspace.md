# Rapports personnalisables et application mobile

- « Tableaux du rapport » choisit les sections affichées et incluses dans l’export PDF regroupé (icône tableau).
- « Colonnes / KPI » dans chaque tableau choisit les colonnes de ce modèle, à l’écran et dans ses exports PDF/CSV. Ces choix sont conservés dans l’URL, pas dans le profil utilisateur.
- Un clic sur l’en-tête trie par cet indicateur ; un second inverse le sens. Les valeurs absentes restent en fin de liste. Par défaut, les publications sont triées par date de publication, les synthèses par mois.
- L’historique individuel conserve les relevés successifs. Sa colonne « Relevé le » est optionnelle. Une date de publication inconnue n’est pas remplacée à l’écran par la date d’importation.
- Les anciennes collectes ne contiennent pas le format du média : « Non renseigné » est attendu jusqu’à leur prochaine collecte. La collecte n’invente pas de format absent de la réponse Meta.
- Les exports tabulaires portent le client dans le titre et le nom du fichier ; les lignes portent le compte/Page. Les URL HTTP(S) sont des liens compacts cliquables.
- Les sélections, tris, exports et collectes de la page Statistiques utilisent AJAX. Les autres écrans conservent leurs mécanismes existants ; il ne s’agit pas d’une conversion globale en SPA.

## Installation mobile

Déployer aussi `manifest.webmanifest` et `public/assets/brand/app-192.png`, `app-512.png` (inclus dans `.cpanel.yml`). Sur HTTPS, les navigateurs compatibles proposent « Installer Strax ». Sur iPhone/iPad : Partager → Sur l’écran d’accueil. Aucun cache hors connexion de données clients n’est ajouté. Ce mécanisme installe un raccourci autonome PWA, pas un installeur serveur PHP.

## Vérifications

`php scripts/test-report-presentation.php` : listes autorisées, absence/zéro, URL et tri.

`php scripts/test-report-presentation.php --render` puis `--selection` : PDF de contrôle dans `tmp/pdfs`, avec le vrai moteur TCPDF.

`node scripts/reporting-workspace-ui-check.cjs` : navigateur Chrome headless, réponses simulées, filtres/tris/loader. Configurer `PLAYWRIGHT_MODULE` si Playwright est hors du projet.

La validation des requêtes sur les données réelles et de l’installation sur téléphone doit être faite après déploiement. La base MySQL locale était indisponible lors de cette modification.
