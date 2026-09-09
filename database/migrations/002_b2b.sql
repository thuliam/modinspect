USE modinspect;
CREATE TABLE IF NOT EXISTS shops (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
  email VARCHAR(190) NULL, phone VARCHAR(40) NULL, plan ENUM('trial','starter','professional') NOT NULL DEFAULT 'trial',
  is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS shop_users (
  shop_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,role ENUM('owner','manager','staff') NOT NULL DEFAULT 'staff',
  PRIMARY KEY(shop_id,user_id),CONSTRAINT fk_shop_user_shop FOREIGN KEY(shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_shop_user_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS shop_inventory_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,shop_id BIGINT UNSIGNED NOT NULL,file_name VARCHAR(255) NOT NULL,
  status ENUM('queued','processing','completed','partial','failed') NOT NULL DEFAULT 'queued',row_count INT UNSIGNED NOT NULL DEFAULT 0,
  matched_count INT UNSIGNED NOT NULL DEFAULT 0,error_count INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,CONSTRAINT fk_import_shop FOREIGN KEY(shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS shop_inventory_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,shop_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NULL,sku VARCHAR(100) NULL,
  raw_product_name VARCHAR(255) NOT NULL,cost_price DECIMAL(12,2) NOT NULL,asking_price DECIMAL(12,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,condition_level ENUM('like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  acquired_at DATE NULL,status ENUM('in_stock','reserved','sold','archived') NOT NULL DEFAULT 'in_stock',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inventory_shop FOREIGN KEY(shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_inventory_status(shop_id,status,acquired_at)
) ENGINE=InnoDB;
INSERT IGNORE INTO data_sources (id,name,domain,source_type,access_method,risk_level,last_success_at) VALUES
(1,'Community sample','community.example','forum','manual','medium',NOW()),(2,'User submission',NULL,'user','upload','low',NOW()),(3,'Shop partner sample','shop.example','shop','feed','low',NOW());
INSERT IGNORE INTO shops (id,name,slug,email,plan) VALUES (1,'Radar Demo Shop','radar-demo','demo@example.test','professional');
INSERT IGNORE INTO shop_inventory_items (id,shop_id,product_id,sku,raw_product_name,cost_price,asking_price,quantity,condition_level,acquired_at) VALUES
(1,1,1,'CPU-5700X3D-01','Ryzen 7 5700X3D',6500,7900,2,'good','2026-06-12'),
(2,1,2,'GPU-RTX3070-01','RTX 3070 8GB',6900,8600,1,'fair','2026-05-01'),
(3,1,3,'CPU-12400F-01','Core i5 12400F',2500,3400,3,'good','2026-07-10');
