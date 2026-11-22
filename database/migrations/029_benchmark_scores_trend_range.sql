-- LS-32 A: published benchmark scores per catalog model (reference information, never a price input) and the lowest/highest asking price per trend month
-- (the wicks of the candle chart). Both are additive: nothing existing is renamed or rewritten, and a row without low/high simply draws no wick.
CREATE TABLE IF NOT EXISTS product_benchmark_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(40) NOT NULL,
  metric_key VARCHAR(40) NOT NULL,
  score_value DECIMAL(8,2) NOT NULL,
  scale_note VARCHAR(120) NOT NULL,
  source_name VARCHAR(80) NOT NULL,
  source_url VARCHAR(500) NOT NULL,
  license_note VARCHAR(80) NOT NULL,
  source_updated_on DATE NULL,
  retrieved_on DATE NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bench_score (product_id, source_key, metric_key),
  INDEX idx_bench_product (product_id),
  CONSTRAINT fk_bench_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE price_trend_points
  ADD COLUMN IF NOT EXISTS low_price DECIMAL(12,2) NULL AFTER q3,
  ADD COLUMN IF NOT EXISTS high_price DECIMAL(12,2) NULL AFTER low_price;
