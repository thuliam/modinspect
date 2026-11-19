-- LS-21 (R-11, ADR-006 option A, accepted 2026-11-17): community reports. Structured choices and numbers only, no free text, anonymous.
-- A report is a SIGNAL: it can never create or change a price, a snapshot, a confidence label or an approval. A person decides every report, and the
-- only effect of an accepted sold-price report is a PENDING observation candidate (price_type 'sold') that a reviewer still approves or excludes;
-- a sold price is never part of the asking cohort the public prices are made from. The feature is off unless COMMUNITY_REPORTS_ENABLED=true and a
-- real COMMUNITY_REPORTS_HMAC_KEY is set. reporter_hash is an HMAC of the client address with a key that changes every day: flood and duplicate
-- control, not identity, and a person cannot be followed across days.
CREATE TABLE IF NOT EXISTS community_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('wrong_product','price_looks_off','stale','sold_price','spec_wrong') NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  observation_id BIGINT UNSIGNED NULL,
  snapshot_id BIGINT UNSIGNED NULL,
  candidate_observation_id BIGINT UNSIGNED NULL,
  payload JSON NOT NULL,
  reporter_hash CHAR(32) NOT NULL,
  status ENUM('new','accepted','rejected','spam') NOT NULL DEFAULT 'new',
  moderator_id BIGINT UNSIGNED NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  INDEX idx_reports_status (status, created_at),
  INDEX idx_reports_product (product_id, report_type),
  INDEX idx_reports_reporter (reporter_hash, product_id, created_at),
  CONSTRAINT fk_community_report_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- the evidence source a candidate observation points at: low reliability, low evidence quality, so it can never outrank real evidence
INSERT IGNORE INTO data_sources (source_key, name, domain, source_type, access_method, allowed_collection_method, risk_level, reliability_score, evidence_quality, freshness_expectation_days, terms_note)
VALUES ('community_report', 'Community report (checked by a person)', NULL, 'user', 'manual', 'manual', 'high', 20.00, 'low', 90, 'Anonymous structured report, accepted by a moderator; a sold-price candidate still needs a reviewer and is never part of the asking cohort.');
