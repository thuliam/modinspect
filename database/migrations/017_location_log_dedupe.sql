-- Repost marker for the private province log (sprint LS-1, D-2): lets the admin see how much of a province's volume is repeated posts.
ALTER TABLE listing_location_log
  ADD COLUMN dedupe_key CHAR(12) NULL,
  ADD COLUMN is_repost TINYINT(1) NOT NULL DEFAULT 0,
  ADD INDEX idx_location_repost (province, is_repost);
