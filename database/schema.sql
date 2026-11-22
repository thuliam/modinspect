CREATE DATABASE IF NOT EXISTS modinspect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE modinspect;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','reviewer','operator','admin') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_login_attempts_window (email_hash, ip_address, attempted_at),
  INDEX idx_login_attempts_success (success, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE product_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE brands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NOT NULL,
  model_name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  full_name VARCHAR(255) NOT NULL,
  generation VARCHAR(100) NULL,
  release_year SMALLINT UNSIGNED NULL,
  spec_summary TEXT NULL,
  tdp_watt SMALLINT UNSIGNED NULL,
  recommended_psu_watt SMALLINT UNSIGNED NULL,
  socket VARCHAR(40) NULL,
  chipset VARCHAR(40) NULL,
  memory_type VARCHAR(20) NULL,
  form_factor VARCHAR(30) NULL,
  capacity_gb INT UNSIGNED NULL,
  image_path VARCHAR(255) NULL,
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id),
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id),
  INDEX idx_products_search (is_active, category_id, brand_id),
  FULLTEXT INDEX ft_products_name (model_name, full_name, spec_summary)
) ENGINE=InnoDB;

CREATE TABLE product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NULL,
  variant_name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  cooler_type VARCHAR(80) NULL,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_variant_slug (product_id, slug),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_variants_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB;

CREATE TABLE product_aliases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  alias_text VARCHAR(255) NOT NULL,
  normalized_alias VARCHAR(255) NOT NULL,
  source_note VARCHAR(255) NULL,
  confidence DECIMAL(5,2) NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_normalized_alias (normalized_alias),
  CONSTRAINT fk_alias_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_alias_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE data_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_key VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  domain VARCHAR(190) NULL,
  source_type ENUM('marketplace','forum','shop','user','research','manual') NOT NULL,
  access_method ENUM('api','feed','manual','browser','upload') NOT NULL,
  allowed_collection_method VARCHAR(80) NOT NULL DEFAULT 'manual',
  risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  reliability_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  evidence_quality ENUM('high','medium','low','unknown') NOT NULL DEFAULT 'unknown',
  freshness_expectation_days INT UNSIGNED NULL,
  request_budget_per_day INT UNSIGNED NULL,
  cost_budget_per_day DECIMAL(10,4) NULL,
  terms_note TEXT NULL,
  robots_note TEXT NULL,
  policy_note TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_paused TINYINT(1) NOT NULL DEFAULT 0,
  pause_reason TEXT NULL,
  disabled_reason TEXT NULL,
  last_success_at DATETIME NULL,
  last_blocked_at DATETIME NULL,
  last_failure_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE collector_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id BIGINT UNSIGNED NOT NULL,
  job_type VARCHAR(80) NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  query_text VARCHAR(255) NULL,
  status ENUM('queued','running','completed','partial','failed') NOT NULL DEFAULT 'queued',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  run_id VARCHAR(80) NULL,
  started_at DATETIME NULL, completed_at DATETIME NULL,
  raw_count INT UNSIGNED NOT NULL DEFAULT 0,
  valid_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  api_cost DECIMAL(10,4) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_jobs_source FOREIGN KEY (source_id) REFERENCES data_sources(id),
  CONSTRAINT fk_jobs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_jobs_status (status, created_at)
  ,INDEX idx_jobs_run_id (run_id)
) ENGINE=InnoDB;

CREATE TABLE collection_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id VARCHAR(80) NOT NULL UNIQUE,
  source_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(120) NOT NULL,
  job_type VARCHAR(80) NOT NULL,
  execution_mode ENUM('normal','dry_run') NOT NULL DEFAULT 'normal',
  started_at DATETIME NOT NULL,
  finished_at DATETIME NOT NULL,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_claimed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_failed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_skipped INT UNSIGNED NOT NULL DEFAULT 0,
  candidates_created INT UNSIGNED NOT NULL DEFAULT 0,
  evidence_created INT UNSIGNED NOT NULL DEFAULT 0,
  extractions_created INT UNSIGNED NOT NULL DEFAULT 0,
  reviews_created INT UNSIGNED NOT NULL DEFAULT 0,
  green_count INT UNSIGNED NOT NULL DEFAULT 0,
  amber_count INT UNSIGNED NOT NULL DEFAULT 0,
  red_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_collection_runs_source FOREIGN KEY (source_id) REFERENCES data_sources(id),
  INDEX idx_collection_runs_source (source_id, started_at),
  INDEX idx_collection_runs_mode (execution_mode, started_at)
) ENGINE=InnoDB;

