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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
