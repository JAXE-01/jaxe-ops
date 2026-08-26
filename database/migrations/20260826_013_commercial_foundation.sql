CREATE TABLE IF NOT EXISTS team_invitation_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membership_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_team_invitation_membership (membership_id, expires_at),
    CONSTRAINT fk_team_invitation_membership FOREIGN KEY (membership_id) REFERENCES tenant_memberships(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_invitation_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO clients (tenant_id, organization_id, managed_by_organization_id, relationship_mode, connected_at, nom, entreprise, email, statut)
SELECT o.tenant_id, o.id, NULL, 'Connected', NOW(), o.name, o.name,
       (SELECT u.email FROM tenant_memberships tm JOIN users u ON u.id=tm.user_id
        WHERE tm.organization_id=o.id AND tm.membership_role='Owner' ORDER BY tm.id LIMIT 1),
       'Actif'
FROM organizations o
WHERE o.account_type='ClientCompany'
  AND o.registration_state='Registered'
  AND NOT EXISTS (SELECT 1 FROM clients c WHERE c.organization_id=o.id);
