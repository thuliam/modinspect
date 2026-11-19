-- LS-14 Module 6 (owner decision OD-2, 2026-11-18): a retail NEW price an admin looked up for one product, with the shop link and the day it was checked.
-- It is a separate reference for the Build page when a part has no used-market price. It is never an observation, never part of a snapshot,
-- a median or a confidence score, and it is always labelled "ราคาของใหม่อ้างอิง" where it is shown. Additive - nothing else changes.
CREATE TABLE IF NOT EXISTS new_price_references (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  source_url VARCHAR(500) NOT NULL,
  checked_on DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_new_price_product (product_id, checked_on),
  CONSTRAINT fk_new_price_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_new_price_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;
