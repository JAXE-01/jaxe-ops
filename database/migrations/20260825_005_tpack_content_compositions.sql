CREATE TABLE IF NOT EXISTS content_compositions (
 id INT AUTO_INCREMENT PRIMARY KEY, livrable_item_id INT NOT NULL, projet_id INT NOT NULL, client_id INT NOT NULL,
 method VARCHAR(40) NOT NULL DEFAULT 'TPACK', target_audience VARCHAR(180) NULL, objective VARCHAR(180) NULL,
 problem_need VARCHAR(255) NULL, product_offer VARCHAR(180) NULL, content_format VARCHAR(180) NULL,
 call_to_action VARCHAR(255) NULL, platform VARCHAR(120) NULL, hook_idea VARCHAR(255) NULL,
 priority VARCHAR(30) NOT NULL DEFAULT 'Moyenne', idea_status VARCHAR(40) NOT NULL DEFAULT 'A discuter', generated_brief TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_content_composition_deliverable (livrable_item_id), KEY idx_content_composition_project (projet_id), KEY idx_content_composition_client (client_id),
 CONSTRAINT fk_content_composition_deliverable FOREIGN KEY (livrable_item_id) REFERENCES livrable_items(id) ON DELETE CASCADE,
 CONSTRAINT fk_content_composition_project FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE,
 CONSTRAINT fk_content_composition_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;