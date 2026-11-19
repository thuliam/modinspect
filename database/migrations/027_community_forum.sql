-- LS-24 M9: community forum (threads, replies, structured reviews of what a buyer got, helpful votes, content flags). Additive. Nothing here touches a price,
-- an observation or a snapshot. The feature is OFF until COMMUNITY_FORUM_ENABLED=true. Content belongs to a member and is deleted with the member (ON DELETE CASCADE).
CREATE TABLE community_threads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(2000) NOT NULL,
  body_hash CHAR(32) NOT NULL,
  status ENUM('visible','hidden','locked') NOT NULL DEFAULT 'visible',
  hidden_reason VARCHAR(40) NULL,
  post_count INT UNSIGNED NOT NULL DEFAULT 0,
  flag_count INT UNSIGNED NOT NULL DEFAULT 0,
  helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_post_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_threads_list (status, last_post_at),
  INDEX idx_threads_product (product_id, status, created_at),
  INDEX idx_threads_member (member_id, created_at),
  CONSTRAINT fk_thread_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_thread_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE community_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  body VARCHAR(1500) NOT NULL,
  body_hash CHAR(32) NOT NULL,
  status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  hidden_reason VARCHAR(40) NULL,
  flag_count INT UNSIGNED NOT NULL DEFAULT 0,
  helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_posts_thread (thread_id, status, created_at),
  INDEX idx_posts_member (member_id, created_at),
  CONSTRAINT fk_post_thread FOREIGN KEY (thread_id) REFERENCES community_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE community_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  price_paid INT UNSIGNED NOT NULL,
  source_kind ENUM('marketplace','shop','friend','other') NOT NULL,
  condition_received ENUM('like_new','good','fair','poor') NOT NULL,
  issue_30d ENUM('none','minor','major') NOT NULL,
  would_buy_again TINYINT(1) NOT NULL,
  note VARCHAR(500) NULL,
  status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  hidden_reason VARCHAR(40) NULL,
  flag_count INT UNSIGNED NOT NULL DEFAULT 0,
  helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_member_product (member_id, product_id),
  INDEX idx_reviews_product (product_id, status, created_at),
  CONSTRAINT fk_review_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE community_votes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('thread','post','review') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vote (target_type, target_id, member_id),
  CONSTRAINT fk_vote_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE community_flags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('thread','post','review') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  reason ENUM('pii','accusation','spam','abuse','wrong') NOT NULL,
  status ENUM('open','upheld','dismissed') NOT NULL DEFAULT 'open',
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_flag (target_type, target_id, member_id),
  INDEX idx_flags_queue (status, created_at),
  CONSTRAINT fk_flag_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;
