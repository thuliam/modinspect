ALTER TABLE users MODIFY role ENUM('user','reviewer','operator','admin') NOT NULL DEFAULT 'user';

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_login_attempts_window (email_hash, ip_address, attempted_at),
  INDEX idx_login_attempts_success (success, attempted_at)
) ENGINE=InnoDB;
