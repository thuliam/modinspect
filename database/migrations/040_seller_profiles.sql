-- LS-90 D1: a seller's public profile and the history of its verification. Additive only; the seller pages stay behind SELLER_LISTINGS_PUBLIC.
-- No real name, phone number or address is stored. The display name and note are checked by ContentGuard before they are saved.
CREATE TABLE IF NOT EXISTS seller_profiles (
  member_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(60) NOT NULL,
  province VARCHAR(40) NULL,
  about VARCHAR(300) NULL,
  verification ENUM('unverified','pending','verified','revoked') NOT NULL DEFAULT 'unverified',
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  INDEX idx_seller_profile_verification (verification, updated_at),
  CONSTRAINT fk_seller_profile_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_seller_profile_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seller_verification_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  actor_type ENUM('member','admin') NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  from_state VARCHAR(20) NOT NULL,
  to_state VARCHAR(20) NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_verification_member (member_id, id),
  CONSTRAINT fk_verification_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
