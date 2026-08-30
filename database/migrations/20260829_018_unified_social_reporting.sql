ALTER TABLE reporting_metrics
    MODIFY campagne_id INT NULL,
    ADD COLUMN IF NOT EXISTS tenant_id INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS project_id INT NULL AFTER campagne_id,
    ADD COLUMN IF NOT EXISTS social_publication_id BIGINT UNSIGNED NULL AFTER contenu_id,
    ADD COLUMN IF NOT EXISTS social_target_id BIGINT UNSIGNED NULL AFTER social_publication_id,
    ADD COLUMN IF NOT EXISTS source VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER social_target_id,
    ADD COLUMN IF NOT EXISTS collected_at DATETIME NULL AFTER date_collecte;

UPDATE reporting_metrics rm
JOIN campagnes cp ON cp.id = rm.campagne_id
JOIN clients cl ON cl.id = cp.client_id
SET rm.tenant_id = cl.tenant_id
WHERE rm.tenant_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_reporting_metric_tenant_source
    ON reporting_metrics (tenant_id, source, date_collecte);

CREATE INDEX IF NOT EXISTS idx_reporting_metric_social_target
    ON reporting_metrics (social_target_id, date_collecte);
