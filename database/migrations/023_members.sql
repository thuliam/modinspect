-- LS-14 Module 7 (owner decision OD-4): visitor accounts through Google, Microsoft or Facebook, kept apart from the admin users table.
-- A member row holds only what the provider confirmed and what the site needs: a display name, an email address and when the policy was accepted.
-- No password, no access token, no profile photo. One identity per provider account. Deleting a member removes the identities and detaches
-- saved builds (they stay, without an owner).
CREATE TABLE IF NOT EXISTS members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  terms_accepted_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_members_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_identities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL,
  subject VARCHAR(191) NOT NULL,
  email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_identity (provider, subject),
  CONSTRAINT fk_identity_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE spec_builds ADD COLUMN member_id BIGINT UNSIGNED NULL;
ALTER TABLE spec_builds ADD INDEX idx_spec_builds_member (member_id);
