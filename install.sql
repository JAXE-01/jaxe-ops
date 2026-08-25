
-- Table users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','CC','Clientele','CM','Createur','Cadreur','Designer','Videaste') DEFAULT 'Admin',
    secondary_roles TEXT NULL,
    statut ENUM('Actif','Inactif') DEFAULT 'Actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table clients
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    entreprise VARCHAR(100),
    secteur VARCHAR(100),
    telephone VARCHAR(30),
    email VARCHAR(100),
    statut ENUM('Actif','Inactif') DEFAULT 'Actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS client_social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    reseau ENUM('facebook','linkedin','instagram','tiktok','youtube','whatsapp') NOT NULL,
    compte_label VARCHAR(190) NOT NULL,
    identifiant_compte VARCHAR(190) NULL,
    page_id VARCHAR(190) NULL,
    page_nom VARCHAR(190) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    statut ENUM('Actif','Inactif') DEFAULT 'Actif',
    is_default TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_network_status (client_id, reseau, statut),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Table offres
CREATE TABLE IF NOT EXISTS offres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    produit_service VARCHAR(100) NOT NULL,
    description TEXT,
    prix DECIMAL(10,2),
    packages JSON,
    avantage_offre TEXT,
    usp TEXT,
    positionnement VARCHAR(50),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Table personas
CREATE TABLE IF NOT EXISTS personas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    nom_persona VARCHAR(100) NOT NULL,
    age INT,
    profession VARCHAR(100),
    revenu DECIMAL(10,2),
    localisation VARCHAR(100),
    objectif TEXT,
    probleme TEXT,
    craintes TEXT,
    desirs TEXT,
    declencheur_achat TEXT,
    freins TEXT,
    valeur_percue TEXT,
    garanties TEXT,
    canaux TEXT,
    horaires VARCHAR(100),
    priorite ENUM('Haute','Moyenne','Basse') DEFAULT 'Moyenne',
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Table messages_marketing
CREATE TABLE IF NOT EXISTS messages_marketing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    persona_id INT NOT NULL,
    angle VARCHAR(100),
    hook VARCHAR(255),
    message TEXT,
    preuve TEXT,
    offre_associee INT,
    call_to_action VARCHAR(255),
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    FOREIGN KEY (offre_associee) REFERENCES offres(id) ON DELETE SET NULL
);

-- Table campagnes
CREATE TABLE IF NOT EXISTS campagnes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    date_debut DATE,
    date_fin DATE,
    objectif VARCHAR(100),
    persona_cible INT,
    type ENUM('Commercial','Non-commercial') DEFAULT 'Commercial',
    statut ENUM('Planifiee','En cours','Terminee') DEFAULT 'Planifiee',
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (persona_cible) REFERENCES personas(id) ON DELETE SET NULL
);

-- Table tunnel_conversion
CREATE TABLE IF NOT EXISTS tunnel_conversion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    persona_id INT NOT NULL,
    etape ENUM('Decouverte','Consideration','Achat','Fidelisation') DEFAULT 'Decouverte',
    objectif TEXT,
    message TEXT,
    type_contenu VARCHAR(50),
    canal VARCHAR(50),
    CTA VARCHAR(100),
    KPI VARCHAR(100),
    FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE,
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE
);

-- Table contenus
CREATE TABLE IF NOT EXISTS contenus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NULL,
    persona_id INT NULL,
    projet_id INT NULL,
    plan_mensuel_id INT NULL,
    livrable_item_id INT NULL,
    type ENUM('Visuel','Video','Carrousel','Article','Story') DEFAULT 'Visuel',
    sous_type VARCHAR(80) NULL,
    nombre_pages_carrousel INT DEFAULT 1,
    sujet TEXT,
    message TEXT,
    objectif_publication TEXT NULL,
    cible_libre TEXT NULL,
    reseau_cible VARCHAR(120) NULL,
    statut ENUM('Strategique defini','Brief cree','En production','Finalise','Publie') DEFAULT 'Strategique defini',
    responsable VARCHAR(100),
    UNIQUE KEY unique_contenu_livrable (livrable_item_id),
    FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE SET NULL,
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL,
    FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_mensuel_id) REFERENCES plans_mensuels(id) ON DELETE CASCADE,
    FOREIGN KEY (livrable_item_id) REFERENCES livrable_items(id) ON DELETE CASCADE
);

-- Table briefs
CREATE TABLE IF NOT EXISTS briefs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contenu_id INT NOT NULL,
    livrable_item_id INT NULL,
    nature_brief ENUM('Script video','Brief visuel') DEFAULT 'Brief visuel',
    format_livrable VARCHAR(100) NULL,
    nombre_pages_carrousel INT DEFAULT 1,
    pdf_requis TINYINT(1) DEFAULT 0,
    source_requis TINYINT(1) DEFAULT 0,
    titre_brief VARCHAR(190) NULL,
    details_message TEXT NULL,
    informations_complementaires TEXT NULL,
    cta VARCHAR(190) NULL,
    recommandation_design TEXT NULL,
    description_publication TEXT NULL,
    hook_video VARCHAR(255) NULL,
    plan_script TEXT NULL,
    pages_carrousel JSON NULL,
    texte_script TEXT,
    instructions_visuelles TEXT,
    format VARCHAR(100),
    statut ENUM('A faire','En cours','Valide') DEFAULT 'A faire',
    responsable VARCHAR(100),
    pieces_jointes JSON NULL,
    FOREIGN KEY (contenu_id) REFERENCES contenus(id) ON DELETE CASCADE
);

