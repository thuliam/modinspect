-- Backend-only log of the coarse place (province + region) an owner-collected listing was offered from.
-- Province granularity only: no district, address, shop or seller. Never exposed on public pages.
-- Self-contained (category/model_key/asking price copied from the sanitized row) because raw source references are encrypted.
CREATE TABLE IF NOT EXISTS listing_location_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  row_ref VARCHAR(40) NOT NULL,
  province VARCHAR(60) NOT NULL,
  region ENUM('central','east','north','northeast','south') NOT NULL,
  note_date DATE NULL,
  category VARCHAR(20) NULL,
  model_key VARCHAR(80) NULL,
  asking_price_thb INT UNSIGNED NULL,
  source_label VARCHAR(80) NOT NULL DEFAULT 'owner_collected_facebook_text',
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_location_row (row_ref),
  INDEX idx_location_province (province)
) ENGINE=InnoDB;
