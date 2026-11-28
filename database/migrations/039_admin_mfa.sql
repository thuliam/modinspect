-- LS-89 BE-3: second sign-in factor for admin accounts (TOTP). Additive only; the feature is off unless AUTH_MFA_ENABLED=true.
-- The secret is stored encrypted with AUTH_MFA_KEY, recovery codes only as password hashes.
CREATE TABLE IF NOT EXISTS user_mfa (
  user_id BIGINT UNSIGNED NOT NULL,
  secret_encrypted VARCHAR(255) NOT NULL,
  enabled_at DATETIME NULL,
  last_used_step BIGINT UNSIGNED NULL,
  recovery_codes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_user_mfa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
