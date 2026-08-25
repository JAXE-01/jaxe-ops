-- Fondation SaaS retrocompatible : tenants, organisations et memberships.
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    status ENUM('Actif','Suspendu','Ferme') NOT NULL DEFAULT 'Actif',
    plan_code VARCHAR(80) NOT NULL DEFAULT 'legacy',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    legacy_client_id INT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    status ENUM('Actif','Inactif') NOT NULL DEFAULT 'Actif',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organizations_tenant_slug (tenant_id, slug),
    UNIQUE KEY uq_organizations_legacy_client (legacy_client_id),
    INDEX idx_organizations_tenant_status (tenant_id, status),
    CONSTRAINT fk_organizations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NULL,
    user_id INT NOT NULL,
    membership_role ENUM('Owner','Admin','Manager','Member','Client') NOT NULL DEFAULT 'Member',
    status ENUM('Invite','Actif','Suspendu') NOT NULL DEFAULT 'Actif',
    invited_at DATETIME NULL,
    joined_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_membership_scope (tenant_id, organization_id, user_id),
    INDEX idx_membership_user_status (user_id, status),
    INDEX idx_membership_tenant_status (tenant_id, status),
    CONSTRAINT fk_memberships_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clients ADD COLUMN IF NOT EXISTS tenant_id INT NULL AFTER id;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS organization_id INT NULL AFTER tenant_id;
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_clients_tenant_status (tenant_id, statut);
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_clients_organization (organization_id);

INSERT INTO tenants (name, slug, status, plan_code)
SELECT 'Jaxe Ops', 'jaxe-ops', 'Actif', 'legacy'
WHERE NOT EXISTS (SELECT 1 FROM tenants WHERE slug = 'jaxe-ops');

INSERT INTO organizations (tenant_id, legacy_client_id, name, slug, status)
SELECT t.id,
       c.id,
       COALESCE(NULLIF(c.entreprise, ''), NULLIF(c.nom, ''), CONCAT('Client ', c.id)),
       CONCAT('client-', c.id),
       CASE WHEN c.statut = 'Inactif' THEN 'Inactif' ELSE 'Actif' END
FROM clients c
JOIN tenants t ON t.slug = 'jaxe-ops'
LEFT JOIN organizations o ON o.legacy_client_id = c.id
WHERE o.id IS NULL;

UPDATE clients c
JOIN organizations o ON o.legacy_client_id = c.id
JOIN tenants t ON t.id = o.tenant_id AND t.slug = 'jaxe-ops'
SET c.tenant_id = t.id,
    c.organization_id = o.id
WHERE c.tenant_id IS NULL OR c.organization_id IS NULL;

INSERT INTO tenant_memberships (tenant_id, organization_id, user_id, membership_role, status, joined_at)
SELECT t.id,
       NULL,
       u.id,
       CASE
           WHEN u.role = 'Admin' THEN 'Owner'
           WHEN u.role = 'CC' THEN 'Admin'
           ELSE 'Member'
       END,
       CASE WHEN u.statut = 'Inactif' THEN 'Suspendu' ELSE 'Actif' END,
       COALESCE(u.date_creation, NOW())
FROM users u
JOIN tenants t ON t.slug = 'jaxe-ops'
LEFT JOIN tenant_memberships tm
       ON tm.tenant_id = t.id
      AND tm.organization_id IS NULL
      AND tm.user_id = u.id
WHERE tm.id IS NULL;

ALTER TABLE clients ADD CONSTRAINT fk_clients_tenant FOREIGN KEY IF NOT EXISTS (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT;
ALTER TABLE clients ADD CONSTRAINT fk_clients_organization FOREIGN KEY IF NOT EXISTS (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT;
