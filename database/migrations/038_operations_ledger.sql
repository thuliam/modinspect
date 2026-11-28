-- LS-89 BE-3: which migration files this database has run, and what the scheduled jobs did. Additive only.
CREATE TABLE IF NOT EXISTS schema_migrations (
  filename VARCHAR(190) NOT NULL,
  checksum CHAR(64) NOT NULL,
  method ENUM('run','recorded') NOT NULL DEFAULT 'run',
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_name VARCHAR(60) NOT NULL,
  status ENUM('running','ok','failed') NOT NULL DEFAULT 'running',
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  summary TEXT NULL,
  error_code VARCHAR(120) NULL,
  PRIMARY KEY (id),
  KEY idx_job_runs_name (job_name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
