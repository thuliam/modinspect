-- LS-71: RAM facts as columns, like memory type, form factor and capacity already are: the memory bus speed (MHz) and the number of modules in the kit.
-- Additive and nullable: every existing row stays as it is. Filled by cli/ram_backfill.php and the import centre's RAM type.
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS memory_speed_mhz SMALLINT UNSIGNED NULL AFTER capacity_gb,
  ADD COLUMN IF NOT EXISTS kit_modules TINYINT UNSIGNED NULL AFTER memory_speed_mhz;