CREATE TABLE raw_price_observations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id BIGINT UNSIGNED NOT NULL,
  collector_job_id BIGINT UNSIGNED NULL,
  external_reference_hash CHAR(64) NOT NULL,
  raw_title VARCHAR(500) NOT NULL,
  raw_price_text VARCHAR(120) NULL,
  raw_condition_text VARCHAR(255) NULL,
  raw_warranty_text VARCHAR(255) NULL,
  source_url_encrypted TEXT NULL,
  evidence_path VARCHAR(255) NULL,
  captured_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  processing_status ENUM('new','processed','review','rejected') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_source_reference (source_id, external_reference_hash),
  CONSTRAINT fk_raw_source FOREIGN KEY (source_id) REFERENCES data_sources(id),
  CONSTRAINT fk_raw_job FOREIGN KEY (collector_job_id) REFERENCES collector_jobs(id) ON DELETE SET NULL,
  INDEX idx_raw_queue (processing_status, captured_at)
) ENGINE=InnoDB;

CREATE TABLE price_observations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  price_type ENUM('asking','sold','trade_in','new') NOT NULL DEFAULT 'asking',
  asking_price DECIMAL(12,2) NULL,
  price_value DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'THB',
  condition_level ENUM('new','like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  warranty_months SMALLINT UNSIGNED NULL,
  has_box TINYINT(1) NULL, has_receipt TINYINT(1) NULL, has_benchmark TINYINT(1) NULL,
  listing_type ENUM('single_item','bundle','whole_pc','wanted','unknown') NOT NULL DEFAULT 'single_item',
  is_deposit TINYINT(1) NOT NULL DEFAULT 0,
  is_defective TINYINT(1) NOT NULL DEFAULT 0,
  is_duplicate TINYINT(1) NOT NULL DEFAULT 0,
  evidence_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  evidence_weight DECIMAL(6,4) NOT NULL DEFAULT 1,
  freshness_weight DECIMAL(6,4) NOT NULL DEFAULT 1,
  source_weight DECIMAL(6,4) NOT NULL DEFAULT 1,
  classification_confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
  final_weight DECIMAL(8,4) NOT NULL DEFAULT 1,
  quality_flags JSON NULL,
  verified_status ENUM('pending','approved','rejected','excluded') NOT NULL DEFAULT 'pending',
  observed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  condition_percent TINYINT UNSIGNED NULL,
  under_warranty TINYINT(1) NULL,
  product_age_months SMALLINT UNSIGNED NULL,
  province VARCHAR(40) NULL,
  seller_warranty_terms VARCHAR(255) NULL,
  has_repair_history TINYINT(1) NULL,
  shipping_option VARCHAR(20) NULL,
  shipping_cost DECIMAL(10,2) NULL,
  test_result_note VARCHAR(255) NULL,
  defect_codes VARCHAR(255) NULL,
  defect_note VARCHAR(255) NULL,
  CONSTRAINT fk_obs_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_obs_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_obs_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
  INDEX idx_obs_index (product_id, verified_status, price_type, observed_at),
  INDEX idx_obs_review (verified_status, classification_confidence)
) ENGINE=InnoDB;

