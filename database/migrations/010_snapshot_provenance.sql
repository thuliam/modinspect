ALTER TABLE price_indices
  ADD COLUMN formula_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned' AFTER confidence_label,
  ADD COLUMN cohort_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned' AFTER formula_version,
  ADD COLUMN quartile_method_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned' AFTER cohort_version,
  ADD COLUMN confidence_method_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned' AFTER quartile_method_version,
  ADD COLUMN calculation_hash CHAR(64) NULL AFTER confidence_method_version,
  ADD COLUMN calculation_manifest JSON NULL AFTER calculation_hash,
  ADD COLUMN provenance_status ENUM('legacy_unavailable','recorded') NOT NULL DEFAULT 'legacy_unavailable' AFTER calculation_manifest,
  ADD INDEX idx_index_hash (calculation_hash);

CREATE TABLE price_snapshot_observations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  observation_id BIGINT UNSIGNED NOT NULL,
  inclusion_status ENUM('included','excluded') NOT NULL,
  exclusion_reason VARCHAR(80) NULL,
  calculation_price DECIMAL(12,2) NULL,
  snapshot_verified_status VARCHAR(30) NOT NULL,
  snapshot_price_type VARCHAR(30) NOT NULL,
  snapshot_listing_type VARCHAR(30) NOT NULL,
  snapshot_source_id BIGINT UNSIGNED NULL,
  snapshot_observed_at DATETIME NOT NULL,
  weight DECIMAL(8,4) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_snapshot_observation (snapshot_id, observation_id),
  CONSTRAINT fk_snapshot_observations_snapshot FOREIGN KEY (snapshot_id) REFERENCES price_indices(id) ON DELETE CASCADE,
  CONSTRAINT fk_snapshot_observations_observation FOREIGN KEY (observation_id) REFERENCES price_observations(id) ON DELETE CASCADE,
  INDEX idx_snapshot_observations_snapshot (snapshot_id, inclusion_status),
  INDEX idx_snapshot_observations_observation (observation_id)
) ENGINE=InnoDB;
