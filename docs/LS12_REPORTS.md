# LS-12 report (2026-11-17)

Owner request: fill the catalog from `official_hardware_sources.csv` before the pilot. Done on DEV after a backup. No spend, no live crawl.

## What the file is

22 rows of manufacturer spec pages (CPU 3, GPU 2, motherboard 4, RAM 2, storage 1, PSU 2, case 3, cooling 2, monitor 3). Every row has an official URL and a check date (2026-11-17), and **every row says `NOT_VERIFIED_FOR_MARKET_PRICING`**: it is specifications, not prices.

## What was done

- The CSV is kept at `database/reference/official_hardware_sources.csv`. `cli/gen_hardware_reference_migration.php` generates `database/migrations/020_hardware_reference_specs.sql` from it, so the SQL is never edited by hand (a test fails if they drift).
- **7 candidate parts added to the catalog** (the rows marked CANDIDATE_ADD): MSI PRO B650M-A WIFI, Kingston FURY Beast DDR5-6000 32GB EXPO, Samsung 990 EVO Plus 1TB, MSI MAG A750GL PCIE5, DeepCool CC560, Montech AIR 903 MAX, DeepCool AK400 ZERO DARK. Only facts the CSV states are written (socket, memory type, form factor, capacity, a Thai summary built from those facts); release year, generation and chipset are left empty rather than guessed. Two new brands (DeepCool, Montech).
- **New table `product_reference_specs`**: 45 spec rows for the 19 non-monitor rows, each with its source URL, check date and a review status. The four rows that warn about exact variants (Kingston 16GB kit, Corsair RM750x, Corsair 4000D Airflow, Cooler Master Hyper 212 Halo) keep the status `needs_variant_review`, because the catalog holds a generic model and the source describes one exact SKU. The table is mirrored in `database/schema.sql` and `modinspect.sql` (the dump guard passes).
- **Not done: the 3 monitors.** No monitor category exists; the page has a "จอภาพ" slot but no data behind it. They stay in the CSV.
- The 12 existing parts named in the file (CHECK_EXISTING) were compared with the catalog: socket, memory type and form factor agree with what the database already holds. No existing product row was changed.

## Evidence

- `tests/hardware_reference_migration_test.php` (pure): quoting, only candidates become products, monitors never appear in a statement, review statuses, units, no UPDATE/DELETE/DROP/ALTER, no price tables touched, committed migration equals generator output, counts match the CSV (7 products, 45 reference specs).
- Applied twice to the isolated test database: identical counts both times (idempotent), then removed from it.
- DEV: backup of `thuliam_modinspect` taken first (5.6 MB, 33 tables, kept in the agent scratchpad, outside the repository). Migration applied once: 7 products, 17 aliases, 45 reference specs, 2 brands, exactly the expected numbers.

## What this does NOT do (said plainly)

- **It does not unlock the Build page.** A category is selectable only when a part has a market price from approved listings. These parts have none, so motherboard, PSU, case and cooling stay locked ("รอข้อมูล") and a complete PC still cannot be built. Only reviewed listings for those categories can change that.
- Nothing reads `product_reference_specs` yet. It is storage with provenance, ready for future compatibility checks (GPU length against case, cooler height against case, PSU wattage).
- The daily target list (`cli/collection_targets.php`) only lists products that already have collected rows, so it does not point the owner at motherboard, PSU, case or cooling. That is a gap worth closing next, because those listings are the only way to open the page.
- The Thai summaries of the seven new parts were built by the generator from the CSV facts and have not been reviewed by the owner.
- The CSV's own claims were taken as given; I did not open the manufacturer pages. `Ryzen 3 3400G` in the catalog may be a wrong name (the real product is probably Ryzen 5 3400G); still unconfirmed and unchanged.

## Close-out

Full sequential suite exit 0 with the new test registered (run before the last documentation edits, which are text only). The secret scan covers tracked files only, so the new untracked files were searched separately for credential patterns and only prose mentions turned up. Nothing is committed or pushed yet.
