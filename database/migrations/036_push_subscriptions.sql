-- LS-86 (PWA P3 backend): browser push subscriptions for price-change alerts. Additive; nothing reads it unless PUSH_ENABLED=true.
-- Minimal data by design: the push endpoint and its two public keys (all the browser gives), and the list of watched model slugs with the median seen when
-- watching began (the visitor's own cookie list, see WatchList). No name, email, phone, account, IP address or user agent is stored.
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  endpoint_hash CHAR(64) NOT NULL,
  endpoint VARCHAR(1024) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(64) NOT NULL,
  watch_json TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_notified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_push_endpoint (endpoint_hash),
  KEY idx_push_active (is_active, last_notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