-- Table calendrier_contenus
CREATE TABLE IF NOT EXISTS calendrier_contenus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    contenu_id INT NOT NULL,
    date_publication DATE,
    heure_publication TIME,
    canal VARCHAR(50),
    statut ENUM('Planifie','Publie','Annule') DEFAULT 'Planifie',
    note TEXT,
    FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE,
    FOREIGN KEY (contenu_id) REFERENCES contenus(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS public_validation_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_mensuel_id INT NOT NULL,
    token VARCHAR(120) NOT NULL UNIQUE,
    created_by INT NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_mensuel_id) REFERENCES plans_mensuels(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS public_validation_link_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_id INT NOT NULL,
    deliverable_item_id INT NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_link_deliverable (link_id, deliverable_item_id),
    FOREIGN KEY (link_id) REFERENCES public_validation_links(id) ON DELETE CASCADE,
    FOREIGN KEY (deliverable_item_id) REFERENCES livrable_items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_event_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    integration VARCHAR(60) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    project_id INT NULL,
    task_id INT NULL,
    success TINYINT(1) DEFAULT 0,
    status_code INT NULL,
    payload_json LONGTEXT NULL,
    response_body LONGTEXT NULL,
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projets(id) ON DELETE SET NULL,
    FOREIGN KEY (task_id) REFERENCES taches_pipeline(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS validation_decision_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    plan_mensuel_id INT NULL,
    project_id INT NULL,
    deliverable_item_id INT NULL,
    source ENUM('public','internal') DEFAULT 'internal',
    decision ENUM('Valide','Non valide') NOT NULL,
    comment TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES taches_pipeline(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_mensuel_id) REFERENCES plans_mensuels(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projets(id) ON DELETE SET NULL,
    FOREIGN KEY (deliverable_item_id) REFERENCES livrable_items(id) ON DELETE SET NULL
);

ALTER TABLE public_validation_links ADD COLUMN IF NOT EXISTS revoked_at DATETIME NULL AFTER expires_at;

CREATE TABLE IF NOT EXISTS contenu_resultats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contenu_id INT NOT NULL,
    task_id INT NULL,
    date_collecte DATE NOT NULL,
    periode_label VARCHAR(120) NULL,
    valeur_cle VARCHAR(190) NULL,
    metric_snapshot TEXT NULL,
    note TEXT NULL,
    reseau_collecte VARCHAR(60) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contenu_resultats_task_date_network (task_id, date_collecte, reseau_collecte),
    FOREIGN KEY (contenu_id) REFERENCES contenus(id) ON DELETE CASCADE
);

-- Table reportings
CREATE TABLE IF NOT EXISTS reportings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    performance TEXT,
    recommandations TEXT,
    actions_prevues TEXT,
    FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE
);

ALTER TABLE campagnes MODIFY objectif TEXT;

-- Table projets
CREATE TABLE IF NOT EXISTS abonnements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    type_projet VARCHAR(120) NOT NULL,
    canal_principal VARCHAR(120) NULL,
    duree_mois INT DEFAULT 1,
    sea_budget DECIMAL(12,2) NULL,
    quota_videos_mensuel INT DEFAULT 0,
    quota_visuels_mensuel INT DEFAULT 0,
    notes TEXT NULL,
    statut ENUM('Actif','Inactif') DEFAULT 'Actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role VARCHAR(50) NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role, permission_key)
);

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    allowed TINYINT(1) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dolibarr_user_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dolibarr_user_id INT NOT NULL UNIQUE,
    local_user_id INT NULL,
    remote_login VARCHAR(120) NULL,
    remote_email VARCHAR(120) NULL,
    remote_name VARCHAR(190) NOT NULL,
    remote_payload JSON NULL,
    last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (local_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS dolibarr_project_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dolibarr_project_id INT NOT NULL UNIQUE,
    local_project_id INT NULL,
    remote_ref VARCHAR(120) NULL,
    remote_title VARCHAR(190) NOT NULL,
    remote_thirdparty VARCHAR(190) NULL,
    remote_status VARCHAR(120) NULL,
    remote_payload JSON NULL,
    last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'default_charge_compte_id', NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'default_charge_compte_id');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'default_charge_clientele_id', NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'default_charge_clientele_id');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'default_cm_id', NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'default_cm_id');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'default_createur_id', NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'default_createur_id');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'default_cadreur_id', NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'default_cadreur_id');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'default_videaste_id', NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'default_videaste_id');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'project_type_options', '["SEA ponctuel","Abonnement mensuel","Abonnement mixte"]'
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'project_type_options');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'subscription_type_options', '["Abonnement mensuel","Abonnement mixte"]'
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'subscription_type_options');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'dolibarr_enabled', '0'
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'dolibarr_enabled');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'dolibarr_base_url', ''
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'dolibarr_base_url');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'dolibarr_api_key', ''
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'dolibarr_api_key');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'dolibarr_entity', ''
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'dolibarr_entity');

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'api_integrations_config', '{"facebook":{"mode":"oauth"},"linkedin":{"mode":"oauth"},"instagram":{"mode":"oauth"},"tiktok":{"mode":"direct"},"youtube":{"mode":"direct"},"whatsapp":{"mode":"direct"},"webhooks":{"publication":"","kpi":""}}'
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'api_integrations_config');

