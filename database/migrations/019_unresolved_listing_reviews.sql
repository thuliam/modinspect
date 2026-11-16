-- LS-3 J-1: a person's decision about a listing the resolver could not match to a product (one row per raw observation).
-- 'alias_added' records which alias a reviewer created for it; 'dismissed' marks a listing that should stay unresolved (not a catalog item, bundle, noise).
-- Nothing here changes a price: an alias only affects how FUTURE imports resolve.
CREATE TABLE IF NOT EXISTS unresolved_listing_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NOT NULL,
  status ENUM('alias_added','dismissed') NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  alias_id BIGINT UNSIGNED NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unresolved_raw (raw_observation_id),
  INDEX idx_unresolved_status (status, created_at),
  CONSTRAINT fk_unresolved_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_unresolved_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_unresolved_alias FOREIGN KEY (alias_id) REFERENCES product_aliases(id) ON DELETE SET NULL,
  CONSTRAINT fk_unresolved_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB;
