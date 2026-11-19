-- LS-14 Module 9 (owner OD-5, 2026-11-19): one set of listing facts for imported market data and for seller listings.
-- price_observations already has warranty_months, is_defective and has_benchmark (used as "has a test result"), the rest is added here, all
-- nullable. Nothing in the price engine reads these columns: they describe a listing, they do not change how a price is calculated.
ALTER TABLE price_observations ADD COLUMN condition_percent TINYINT UNSIGNED NULL;
ALTER TABLE price_observations ADD COLUMN under_warranty TINYINT(1) NULL;
ALTER TABLE price_observations ADD COLUMN product_age_months SMALLINT UNSIGNED NULL;
ALTER TABLE price_observations ADD COLUMN province VARCHAR(40) NULL;
ALTER TABLE price_observations ADD COLUMN seller_warranty_terms VARCHAR(255) NULL;
ALTER TABLE price_observations ADD COLUMN has_repair_history TINYINT(1) NULL;
ALTER TABLE price_observations ADD COLUMN shipping_option VARCHAR(20) NULL;
ALTER TABLE price_observations ADD COLUMN shipping_cost DECIMAL(10,2) NULL;
ALTER TABLE price_observations ADD COLUMN test_result_note VARCHAR(255) NULL;
ALTER TABLE price_observations ADD COLUMN defect_codes VARCHAR(255) NULL;
ALTER TABLE price_observations ADD COLUMN defect_note VARCHAR(255) NULL;

-- A member's own listing. It is a draft until submitted, then an admin approves or rejects it. Approval does not create a price:
-- whether approved listings count as market evidence is an owner decision (HG-17). No seller name, phone or address is stored.
CREATE TABLE IF NOT EXISTS seller_listings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(32) NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  asking_price DECIMAL(12,2) NOT NULL,
  status ENUM('draft','submitted','approved','rejected','withdrawn') NOT NULL DEFAULT 'draft',
  condition_percent TINYINT UNSIGNED NULL,
  under_warranty TINYINT(1) NULL,
  warranty_months SMALLINT UNSIGNED NULL,
  product_age_months SMALLINT UNSIGNED NULL,
  province VARCHAR(40) NULL,
  seller_warranty_terms VARCHAR(255) NULL,
  is_defective TINYINT(1) NULL,
  has_repair_history TINYINT(1) NULL,
  shipping_option VARCHAR(20) NULL,
  shipping_cost DECIMAL(10,2) NULL,
  has_test_result TINYINT(1) NULL,
  test_result_note VARCHAR(255) NULL,
  defect_codes VARCHAR(255) NULL,
  defect_note VARCHAR(255) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seller_listing_uuid (uuid),
  INDEX idx_seller_listing_member (member_id),
  INDEX idx_seller_listing_status (status, created_at),
  CONSTRAINT fk_seller_listing_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_seller_listing_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Up to 10 photos per listing, re-encoded on upload (EXIF and GPS removed) and kept outside the web root.
CREATE TABLE IF NOT EXISTS seller_listing_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(80) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  bytes INT UNSIGNED NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_listing_photo (listing_id, sort_order),
  CONSTRAINT fk_listing_photo FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;