CREATE TABLE IF NOT EXISTS projets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    abonnement_id INT NULL,
    campagne_id INT NULL,
    nom VARCHAR(150) NOT NULL,
    type_projet VARCHAR(120) NOT NULL,
    canal_principal VARCHAR(120) NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    duree_mois INT DEFAULT 1,
    sea_budget DECIMAL(12,2) NULL,
    quota_videos_mensuel INT DEFAULT 0,
    quota_visuels_mensuel INT DEFAULT 0,
    charge_compte_id INT NULL,
    charge_clientele_id INT NULL,
    cm_id INT NULL,
    createur_id INT NULL,
    cadreur_id INT NULL,
    videaste_id INT NULL,
    designer_id INT NULL,
    statut ENUM('Brouillon','Actif','Suspendu','Termine') DEFAULT 'Brouillon',
    notes TEXT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (abonnement_id) REFERENCES abonnements(id) ON DELETE SET NULL,
    FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE SET NULL,
    FOREIGN KEY (charge_compte_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (charge_clientele_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cm_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (createur_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cadreur_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (videaste_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (designer_id) REFERENCES users(id) ON DELETE SET NULL
);

ALTER TABLE projets ADD COLUMN IF NOT EXISTS duree_mois INT DEFAULT 1 AFTER date_fin;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS abonnement_id INT NULL AFTER client_id;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS charge_clientele_id INT NULL AFTER charge_compte_id;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS cadreur_id INT NULL AFTER createur_id;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS videaste_id INT NULL AFTER cadreur_id;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS designer_id INT NULL AFTER videaste_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS secondary_roles TEXT NULL AFTER role;
ALTER TABLE abonnements MODIFY type_projet VARCHAR(120) NOT NULL;
ALTER TABLE projets MODIFY type_projet VARCHAR(120) NOT NULL;
ALTER TABLE users MODIFY role ENUM('Admin','CC','Clientele','CM','Createur','Cadreur','Designer','Videaste') DEFAULT 'Admin';
ALTER TABLE contenus MODIFY campagne_id INT NULL;
ALTER TABLE contenus MODIFY persona_id INT NULL;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS projet_id INT NULL AFTER persona_id;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS plan_mensuel_id INT NULL AFTER projet_id;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS livrable_item_id INT NULL AFTER plan_mensuel_id;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS objectif_publication TEXT NULL AFTER message;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS cible_libre TEXT NULL AFTER objectif_publication;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS reseau_cible VARCHAR(120) NULL AFTER cible_libre;

INSERT INTO abonnements (nom, type_projet, canal_principal, duree_mois, sea_budget, quota_videos_mensuel, quota_visuels_mensuel, notes, statut)
SELECT 'Abonnement editorial Starter', 'Abonnement mensuel', 'Instagram', 3, NULL, 2, 4, 'Pack recurrent pour clients avec cadence simple.', 'Actif'
WHERE NOT EXISTS (SELECT 1 FROM abonnements WHERE nom = 'Abonnement editorial Starter');

INSERT INTO abonnements (nom, type_projet, canal_principal, duree_mois, sea_budget, quota_videos_mensuel, quota_visuels_mensuel, notes, statut)
SELECT 'Abonnement editorial Growth', 'Abonnement mixte', 'Instagram / TikTok', 6, 150000, 4, 8, 'Pack mixte avec videos, visuels et amplification marketing.', 'Actif'
WHERE NOT EXISTS (SELECT 1 FROM abonnements WHERE nom = 'Abonnement editorial Growth');

-- Table plans_mensuels
CREATE TABLE IF NOT EXISTS plans_mensuels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projet_id INT NOT NULL,
    periode_mois DATE NOT NULL,
    index_mois INT DEFAULT 1,
    contexte_mois TEXT NULL,
    objectif_mois TEXT NULL,
    temps_forts_mois TEXT NULL,
    videos_prevus INT DEFAULT 0,
    videos_livres INT DEFAULT 0,
    visuels_prevus INT DEFAULT 0,
    visuels_livres INT DEFAULT 0,
    livrables_prevus INT DEFAULT 0,
    livrables_livres INT DEFAULT 0,
    statut ENUM('Planifie','En cours','Partiel','Termine') DEFAULT 'Planifie',
    UNIQUE KEY unique_plan_projet_mois (projet_id, periode_mois),
    FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
);

ALTER TABLE plans_mensuels ADD COLUMN IF NOT EXISTS contexte_mois TEXT NULL AFTER index_mois;
ALTER TABLE plans_mensuels ADD COLUMN IF NOT EXISTS objectif_mois TEXT NULL AFTER contexte_mois;
ALTER TABLE plans_mensuels ADD COLUMN IF NOT EXISTS temps_forts_mois TEXT NULL AFTER objectif_mois;

-- Table livrable_items
CREATE TABLE IF NOT EXISTS livrable_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projet_id INT NOT NULL,
    plan_mensuel_id INT NOT NULL,
    type_livrable ENUM('Video','Visuel') NOT NULL,
    sous_type VARCHAR(80) NULL,
    nombre_pages INT DEFAULT 1,
    numero_ordre INT NOT NULL,
    titre VARCHAR(190) NOT NULL,
    statut ENUM('Planifie','En production','Pret','Publie') DEFAULT 'Planifie',
    date_prevue DATE NULL,
    canal VARCHAR(100) NULL,
    pieces_jointes JSON NULL,
    UNIQUE KEY unique_livrable_plan_type_numero (plan_mensuel_id, type_livrable, numero_ordre),
    FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_mensuel_id) REFERENCES plans_mensuels(id) ON DELETE CASCADE
);

-- Table taches_pipeline
CREATE TABLE IF NOT EXISTS taches_pipeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projet_id INT NOT NULL,
    plan_mensuel_id INT NULL,
    livrable_item_id INT NULL,
    parent_task_id INT NULL,
    titre VARCHAR(190) NOT NULL,
    type_tache ENUM('Onboarding','Strategie','Calendrier','Brief','Script','Production','Tournage','Montage','Validation interne','Validation client','Publication','Interactions','Collecte KPI','Reporting','Optimisation') NOT NULL,
    auteur_id INT NULL,
    statut ENUM('Bloquee','A faire','En cours','Terminee','Annulee') DEFAULT 'Bloquee',
    deadline DATE NULL,
    ordre_pipeline INT DEFAULT 1,
    notes TEXT NULL,
    fichiers_livres JSON NULL,
    validation_decision ENUM('En attente','Valide','Non valide') DEFAULT 'En attente',
    note_sur_10 TINYINT NULL,
    validation_commentaire TEXT NULL,
    publication_reseaux JSON NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_mensuel_id) REFERENCES plans_mensuels(id) ON DELETE CASCADE,
    FOREIGN KEY (livrable_item_id) REFERENCES livrable_items(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_task_id) REFERENCES taches_pipeline(id) ON DELETE SET NULL,
    FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL
);

ALTER TABLE contenus ADD COLUMN IF NOT EXISTS sous_type VARCHAR(80) NULL AFTER type;
ALTER TABLE contenus ADD COLUMN IF NOT EXISTS nombre_pages_carrousel INT DEFAULT 1 AFTER sous_type;
ALTER TABLE contenus MODIFY type ENUM('Visuel','Video') DEFAULT 'Visuel';

ALTER TABLE briefs ADD COLUMN IF NOT EXISTS nature_brief ENUM('Script video','Brief visuel') DEFAULT 'Brief visuel' AFTER contenu_id;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS livrable_item_id INT NULL AFTER contenu_id;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS format_livrable VARCHAR(100) NULL AFTER nature_brief;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS nombre_pages_carrousel INT DEFAULT 1 AFTER format_livrable;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS pdf_requis TINYINT(1) DEFAULT 0 AFTER nombre_pages_carrousel;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS source_requis TINYINT(1) DEFAULT 0 AFTER pdf_requis;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS titre_brief VARCHAR(190) NULL AFTER source_requis;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS details_message TEXT NULL AFTER titre_brief;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS informations_complementaires TEXT NULL AFTER details_message;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS cta VARCHAR(190) NULL AFTER informations_complementaires;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS recommandation_design TEXT NULL AFTER cta;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS description_publication TEXT NULL AFTER recommandation_design;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS hook_video VARCHAR(255) NULL AFTER description_publication;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS plan_script TEXT NULL AFTER hook_video;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS pages_carrousel JSON NULL AFTER plan_script;
ALTER TABLE briefs ADD COLUMN IF NOT EXISTS pieces_jointes JSON NULL AFTER responsable;

