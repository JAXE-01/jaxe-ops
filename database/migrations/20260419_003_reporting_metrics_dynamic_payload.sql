ALTER TABLE reporting_metrics
    ADD COLUMN IF NOT EXISTS ctr DECIMAL(10,4) NULL AFTER clics,
    ADD COLUMN IF NOT EXISTS engagement_rate DECIMAL(10,4) NULL AFTER ctr,
    ADD COLUMN IF NOT EXISTS avg_watch_time DECIMAL(10,4) NULL AFTER engagement_rate,
    ADD COLUMN IF NOT EXISTS kpi_payload JSON NULL AFTER avg_watch_time;
