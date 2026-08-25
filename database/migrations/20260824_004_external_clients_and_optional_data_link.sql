ALTER TABLE organizations ADD COLUMN IF NOT EXISTS registration_state ENUM('Registered','ExternalProfile') NOT NULL DEFAULT 'ExternalProfile' AFTER project_mode;

ALTER TABLE clients ADD COLUMN IF NOT EXISTS relationship_mode ENUM('External','Connected') NOT NULL DEFAULT 'External' AFTER managed_by_organization_id;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS connected_at DATETIME NULL AFTER relationship_mode;

ALTER TABLE projets ADD COLUMN IF NOT EXISTS beneficiary_organization_id INT NULL AFTER owner_organization_id;
ALTER TABLE projets ADD COLUMN IF NOT EXISTS workspace_owner_type ENUM('Agency','ClientCompany') NOT NULL DEFAULT 'Agency' AFTER managed_by_organization_id;
ALTER TABLE projets ADD INDEX IF NOT EXISTS idx_projects_beneficiary_organization (beneficiary_organization_id);
ALTER TABLE projets ADD CONSTRAINT fk_projects_beneficiary_organization FOREIGN KEY IF NOT EXISTS (beneficiary_organization_id) REFERENCES organizations(id) ON DELETE RESTRICT;

ALTER TABLE organization_agency_grants ADD COLUMN IF NOT EXISTS connection_purpose ENUM('DataLink','HistoryImport','WorkspaceTransfer') NOT NULL DEFAULT 'DataLink' AFTER status;
ALTER TABLE organization_agency_grants ADD COLUMN IF NOT EXISTS history_access_from DATETIME NULL AFTER connection_purpose;

UPDATE organizations
SET registration_state = CASE WHEN account_type = 'Agency' THEN 'Registered' ELSE 'ExternalProfile' END;

UPDATE organizations o
JOIN tenant_memberships tm ON tm.organization_id = o.id AND tm.status = 'Actif'
SET o.registration_state = 'Registered';

UPDATE clients c
JOIN organizations o ON o.id = c.organization_id
SET c.relationship_mode = CASE WHEN o.registration_state = 'Registered' AND o.account_type = 'ClientCompany' THEN 'Connected' ELSE 'External' END,
    c.connected_at = CASE WHEN o.registration_state = 'Registered' AND o.account_type = 'ClientCompany' THEN COALESCE(c.connected_at, NOW()) ELSE NULL END;

UPDATE projets p
JOIN clients c ON c.id = p.client_id
SET p.beneficiary_organization_id = c.organization_id,
    p.owner_organization_id = CASE WHEN c.relationship_mode = 'External' THEN c.managed_by_organization_id ELSE c.organization_id END,
    p.workspace_owner_type = CASE WHEN c.relationship_mode = 'External' THEN 'Agency' ELSE 'ClientCompany' END;

UPDATE organization_agency_grants
SET connection_purpose = 'DataLink'
WHERE connection_purpose IS NULL OR connection_purpose = '';
