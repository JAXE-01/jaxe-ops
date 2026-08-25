CREATE TABLE IF NOT EXISTS content_matrices (
 id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, owner_organization_id INT NULL, client_id INT NOT NULL,
 name VARCHAR(160) NOT NULL, description TEXT NULL, target_options JSON NULL, objective_options JSON NULL,
 problem_options JSON NULL, product_options JSON NULL, format_options JSON NULL, cta_options JSON NULL, platform_options JSON NULL,
 default_deliverable_type ENUM('Video','Visuel') NOT NULL DEFAULT 'Video', default_format VARCHAR(120) NULL,
 status ENUM('Active','Archived') NOT NULL DEFAULT 'Active', created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_matrix_tenant_client (tenant_id,client_id), CONSTRAINT fk_matrix_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_matrix_owner_org FOREIGN KEY (owner_organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
 CONSTRAINT fk_matrix_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
 CONSTRAINT fk_matrix_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matrix_ideas (
 id INT AUTO_INCREMENT PRIMARY KEY, matrix_id INT NOT NULL, tenant_id INT NOT NULL, client_id INT NOT NULL, projet_id INT NOT NULL,
 target_month DATE NOT NULL, target_audience VARCHAR(180) NULL, objective VARCHAR(180) NULL, problem_need VARCHAR(255) NULL,
 product_offer VARCHAR(180) NULL, creative_format VARCHAR(180) NULL, deliverable_type ENUM('Video','Visuel') NOT NULL DEFAULT 'Video',
 platform VARCHAR(120) NULL, hook_idea VARCHAR(255) NOT NULL, call_to_action VARCHAR(255) NULL, generated_brief TEXT NULL,
 priority ENUM('Haute','Moyenne','Basse') NOT NULL DEFAULT 'Moyenne', status ENUM('Brouillon','Validee','Synchronisee','Ecartee') NOT NULL DEFAULT 'Brouillon',
 synced_deliverable_id INT NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_matrix_ideas_context (tenant_id,client_id,projet_id,target_month), KEY idx_matrix_ideas_status (matrix_id,status),
 UNIQUE KEY uq_matrix_synced_deliverable (synced_deliverable_id), CONSTRAINT fk_matrix_idea_matrix FOREIGN KEY (matrix_id) REFERENCES content_matrices(id) ON DELETE CASCADE,
 CONSTRAINT fk_matrix_idea_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_matrix_idea_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
 CONSTRAINT fk_matrix_idea_project FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE,
 CONSTRAINT fk_matrix_idea_deliverable FOREIGN KEY (synced_deliverable_id) REFERENCES livrable_items(id) ON DELETE SET NULL,
 CONSTRAINT fk_matrix_idea_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
