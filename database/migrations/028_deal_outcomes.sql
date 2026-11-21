-- LS-28 B1: what a visitor says happened after a Deal Checker result (bought / negotiated / passed) and, optionally, the price actually paid.
-- First-party signal only: no free text, no contact data, one row per result. It is a paid/final price and is never mixed with asking prices; nothing here
-- feeds price_observations or price snapshots (community and first-party signals are evidence, not market prices).
CREATE TABLE IF NOT EXISTS deal_outcomes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_check_id BIGINT UNSIGNED NOT NULL,
  outcome ENUM('bought','negotiated','passed') NOT NULL,
  final_price DECIMAL(12,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_deal_outcome (deal_check_id),
  CONSTRAINT fk_outcome_deal FOREIGN KEY (deal_check_id) REFERENCES deal_checks(id) ON DELETE CASCADE
) ENGINE=InnoDB;
