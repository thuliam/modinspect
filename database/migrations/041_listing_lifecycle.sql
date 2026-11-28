-- LS-90 D2: the life of a seller listing after moderation: how many, until when, and whether it is still for sale. Additive only.
-- The moderation status column is unchanged. A listing is never market evidence (HG-17): nothing here touches price tables.
ALTER TABLE seller_listings ADD COLUMN quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE seller_listings ADD COLUMN sale_state ENUM('available','reserved','sold') NOT NULL DEFAULT 'available';
ALTER TABLE seller_listings ADD COLUMN expires_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS listing_status_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id BIGINT UNSIGNED NOT NULL,
  actor_type ENUM('member','admin','system') NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  event VARCHAR(30) NOT NULL,
  from_value VARCHAR(30) NULL,
  to_value VARCHAR(30) NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_listing_event (listing_id, id),
  CONSTRAINT fk_listing_event FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
