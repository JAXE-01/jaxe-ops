ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER statut;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_verification_user (user_id, expires_at),
    CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_registration_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identity_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_registration_identity_time (identity_hash, attempted_at),
    INDEX idx_registration_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
