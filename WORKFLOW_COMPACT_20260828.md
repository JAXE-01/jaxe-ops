# Workflow compact — livraison locale du 28 août 2026

Ce document remplace l'état précédent de `PLANNING_HEBDOMADAIRE.md`.

## Disponible dans ce lot

- Mois de travail dans une barre persistante, en haut de la zone de contenu, mémorisé par entreprise dans la session. Matrice, calendriers, réalisations et file sociale l'utilisent. Les filtres de période explicites des réalisations restent prioritaires. Le mois est un contexte de navigation : il ne déplace pas les contenus. Depuis une fiche ou tâche, changer de mois retourne au calendrier.
- Fiche compacte : métadonnées sur une ligne repliable, champs courts réduits, message plus large, réseau déroulant préservant les anciennes valeurs, contexte mensuel ordonné dates → contexte → objectif. Le sujet reste l'angle éditorial, distinct du nom du livrable.
- Mini-calendrier dépliable des autres publications du plan mensuel ; clic sur un jour = choix de la date avec sauvegarde existante. Plusieurs publications le même jour restent possibles.
- Brief/script chargé dans la même fiche, à la demande, depuis son formulaire authentifié existant. Enregistrement explicite AJAX, CSRF et contrôles serveur inchangés. La fiche conserve sa sauvegarde automatique. Pas de formulaire imbriqué. Avertissement si on quitte un brief modifié non enregistré. Les suppressions de pièces jointes et invalidations restent dans la vue détaillée.
- Cadence par jours réels, format/intention et alternance de deux semaines. Abonnements actifs isolés par entreprise. Campagne complémentaire facultative. Résumé du persona dans la fiche.
- Révision avec mois d'effet strictement futur et confirmation. Historique des règles par mois et événements conservés dans `publication_rules` ; pas de réécriture des migrations déjà appliquées.
- Révision conservatrice : seuls les créneaux issus de règles hebdomadaires, encore vierges et conformes à leurs valeurs générées sont déplacés. Tout contenu personnalisé, lié à un brief, une idée, une publication, un résultat ou une validation est conservé. Les anciennes générations par quotas, sans provenance hebdomadaire vérifiable, sont conservées.
- Les créneaux excédentaires ne sont jamais supprimés automatiquement lors d'une réduction de cadence ; ils restent des exceptions. Le formulaire indique les nombres adaptés/conservés. Les totaux du plan reflètent les contenus réellement présents, exceptions comprises.
- Pour les nouveaux créneaux, échéances de préparation/production/validation relatives à la date de publication ; rappel KPI à J+14 prévu. Cela ne constitue pas une collecte réelle après publication.

## Vérifications locales

- `scripts/editorial_cadence_regression_check.php`
- `scripts/cadence_revision_regression_check.php`
- `scripts/month_context_regression_check.php`
- `scripts/content_view_regression_check.php`
- `scripts/social_context_regression_check.php`
- `scripts/account_content_regression_check.php`
- `scripts/access_ui_regression_check.php`

Fixtures SQL annulées par transaction ; aucun appel Meta ni publication réelle dans ces tests.
Le contrôle visuel interactif n'est **pas validé** : le navigateur fourni échoue au démarrage avec une erreur ACL Windows. Le HTML rendu est contrôlé, pas l'apparence effective ni le parcours AJAX complet.

## À poursuivre

- Aperçu détaillé avant révision et gestion manuelle guidée des exceptions ; aucune suppression silencieuse.
- Workflow Solo/Équipe configurable, validations adaptées agence/entreprise et synchronisation effective publication → collecte.
- Nomenclature configurable des livrables (distincte du sujet éditorial), si souhaitée.
- Suggestions de dates clés togolaises : à proposer avec sources vérifiées et confirmation, jamais en écrasant le contexte saisi.
- Généralisation des résumés de personas et du filtrage mensuel aux éventuels rapports/sections non encore couverts.

## Déploiement non effectué

La migration additive `20260828_017_editorial_cadence.sql` est requise. Sauvegarder la base de production, examiner le dry-run et vérifier le rattachement du tenant historique `jaxe-ops` avant application. Ne pas importer la base locale sur la production. Aucun commit, push ou changement serveur réalisé dans ce lot.
