CREATE TABLE IF NOT EXISTS social_connection_projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    project_id INT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_social_connection_project (connection_id, project_id),
    INDEX idx_social_connection_projects_tenant (tenant_id, project_id),
    CONSTRAINT fk_social_connection_projects_connection FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_connection_projects_project FOREIGN KEY (project_id) REFERENCES projets(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_connection_projects_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
