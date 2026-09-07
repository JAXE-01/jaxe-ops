CREATE TABLE IF NOT EXISTS calendar_progress_flashes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    sender_id INT NOT NULL,
    period_month DATE NOT NULL,
    project_ids JSON NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    snapshot_json JSON NOT NULL,
    primary_recipient VARCHAR(190) NOT NULL,
    cc_recipients TEXT NULL,
    delivery_status ENUM('Sent','Failed') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_calendar_flash_period (tenant_id, period_month, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
