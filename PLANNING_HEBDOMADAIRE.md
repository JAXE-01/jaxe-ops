> État initial conservé pour historique. Voir WORKFLOW_COMPACT_20260828.md pour la livraison actuelle.

# Planification hebdomadaire — état du lot

## Disponible localement
- Mois de travail conservé en session, par entreprise, entre matrice et calendriers (pas encore tous les modules).
- Création de projet : règles de jour, intention, format, type et alternance toutes les deux semaines. Sans règles, quotas existants conservés.
- Comptage par dates réelles, borné par début/fin du projet ; alternance continue ancrée sur la semaine du début du projet. Un cinquième vendredi suit l'alternance, sans plafond artificiel à quatre vidéos.
- Réexécution sans doublons et sans déplacer les contenus hebdomadaires existants. Échéances Publication et Collecte KPI alignées sur la date prévue (la collecte réelle après publication reste un chantier séparé).
- Abonnements actifs rattachés au tenant ; les abonnements historiques sont affectés au tenant legacy `jaxe-ops`, jamais partagés globalement.
- Campagne complémentaire facultative sous une rubrique avancée. Persona ou cible libre, résumé du persona dans la fiche contenu.

## Limites à traiter ensuite
- Changement de cadence d'un projet ayant déjà des plans : volontairement bloqué. Prévoir un aperçu des différences et application aux seuls mois futurs, sans modifier les contenus en cours.
- Fusion calendrier/brief, workflow Solo/Équipe et validation client réservée aux agences restent à intégrer.
- Calendrier des dates internes de production/validation à recalculer selon un délai configurable ; seule la date Publication et le rappel KPI sont alignés dans ce lot.
- Résumé des personas dans les autres écrans de brief et matrice à généraliser.
- Affichage visuel à vérifier en navigateur ; les tests actuels portent sur le calcul, la persistance, l'isolation et le HTML rendu.

## Déploiement
Migration `20260828_017_editorial_cadence.sql` requise. Sauvegarder la base avant déploiement, vérifier le dry-run et l'identité du tenant legacy ; ne pas importer la base locale en production.
Test : `php scripts/editorial_cadence_regression_check.php` (fixtures annulées par transaction).
