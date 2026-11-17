# LS-8 reports (2026-11-17)

Scope approved by the owner: O-1 to O-4. Nothing was written to the DEV database (the targets list only reads; the purge tool was only run in dry-run mode on the test database). No paid service, no live collection.

## O-1 Thai collection target list

`php cli/collection_targets.php [--format=text|csv]` (read-only). It lists the products that still have no public price, closest to a price first, with how many usable asking rows each lacks, how many it already has, and search words taken only from the catalog name (leading brand words such as NVIDIA, GeForce, AMD, Intel, Core, Radeon dropped, so `AMD Radeon RX 6600 XT` becomes `RX 6600 XT`). A `bimodal` product (enough rows but two price groups) goes last and is told to have its old rows reviewed first, not to get more rows. On DEV today: 22 products, 33 more rows needed to reach 3 each, 9 products only one row short.

Why 33 and not the 34 of `cli/pending_gap_report.php`: that older report adds the shortfall of the bimodal product too; the new list leaves it out because collecting more rows does not fix it. The old report was not changed. Code: `app/Services/Admin/CollectionTargets.php`; `tests/collection_targets_test.php` (4 mutations caught; one mutant survived until the test gave the bimodal row a non-zero need).

## O-2 Retention tool (no default period, dry run first)

`php cli/retention_purge.php [--deal-checks-days=N] [--builds-days=N] [--ratelimit-hours=N] [--apply]`. The owner has not chosen retention periods (blank in `docs/PRIVACY_TERMS_DRAFT_TH.md`), so there is **no default**: a target is only touched when its period is given, and with no period the tool refuses. Without `--apply` it only counts. Floors: 7 days for a database table, 1 hour for the cache; below that it refuses, so a typo (`--builds-days=0`) cannot wipe data. It deletes Deal Checker rows, Build rows (their items follow by the foreign key) and rate-limit cache files older than the cut-off, in one transaction, and writes one audit row with counts only (no uuid, no content). It was run only as a dry run on the test database; it was not run on DEV. Take a backup first (`php cli/db_backup.php`). Code: `app/Services/Ops/RetentionPurge.php`; `tests/retention_purge_test.php` (6 mutations caught).

## O-3 Admin analytics: Capture Method card

`/admin/review-analytics` has a new card listing the five buckets (owner transcription, machine capture, owner text, other real, fixture) with candidates and reviewed/approved/rejected/excluded/corrected, and marks fixtures "not live evidence". It reuses the existing `mi-status-list` markup; no CSS or JS was written. Pinned by an assertion in `tests/review_calibration_test.php` (red first). **Not viewed in a browser** (it needs a signed-in admin session); only the rendered HTML was checked.

## O-4 Verification

Full suite and secret scan before the commit; CI status read from the PR monitor after the push.
