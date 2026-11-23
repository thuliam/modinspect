-- LS-68: up to five pictures per Product Master model, each with its source and licence (a picture without rights information is refused by the importer).
-- Additive. products.image_path stays the picture the public pages already use and is kept equal to slot 1 by the importer.
CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  slot TINYINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(160) NULL,
  source_name VARCHAR(120) NOT NULL,
  source_url VARCHAR(500) NULL,
  license_note VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_image_slot (product_id, slot),
  CONSTRAINT fk_product_image_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
