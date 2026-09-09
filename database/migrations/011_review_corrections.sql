ALTER TABLE price_observations
  MODIFY asking_price DECIMAL(12,2) NULL,
  ADD COLUMN price_value DECIMAL(12,2) NULL AFTER asking_price,
  ADD COLUMN quality_flags JSON NULL AFTER final_weight;

UPDATE price_observations SET price_value=asking_price WHERE price_value IS NULL AND asking_price IS NOT NULL;

CREATE TABLE review_corrections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_observation_id BIGINT UNSIGNED NOT NULL,
  review_decision_id BIGINT UNSIGNED NULL,
  field_name VARCHAR(80) NOT NULL,
  original_value TEXT NULL,
  corrected_value TEXT NULL,
  reason TEXT NOT NULL,
  corrected_by BIGINT UNSIGNED NULL,
  corrected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_correction_field (price_observation_id, field_name),
  CONSTRAINT fk_review_corrections_observation FOREIGN KEY (price_observation_id) REFERENCES price_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_corrections_decision FOREIGN KEY (review_decision_id) REFERENCES observation_review_decisions(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_corrections_user FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_review_corrections_observation (price_observation_id, corrected_at)
) ENGINE=InnoDB;
