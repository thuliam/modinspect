-- LS-74: storage and PSU facts as columns of products, like RAM and motherboards already have: drive interface and media (NVMe/SATA, SSD/HDD), PSU wattage, 80 PLUS efficiency and cable type.
-- Additive and nullable: every existing row stays as it is. Filled by cli/storage_psu_backfill.php and the import centre's Storage and PSU types.
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS interface_type VARCHAR(20) NULL AFTER kit_modules,
  ADD COLUMN IF NOT EXISTS media_type VARCHAR(10) NULL AFTER interface_type,
  ADD COLUMN IF NOT EXISTS power_watt SMALLINT UNSIGNED NULL AFTER media_type,
  ADD COLUMN IF NOT EXISTS efficiency_rating VARCHAR(20) NULL AFTER power_watt,
  ADD COLUMN IF NOT EXISTS modular_type VARCHAR(20) NULL AFTER efficiency_rating;
