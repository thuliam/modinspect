-- Price trend buckets derived from approved observations' observed_at (rebuilt idempotently by cli/price_trend_build.php).
-- Derived data only: it never feeds price snapshots or confidence.
CREATE TABLE IF NOT EXISTS price_trend_points (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  period_start DATE NOT NULL,
  period_type ENUM('month') NOT NULL DEFAULT 'month',
  q1 DECIMAL(12,2) NOT NULL,
  median DECIMAL(12,2) NOT NULL,
  q3 DECIMAL(12,2) NOT NULL,
  sample_size INT UNSIGNED NOT NULL,
  built_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trend (product_id, period_type, period_start),
  CONSTRAINT fk_trend_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