CREATE TABLE price_indices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  price_type ENUM('asking','sold','trade_in','new') NOT NULL DEFAULT 'asking',
  price_low DECIMAL(12,2) NOT NULL, q1 DECIMAL(12,2) NOT NULL, median DECIMAL(12,2) NOT NULL,
  q3 DECIMAL(12,2) NOT NULL, price_high DECIMAL(12,2) NOT NULL,
  fast_sale_min DECIMAL(12,2) NULL, fast_sale_max DECIMAL(12,2) NULL,
  market_min DECIMAL(12,2) NULL, market_max DECIMAL(12,2) NULL,
  premium_min DECIMAL(12,2) NULL, premium_max DECIMAL(12,2) NULL,
  sample_size INT UNSIGNED NOT NULL DEFAULT 0,
  valid_sample_size INT UNSIGNED NOT NULL DEFAULT 0,
  fresh_sample_ratio DECIMAL(5,2) NOT NULL DEFAULT 0,
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0,
  confidence_label ENUM('high','medium','medium_low','low','insufficient') NOT NULL DEFAULT 'insufficient',
  formula_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned',
  cohort_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned',
  quartile_method_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned',
  confidence_method_version VARCHAR(80) NOT NULL DEFAULT 'legacy-unversioned',
  calculation_hash CHAR(64) NULL,
  calculation_manifest JSON NULL,
  provenance_status ENUM('legacy_unavailable','recorded') NOT NULL DEFAULT 'legacy_unavailable',
  last_calculated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_index_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_index_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  INDEX idx_index_latest (product_id, product_variant_id, price_type, last_calculated_at),
  INDEX idx_index_hash (calculation_hash)
) ENGINE=InnoDB;

CREATE TABLE price_snapshot_observations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  observation_id BIGINT UNSIGNED NOT NULL,
  inclusion_status ENUM('included','excluded') NOT NULL,
  exclusion_reason VARCHAR(80) NULL,
  calculation_price DECIMAL(12,2) NULL,
  snapshot_verified_status VARCHAR(30) NOT NULL,
  snapshot_price_type VARCHAR(30) NOT NULL,
  snapshot_listing_type VARCHAR(30) NOT NULL,
  snapshot_source_id BIGINT UNSIGNED NULL,
  snapshot_observed_at DATETIME NOT NULL,
  weight DECIMAL(8,4) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_snapshot_observation (snapshot_id, observation_id),
  CONSTRAINT fk_snapshot_observations_snapshot FOREIGN KEY (snapshot_id) REFERENCES price_indices(id) ON DELETE CASCADE,
  CONSTRAINT fk_snapshot_observations_observation FOREIGN KEY (observation_id) REFERENCES price_observations(id) ON DELETE CASCADE,
  INDEX idx_snapshot_observations_snapshot (snapshot_id, inclusion_status),
  INDEX idx_snapshot_observations_observation (observation_id)
) ENGINE=InnoDB;

CREATE TABLE price_histories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  q1 DECIMAL(12,2) NOT NULL, median DECIMAL(12,2) NOT NULL, q3 DECIMAL(12,2) NOT NULL,
  sample_size INT UNSIGNED NOT NULL,
  confidence_score DECIMAL(5,2) NOT NULL,
  snapshot_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_history_snapshot (product_id, product_variant_id, snapshot_date),
  CONSTRAINT fk_history_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE deal_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  user_price DECIMAL(12,2) NOT NULL,
  condition_level ENUM('like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  warranty_months SMALLINT UNSIGNED NULL,
  has_box TINYINT(1) NOT NULL DEFAULT 0, has_receipt TINYINT(1) NOT NULL DEFAULT 0,
  has_benchmark TINYINT(1) NOT NULL DEFAULT 0,
  intended_use VARCHAR(100) NULL,
  price_score DECIMAL(5,2) NULL, risk_score DECIMAL(5,2) NULL,
  value_score DECIMAL(5,2) NULL, fit_score DECIMAL(5,2) NULL,
  result_label ENUM('below_range','in_range','above_range','insufficient') NOT NULL,
  suggested_price_min DECIMAL(12,2) NULL, suggested_price_max DECIMAL(12,2) NULL,
  recommendation TEXT NOT NULL, warning_note TEXT NULL,
  consent_to_anonymous_observation TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_deal_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_deal_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE spec_builds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NULL,
  uuid CHAR(36) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NULL,
  budget_min DECIMAL(12,2) NOT NULL,
  budget_max DECIMAL(12,2) NOT NULL,
  intended_use VARCHAR(100) NOT NULL,
  target_resolution ENUM('1080p','1440p','4k','none') NOT NULL DEFAULT '1080p',
  mix_preference ENUM('used','mixed','new') NOT NULL DEFAULT 'used',
  estimated_low DECIMAL(12,2) NULL,
  estimated_expected DECIMAL(12,2) NULL,
  estimated_high DECIMAL(12,2) NULL,
  compatibility_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_build_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_spec_builds_member (member_id)
) ENGINE=InnoDB;

