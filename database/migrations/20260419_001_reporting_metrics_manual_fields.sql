ALTER TABLE reporting_metrics
    ADD COLUMN IF NOT EXISTS couverture INT DEFAULT 0 AFTER impressions,
    ADD COLUMN IF NOT EXISTS clics INT DEFAULT 0 AFTER partages;
