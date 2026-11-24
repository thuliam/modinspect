-- LS-87: a signed-in member's watch list lives on the server (so it follows them across devices) and a push subscription can belong to a member.
-- Additive. A visitor without an account keeps the cookie list exactly as before. Nothing personal is stored beyond the member's own id and the model slug.
CREATE TABLE IF NOT EXISTS watch_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  product_slug VARCHAR(120) NOT NULL,
  median_at_watch DECIMAL(12,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_watch_member_slug (member_id, product_slug),
  CONSTRAINT fk_watch_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE push_subscriptions
  ADD COLUMN IF NOT EXISTS member_id BIGINT UNSIGNED NULL AFTER endpoint_hash,
  ADD INDEX IF NOT EXISTS idx_push_member (member_id);