CREATE TABLE spec_build_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spec_build_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  component_role VARCHAR(50) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  price_low DECIMAL(12,2) NULL,
  price_expected DECIMAL(12,2) NULL,
  price_high DECIMAL(12,2) NULL,
  confidence_score DECIMAL(5,2) NULL,
  is_alternative TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_build_item_build FOREIGN KEY (spec_build_id) REFERENCES spec_builds(id) ON DELETE CASCADE,
  CONSTRAINT fk_build_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE articles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
  excerpt TEXT NULL, body LONGTEXT NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_article_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE faqs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id BIGINT UNSIGNED NULL,
  question VARCHAR(500) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_faq_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL, action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(100) NULL, entity_id BIGINT UNSIGNED NULL,
  before_data JSON NULL, after_data JSON NULL,
  ip_address VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;

-- LS-19 A1: tables from migration 005 that schema.sql had lost (found by tests/fresh_install_schema_test.php)
CREATE TABLE market_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  evidence_level ENUM('A','B','C','D','E') NOT NULL DEFAULT 'C',
  evidence_type ENUM('url','snippet','screenshot','page_capture','upload','fixture') NOT NULL DEFAULT 'url',
  source_url_hash CHAR(64) NULL,
  storage_path VARCHAR(255) NULL,
  excerpt TEXT NULL,
  content_hash CHAR(64) NULL,
  captured_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evidence_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_evidence_source FOREIGN KEY (source_id) REFERENCES data_sources(id),
  INDEX idx_evidence_source (source_id, captured_at),
  INDEX idx_evidence_hash (content_hash)
) ENGINE=InnoDB;

CREATE TABLE extraction_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NOT NULL,
  evidence_id BIGINT UNSIGNED NULL,
  provider_name VARCHAR(120) NOT NULL,
  model_name VARCHAR(120) NULL,
  prompt_version VARCHAR(80) NULL,
  schema_version VARCHAR(80) NOT NULL DEFAULT 'market_observation_v1',
  extracted_data JSON NOT NULL,
  confidence DECIMAL(6,4) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_extraction_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_extraction_evidence FOREIGN KEY (evidence_id) REFERENCES market_evidence(id) ON DELETE SET NULL,
  INDEX idx_extraction_raw (raw_observation_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE observation_review_decisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_observation_id BIGINT UNSIGNED NULL,
  raw_observation_id BIGINT UNSIGNED NULL,
  extraction_run_id BIGINT UNSIGNED NULL,
  lane ENUM('green','amber','red') NOT NULL,
  decision ENUM('review_required','approved','edited','rejected','quarantined') NOT NULL,
  reason_codes JSON NOT NULL,
  ai_value JSON NULL,
  rule_result JSON NULL,
  human_value JSON NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_observation FOREIGN KEY (price_observation_id) REFERENCES price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_extraction FOREIGN KEY (extraction_run_id) REFERENCES extraction_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_user FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_review_lane_decision (lane, decision, created_at)
) ENGINE=InnoDB;

CREATE TABLE review_corrections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_observation_id BIGINT UNSIGNED NOT NULL,
  review_decision_id BIGINT UNSIGNED NULL,
  field_name VARCHAR(80) NOT NULL,
  original_value TEXT NULL,
  corrected_value TEXT NULL,
  reason TEXT NOT NULL,
  corrected_by BIGINT UNSIGNED NULL,
  corrected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_correction_field (price_observation_id, field_name),
  CONSTRAINT fk_review_corrections_observation FOREIGN KEY (price_observation_id) REFERENCES price_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_corrections_decision FOREIGN KEY (review_decision_id) REFERENCES observation_review_decisions(id) ON DELETE SET NULL,
  CONSTRAINT fk_review_corrections_user FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_review_corrections_observation (price_observation_id, corrected_at)
) ENGINE=InnoDB;