ALTER TABLE livrable_items ADD COLUMN IF NOT EXISTS sous_type VARCHAR(80) NULL AFTER type_livrable;
ALTER TABLE livrable_items ADD COLUMN IF NOT EXISTS nombre_pages INT DEFAULT 1 AFTER sous_type;
ALTER TABLE livrable_items ADD COLUMN IF NOT EXISTS pieces_jointes JSON NULL AFTER canal;

ALTER TABLE taches_pipeline ADD COLUMN IF NOT EXISTS fichiers_livres JSON NULL AFTER notes;
ALTER TABLE taches_pipeline ADD COLUMN IF NOT EXISTS validation_decision ENUM('En attente','Valide','Non valide') DEFAULT 'En attente' AFTER fichiers_livres;
ALTER TABLE taches_pipeline ADD COLUMN IF NOT EXISTS note_sur_10 TINYINT NULL AFTER validation_decision;
ALTER TABLE taches_pipeline ADD COLUMN IF NOT EXISTS validation_commentaire TEXT NULL AFTER note_sur_10;
ALTER TABLE taches_pipeline ADD COLUMN IF NOT EXISTS publication_reseaux JSON NULL AFTER validation_commentaire;
ALTER TABLE taches_pipeline MODIFY type_tache ENUM('Onboarding','Strategie','Calendrier','Brief','Script','Production','Tournage','Montage','Validation interne','Validation client','Publication','Interactions','Collecte KPI','Reporting','Optimisation') NOT NULL;

-- Table reporting_metrics
CREATE TABLE IF NOT EXISTS reporting_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    contenu_id INT NULL,
    plateforme VARCHAR(80) NOT NULL,
    date_collecte DATE NULL,
    impressions INT DEFAULT 0,
    couverture INT DEFAULT 0,
    vues INT DEFAULT 0,
    likes INT DEFAULT 0,
    commentaires INT DEFAULT 0,
    partages INT DEFAULT 0,
    clics INT DEFAULT 0,
    ctr DECIMAL(10,4) NULL,
    engagement_rate DECIMAL(10,4) NULL,
    avg_watch_time DECIMAL(10,4) NULL,
    sauvegardes INT DEFAULT 0,
    abonnes_gagnes INT DEFAULT 0,
    kpi_payload JSON NULL,
    url_publication VARCHAR(255) NULL,
    FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE,
    FOREIGN KEY (contenu_id) REFERENCES contenus(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS documentation_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    titre VARCHAR(190) NOT NULL,
    categorie VARCHAR(100) NULL,
    fichier_path VARCHAR(255) NOT NULL,
    fichier_nom VARCHAR(190) NOT NULL,
    date_document DATE NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS upload_trash_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_path VARCHAR(255) NOT NULL,
    trash_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(190) NULL,
    size_bytes BIGINT DEFAULT 0,
    module_key VARCHAR(100) NULL,
    source_table VARCHAR(120) NULL,
    source_record_id INT NULL,
    deleted_by INT NULL,
    deleted_at DATETIME NOT NULL,
    purged_at DATETIME NULL,
    status ENUM('trashed','purged') NOT NULL DEFAULT 'trashed',
    INDEX idx_upload_trash_status_deleted_at (status, deleted_at),
    INDEX idx_upload_trash_source (source_table, source_record_id)
);

INSERT INTO users (nom, email, password, role, statut)
VALUES ('Administrateur', 'admin@jaxe-ops.local', '$2y$10$cMFc4v3H72csViKXC7MxX.ckUW0C9FKDkFd2w130YaMVT1A1XzXre', 'Admin', 'Actif')
ON DUPLICATE KEY UPDATE nom = VALUES(nom), password = VALUES(password), role = VALUES(role), statut = VALUES(statut);

INSERT INTO users (nom, email, password, role, statut)
VALUES
('Afi Charge de communication', 'afi.cc@jaxe-ops.local', '$2y$10$cMFc4v3H72csViKXC7MxX.ckUW0C9FKDkFd2w130YaMVT1A1XzXre', 'CC', 'Actif'),
('Nadia Charge clientele', 'nadia.clientele@jaxe-ops.local', '$2y$10$cMFc4v3H72csViKXC7MxX.ckUW0C9FKDkFd2w130YaMVT1A1XzXre', 'Clientele', 'Actif'),
('Komi Community Manager', 'komi.cm@jaxe-ops.local', '$2y$10$cMFc4v3H72csViKXC7MxX.ckUW0C9FKDkFd2w130YaMVT1A1XzXre', 'CM', 'Actif'),
('Eyram Createur', 'eyram.createur@jaxe-ops.local', '$2y$10$cMFc4v3H72csViKXC7MxX.ckUW0C9FKDkFd2w130YaMVT1A1XzXre', 'Createur', 'Actif')
ON DUPLICATE KEY UPDATE nom = VALUES(nom), password = VALUES(password), role = VALUES(role), statut = VALUES(statut);

-- Donnees de test: Galerie PHIL'S
INSERT INTO clients (nom, entreprise, secteur, telephone, email, statut)
SELECT 'PHILS', 'Galerie PHIL''S', 'Mode et retail vestimentaire', NULL, NULL, 'Actif'
WHERE NOT EXISTS (
        SELECT 1 FROM clients WHERE entreprise = 'Galerie PHIL''S'
);

INSERT INTO offres (client_id, produit_service, description, prix, packages, avantage_offre, usp, positionnement)
SELECT c.id,
             'Collection elegante et conseil vestimentaire',
             'Boutique de vetements classe a Lome pour toutes les occasions, avec conseil vestimentaire et experience showroom.',
             NULL,
             JSON_OBJECT(
                     'costumes', 'Selections de costumes pour occasions et image professionnelle',
                     'robes', 'Robes elegantes pour ceremonies et sorties',
                     'accessoires', 'Noeuds papillons, manchettes, sacs et chaussures',
                     'visite', 'Reservation de visite personnalisee en boutique'
             ),
             'Accompagnement sur mesure, qualite, confiance et distinction.',
             'L elegance qui vous distingue.',
             'Accessible premium'
