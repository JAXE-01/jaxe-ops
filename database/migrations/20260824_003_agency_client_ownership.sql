ALTER TABLE organizations ADD COLUMN IF NOT EXISTS account_type ENUM('Platform','Agency','ClientCompany') NOT NULL DEFAULT 'ClientCompany' AFTER slug;
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS project_mode ENUM('Single','Multiple') NOT NULL DEFAULT 'Single' AFTER account_type;

CREATE TABLE IF NOT EXISTS organization_agency_grants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    client_organization_id INT NOT NULL,
    agency_organization_id INT NOT NULL,
    status ENUM('Pending','Active','Revoked') NOT NULL DEFAULT 'Pending',
    permission_scope JSON NULL,
    requested_by INT NULL,
    approved_by INT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    revoked_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_agency_grant_pair (client_organization_id, agency_organization_id),
    INDEX idx_agency_grants_agency_status (agency_organization_id, status),
    INDEX idx_agency_grants_client_status (client_organization_id, status),
    CONSTRAINT fk_agency_grants_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_agency_grants_client FOREIGN KEY (client_organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_agency_grants_agency FOREIGN KEY (agency_organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_agency_grants_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_agency_grants_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clients ADD COLUMN IF NOT EXISTS managed_by_organization_id INT NULL AFTER organization_id;
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_clients_managing_agency (managed_by_organization_id);
ALTER TABLE clients ADD CONSTRAINT fk_clients_managing_agency FOREIGN KEY IF NOT EXISTS (managed_by_organization_id) REFERENCES organizations(id) ON DELETE SET NULL;

ALTER TABLE projets ADD COLUMN IF NOT EXISTS owner_organization_id INT NULL AFTER client_id;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS managed_by_organization_id INT NULL AFTER owner_organization_id;
ALTER TABLE projets ADD INDEX IF NOT EXISTS idx_projects_owner_organization (owner_organization_id);
ALTER TABLE projets ADD INDEX IF NOT EXISTS idx_projects_managing_agency (managed_by_organization_id);
ALTER TABLE projets ADD CONSTRAINT fk_projects_owner_organization FOREIGN KEY IF NOT EXISTS (owner_organization_id) REFERENCES organizations(id) ON DELETE RESTRICT;
ALTER TABLE projets ADD CONSTRAINT fk_projects_managing_agency FOREIGN KEY IF NOT EXISTS (managed_by_organization_id) REFERENCES organizations(id) ON DELETE SET NULL;

UPDATE organizations o
JOIN clients c ON c.organization_id = o.id
SET o.account_type = CASE WHEN UPPER(COALESCE(c.entreprise, '')) LIKE '%JAXE%COMMUNICATION%' THEN 'Agency' ELSE 'ClientCompany' END,
    o.project_mode = CASE WHEN UPPER(COALESCE(c.entreprise, '')) LIKE '%JAXE%COMMUNICATION%' THEN 'Multiple' ELSE 'Single' END;

UPDATE tenant_memberships tm
JOIN organizations agency ON agency.tenant_id = tm.tenant_id AND agency.account_type = 'Agency'
SET tm.organization_id = agency.id
WHERE tm.organization_id IS NULL;

UPDATE clients c
JOIN organizations agency ON agency.tenant_id = c.tenant_id AND agency.account_type = 'Agency'
SET c.managed_by_organization_id = agency.id
WHERE c.managed_by_organization_id IS NULL;

UPDATE projets p
JOIN clients c ON c.id = p.client_id
SET p.owner_organization_id = c.organization_id,
    p.managed_by_organization_id = c.managed_by_organization_id
WHERE p.owner_organization_id IS NULL OR p.managed_by_organization_id IS NULL;

INSERT INTO organization_agency_grants
    (tenant_id, client_organization_id, agency_organization_id, status, permission_scope)
SELECT client_org.tenant_id,
       client_org.id,
       agency.id,
       'Pending',
       JSON_OBJECT('projects', true, 'content', true, 'publishing', false, 'analytics', true, 'users', false)
FROM organizations client_org
JOIN organizations agency ON agency.tenant_id = client_org.tenant_id AND agency.account_type = 'Agency'
LEFT JOIN organization_agency_grants grant_row
       ON grant_row.client_organization_id = client_org.id
      AND grant_row.agency_organization_id = agency.id
WHERE client_org.account_type = 'ClientCompany'
  AND grant_row.id IS NULL;
