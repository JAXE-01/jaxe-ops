ALTER TABLE social_connections
    ADD COLUMN IF NOT EXISTS last_validated_at DATETIME NULL AFTER token_expires_at;

ALTER TABLE social_publications
    ADD COLUMN IF NOT EXISTS media_path TEXT NULL AFTER media_url,
    ADD COLUMN IF NOT EXISTS media_mime VARCHAR(100) NULL AFTER media_path;

ALTER TABLE social_publication_targets
    ADD COLUMN IF NOT EXISTS external_container_id VARCHAR(190) NULL AFTER external_post_id,
    ADD COLUMN IF NOT EXISTS remote_deleted_at DATETIME NULL AFTER published_at,
    ADD COLUMN IF NOT EXISTS remote_delete_error TEXT NULL AFTER remote_deleted_at;
