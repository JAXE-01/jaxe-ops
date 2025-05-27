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
   CREATE DATABASE jaxe_ops;
   USE jaxe_ops;

-- USERS TABLE
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
email VARCHAR(100) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
role ENUM('communication_manager', 'content_creator', 'designer', 'videographer', 'community_manager', 'admin') NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- CLIENTS TABLE
CREATE TABLE clients (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
active_contract BOOLEAN DEFAULT TRUE,
social_networks TEXT,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- PROJECTS TABLE
CREATE TABLE projects (
id INT AUTO_INCREMENT PRIMARY KEY,
client_id INT NOT NULL,
month_year VARCHAR(7) NOT NULL, -- Format: MM-YYYY
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- PUBLICATIONS TABLE
CREATE TABLE publications (
id INT AUTO_INCREMENT PRIMARY KEY,
project_id INT NOT NULL,
general_theme VARCHAR(255),
specific_theme VARCHAR(255),
publication_type ENUM('visual', 'video', 'carousel', 'reel', 'other'),
publication_date DATETIME,
message TEXT,
objectives TEXT,
instructions TEXT,
description TEXT,
content_url TEXT,
source_file_url TEXT,
status ENUM('pending', 'published', 'validated') DEFAULT 'pending',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- TASKS TABLE
CREATE TABLE tasks (
id INT AUTO_INCREMENT PRIMARY KEY,
publication_id INT NOT NULL,
assignee_id INT NOT NULL,
description TEXT,
status ENUM('pending', 'in_progress', 'blocked', 'completed' 'unvalidated' 'validated') DEFAULT 'pending',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
due_date DATE,
FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
);

-- CONVERSATIONS TABLE
CREATE TABLE conversations (
id INT AUTO_INCREMENT PRIMARY KEY,
task_id INT NOT NULL,
author_id INT NOT NULL,
message TEXT NOT NULL,
sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- TASK VALIDATORS TABLE
CREATE TABLE task_validators (
id INT AUTO_INCREMENT PRIMARY KEY,
task_id INT NOT NULL,
user_id INT NOT NULL,
validated BOOLEAN DEFAULT FALSE,
validated_at DATETIME,
FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- RESULTS TABLE
CREATE TABLE results (
id INT AUTO_INCREMENT PRIMARY KEY,
publication_id INT NOT NULL,
platform VARCHAR(50),
collection_date DATE,
views INT DEFAULT 0,
reach INT DEFAULT 0,
shares INT DEFAULT 0,
comments INT DEFAULT 0,
likes INT DEFAULT 0,
link_clicks INT DEFAULT 0,
FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
); 2. Architecture MVC opérationnelle (ok) 3. Tableau de bord dynamique par utilisateur 4. Système de tâches chaînées et valides 5. Interface responsive (bureau / mobile) 6. Export de rapports mensuels par client 7. Option intégration Slack ou chat interne
