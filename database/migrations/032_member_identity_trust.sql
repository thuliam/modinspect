-- Marketplace P1-A (identity and trust foundation). Additive only: no existing table or column is changed.
-- Legal documents are versioned; nothing here publishes a document (status stays draft until a human publishes it).
-- Acceptances cascade with the member; whether acceptance evidence must outlive account deletion is an HG-LEGAL question.

CREATE TABLE IF NOT EXISTS member_preferences (
  member_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  notify_transactional TINYINT(1) NOT NULL DEFAULT 1,
  notify_marketing TINYINT(1) NOT NULL DEFAULT 0,
  locale VARCHAR(5) NOT NULL DEFAULT 'th',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_member_pref_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS legal_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type VARCHAR(40) NOT NULL,
  version VARCHAR(20) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body_text MEDIUMTEXT NULL,
  status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  published_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_legal_doc_version (doc_type, version),
  INDEX idx_legal_doc_status (doc_type, status),
  CONSTRAINT fk_legal_doc_publisher FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS legal_acceptances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_legal_acceptance (member_id, document_id),
  CONSTRAINT fk_legal_acc_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_legal_acc_doc FOREIGN KEY (document_id) REFERENCES legal_documents(id)
) ENGINE=InnoDB;

-- A request outlives the member row (member_id becomes NULL on deletion) so the handling record is kept. due_at stays NULL unless a deadline is configured.
CREATE TABLE IF NOT EXISTS privacy_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NULL,
  request_type ENUM('access','export','correction','deletion','objection') NOT NULL,
  status ENUM('received','in_progress','completed','rejected') NOT NULL DEFAULT 'received',
  detail VARCHAR(500) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_at DATETIME NULL,
  responded_at DATETIME NULL,
  handler_user_id BIGINT UNSIGNED NULL,
  response_note VARCHAR(500) NULL,
  INDEX idx_privacy_req_status (status, received_at),
  INDEX idx_privacy_req_member (member_id),
  CONSTRAINT fk_privacy_req_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
  CONSTRAINT fk_privacy_req_handler FOREIGN KEY (handler_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Capabilities instead of one global role: a member can hold several (seller now, others later). Rows are revoked, never deleted.
CREATE TABLE IF NOT EXISTS member_capabilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  capability VARCHAR(40) NOT NULL,
  granted_by_user_id BIGINT UNSIGNED NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_member_capability (member_id, capability),
  CONSTRAINT fk_member_cap_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_cap_granter FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Append-only record of member-facing actions. No IP address and no free text from the member is stored here.
CREATE TABLE IF NOT EXISTS member_audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NULL,
  actor_type ENUM('member','admin','system') NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_member_audit_member (member_id, created_at),
  INDEX idx_member_audit_action (action, created_at),
  CONSTRAINT fk_member_audit_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB;
