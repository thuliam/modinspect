-- LS-90 D5: notifications waiting to be delivered. The only transport is a local log file: nothing is e-mailed or pushed.
-- The payload holds identifiers and codes, never an address or message text written by a person.
CREATE TABLE IF NOT EXISTS notification_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(40) NOT NULL,
  subject_id BIGINT UNSIGNED NULL,
  status ENUM('queued','logged') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  INDEX idx_notification_queue (status, id),
  CONSTRAINT fk_notification_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