FROM clients c
WHERE c.entreprise = 'Galerie PHIL''S'
    AND NOT EXISTS (
            SELECT 1 FROM offres o WHERE o.client_id = c.id AND o.produit_service = 'Collection elegante et conseil vestimentaire'
    );

INSERT INTO personas (client_id, nom_persona, age, profession, revenu, localisation, objectif, probleme, craintes, desirs, declencheur_achat, freins, valeur_percue, garanties, canaux, horaires, priorite)
SELECT c.id,
             'Manager urbain soigne',
             35,
             'Manager / decideur',
             850000,
             'Lome',
             'Avoir une allure elegante et credible pour le travail et les evenements.',
             'Manque de temps pour composer une tenue vraiment classe.',
             'Choisir une tenue qui ne valorise pas son image.',
             'Projeter la confiance, le statut et la distinction.',
             'Besoin d une tenue pour rendez-vous, ceremonies ou representation.',
             'Peur de payer cher pour un rendu banal.',
             'Conseil personnalise, qualite visible et image rehaussee.',
             'Accueil en boutique, selection adaptee et confiance sur le style.',
             'Facebook, Instagram, WhatsApp, visite boutique',
             '10h-19h',
             'Haute'
FROM clients c
WHERE c.entreprise = 'Galerie PHIL''S'
    AND NOT EXISTS (
            SELECT 1 FROM personas p WHERE p.client_id = c.id AND p.nom_persona = 'Manager urbain soigne'
    );

INSERT INTO messages_marketing (persona_id, angle, hook, message, preuve, offre_associee, call_to_action)
SELECT p.id,
             'Prestance',
             'Votre tenue parle avant vous.',
             'Chez PHIL''S, chaque collection et chaque conseil sont penses pour sublimer votre personnalite et affirmer votre presence.',
             'Plus de dix ans d experience, 4 galeries a travers le pays et une clientele fidele.',
             o.id,
             'Reserver une visite en boutique'
FROM personas p
JOIN clients c ON c.id = p.client_id
LEFT JOIN offres o ON o.client_id = c.id AND o.produit_service = 'Collection elegante et conseil vestimentaire'
WHERE c.entreprise = 'Galerie PHIL''S'
    AND p.nom_persona = 'Manager urbain soigne'
    AND NOT EXISTS (
            SELECT 1 FROM messages_marketing mm WHERE mm.persona_id = p.id AND mm.hook = 'Votre tenue parle avant vous.'
    );

INSERT INTO campagnes (client_id, nom, date_debut, date_fin, objectif, persona_cible, type, statut)
SELECT c.id,
             'Campagne image et elegance PHILS',
             CURDATE(),
             DATE_ADD(CURDATE(), INTERVAL 30 DAY),
             'Conversion',
             p.id,
             'Commercial',
             'Planifiee'
FROM clients c
LEFT JOIN personas p ON p.client_id = c.id AND p.nom_persona = 'Manager urbain soigne'
WHERE c.entreprise = 'Galerie PHIL''S'
    AND NOT EXISTS (
            SELECT 1 FROM campagnes ca WHERE ca.client_id = c.id AND ca.nom = 'Campagne image et elegance PHILS'
    );

INSERT INTO contenus (campagne_id, persona_id, type, sujet, message, statut, responsable)
SELECT ca.id,
             p.id,
             'Carrousel',
             '3 looks pour imposer votre presence au bureau et en ceremonie',
             'Des selections uniques pour ceux qui recherchent elegance, personnalite et distinction.',
             'Strategique defini',
             'CM'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne image et elegance PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM contenus ct WHERE ct.campagne_id = ca.id AND ct.sujet = '3 looks pour imposer votre presence au bureau et en ceremonie'
    );

INSERT INTO calendrier_contenus (campagne_id, contenu_id, date_publication, heure_publication, canal, statut, note)
SELECT ca.id,
             ct.id,
             DATE_ADD(CURDATE(), INTERVAL 3 DAY),
             '10:00:00',
             'Instagram',
             'Planifie',
             'Mettre en avant les costumes, accessoires et la reservation de visite boutique.'
FROM campagnes ca
JOIN contenus ct ON ct.campagne_id = ca.id
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne image et elegance PHILS'
    AND ct.sujet = '3 looks pour imposer votre presence au bureau et en ceremonie'
    AND NOT EXISTS (
            SELECT 1 FROM calendrier_contenus cc WHERE cc.contenu_id = ct.id AND cc.canal = 'Instagram'
    );

INSERT INTO reportings (campagne_id, performance, recommandations, actions_prevues)
SELECT ca.id,
             'Donnee de test: campagne en preparation, KPI a renseigner apres lancement.',
             'Mesurer les demandes de visite boutique et les interactions sur les looks presentes.',
             'Ajouter des contenus reels et stories autour des collections et des reservations.'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne image et elegance PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM reportings r WHERE r.campagne_id = ca.id
    );

-- Cas pratique: PHIL'S - Campagne referencement naturel sur 3 mois
INSERT INTO campagnes (client_id, nom, date_debut, date_fin, objectif, persona_cible, type, statut)
SELECT c.id,
             'Campagne SEO tutoriels PHILS',
             CURDATE(),
             DATE_ADD(CURDATE(), INTERVAL 3 MONTH),
             'Augmenter le nombre d abonnes et la valeur percue via des videos tutoriels optimisees pour le referencement naturel',
             p.id,
             'Non-commercial',
             'Planifiee'
FROM clients c
LEFT JOIN personas p ON p.client_id = c.id AND p.nom_persona = 'Manager urbain soigne'
WHERE c.entreprise = 'Galerie PHIL''S'
    AND NOT EXISTS (
            SELECT 1 FROM campagnes ca WHERE ca.client_id = c.id AND ca.nom = 'Campagne SEO tutoriels PHILS'
    );

