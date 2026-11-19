# Data contract for collected files (intake-v1)

LS-23 B1/B2, 2026-11-19. The machine-readable source of truth is `database/contracts/intake-v1.json`; this page explains it. The same field names, required/optional flags and
completeness numbers are in the master prompt given to collectors, and `tests/intake_validator_test.php` pins the contract so the two cannot drift apart silently.

## What the check does and does not do

`php cli/intake_validate.php <folder-or-zip> [--json] [--no-db]` is **read-only**. It finds files by their contract names, judges every row, and prints per file the row count, the valid
row count and problems as `(row, column, code)`. It never prints a title, price or link, so a report can be pasted anywhere. It then measures **completeness** against the criteria below.
With a database it also checks that a named model exists (or is proposed in the same batch) and that an alias is new. `--no-db` skips those checks. A zip is read entry by entry under our
own names (a path inside the archive is never used) and each file is capped at 5 MB.

A passing check means "well formed and complete". It does **not** make a row true, and nothing is imported by it. Import stays dry-run first, real rows go to the review queue,
and a person approves them (AGENTS.md: community and collected data never set a price snapshot).

## Datasets

| Key | File | Rows mean | Required columns | Optional columns |
|---|---|---|---|---|
| A | `A_used_listings.csv` | one asking-price listing | capture_method (`owner_transcription`), source_listing_url, source_item_id, listing_title, asking_price, observed_at | condition_hint |
| A_extra | `A_used_listings_extra.csv` | extra facts for one listing (join on source_item_id) | source_item_id | product_name_guess, warranty_remaining_months, has_box, has_receipt, usage_hint_months, province, posted_at, flag |
| B | `B_new_price_references.csv` | a retailer's new price | product_name, price_thb, source_url, checked_on (not older than 14 days) | in_stock, note |
| C | `C_reference_specs.csv` | one manufacturer spec value | product_name, spec_key, spec_value, source_url, date_checked, review_status | unit, review_note |
| D_products | `D_new_products.csv` | a model missing from the catalog | category, brand, model_name, full_name, official_url | release_year, generation, market_note |
| D_aliases | `D_aliases.csv` | a name people use for a model | product_name, alias_text | alias_kind, evidence_url |
| E | `E_condition_checks.csv` | a pre-purchase check (draft, owner approves wording) | category, check_title_th, how_to_check_th, red_flag_th, severity | needs_tool, source_url |
| F | `F_sold_prices.csv` | a confirmed sold price (review queue only) | source_listing_url, source_item_id, listing_title, sold_price, sold_confirmed_by, observed_at | asking_price_before, condition_hint |

Asking and sold prices never share a file. Listing links must be on facebook.com, pantip.com or kaidee.com (the owner-transcription allow-list in `CaptureMethod`).

## Completeness criteria (what "complete" means)

| Dataset | Complete when |
|---|---|
| A | each model has at least 5 listings from at least 2 sources within the last 45 days |
| B | every target model (no new-price reference yet) has at least 1 row, 2 shops preferred |
| C | each model has every required key of its category (cpu: cores, threads, base_ghz, boost_ghz, socket, memory_type; gpu: vram_gb, board_power_watt; motherboard: socket, memory_type, form_factor; ram: capacity_gb, memory_type, speed_mhz; storage: capacity_gb, interface; psu: psu_capacity_watt; case: form_factor, max_gpu_length_mm; cooling: cooler_type, cooler_height_mm) |
| D_aliases | each model has at least 4 aliases, none already in the catalog |
| E | each category has at least 6 checks with at least 3 `must_check` |

Spec keys marked "proposed" in the contract (chipset, speed_mhz, interface, cooler_type, ...) are accepted by the check but need an additive migration before they can be stored.

## Row codes

