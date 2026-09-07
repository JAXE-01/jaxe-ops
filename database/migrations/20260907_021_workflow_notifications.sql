CREATE TABLE IF NOT EXISTS workflow_progress_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    task_id INT NOT NULL,
    sender_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    primary_recipient VARCHAR(190) NOT NULL,
    cc_recipients TEXT NULL,
    delivery_status ENUM('Sent','Failed') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_progress_task (tenant_id, task_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_notification_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    notification_key VARCHAR(190) NOT NULL,
    notification_type VARCHAR(40) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    delivery_status ENUM('Sent','Failed') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workflow_notification (tenant_id, notification_key, recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