INSERT INTO tunnel_conversion (campagne_id, persona_id, etape, objectif, message, type_contenu, canal, CTA, KPI)
SELECT ca.id,
             p.id,
             'Decouverte',
             'Attirer une audience qualifiee via des videos tutorielles utiles et bien referencees.',
             'Des conseils pratiques de style pour aider chaque abonne a mieux se presenter et a gagner en distinction.',
             'Video',
             'Instagram / Facebook / YouTube Shorts',
             'S abonner a la page',
             'Nouveaux abonnes et vues organiques'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM tunnel_conversion tc WHERE tc.campagne_id = ca.id AND tc.etape = 'Decouverte' AND tc.KPI = 'Nouveaux abonnes et vues organiques'
    );

INSERT INTO tunnel_conversion (campagne_id, persona_id, etape, objectif, message, type_contenu, canal, CTA, KPI)
SELECT ca.id,
             p.id,
             'Consideration',
             'Construire la credibilite de PHIL''S comme reference locale en elegance masculine et feminine.',
             'Chez PHIL''S, s habiller est un langage: chaque video aide a mieux choisir, associer et valoriser ses tenues.',
             'Video',
             'Instagram / Facebook / Blog',
             'Enregistrer et partager la video',
             'Temps de visionnage et partages'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM tunnel_conversion tc WHERE tc.campagne_id = ca.id AND tc.etape = 'Consideration' AND tc.KPI = 'Temps de visionnage et partages'
    );

INSERT INTO tunnel_conversion (campagne_id, persona_id, etape, objectif, message, type_contenu, canal, CTA, KPI)
SELECT ca.id,
             p.id,
             'Fidelisation',
             'Transformer les nouveaux abonnes en audience reguliere qui attend les prochaines videos.',
             'Rejoignez le cercle prive de l elegance et recevez regulièrement des conseils vestimentaires concrets.',
             'Video',
             'Instagram / WhatsApp / Newsletter',
             'Activer les notifications et reserver une visite',
             'Abonnes recurrents et demandes de visite'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM tunnel_conversion tc WHERE tc.campagne_id = ca.id AND tc.etape = 'Fidelisation' AND tc.KPI = 'Abonnes recurrents et demandes de visite'
    );

INSERT INTO contenus (campagne_id, persona_id, type, sujet, message, statut, responsable)
SELECT ca.id, p.id, 'Video', content_data.sujet, content_data.message, 'Strategique defini', 'CM'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
JOIN (
        SELECT 1 AS ordre, 'Bien choisir un costume selon sa morphologie' AS sujet, 'Tutoriel pratique pour aider les abonnes a mieux choisir une coupe elegante et valorisante.' AS message
        UNION ALL SELECT 2, '3 erreurs qui ruinent une tenue professionnelle', 'Contenu educatif pour corriger les fautes de style les plus frequentes.'
        UNION ALL SELECT 3, 'Comment associer costume, chemise et accessoires', 'Video utile pour augmenter la valeur percue grace a des conseils concrets et faciles a appliquer.'
        UNION ALL SELECT 4, 'Avoir l air elegant sans surconsommer', 'Conseils simples pour paraitre plus distingue avec des choix strategiques.'
        UNION ALL SELECT 5, 'Choisir ses chaussures selon l occasion', 'Tutoriel pour relier style, contexte et confiance en soi.'
        UNION ALL SELECT 6, 'Noeud papillon ou cravate: quand porter quoi ?', 'Video explicative pour aider l audience a faire le bon choix selon l evenement.'
        UNION ALL SELECT 7, 'Comment s habiller pour un mariage avec style', 'Contenu inspirationnel et pratique autour des tenues d occasion.'
        UNION ALL SELECT 8, 'Les indispensables du dressing d un homme distingue', 'Video de valeur pour renforcer l image d expert de PHIL''S.'
        UNION ALL SELECT 9, 'Comment entretenir ses pieces pour les faire durer', 'Tutoriel utile pour l entretien des vetements et accessoires haut de gamme.'
        UNION ALL SELECT 10, 'Construire son image personnelle par le style', 'Contenu de fond pour lier presentation, confiance et perception sociale.'
        UNION ALL SELECT 11, 'Bien choisir ses accessoires pour marquer sa presence', 'Conseils sur les accessoires qui elev ent une tenue sans en faire trop.'
        UNION ALL SELECT 12, 'Passer du bureau a une sortie avec le meme look', 'Video transformation pour montrer la polyvalence d une garde-robe bien pensee.'
) AS content_data
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM contenus ct WHERE ct.campagne_id = ca.id AND ct.sujet = content_data.sujet
    );

INSERT INTO briefs (contenu_id, texte_script, instructions_visuelles, format, statut, responsable)
SELECT ct.id,
             CONCAT('Script tutoriel pour: ', ct.sujet, '. Ouvrir avec une question frequente, expliquer en 3 points, montrer des exemples en boutique, conclure avec invitation a s abonner.'),
             'Format vertical. Hook dans les 3 premieres secondes. Montrer les pieces, les details, puis une conclusion face camera ou voix off avec CTA abonnement.',
             'Video 45 a 60 secondes',
             'A faire',
             'Createur de contenu'
FROM contenus ct
JOIN campagnes ca ON ca.id = ct.campagne_id
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM briefs b WHERE b.contenu_id = ct.id
    );

INSERT INTO calendrier_contenus (campagne_id, contenu_id, date_publication, heure_publication, canal, statut, note)
SELECT ca.id,
             ct.id,
             DATE_ADD(CURDATE(), INTERVAL schedule_data.jours DAY),
             '18:30:00',
             'Instagram',
             'Planifie',
             CONCAT('Publication organique hebdomadaire. CTA principal: s abonner pour plus de conseils. Mois ', schedule_data.mois)
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
JOIN contenus ct ON ct.campagne_id = ca.id
JOIN (
        SELECT 'Bien choisir un costume selon sa morphologie' AS sujet, 0 AS jours, 1 AS mois
        UNION ALL SELECT '3 erreurs qui ruinent une tenue professionnelle', 7, 1
        UNION ALL SELECT 'Comment associer costume, chemise et accessoires', 14, 1
        UNION ALL SELECT 'Avoir l air elegant sans surconsommer', 21, 1
        UNION ALL SELECT 'Choisir ses chaussures selon l occasion', 31, 2
        UNION ALL SELECT 'Noeud papillon ou cravate: quand porter quoi ?', 38, 2
        UNION ALL SELECT 'Comment s habiller pour un mariage avec style', 45, 2
        UNION ALL SELECT 'Les indispensables du dressing d un homme distingue', 52, 2
        UNION ALL SELECT 'Comment entretenir ses pieces pour les faire durer', 62, 3
        UNION ALL SELECT 'Construire son image personnelle par le style', 69, 3
        UNION ALL SELECT 'Bien choisir ses accessoires pour marquer sa presence', 76, 3
        UNION ALL SELECT 'Passer du bureau a une sortie avec le meme look', 83, 3
) AS schedule_data ON schedule_data.sujet = ct.sujet
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM calendrier_contenus cc WHERE cc.contenu_id = ct.id AND cc.canal = 'Instagram'
    );

