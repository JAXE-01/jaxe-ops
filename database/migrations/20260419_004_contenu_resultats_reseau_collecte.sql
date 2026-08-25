ALTER TABLE contenu_resultats
    ADD COLUMN IF NOT EXISTS reseau_collecte VARCHAR(60) NULL AFTER note,
    ADD INDEX IF NOT EXISTS idx_contenu_resultats_task_date_network (task_id, date_collecte, reseau_collecte);
