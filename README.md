# Projet desriptions

CAHIER DES CHARGES DU PROJET JAXE OPS

---

1. PRÉSENTATION GÉNÉRALE DU PROJET
   Nom du projet : JAXE OPS
   Objectif : Centraliser, planifier, produire, publier et analyser toutes les publications digitales créées pour les clients de JAXE COMMUNICATION.
   Périmètre : L'application couvrira toutes les étapes du processus de publication : planification » brief » production » publication » analyse des résultats. Elle devra gérer des tâches enchaînées et réparties selon les profils des utilisateurs.
   Technologies :
   • Back-end : PHP (POO + MVC)
   • Base de données : MySQL / MariaDB
   • Front-end : HTML, CSS (Bootstrap ou Tailwind), JS (vanilla ou Vue.js)

---

2. MODULES FONCTIONNELS
   2.1 Module Publication
   Chaque publication comprend :
   Infos générales
   • Client
   • Réseaux sociaux visés (Facebook, Instagram, LinkedIn...)
   • Thème général du mois
   Infos spécifiques
   • Thème spécifique de la publication
   • Type de publication (visuel, vidéo, carrousel, etc.)
   • Date et heure de publication
   • Objectifs de la publication (visibilité, engagement, clic, etc.)
   • Message clé de la publication
   Brief
   • Instructions créatives détaillées
   • Descriptif de contenu
   Contenu
   • Fichier final (visuel ou vidéo)
   • Fichier source (PSD, AI, fichier montage...)
   Publication
   • Etat : En attente / Prête / Publiée / En retard
   • Résultats (par réseau social)
   o Réseau concerné
   o Date de collecte
   o Vues
   o Couverture
   o Partages
   o Commentaires
   o Likes
   o Clics sur le lien
   2.2 Module Tâches
   Chaque tâche a une durée de vie courte et est liée à une étape du processus de publication.
   Attributs d’une tâche :
   • Date de création
   • Date limite d’exécution
   • Responsable
   • Description
   • Etat (A faire / En cours / Terminée / Validée)
   • Conversation (commentaires liés, système de discussion intégré ou via Slack)
   • Valideurs (un ou plusieurs)
   Fonctionnement en chaîne :
   • La validation d’une tâche génère automatiquement la suivante dans le processus, selon le workflow :
1. Chargé de communication : création de la publication
1. Créateur de contenu : rédaction du brief
1. Designer / Vidéaste : création du contenu
1. Community manager : publication + saisie des résultats
1. Chargé de communication : validation finale + rapport mensuel
   2.3 Module Projet Mensuel
   • Un projet est lié à un client et un mois donné.
   • Génération automatique des projets chaque début de mois pour les clients en contrat.
   • Chaque projet contiendra les publications et les tâches liées du mois.
   2.4 Module Rapports
   • Récupération des données de performance
   • Tableau d’analyse
   • Export PDF ou Excel
   • Rapport mensuel par client et projet

---

3. UTILISATEURS ET TABLEAUX DE BORD
   Profils d’utilisateurs
   • Administrateur : accès total, gestion des comptes, vue globale
   • Chargé de communication : planification, pilotage, rapport final
   • Créateur de contenu : accès aux briefs à rédiger
   • Designer / Vidéaste : accès aux tâches de production
   • Community manager : accès à la publication + saisie de résultats
   Dashboards personnalisés
   • Liste des tâches à faire
   • Vue détaillée de la tâche sélectionnée
   • Filtres : par client, par projet, par état, par responsable

---

4. STRUCTURE DES DOSSIERS (MVC - PHP)
   / JAXE_OPS
   |
   |-- /app
   | |-- /controllers --> Contrôleurs de chaque module
   | |-- /models --> Modèles ORM pour chaque entité (Publication, Tâche...)
   | |-- /views --> Vues HTML (avec includes pour header/footer)
   | |-- /core --> Routeur, baseController, baseModel
   |
   |-- /public
   | |-- /assets --> CSS, JS, images
   | |-- index.php --> Point d’entrée de l’application
   |
   |-- /config
   | |-- database.php --> Connexion à la BDD
   | |-- routes.php --> Routing des URL
   |
   |-- /storage
   | |-- /uploads --> Fichiers source / visuels / vidéos
   | |-- /logs --> Logs d’erreurs
   |
   |-- .env --> Variables d’environnement (base de données, API Slack...)
   |-- composer.json --> Dépendances PHP si utilisé

---

5. LIVRABLES ATTENDUS
1. Base de données relationnelle (avec scripts de création SQL)
1. Architecture MVC opérationnelle (ok)
1. Tableau de bord dynamique par utilisateur
1. Système de tâches chaînées et valides
1. Interface responsive (bureau / mobile)
1. Export de rapports mensuels par client 7. Option intégration Slack ou chat interne