INSERT INTO reportings (campagne_id, performance, recommandations, actions_prevues)
SELECT ca.id,
             'Objectif de pilotage: + abonnes, hausse des vues organiques, meilleur engagement et perception d expertise.',
             'Suivre chaque semaine les vues, le watch time, les sauvegardes, les partages, les nouveaux abonnes et les demandes de visite boutique.',
             'Produire 4 videos tutoriels par mois pendant 3 mois, recycler les meilleurs extraits en reels, stories et mini articles SEO.'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'Galerie PHIL''S'
    AND ca.nom = 'Campagne SEO tutoriels PHILS'
    AND NOT EXISTS (
            SELECT 1 FROM reportings r WHERE r.campagne_id = ca.id
    );

INSERT INTO projets (client_id, campagne_id, nom, type_projet, canal_principal, date_debut, date_fin, duree_mois, sea_budget, quota_videos_mensuel, quota_visuels_mensuel, charge_compte_id, cm_id, createur_id, statut, notes)
SELECT c.id,
             ca.id,
             'Abonnement editorial PHIL''S - 4 videos / mois',
             'Abonnement mensuel',
             'Instagram',
             CURDATE(),
    LAST_DAY(DATE_ADD(CURDATE(), INTERVAL 2 MONTH)),
    3,
             NULL,
             4,
             0,
             cc.id,
             cm.id,
             cr.id,
             'Actif',
             'Projet d abonnement mensuel. Le pipeline doit couvrir strategie, calendrier, briefs, production, validation, publication et collecte KPI.'
FROM clients c
JOIN campagnes ca ON ca.client_id = c.id AND ca.nom = 'Campagne SEO tutoriels PHILS'
LEFT JOIN users cc ON cc.email = 'afi.cc@jaxe-ops.local'
LEFT JOIN users cm ON cm.email = 'komi.cm@jaxe-ops.local'
LEFT JOIN users cr ON cr.email = 'eyram.createur@jaxe-ops.local'
WHERE c.entreprise = 'Galerie PHIL''S'
    AND NOT EXISTS (
            SELECT 1 FROM projets p WHERE p.client_id = c.id AND p.nom = 'Abonnement editorial PHIL''S - 4 videos / mois'
    );

UPDATE projets
SET date_fin = LAST_DAY(DATE_ADD(date_debut, INTERVAL 2 MONTH)),
    duree_mois = 3,
    quota_videos_mensuel = 4,
    quota_visuels_mensuel = 0,
    statut = 'Actif'
WHERE nom = 'Abonnement editorial PHIL''S - 4 videos / mois';

INSERT INTO projets (client_id, campagne_id, nom, type_projet, canal_principal, date_debut, date_fin, duree_mois, sea_budget, quota_videos_mensuel, quota_visuels_mensuel, charge_compte_id, cm_id, createur_id, statut, notes)
SELECT c.id,
             NULL,
             'Sprint SEA ESTABAT - inscriptions',
             'SEA ponctuel',
             'Meta Ads',
             CURDATE(),
             DATE_ADD(CURDATE(), INTERVAL 21 DAY),
    1,
             350000,
             0,
             2,
             cc.id,
             cm.id,
             cr.id,
             'Actif',
             'Exemple de projet ponctuel SEA pour distinguer le pilotage des abonnements et des activations courtes.'
FROM clients c
LEFT JOIN users cc ON cc.email = 'afi.cc@jaxe-ops.local'
LEFT JOIN users cm ON cm.email = 'komi.cm@jaxe-ops.local'
LEFT JOIN users cr ON cr.email = 'eyram.createur@jaxe-ops.local'
WHERE c.entreprise = 'ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM projets p WHERE p.client_id = c.id AND p.nom = 'Sprint SEA ESTABAT - inscriptions'
    );

UPDATE projets
SET duree_mois = 1,
    quota_videos_mensuel = 0,
    quota_visuels_mensuel = 2,
    statut = 'Actif'
WHERE nom = 'Sprint SEA ESTABAT - inscriptions';

-- Donnees de test: ESTABAT
INSERT INTO clients (nom, entreprise, secteur, telephone, email, statut)
SELECT 'ESTABAT', 'ESTABAT', 'Enseignement superieur', '+228 91312450', 'contact@estabat.org', 'Actif'
WHERE NOT EXISTS (
        SELECT 1 FROM clients WHERE entreprise = 'ESTABAT'
);

INSERT INTO offres (client_id, produit_service, description, prix, packages, avantage_offre, usp, positionnement)
SELECT c.id,
             'Formations en architecture et topographie',
             'Ecole superieure d architecture et de topographie au Togo, avec BTS, Licence et cycles modulaires en cours de jour et de soir.',
             NULL,
             JSON_OBJECT(
                     'architecture', 'BTS et Licence en Architecture',
                     'topographie', 'BT, BTS et Licence en Topographie',
                     'modules', 'Cycles modulaires et perfectionnement professionnels'
             ),
             'Approche professionnalisante, stages, partenariats et insertion directe.',
             'Former des architectes, urbanistes et geomètres-topographes capables de batir l Afrique de demain.',
             'Institutionnel premium'
FROM clients c
WHERE c.entreprise = 'ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM offres o WHERE o.client_id = c.id AND o.produit_service = 'Formations en architecture et topographie'
    );

