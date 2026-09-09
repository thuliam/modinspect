USE modinspect;

ALTER TABLE data_sources
  ADD COLUMN source_key VARCHAR(120) NULL AFTER id,
  ADD COLUMN is_paused TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN allowed_collection_method VARCHAR(80) NOT NULL DEFAULT 'manual' AFTER access_method,
  ADD COLUMN reliability_score DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER risk_level,
  ADD COLUMN evidence_quality ENUM('high','medium','low','unknown') NOT NULL DEFAULT 'unknown' AFTER reliability_score,
  ADD COLUMN freshness_expectation_days INT UNSIGNED NULL AFTER evidence_quality,
  ADD COLUMN request_budget_per_day INT UNSIGNED NULL AFTER freshness_expectation_days,
  ADD COLUMN cost_budget_per_day DECIMAL(10,4) NULL AFTER request_budget_per_day,
  ADD COLUMN last_failure_at DATETIME NULL AFTER last_blocked_at,
  ADD COLUMN policy_note TEXT NULL AFTER robots_note,
  ADD COLUMN pause_reason TEXT NULL AFTER is_paused,
  ADD COLUMN disabled_reason TEXT NULL AFTER pause_reason;

UPDATE data_sources
SET source_key = LOWER(REPLACE(REPLACE(REPLACE(name, ' ', '_'), '-', '_'), '.', '_'))
WHERE source_key IS NULL;

ALTER TABLE data_sources
  MODIFY source_key VARCHAR(120) NOT NULL,
  ADD UNIQUE KEY uq_data_sources_key (source_key);

UPDATE data_sources
SET allowed_collection_method = access_method,
    reliability_score = CASE risk_level WHEN 'low' THEN 85.00 WHEN 'medium' THEN 65.00 ELSE 35.00 END,
    evidence_quality = CASE risk_level WHEN 'low' THEN 'high' WHEN 'medium' THEN 'medium' ELSE 'low' END,
    freshness_expectation_days = COALESCE(freshness_expectation_days, 14);

ALTER TABLE collector_jobs
  ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN run_id VARCHAR(80) NULL AFTER attempt_count,
  ADD INDEX idx_jobs_run_id (run_id);

CREATE TABLE IF NOT EXISTS collection_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id VARCHAR(80) NOT NULL UNIQUE,
  source_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(120) NOT NULL,
  job_type VARCHAR(80) NOT NULL,
  execution_mode ENUM('normal','dry_run') NOT NULL DEFAULT 'normal',
  started_at DATETIME NOT NULL,
  finished_at DATETIME NOT NULL,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_claimed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_failed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_skipped INT UNSIGNED NOT NULL DEFAULT 0,
  candidates_created INT UNSIGNED NOT NULL DEFAULT 0,
  evidence_created INT UNSIGNED NOT NULL DEFAULT 0,
  extractions_created INT UNSIGNED NOT NULL DEFAULT 0,
  reviews_created INT UNSIGNED NOT NULL DEFAULT 0,
  green_count INT UNSIGNED NOT NULL DEFAULT 0,
  amber_count INT UNSIGNED NOT NULL DEFAULT 0,
  red_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_collection_runs_source FOREIGN KEY (source_id) REFERENCES data_sources(id),
  INDEX idx_collection_runs_source (source_id, started_at),
  INDEX idx_collection_runs_mode (execution_mode, started_at)
) ENGINE=InnoDB;

ALTER TABLE source_incidents
  MODIFY incident_type ENUM('blocked','rate_limited','authentication_required','source_structure_changed','repeated_extraction_failure','manually_paused','manually_disabled','terms_change','quality_drop','manual_pause','other') NOT NULL,
  ADD COLUMN message TEXT NULL AFTER incident_type,
  ADD COLUMN occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER message,
  ADD COLUMN created_by VARCHAR(80) NOT NULL DEFAULT 'system' AFTER action_taken;

UPDATE source_incidents
SET message = COALESCE(message, description),
    occurred_at = COALESCE(occurred_at, created_at);

INSERT IGNORE INTO data_sources
  (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,request_budget_per_day,cost_budget_per_day,terms_note,policy_note,is_active,is_paused)
VALUES
  ('gemini_google_search','Gemini Google Search POC','googleapis.com','research','api','api','medium',60,'medium',7,3,NULL,'Phase 2D-A provider source. Disabled by global live/provider guards until explicit approval.',NULL,1,0);
