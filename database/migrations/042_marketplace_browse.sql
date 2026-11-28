-- LS-90 D3: what a member saves while browsing listings, and reports about a listing. Additive only.
CREATE TABLE IF NOT EXISTS saved_listings (
  member_id BIGINT UNSIGNED NOT NULL,
  listing_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id, listing_id),
  CONSTRAINT fk_saved_listing_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_saved_listing_listing FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_searches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  filters_json VARCHAR(500) NOT NULL,
  filters_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_saved_search (member_id, filters_hash),
  CONSTRAINT fk_saved_search_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  reason ENUM('wrong_info','suspicious','already_sold','prohibited','personal_data') NOT NULL,
  note VARCHAR(255) NULL,
  status ENUM('open','upheld','dismissed') NOT NULL DEFAULT 'open',
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_listing_report (listing_id, member_id),
  INDEX idx_listing_report_queue (status, created_at),
  CONSTRAINT fk_listing_report_listing FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_listing_report_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