INSERT INTO personas (client_id, nom_persona, age, profession, revenu, localisation, objectif, probleme, craintes, desirs, declencheur_achat, freins, valeur_percue, garanties, canaux, horaires, priorite)
SELECT c.id,
             'Nouveau bachelier ambitieux',
             20,
             'Etudiant',
             0,
             'Togo et sous-region',
             'Trouver une formation professionnalisante avec debouches solides.',
             'Difficulte a choisir une ecole credible et utile pour son avenir.',
             'Investir dans une formation peu reconnue ou sans emploi a la sortie.',
             'Entrer dans un secteur porteur avec une vraie insertion professionnelle.',
             'Periode d inscription et comparaison des etablissements.',
             'Cout, confiance et manque d informations claires.',
             'Diplomes reconnus, stages, experts praticiens et apprentissage concret.',
             'Accreditation, reconnaissance et accompagnement vers l emploi.',
             'Facebook, Instagram, LinkedIn, site web, telephone',
             '08h-18h',
             'Haute'
FROM clients c
WHERE c.entreprise = 'ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM personas p WHERE p.client_id = c.id AND p.nom_persona = 'Nouveau bachelier ambitieux'
    );

INSERT INTO messages_marketing (persona_id, angle, hook, message, preuve, offre_associee, call_to_action)
SELECT p.id,
             'Preuve et employabilite',
             'Choisissez une formation reconnue et orientee terrain.',
             'ESTABAT propose des formations en architecture et topographie avec un equilibre entre theorie, pratique et projets reels pour faciliter l insertion professionnelle.',
             'Diplomes reconnus par l Etat togolais et le CAMES, corps enseignant expert, stages structures et reseau de partenaires.',
             o.id,
             'S inscrire maintenant'
FROM personas p
JOIN clients c ON c.id = p.client_id
LEFT JOIN offres o ON o.client_id = c.id AND o.produit_service = 'Formations en architecture et topographie'
WHERE c.entreprise = 'ESTABAT'
    AND p.nom_persona = 'Nouveau bachelier ambitieux'
    AND NOT EXISTS (
            SELECT 1 FROM messages_marketing mm WHERE mm.persona_id = p.id AND mm.hook = 'Choisissez une formation reconnue et orientee terrain.'
    );

INSERT INTO campagnes (client_id, nom, date_debut, date_fin, objectif, persona_cible, type, statut)
SELECT c.id,
             'Campagne inscriptions ESTABAT',
             CURDATE(),
             DATE_ADD(CURDATE(), INTERVAL 45 DAY),
             'Conversion',
             p.id,
             'Commercial',
             'Planifiee'
FROM clients c
LEFT JOIN personas p ON p.client_id = c.id AND p.nom_persona = 'Nouveau bachelier ambitieux'
WHERE c.entreprise = 'ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM campagnes ca WHERE ca.client_id = c.id AND ca.nom = 'Campagne inscriptions ESTABAT'
    );

INSERT INTO tunnel_conversion (campagne_id, persona_id, etape, objectif, message, type_contenu, canal, CTA, KPI)
SELECT ca.id,
             p.id,
             'Decouverte',
             'Faire connaitre les filieres et la promesse employabilite de l ecole.',
             'Former des professionnels capables de batir l Afrique de demain avec competence, innovation et responsabilite.',
             'Video',
             'Facebook',
             'Decouvrir nos formations',
             'Portee et clics'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
WHERE c.entreprise = 'ESTABAT'
    AND ca.nom = 'Campagne inscriptions ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM tunnel_conversion tc WHERE tc.campagne_id = ca.id AND tc.etape = 'Decouverte'
    );

INSERT INTO contenus (campagne_id, persona_id, type, sujet, message, statut, responsable)
SELECT ca.id,
             p.id,
             'Video',
             'Pourquoi choisir ESTABAT apres le bac ?',
             'Accreditation, expertise des enseignants, approche professionnalisante et insertion directe.',
             'Strategique defini',
             'CM'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
LEFT JOIN personas p ON p.id = ca.persona_cible
WHERE c.entreprise = 'ESTABAT'
    AND ca.nom = 'Campagne inscriptions ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM contenus ct WHERE ct.campagne_id = ca.id AND ct.sujet = 'Pourquoi choisir ESTABAT apres le bac ?'
    );

INSERT INTO briefs (contenu_id, texte_script, instructions_visuelles, format, statut, responsable)
SELECT ct.id,
             'Script de test: ouvrir sur la question du choix d orientation, montrer campus, formations, ateliers, partenariats et conclure sur l inscription.',
             'Utiliser visuels campus, etudiants, ateliers et mentionner Tokoin-Todman, 47 Boulevard de la Kara.',
             'Video 45 secondes',
             'A faire',
             'Createur de contenu'
FROM contenus ct
JOIN campagnes ca ON ca.id = ct.campagne_id
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'ESTABAT'
    AND ct.sujet = 'Pourquoi choisir ESTABAT apres le bac ?'
    AND NOT EXISTS (
            SELECT 1 FROM briefs b WHERE b.contenu_id = ct.id
    );

INSERT INTO calendrier_contenus (campagne_id, contenu_id, date_publication, heure_publication, canal, statut, note)
SELECT ca.id,
             ct.id,
             DATE_ADD(CURDATE(), INTERVAL 5 DAY),
             '09:00:00',
             'Facebook',
             'Planifie',
             'Ajouter CTA vers les inscriptions et mentionner le telephone +228 91312450.'
FROM campagnes ca
JOIN contenus ct ON ct.campagne_id = ca.id
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'ESTABAT'
    AND ca.nom = 'Campagne inscriptions ESTABAT'
    AND ct.sujet = 'Pourquoi choisir ESTABAT apres le bac ?'
    AND NOT EXISTS (
            SELECT 1 FROM calendrier_contenus cc WHERE cc.contenu_id = ct.id AND cc.canal = 'Facebook'
    );

INSERT INTO reportings (campagne_id, performance, recommandations, actions_prevues)
SELECT ca.id,
             'Donnee de test: campagne d inscriptions en preparation.',
             'Suivre les clics sur les pages formations et inscriptions, puis comparer les demandes d informations par telephone et email.',
             'Produire un carrousel des filieres et une video campus pour renforcer la preuve sociale.'
FROM campagnes ca
JOIN clients c ON c.id = ca.client_id
WHERE c.entreprise = 'ESTABAT'
    AND ca.nom = 'Campagne inscriptions ESTABAT'
    AND NOT EXISTS (
            SELECT 1 FROM reportings r WHERE r.campagne_id = ca.id
    );
