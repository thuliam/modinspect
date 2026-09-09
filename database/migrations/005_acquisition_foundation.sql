USE modinspect;

CREATE TABLE IF NOT EXISTS market_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  evidence_level ENUM('A','B','C','D','E') NOT NULL DEFAULT 'C',
  evidence_type ENUM('url','snippet','screenshot','page_capture','upload','fixture') NOT NULL DEFAULT 'url',
  source_url_hash CHAR(64) NULL,
  storage_path VARCHAR(255) NULL,
  excerpt TEXT NULL,
  content_hash CHAR(64) NULL,
  captured_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evidence_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_evidence_source FOREIGN KEY (source_id) REFERENCES data_sources(id),
  INDEX idx_evidence_source (source_id, captured_at),
  INDEX idx_evidence_hash (content_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS extraction_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NOT NULL,
  evidence_id BIGINT UNSIGNED NULL,
  provider_name VARCHAR(120) NOT NULL,
  model_name VARCHAR(120) NULL,
  prompt_version VARCHAR(80) NULL,
  schema_version VARCHAR(80) NOT NULL DEFAULT 'market_observation_v1',
  extracted_data JSON NOT NULL,
  confidence DECIMAL(6,4) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_extraction_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_extraction_evidence FOREIGN KEY (evidence_id) REFERENCES market_evidence(id) ON DELETE SET NULL,
  INDEX idx_extraction_raw (raw_observation_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS observation_review_decisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_observation_id BIGINT UNSIGNED NULL,
  raw_observation_id BIGINT UNSIGNED NULL,
  extraction_run_id BIGINT UNSIGNED NULL,
  lane ENUM('green','amber','red') NOT NULL,
  decision ENUM('review_required','approved','edited','rejected','quarantined') NOT NULL,
  reason_codes JSON NOT NULL,
  ai_value JSON NULL,
  rule_result JSON NULL,
  human_value JSON NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_observation FOREIGN KEY (price_observation_id) REFERENCES price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_extraction FOREIGN KEY (extraction_run_id) REFERENCES extraction_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_user FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_review_lane_decision (lane, decision, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS source_incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id BIGINT UNSIGNED NOT NULL,
  incident_type ENUM('blocked','terms_change','rate_limited','quality_drop','manual_pause','other') NOT NULL,
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  description TEXT NOT NULL,
  action_taken ENUM('paused','disabled','reduced_rate','monitor','none') NOT NULL DEFAULT 'paused',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_incident_source FOREIGN KEY (source_id) REFERENCES data_sources(id) ON DELETE CASCADE,
  INDEX idx_incident_source (source_id, created_at)
) ENGINE=InnoDB;
