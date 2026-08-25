CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identity_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_identity_time (identity_hash, attempted_at),
    INDEX idx_auth_ip_time (ip_hash, attempted_at),
    INDEX idx_auth_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_sync_codes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    client_organization_id INT NOT NULL,
    code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    max_uses SMALLINT NOT NULL DEFAULT 1,
    use_count SMALLINT NOT NULL DEFAULT 0,
    created_by INT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organization_sync_code_hash (code_hash),
    INDEX idx_sync_codes_client_active (client_organization_id, expires_at, revoked_at),
    CONSTRAINT fk_sync_code_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_sync_code_client_org FOREIGN KEY (client_organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_sync_code_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE organization_agency_grants
    ADD COLUMN IF NOT EXISTS request_origin ENUM('AgencyCode','ClientInvitation','PlatformAdmin') NOT NULL DEFAULT 'PlatformAdmin' AFTER connection_purpose,
    ADD COLUMN IF NOT EXISTS client_confirmed_by INT NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS client_confirmed_at DATETIME NULL AFTER approved_at,
    ADD COLUMN IF NOT EXISTS last_accessed_at DATETIME NULL AFTER history_access_from;

CREATE TABLE IF NOT EXISTS organization_activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NULL,
    agency_grant_id INT NULL,
    actor_user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(80) NULL,
    target_id VARCHAR(100) NULL,
    metadata JSON NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_org_activity_org_time (organization_id, created_at),
    INDEX idx_org_activity_grant_time (agency_grant_id, created_at),
    INDEX idx_org_activity_action_time (action, created_at),
    CONSTRAINT fk_org_activity_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_activity_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_activity_grant FOREIGN KEY (agency_grant_id) REFERENCES organization_agency_grants(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_activity_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
