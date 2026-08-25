ALTER TABLE matrix_ideas
    ADD COLUMN script_content LONGTEXT NULL AFTER generated_brief,
    ADD COLUMN generation_mode ENUM('Manual','Combinaison') NOT NULL DEFAULT 'Manual' AFTER script_content,
    ADD COLUMN anchor_type ENUM('Produit','Cible') NULL AFTER generation_mode,
    ADD COLUMN anchor_value VARCHAR(180) NULL AFTER anchor_type;

CREATE INDEX idx_matrix_ideas_bank
    ON matrix_ideas (matrix_id, projet_id, status, synced_deliverable_id);
