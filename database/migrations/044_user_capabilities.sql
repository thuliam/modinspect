-- LS-90 D4: an extra capability for an admin-side account (content moderation) without changing the role column. Additive only.
CREATE TABLE IF NOT EXISTS user_capabilities (
  user_id BIGINT UNSIGNED NOT NULL,
  capability VARCHAR(40) NOT NULL,
  granted_by_user_id BIGINT UNSIGNED NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY (user_id, capability),
  CONSTRAINT fk_user_capability_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_capability_granter FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