Header: `HEADER_MISSING_COLUMN:<name>`, `HEADER_UNKNOWN_COLUMN:<name>`, `FILE_NOT_FOUND`, `FILE_EMPTY`, `FILE_NOT_UTF8`, `FILE_TOO_MANY_ROWS`.
Row: `ROW_REQUIRED_EMPTY`, `ROW_BLANK`, `ROW_TOO_MANY_CELLS`, `ROW_TOO_LONG`, `ROW_BAD_ENUM`, `ROW_UNKNOWN_SPEC_KEY`, `ROW_BAD_URL`, `ROW_DOMAIN_NOT_ALLOWED`, `ROW_PROFILE_LINK`,
`ROW_TRACKING_PARAMS`, `ROW_BAD_PRICE_FORMAT` (a comma, a baht sign or text), `ROW_PRICE_RANGE`, `ROW_BAD_DATE`, `ROW_BUDDHIST_YEAR`, `ROW_DATE_IN_FUTURE`, `ROW_DATE_TOO_OLD`, `ROW_BAD_INT`,
`ROW_BAD_YEAR`, `ROW_BAD_FLAG`, `ROW_DUPLICATE_KEY`, `ROW_UNKNOWN_PRODUCT`, `ROW_PRODUCT_ALREADY_EXISTS`, `ROW_ALIAS_ALREADY_EXISTS`, `ROW_FORMULA_CELL` (a cell starting with `=`, `+`, `-` or `@`),
`ROW_CONTROL_CHARS`, and the privacy codes `ROW_PHONE_IN_TEXT`, `ROW_EMAIL_IN_TEXT`, `ROW_LINE_ID_IN_TEXT`. The PII scan finds common patterns only; it cannot find a name or an address typed into a title.

## Companion export

`php cli/export_collection_targets.php [--out-dir=storage/import]` writes `B_targets.csv` (models with no new-price reference, motherboard/PSU/case/cooling first), `A_targets.csv`
(models that still need used listings) and `D_existing_names.csv` (every model and alias) for the collector. Read-only on the database; CSV cells that start with `=+-@` are neutralised.

## The commands (all dry run or read-only unless stated)

| Step | Command | What it does |
|---|---|---|
| Targets for the collector | `php cli/export_collection_targets.php` | writes `B_targets.csv`, `A_targets.csv`, `D_existing_names.csv` |
| Check a batch | `php cli/intake_validate.php <folder-or-zip> [--json]` | row codes and completeness, no cell values printed |
| One command for a batch | `php cli/intake_daily_run.php <folder> [--apply --actor=ID]` | dry run by default; each clean dataset goes through its importer; a dataset with a problem is skipped whole |
| A: used listings (+ extras) | `php cli/intake_prepare_import.php <folder>` then `php cli/import_market_evidence.php --file=... --dataset=real --dry-run` | joins the extras (warranty, age, province, box, receipt), leaves out wanted/trade/bundle; rows become PENDING candidates |
| B: new prices | `php cli/intake_import_new_prices.php <file> --actor=ID [--apply]` | `new_price_references` through the admin service, audited |
| C: specs | `php cli/intake_generate_specs_migration.php <file>` | writes an INSERT IGNORE migration; applying it is a separate step |
| D: models and aliases | `php cli/intake_generate_products_migration.php <folder>` | writes an additive migration; run it before the specs migration of the same batch |
| F: sold prices | `php cli/intake_import_sold_prices.php <file> --actor=ID [--apply]` | PENDING `sold` candidates for the review queue; never approved, never a snapshot |
| After a review | `php cli/snapshot_recompute.php [--apply]` | shows which prices appear or move; `--apply` stores a snapshot only for new or changed products |
| Measure | `php cli/coverage_report.php [--missing]`, `php cli/condition_feasibility.php` | per-category coverage; whether a price can honestly be split by warranty, box or receipt |

Dataset E (condition checks) has no importer yet: its text is a draft for the owner's approval (HG-4), so only the check and the completeness criteria exist.
Spec keys the contract adds (chipset, speed_mhz, cooler_type, ...) need no schema change: `spec_key` is free text; `ProductFacts` holds their Thai labels, and the price-index filters read `speed_mhz`, `interface`, `psu_capacity_watt` and `cooler_type` before they fall back to the model name.
`memory_bus_bit` in the contract is stored as the catalog's existing key `bus_width_bit`.
