-- LS-14 Module 8 (owner OD-3, ADR-007): reports about a phone number or bank account, verified by a person before anything is public.
-- The number itself is never stored: identifier_hash is an HMAC-SHA-256 with a key kept in .env. The feature is off unless SELLER_CHECK_ENABLED=true.
CREATE TABLE IF NOT EXISTS seller_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(32) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  identifier_type ENUM('phone','bank_account') NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  identifier_last4 CHAR(4) NOT NULL,
  problem_type VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  status ENUM('pending','verified','rejected','disputed') NOT NULL DEFAULT 'pending',
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seller_report_uuid (uuid),
  INDEX idx_seller_report_hash (identifier_hash, status),
  INDEX idx_seller_report_status (status, created_at),
  CONSTRAINT fk_seller_report_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_report_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_report_evidence (report_id),
  CONSTRAINT fk_report_evidence FOREIGN KEY (report_id) REFERENCES seller_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_report_disputes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL,
  explanation VARCHAR(1000) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dispute_hash (identifier_hash, status)
) ENGINE=InnoDB;
