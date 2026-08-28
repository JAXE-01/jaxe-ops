<?php
class ModuleRegistry {
    public static function roleOptions() {
        return [
            'Admin' => 'Admin',
            'CC' => 'Charge de communication',
            'Clientele' => 'Charge de clientele',
            'CM' => 'Community manager',
            'Createur' => 'Createur',
            'Cadreur' => 'Cadreur',
            'Designer' => 'Designer',
            'Videaste' => 'Videaste'
        ];
    }

    public static function all() {
        return [
            'user' => [
                'route' => 'user',
                'label' => 'Utilisateurs',
                'table' => 'users',
                'primaryKey' => 'id',
                'titleField' => 'nom',
                'hidden' => true,
                'listFields' => ['id', 'nom', 'email', 'role', 'secondary_roles', 'statut'],
                'formFields' => [
                    'nom' => ['label' => 'Nom', 'type' => 'text', 'required' => true],
                    'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
                    'password' => ['label' => 'Mot de passe', 'type' => 'password', 'required' => true],
                    'role' => ['label' => 'Role', 'type' => 'select', 'options' => self::roleOptions()],
                    'secondary_roles' => ['label' => 'Roles secondaires', 'type' => 'multiselect', 'options' => self::roleOptions()],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Actif' => 'Actif', 'Inactif' => 'Inactif']]
                ]
            ],
            'client' => [
                'route' => 'client',
                'label' => 'Clients',
                'table' => 'clients',
                'primaryKey' => 'id',
                'titleField' => 'nom',
                'listFields' => ['id', 'nom', 'entreprise', 'relationship_mode', 'secteur', 'telephone', 'email', 'statut'],
                'formFields' => [
                    'nom' => ['label' => 'Nom', 'type' => 'text', 'required' => true],
                    'entreprise' => ['label' => 'Entreprise', 'type' => 'text'],
                    'secteur' => ['label' => 'Secteur', 'type' => 'text'],
                    'telephone' => ['label' => 'Telephone', 'type' => 'text'],
                    'email' => ['label' => 'Email', 'type' => 'email'],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Actif' => 'Actif', 'Inactif' => 'Inactif']]
                ]
            ],
            'offre' => [
                'route' => 'offre',
                'label' => 'Offres',
                'table' => 'offres',
                'primaryKey' => 'id',
                'titleField' => 'produit_service',
                'requireClientSelection' => true,
                'clientGroup' => ['path' => [['module' => 'client', 'field' => 'client_id']]],
                'listOrder' => ['client_id' => 'ASC', 'produit_service' => 'ASC'],
                'listFields' => ['client_id', 'produit_service', 'description', 'prix', 'positionnement'],
                'formFields' => [
                    'client_id' => ['label' => 'Client', 'type' => 'relation', 'module' => 'client'],
                    'produit_service' => ['label' => 'Produit / Service', 'type' => 'text', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                    'prix' => ['label' => 'Prix', 'type' => 'number', 'step' => '0.01'],
                    'packages' => ['label' => 'Packages (JSON)', 'type' => 'json', 'nullable' => true],
                    'avantage_offre' => ['label' => 'Avantage offre', 'type' => 'textarea'],
                    'usp' => ['label' => 'USP', 'type' => 'textarea'],
                    'positionnement' => ['label' => 'Positionnement', 'type' => 'text']
                ]
            ],
            'persona' => [
                'route' => 'persona',
                'label' => 'Personas',
                'table' => 'personas',
                'primaryKey' => 'id',
                'titleField' => 'nom_persona',
                'requireClientSelection' => true,
                'clientGroup' => ['path' => [['module' => 'client', 'field' => 'client_id']]],
                'listOrder' => ['client_id' => 'ASC', 'nom_persona' => 'ASC'],
                'listFields' => ['client_id', 'nom_persona', 'profession', 'objectif', 'localisation', 'priorite'],
                'formFields' => [
                    'client_id' => ['label' => 'Client', 'type' => 'relation', 'module' => 'client'],
                    'nom_persona' => ['label' => 'Nom du persona', 'type' => 'text', 'required' => true],
                    'age' => ['label' => 'Age', 'type' => 'number'],
                    'profession' => ['label' => 'Profession', 'type' => 'text'],
                    'revenu' => ['label' => 'Revenu', 'type' => 'number', 'step' => '0.01'],
                    'localisation' => ['label' => 'Localisation', 'type' => 'text'],
                    'objectif' => ['label' => 'Objectif', 'type' => 'textarea'],
                    'probleme' => ['label' => 'Probleme', 'type' => 'textarea'],
                    'craintes' => ['label' => 'Craintes', 'type' => 'textarea'],
                    'desirs' => ['label' => 'Desirs', 'type' => 'textarea'],
                    'declencheur_achat' => ['label' => 'Declencheur achat', 'type' => 'textarea'],
                    'freins' => ['label' => 'Freins', 'type' => 'textarea'],
                    'valeur_percue' => ['label' => 'Valeur percue', 'type' => 'textarea'],
                    'garanties' => ['label' => 'Garanties', 'type' => 'textarea'],
                    'canaux' => ['label' => 'Canaux', 'type' => 'textarea'],
                    'horaires' => ['label' => 'Horaires', 'type' => 'text'],
                    'priorite' => ['label' => 'Priorite', 'type' => 'select', 'options' => ['Haute' => 'Haute', 'Moyenne' => 'Moyenne', 'Basse' => 'Basse']]
                ]
            ],
            'message-marketing' => [
                'route' => 'messages-marketing',
                'label' => 'Messages marketing',
                'table' => 'messages_marketing',
                'primaryKey' => 'id',
                'titleField' => 'hook',
                'requireClientSelection' => true,
                'clientGroup' => ['path' => [['module' => 'persona', 'field' => 'persona_id'], ['module' => 'client', 'field' => 'client_id']]],
                'listFields' => ['persona_id', 'angle', 'hook', 'message', 'offre_associee', 'call_to_action'],
                'formFields' => [
                    'persona_id' => ['label' => 'Persona', 'type' => 'relation', 'module' => 'persona'],
                    'angle' => ['label' => 'Angle', 'type' => 'text'],
                    'hook' => ['label' => 'Hook', 'type' => 'text'],
                    'message' => ['label' => 'Message', 'type' => 'textarea'],
                    'preuve' => ['label' => 'Preuve', 'type' => 'textarea'],
                    'offre_associee' => ['label' => 'Offre associee', 'type' => 'relation', 'module' => 'offre', 'nullable' => true],
                    'call_to_action' => ['label' => 'Call to action', 'type' => 'text']
                ]
            ],
            'campagne' => [
                'route' => 'campagne',
                'label' => 'Campagnes',
                'table' => 'campagnes',
                'primaryKey' => 'id',
                'titleField' => 'nom',
                'requireClientSelection' => true,
                'clientGroup' => ['path' => [['module' => 'client', 'field' => 'client_id']]],
                'listOrder' => ['client_id' => 'ASC', 'date_debut' => 'DESC', 'nom' => 'ASC'],
                'listFields' => ['client_id', 'nom', 'objectif', 'persona_cible', 'type', 'statut', 'date_debut', 'date_fin'],
                'formFields' => [
                    'client_id' => ['label' => 'Client', 'type' => 'relation', 'module' => 'client'],
                    'nom' => ['label' => 'Nom', 'type' => 'text', 'required' => true],
                    'date_debut' => ['label' => 'Date debut', 'type' => 'date'],
                    'date_fin' => ['label' => 'Date fin', 'type' => 'date'],
                    'objectif' => ['label' => 'Objectif', 'type' => 'text'],
                    'persona_cible' => ['label' => 'Persona cible', 'type' => 'relation', 'module' => 'persona', 'nullable' => true],
                    'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Commercial' => 'Commercial', 'Non-commercial' => 'Non-commercial']],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Planifiee' => 'Planifiee', 'En cours' => 'En cours', 'Terminee' => 'Terminee']]
                ]
            ],
            'tunnel-conversion' => [
                'route' => 'tunnel-conversion',
                'label' => 'Tunnel de conversion',
                'table' => 'tunnel_conversion',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'etape',
                'listFields' => ['id', 'campagne_id', 'persona_id', 'etape', 'type_contenu', 'canal', 'CTA', 'KPI'],
                'formFields' => [
                    'campagne_id' => ['label' => 'Campagne', 'type' => 'relation', 'module' => 'campagne'],
                    'persona_id' => ['label' => 'Persona', 'type' => 'relation', 'module' => 'persona'],
                    'etape' => ['label' => 'Etape', 'type' => 'select', 'options' => ['Decouverte' => 'Decouverte', 'Consideration' => 'Consideration', 'Achat' => 'Achat', 'Fidelisation' => 'Fidelisation']],
                    'objectif' => ['label' => 'Objectif', 'type' => 'textarea'],
                    'message' => ['label' => 'Message', 'type' => 'textarea'],
                    'type_contenu' => ['label' => 'Type de contenu', 'type' => 'text'],
                    'canal' => ['label' => 'Canal', 'type' => 'text'],
                    'CTA' => ['label' => 'CTA', 'type' => 'text'],
                    'KPI' => ['label' => 'KPI', 'type' => 'text']
                ]
            ],
            'contenu' => [
                'route' => 'contenu',
                'label' => 'Contenus',
                'table' => 'contenus',
                'primaryKey' => 'id',
                'titleField' => 'sujet',
                'listFields' => ['projet_id', 'plan_mensuel_id', 'campagne_id', 'persona_id', 'type', 'sous_type', 'sujet', 'objectif_publication', 'reseau_cible', 'statut', 'responsable'],
                'formFields' => [
                    'campagne_id' => ['label' => 'Campagne', 'type' => 'relation', 'module' => 'campagne', 'nullable' => true],
                    'persona_id' => ['label' => 'Persona', 'type' => 'relation', 'module' => 'persona', 'nullable' => true],
                    'projet_id' => ['label' => 'Projet', 'type' => 'relation', 'module' => 'projet', 'nullable' => true],
                    'plan_mensuel_id' => ['label' => 'Plan mensuel', 'type' => 'relation', 'module' => 'plan-mensuel', 'nullable' => true],
                    'livrable_item_id' => ['label' => 'Livrable associe', 'type' => 'relation', 'module' => 'livrable-item', 'nullable' => true],
                    'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Visuel' => 'Visuel', 'Video' => 'Video']],
                    'sous_type' => ['label' => 'Sous-type', 'type' => 'select', 'options' => ['Post simple' => 'Post simple', 'Carrousel' => 'Carrousel', 'Story' => 'Story', 'Affiche' => 'Affiche', 'Tutoriel' => 'Tutoriel', 'Interview' => 'Interview', 'Reel' => 'Reel']],
                    'nombre_pages_carrousel' => ['label' => 'Pages carrousel', 'type' => 'number'],
                    'sujet' => ['label' => 'Sujet', 'type' => 'textarea'],
                    'message' => ['label' => 'Message', 'type' => 'textarea'],
                    'objectif_publication' => ['label' => 'Objectif de la publication', 'type' => 'textarea'],
                    'cible_libre' => ['label' => 'Cible libre', 'type' => 'textarea'],
                    'reseau_cible' => ['label' => 'Reseau cible', 'type' => 'text'],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Strategique defini' => 'Strategique defini', 'Brief cree' => 'Brief cree', 'En production' => 'En production', 'Finalise' => 'Finalise', 'Publie' => 'Publie']],
                    'responsable' => ['label' => 'Responsable', 'type' => 'text']
                ]
            ],
            'brief' => [
                'route' => 'brief',
                'label' => 'Briefs',
                'table' => 'briefs',
                'primaryKey' => 'id',
                'titleField' => 'format',
                'requireClientSelection' => true,
                'clientGroup' => ['path' => [['module' => 'contenu', 'field' => 'contenu_id'], ['module' => 'campagne', 'field' => 'campagne_id'], ['module' => 'client', 'field' => 'client_id']]],
                'detailFields' => ['contenu_id', 'nature_brief', 'format_livrable', 'nombre_pages_carrousel', 'pdf_requis', 'source_requis', 'texte_script', 'instructions_visuelles', 'pieces_jointes', 'statut', 'responsable'],
                'listFields' => ['contenu_id', 'nature_brief', 'format_livrable', 'texte_script', 'statut', 'responsable'],
                'formFields' => [
                    'contenu_id' => ['label' => 'Contenu', 'type' => 'relation', 'module' => 'contenu'],
                    'nature_brief' => ['label' => 'Nature du brief', 'type' => 'select', 'options' => ['Script video' => 'Script video', 'Brief visuel' => 'Brief visuel']],
                    'format_livrable' => ['label' => 'Format livrable', 'type' => 'text'],
                    'nombre_pages_carrousel' => ['label' => 'Nombre de pages du carrousel', 'type' => 'number'],
                    'pdf_requis' => ['label' => 'PDF requis', 'type' => 'checkbox'],
                    'source_requis' => ['label' => 'PSD / PSB requis', 'type' => 'checkbox'],
                    'texte_script' => ['label' => 'Script / intention', 'type' => 'textarea'],
                    'instructions_visuelles' => ['label' => 'Instructions visuelles', 'type' => 'textarea'],
                    'format' => ['label' => 'Format', 'type' => 'text'],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['A faire' => 'A faire', 'En cours' => 'En cours', 'Valide' => 'Valide']],
                    'responsable' => ['label' => 'Responsable', 'type' => 'text'],
                    'pieces_jointes' => ['label' => 'Fichiers du brief', 'type' => 'files', 'accept' => '.png,.jpg,.jpeg,.pdf,.psd,.psb,.doc,.docx', 'extensions' => ['png', 'jpg', 'jpeg', 'pdf', 'psd', 'psb', 'doc', 'docx'], 'hint' => 'Ajoute les references, maquettes, PDF ou documents utiles au brief.']
                ]
            ],
            'calendrier-contenu' => [
                'route' => 'calendrier-contenu',
                'label' => 'Calendrier',
                'table' => 'calendrier_contenus',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'canal',
                'listFields' => ['id', 'campagne_id', 'contenu_id', 'date_publication', 'heure_publication', 'canal', 'statut'],
                'formFields' => [
                    'campagne_id' => ['label' => 'Campagne', 'type' => 'relation', 'module' => 'campagne'],
                    'contenu_id' => ['label' => 'Contenu', 'type' => 'relation', 'module' => 'contenu'],
                    'date_publication' => ['label' => 'Date de publication', 'type' => 'date'],
                    'heure_publication' => ['label' => 'Heure de publication', 'type' => 'time'],
                    'canal' => ['label' => 'Canal', 'type' => 'text'],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Planifie' => 'Planifie', 'Publie' => 'Publie', 'Annule' => 'Annule']],
                    'note' => ['label' => 'Note', 'type' => 'textarea']
                ]
            ],
            'reporting' => [
                'route' => 'reporting',
                'label' => 'Reportings',
                'table' => 'reportings',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'id',
                'listFields' => ['id', 'campagne_id', 'performance'],
                'formFields' => [
                    'campagne_id' => ['label' => 'Campagne', 'type' => 'relation', 'module' => 'campagne'],
                    'performance' => ['label' => 'Performance', 'type' => 'textarea'],
                    'recommandations' => ['label' => 'Recommandations', 'type' => 'textarea'],
                    'actions_prevues' => ['label' => 'Actions prevues', 'type' => 'textarea']
                ]
            ],
            'abonnement' => [
                'route' => 'abonnement',
                'label' => 'Abonnements',
                'table' => 'abonnements',
                'primaryKey' => 'id',
                'titleField' => 'nom',
                'hidden' => true,
                'listFields' => ['id', 'nom', 'type_projet', 'duree_mois', 'quota_videos_mensuel', 'quota_visuels_mensuel', 'statut'],
                'formFields' => [
                    'nom' => ['label' => 'Nom de l abonnement', 'type' => 'text', 'required' => true],
                    'type_projet' => ['label' => 'Type de prestation', 'type' => 'select', 'options' => ['Abonnement mensuel' => 'Abonnement mensuel', 'Abonnement mixte' => 'Abonnement mixte']],
                    'canal_principal' => ['label' => 'Canal principal', 'type' => 'text'],
                    'duree_mois' => ['label' => 'Duree standard (mois)', 'type' => 'number'],
                    'sea_budget' => ['label' => 'Budget SEA', 'type' => 'number', 'step' => '0.01'],
                    'quota_videos_mensuel' => ['label' => 'Videos / mois', 'type' => 'number'],
                    'quota_visuels_mensuel' => ['label' => 'Visuels / mois', 'type' => 'number'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea'],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Actif' => 'Actif', 'Inactif' => 'Inactif']]
                ]
            ],
            'projet' => [
                'route' => 'projet',
                'label' => 'Projets',
                'table' => 'projets',
                'primaryKey' => 'id',
                'titleField' => 'nom',
                'listFields' => ['id', 'client_id', 'workspace_owner_type', 'nom', 'type_projet', 'charge_compte_id', 'charge_clientele_id', 'createur_id', 'cadreur_id', 'videaste_id', 'designer_id', 'date_debut', 'date_fin', 'quota_videos_mensuel', 'quota_visuels_mensuel', 'statut'],
                'formFields' => [
                    'client_id' => ['label' => 'Client', 'type' => 'relation', 'module' => 'client'],
                    'campagne_id' => ['label' => 'Campagne complémentaire (optionnelle)', 'type' => 'relation', 'module' => 'campagne', 'nullable' => true],
                    'nom' => ['label' => 'Nom du projet', 'type' => 'text', 'required' => true],
                    'configuration_mode' => ['label' => 'Configuration', 'type' => 'select', 'options' => ['abonnement' => 'Abonnement existant', 'custom' => 'Sur mesure']],
                    'abonnement_id' => ['label' => 'Abonnement', 'type' => 'relation', 'module' => 'abonnement', 'nullable' => true, 'showWhen' => ['field' => 'configuration_mode', 'values' => ['abonnement']]],
                    'type_projet' => ['label' => 'Type de prestation', 'type' => 'select', 'options' => ['SEA ponctuel' => 'SEA ponctuel', 'Abonnement mensuel' => 'Abonnement mensuel', 'Abonnement mixte' => 'Abonnement mixte'], 'showWhen' => ['field' => 'configuration_mode', 'values' => ['custom']]],
                    'canal_principal' => ['label' => 'Canal principal', 'type' => 'text', 'showWhen' => ['field' => 'configuration_mode', 'values' => ['custom']]],
                    'date_debut' => ['label' => 'Date debut', 'type' => 'date', 'required' => true],
                    'date_fin' => ['label' => 'Date fin', 'type' => 'date', 'required' => true],
                    'duree_mois' => ['label' => 'Duree contractuelle (mois)', 'type' => 'number', 'showWhen' => ['field' => 'configuration_mode', 'values' => ['custom']]],
                    'sea_budget' => ['label' => 'Budget SEA', 'type' => 'number', 'step' => '0.01', 'showWhen' => ['field' => 'configuration_mode', 'values' => ['custom']]],
                    'quota_videos_mensuel' => ['label' => 'Videos / mois', 'type' => 'number', 'showWhen' => ['field' => 'configuration_mode', 'values' => ['custom']]],
                    'quota_visuels_mensuel' => ['label' => 'Visuels / mois', 'type' => 'number', 'showWhen' => ['field' => 'configuration_mode', 'values' => ['custom']]],
                    'charge_compte_id' => ['label' => 'Charge de communication', 'type' => 'relation', 'module' => 'user'],
                    'charge_clientele_id' => ['label' => 'Charge de clientele', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'cm_id' => ['label' => 'Community manager', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'createur_id' => ['label' => 'Createur contenu', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'cadreur_id' => ['label' => 'Cadreur', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'videaste_id' => ['label' => 'Videaste montage', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'designer_id' => ['label' => 'Designer', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Brouillon' => 'Brouillon', 'Actif' => 'Actif', 'Suspendu' => 'Suspendu', 'Termine' => 'Termine']],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea']
                ]
            ],
            'plan-mensuel' => [
                'route' => 'plan-mensuel',
                'label' => 'Plans mensuels',
                'table' => 'plans_mensuels',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'periode_mois',
                'listFields' => ['id', 'projet_id', 'periode_mois', 'objectif_mois', 'temps_forts_mois', 'videos_prevus', 'videos_livres', 'visuels_prevus', 'visuels_livres', 'statut'],
                'formFields' => [
                    'projet_id' => ['label' => 'Projet', 'type' => 'relation', 'module' => 'projet'],
                    'periode_mois' => ['label' => 'Periode du mois', 'type' => 'date', 'required' => true],
                    'index_mois' => ['label' => 'Index mois', 'type' => 'number'],
                    'contexte_mois' => ['label' => 'Contexte general du mois', 'type' => 'textarea'],
                    'objectif_mois' => ['label' => 'Objectif du mois', 'type' => 'textarea'],
                    'temps_forts_mois' => ['label' => 'Dates ou evenements cles', 'type' => 'textarea'],
                    'videos_prevus' => ['label' => 'Videos prevues', 'type' => 'number'],
                    'videos_livres' => ['label' => 'Videos livrees', 'type' => 'number'],
                    'visuels_prevus' => ['label' => 'Visuels prevus', 'type' => 'number'],
                    'visuels_livres' => ['label' => 'Visuels livres', 'type' => 'number'],
                    'livrables_prevus' => ['label' => 'Livrables prevus', 'type' => 'number'],
                    'livrables_livres' => ['label' => 'Livrables livres', 'type' => 'number'],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Planifie' => 'Planifie', 'En cours' => 'En cours', 'Partiel' => 'Partiel', 'Termine' => 'Termine']]
                ]
            ],
            'livrable-item' => [
                'route' => 'livrable-item',
                'label' => 'Livrables',
                'table' => 'livrable_items',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'titre',
                'listFields' => ['id', 'projet_id', 'plan_mensuel_id', 'type_livrable', 'numero_ordre', 'titre', 'date_prevue', 'statut'],
                'detailFields' => ['projet_id', 'plan_mensuel_id', 'type_livrable', 'sous_type', 'nombre_pages', 'titre', 'statut', 'date_prevue', 'canal', 'pieces_jointes'],
                'formFields' => [
                    'projet_id' => ['label' => 'Projet', 'type' => 'relation', 'module' => 'projet'],
                    'plan_mensuel_id' => ['label' => 'Plan mensuel', 'type' => 'relation', 'module' => 'plan-mensuel'],
                    'type_livrable' => ['label' => 'Type', 'type' => 'select', 'options' => ['Video' => 'Video', 'Visuel' => 'Visuel']],
                    'sous_type' => ['label' => 'Sous-type', 'type' => 'select', 'options' => ['Post simple' => 'Post simple', 'Carrousel' => 'Carrousel', 'Story' => 'Story', 'Affiche' => 'Affiche', 'Tutoriel' => 'Tutoriel', 'Interview' => 'Interview', 'Reel' => 'Reel']],
                    'nombre_pages' => ['label' => 'Nombre de pages / ecrans', 'type' => 'number'],
                    'numero_ordre' => ['label' => 'Numero', 'type' => 'number'],
                    'titre' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Planifie' => 'Planifie', 'En production' => 'En production', 'Pret' => 'Pret', 'Publie' => 'Publie']],
                    'date_prevue' => ['label' => 'Date prevue', 'type' => 'date'],
                    'canal' => ['label' => 'Canal', 'type' => 'text'],
                    'pieces_jointes' => ['label' => 'Fichiers du livrable', 'type' => 'files', 'accept' => '.png,.jpg,.jpeg,.pdf,.psd,.psb,.mp4,.mov,.zip', 'extensions' => ['png', 'jpg', 'jpeg', 'pdf', 'psd', 'psb', 'mp4', 'mov', 'zip'], 'hint' => 'Ajoute les exports, PDF ou fichiers source utiles a conserver sur le livrable.']
                ]
            ],
            'tache-pipeline' => [
                'route' => 'tache-pipeline',
                'label' => 'Taches pipeline',
                'table' => 'taches_pipeline',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'titre',
                'listFields' => ['id', 'projet_id', 'plan_mensuel_id', 'livrable_item_id', 'titre', 'type_tache', 'auteur_id', 'statut', 'deadline'],
                'detailFields' => ['projet_id', 'plan_mensuel_id', 'livrable_item_id', 'titre', 'type_tache', 'auteur_id', 'statut', 'deadline', 'notes', 'fichiers_livres'],
                'formFields' => [
                    'projet_id' => ['label' => 'Projet', 'type' => 'relation', 'module' => 'projet'],
                    'plan_mensuel_id' => ['label' => 'Plan mensuel', 'type' => 'relation', 'module' => 'plan-mensuel', 'nullable' => true],
                    'livrable_item_id' => ['label' => 'Livrable', 'type' => 'relation', 'module' => 'livrable-item', 'nullable' => true],
                    'parent_task_id' => ['label' => 'Tache parente', 'type' => 'relation', 'module' => 'tache-pipeline', 'nullable' => true],
                    'titre' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                    'type_tache' => ['label' => 'Type de tache', 'type' => 'select', 'options' => ['Onboarding' => 'Onboarding', 'Strategie' => 'Strategie', 'Calendrier' => 'Calendrier', 'Brief' => 'Brief', 'Script' => 'Script', 'Production' => 'Production', 'Tournage' => 'Tournage', 'Montage' => 'Montage', 'Validation interne' => 'Validation interne', 'Validation client' => 'Validation client', 'Publication' => 'Publication', 'Interactions' => 'Interactions', 'Collecte KPI' => 'Collecte KPI', 'Reporting' => 'Reporting', 'Optimisation' => 'Optimisation']],
                    'auteur_id' => ['label' => 'Auteur', 'type' => 'relation', 'module' => 'user', 'nullable' => true],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'options' => ['Bloquee' => 'Bloquee', 'A faire' => 'A faire', 'En cours' => 'En cours', 'Terminee' => 'Terminee', 'Annulee' => 'Annulee']],
                    'deadline' => ['label' => 'Deadline', 'type' => 'date'],
                    'ordre_pipeline' => ['label' => 'Ordre', 'type' => 'number'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea'],
                    'fichiers_livres' => ['label' => 'Fichiers livres', 'type' => 'files', 'accept' => '.png,.jpg,.jpeg,.pdf,.psd,.psb,.mp4,.mov,.zip', 'extensions' => ['png', 'jpg', 'jpeg', 'pdf', 'psd', 'psb', 'mp4', 'mov', 'zip'], 'hint' => 'Ajoute ici les exports finaux et les fichiers source necessaires a une reprise urgente.']
                ]
            ],
            'reporting-metric' => [
                'route' => 'reporting-metric',
                'label' => 'Metriques sociales',
                'table' => 'reporting_metrics',
                'primaryKey' => 'id',
                'hidden' => true,
                'titleField' => 'plateforme',
                'listFields' => ['id', 'campagne_id', 'contenu_id', 'plateforme', 'date_collecte', 'impressions', 'couverture', 'vues', 'likes', 'commentaires', 'partages', 'clics', 'abonnes_gagnes'],
                'formFields' => [
                    'campagne_id' => ['label' => 'Campagne', 'type' => 'relation', 'module' => 'campagne'],
                    'contenu_id' => ['label' => 'Contenu', 'type' => 'relation', 'module' => 'contenu', 'nullable' => true],
                    'plateforme' => ['label' => 'Reseau', 'type' => 'text', 'required' => true],
                    'date_collecte' => ['label' => 'Date de collecte', 'type' => 'date'],
                    'impressions' => ['label' => 'Impressions', 'type' => 'number'],
                    'couverture' => ['label' => 'Couverture', 'type' => 'number'],
                    'vues' => ['label' => 'Vues', 'type' => 'number'],
                    'likes' => ['label' => 'Likes', 'type' => 'number'],
                    'commentaires' => ['label' => 'Commentaires', 'type' => 'number'],
                    'partages' => ['label' => 'Partages', 'type' => 'number'],
                    'clics' => ['label' => 'Clics', 'type' => 'number'],
                    'sauvegardes' => ['label' => 'Sauvegardes', 'type' => 'number'],
                    'abonnes_gagnes' => ['label' => 'Abonnes gagnes', 'type' => 'number'],
                    'url_publication' => ['label' => 'URL publication', 'type' => 'text', 'nullable' => true]
                ]
            ]
        ];
    }

    public static function get($key) {
        $modules = self::all();
        return $modules[$key] ?? null;
    }

    public static function navigable() {
        return array_filter(self::all(), static function ($module) {
            return empty($module['hidden']);
        });
    }
}
