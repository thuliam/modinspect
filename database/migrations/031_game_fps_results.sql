-- LS-59: published in-game frame rates per CPU/GPU model (reference information shown on the product page, never a price input).
-- Every row names the part it was paired with (a CPU result needs the GPU used, a GPU result needs the CPU), its source and licence, and an optional sponsor so a sponsored test is always labelled.
-- Additive and empty on creation: a model without rows shows an honest "no results yet" panel.
CREATE TABLE IF NOT EXISTS game_fps_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  game_key VARCHAR(20) NOT NULL,
  resolution VARCHAR(10) NOT NULL,
  quality VARCHAR(30) NOT NULL,
  avg_fps DECIMAL(6,1) NOT NULL,
  low1_fps DECIMAL(6,1) NULL,
  paired_part VARCHAR(120) NOT NULL,
  source_name VARCHAR(80) NOT NULL,
  source_url VARCHAR(500) NOT NULL,
  license_note VARCHAR(80) NOT NULL,
  sponsor_name VARCHAR(80) NULL,
  tested_on DATE NULL,
  retrieved_on DATE NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_game_fps (product_id, game_key, resolution, quality, paired_part),
  INDEX idx_game_fps_product (product_id),
  CONSTRAINT fk_game_fps_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