CREATE TABLE source_incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id BIGINT UNSIGNED NOT NULL,
  incident_type ENUM('blocked','rate_limited','authentication_required','source_structure_changed','repeated_extraction_failure','manually_paused','manually_disabled','terms_change','quality_drop','manual_pause','other') NOT NULL,
  message TEXT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  description TEXT NOT NULL,
  action_taken ENUM('paused','disabled','reduced_rate','monitor','none') NOT NULL DEFAULT 'paused',
  created_by VARCHAR(80) NOT NULL DEFAULT 'system',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_incident_source FOREIGN KEY (source_id) REFERENCES data_sources(id) ON DELETE CASCADE,
  INDEX idx_incident_source (source_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE shops (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  plan ENUM('trial','starter','professional') NOT NULL DEFAULT 'trial',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE shop_users (
  shop_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','manager','staff') NOT NULL DEFAULT 'staff',
  PRIMARY KEY (shop_id,user_id),
  CONSTRAINT fk_shop_user_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_shop_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shop_inventory_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  status ENUM('queued','processing','completed','partial','failed') NOT NULL DEFAULT 'queued',
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  matched_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  CONSTRAINT fk_import_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shop_inventory_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  sku VARCHAR(100) NULL,
  raw_product_name VARCHAR(255) NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL,
  asking_price DECIMAL(12,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  condition_level ENUM('like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  acquired_at DATE NULL,
  status ENUM('in_stock','reserved','sold','archived') NOT NULL DEFAULT 'in_stock',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inventory_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_inventory_status (shop_id,status,acquired_at)
) ENGINE=InnoDB;

CREATE TABLE price_trend_points (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  period_start DATE NOT NULL,
  period_type ENUM('month') NOT NULL DEFAULT 'month',
  q1 DECIMAL(12,2) NOT NULL,
  median DECIMAL(12,2) NOT NULL,
  q3 DECIMAL(12,2) NOT NULL,
  low_price DECIMAL(12,2) NULL,
  high_price DECIMAL(12,2) NULL,
  sample_size INT UNSIGNED NOT NULL,
  built_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trend (product_id, period_type, period_start),
  CONSTRAINT fk_trend_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE listing_location_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  row_ref VARCHAR(40) NOT NULL,
  province VARCHAR(60) NOT NULL,
  region ENUM('central','east','north','northeast','south') NOT NULL,
  note_date DATE NULL,
  category VARCHAR(20) NULL,
  model_key VARCHAR(80) NULL,
  asking_price_thb INT UNSIGNED NULL,
  source_label VARCHAR(80) NOT NULL DEFAULT 'owner_collected_facebook_text',
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dedupe_key CHAR(12) NULL,
  is_repost TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_location_row (row_ref),
  INDEX idx_location_province (province),
  INDEX idx_location_repost (province, is_repost)
) ENGINE=InnoDB;

CREATE TABLE unresolved_listing_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_observation_id BIGINT UNSIGNED NOT NULL,
  status ENUM('alias_added','dismissed') NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  alias_id BIGINT UNSIGNED NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unresolved_raw (raw_observation_id),
  INDEX idx_unresolved_status (status, created_at),
  CONSTRAINT fk_unresolved_raw FOREIGN KEY (raw_observation_id) REFERENCES raw_price_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_unresolved_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_unresolved_alias FOREIGN KEY (alias_id) REFERENCES product_aliases(id) ON DELETE SET NULL,
  CONSTRAINT fk_unresolved_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE product_reference_specs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  spec_key VARCHAR(60) NOT NULL,
  spec_value VARCHAR(120) NOT NULL,
  unit VARCHAR(12) NULL,
  source_url VARCHAR(500) NOT NULL,
  date_checked DATE NOT NULL,
  review_status ENUM('official_checked','needs_variant_review') NOT NULL,
  review_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ref_spec (product_id, spec_key),
  INDEX idx_ref_spec_key (spec_key),
  CONSTRAINT fk_ref_spec_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE new_price_references (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  source_url VARCHAR(500) NOT NULL,
  checked_on DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_new_price_product (product_id, checked_on),
  CONSTRAINT fk_new_price_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_new_price_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  terms_accepted_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_members_email (email)
) ENGINE=InnoDB;

CREATE TABLE member_identities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL,
  subject VARCHAR(191) NOT NULL,
  email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_identity (provider, subject),
  CONSTRAINT fk_identity_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_listings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(32) NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  asking_price DECIMAL(12,2) NOT NULL,
  status ENUM('draft','submitted','approved','rejected','withdrawn') NOT NULL DEFAULT 'draft',
  condition_percent TINYINT UNSIGNED NULL,
  under_warranty TINYINT(1) NULL,
  warranty_months SMALLINT UNSIGNED NULL,
  product_age_months SMALLINT UNSIGNED NULL,
  province VARCHAR(40) NULL,
  seller_warranty_terms VARCHAR(255) NULL,
  is_defective TINYINT(1) NULL,
  has_repair_history TINYINT(1) NULL,
  shipping_option VARCHAR(20) NULL,
  shipping_cost DECIMAL(10,2) NULL,
  has_test_result TINYINT(1) NULL,
  test_result_note VARCHAR(255) NULL,
  defect_codes VARCHAR(255) NULL,
  defect_note VARCHAR(255) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seller_listing_uuid (uuid),
  INDEX idx_seller_listing_member (member_id),
  INDEX idx_seller_listing_status (status, created_at),
  CONSTRAINT fk_seller_listing_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_seller_listing_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE seller_listing_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(80) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  bytes INT UNSIGNED NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_listing_photo (listing_id, sort_order),
  CONSTRAINT fk_listing_photo FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(32) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  identifier_type ENUM('phone','bank_account') NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  identifier_last4 CHAR(4) NOT NULL,
  problem_type VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  status ENUM('pending','verified','rejected','disputed') NOT NULL DEFAULT 'pending',
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seller_report_uuid (uuid),
  INDEX idx_seller_report_hash (identifier_hash, status),
  INDEX idx_seller_report_status (status, created_at),
  CONSTRAINT fk_seller_report_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE seller_report_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_report_evidence (report_id),
  CONSTRAINT fk_report_evidence FOREIGN KEY (report_id) REFERENCES seller_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_report_disputes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL,
  explanation VARCHAR(1000) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dispute_hash (identifier_hash, status)
) ENGINE=InnoDB;

-- LS-21 (R-11): community reports, see database/migrations/026_community_reports.sql
CREATE TABLE community_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('wrong_product','price_looks_off','stale','sold_price','spec_wrong') NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  observation_id BIGINT UNSIGNED NULL,
  snapshot_id BIGINT UNSIGNED NULL,
  candidate_observation_id BIGINT UNSIGNED NULL,
  payload JSON NOT NULL,
  reporter_hash CHAR(32) NOT NULL,
  status ENUM('new','accepted','rejected','spam') NOT NULL DEFAULT 'new',
  moderator_id BIGINT UNSIGNED NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  INDEX idx_reports_status (status, created_at),
  INDEX idx_reports_product (product_id, report_type),
  INDEX idx_reports_reporter (reporter_hash, product_id, created_at),
  CONSTRAINT fk_community_report_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- LS-24 M9: community forum, see database/migrations/027_community_forum.sql
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

CREATE TABLE deal_outcomes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_check_id BIGINT UNSIGNED NOT NULL,
  outcome ENUM('bought','negotiated','passed') NOT NULL,
  final_price DECIMAL(12,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_deal_outcome (deal_check_id),
  CONSTRAINT fk_outcome_deal FOREIGN KEY (deal_check_id) REFERENCES deal_checks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_benchmark_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(40) NOT NULL,
  metric_key VARCHAR(40) NOT NULL,
  score_value DECIMAL(8,2) NOT NULL,
  scale_note VARCHAR(120) NOT NULL,
  source_name VARCHAR(80) NOT NULL,
  source_url VARCHAR(500) NOT NULL,
  license_note VARCHAR(80) NOT NULL,
  source_updated_on DATE NULL,
  retrieved_on DATE NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bench_score (product_id, source_key, metric_key),
  INDEX idx_bench_product (product_id),
  CONSTRAINT fk_bench_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
