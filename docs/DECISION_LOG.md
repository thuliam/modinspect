# ModInspect Decision Log

Dated, short, with consequences. Owner-level decisions are marked **OWNER**. ADRs for architecture live in `docs/decisions/`.

## Decisions taken (2026-11-14 baseline)

| ID | Decision | Why | Consequence |
|---|---|---|---|
| DL-001 | Use official DataTables 1.13.11 (npm `datatables.net` + `datatables.net-bs4`), tarball sha512 verified, installed under `public/assets/admin/vendor/datatables`. **OWNER approved the download.** | The previous `jquery.dataTables.min.js` was a 165-line hand-written "MiniDataTable" adapter, which violated the "official server-side DataTables" gate and silently hid init-contract bugs | Admin tables now run the real library. Bootstrap 4.1.0 and jQuery 3.3.1 unchanged (no major migration) |
| DL-002 | Table source URL lives in `data-source-url`, not `data-ajax` | The official library reads `<table data-*>` as init options; `data-ajax` overrode the `ajax` object and dropped every filter | Guard test forbids `data-ajax`, `data-order`, etc. on server tables |
| DL-003 | `ajax.data` extends DataTables' own request (`$.extend(d, filters)`) and drops `columns` | A returned object replaces the request parameters in the official library | Filters and paging work together; shorter query strings |
| DL-004 | Track `public/assets/admin/vendor/` in git, hash-pinned by `MANIFEST.json` and `cli/admin_vendor_manifest.php` | `.gitignore` `vendor/` hid the only working copy; a clean clone had no admin UI | Anchored `/vendor/` ignore plus explicit un-ignore. Never hand-edit vendor files; replace with an official release and re-run `--write` |
| DL-005 | Fix tests that drifted from the product rather than loosening them | Five failures were stale assertions (array `+` dropping `start`, missing admin session after RBAC, copy changes, hard-coded `C:\xampp8.1\php\php.exe`); none were app defects | The review-queue XSS test now asserts escaping at the data endpoint, which is where titles render |
| DL-006 | Commit the baseline on a **checkpoint branch**, merge `origin/main` locally, **do not push** (owner chose Sprint option A on 2026-11-14) | `studio` was 0 ahead / 2 behind `origin/main` (two CI-only commits); no force, no reset, `studio` itself untouched | Branch `checkpoint/claude-baseline-2026-11-14` = `5b05a31` + merge of `origin/main`. Owner decides about pushing, PR and moving `studio` |
| DL-007 | Public price eligibility: (1) classify mock/fixture/test sources exactly like `Dashboard::datasetLabel` (REAL wins), and (2) never join `legacy_unavailable` snapshots in the public product query | The HTTP E2E proved a leak: approving a TEST-import row (source key `offline_test_example_test`) passed the old "contains mock" check, so the seeded legacy snapshot (range and median with no provenance) was shown publicly | No change to price calculation. Public pages now show insufficient-data until a provenance-recorded snapshot exists. Tests whose "legit" source was named `fixture` now use a neutral source name |
| DL-008 | `.gitattributes` marks `public/assets/admin/vendor/**` as `-text` | `core.autocrlf` on Windows would rewrite line endings on checkout and break the pinned hashes | Vendor files are stored and checked out byte-for-byte |
| DL-009 | Pin the database session `time_zone` to the PHP offset (`SET time_zone = date("P")`) on every connection, and add `tests/timezone_consistency_test.php` | `bootstrap.php` sets PHP to Asia/Bangkok but nothing set the DB session, so `NOW()` followed the server clock. Local MySQL and shared DEV are +07 (consistent), CI MariaDB is UTC, and production is unknown. Mixed `NOW()`/`strtotime()` logic (planner cooldown, freshness) broke on UTC | No change on DEV or the dev machine (already +07); correct on UTC servers. DEV data is unaffected. Verified by CI only for UTC |
| DL-010 | Root `.htaccess` serves only `/public` (everything else 403) | `.env`, `.git`, `*.sql`, `docs/` and `tests/` were downloadable at `/modinspect/...` on the Laragon Apache | Applied live and verified. The correct deployment is still document root = `/public` |
| DL-011 | Only `APP_ENV` local/testing displays PHP errors; anything else (or missing) logs and shows a generic 500; security headers on every web response | Stack traces and `X-Powered-By` must not reach visitors | The Studio `.env` is `local`, so UAT still shows errors. Production `.env` must set `APP_ENV=production` |
| DL-012 | Pass the CSV `$escape` argument explicitly (a single backslash, the old default) to every `fgetcsv`/`fputcsv` | PHP 8.4 deprecates the implicit default; with errors displayed the notices were printed before headers and broke redirects | Behaviour identical on 8.1-8.4. Suite verified on 8.1, 8.3 and 8.4 |
| DL-013 | Owner decision (2026-11-14): Facebook listings the owner collected are usable as REAL evidence with a lower provenance grade (`OWNER_TEXT_NO_URL`); the owner approves per variant. Missing brand/model/spec fields are completed from the listing text where it states them | Owner is the collector and the text is real market data, but there is no listing URL | Approval stays per variant at review (G-4); rows are never auto-approved and never import into shared DEV without a separate owner go-ahead. Completion is deterministic (`ListingEnricher`): stated facts and fixed lookups (line to brand, chipset to socket, speed to DDR generation) are filled and flagged, anything the text does not support stays empty and is listed for the owner (`owner_fill_needed.csv`). Posts that mix products are left blank, never merged |
| DL-014 | Production host is HostAtom: Linux, timezone UTC+7 (owner, 2026-11-14) | Closes the open question behind two Linux/UTC portability bugs | The DB session time zone is pinned to the PHP offset (DL-009); CI already runs on Linux/UTC, so both behaviours are covered. Production `.env` still needs `APP_ENV=production` and document root `/public` (DL-010, DL-011) |
| DL-015 | Visual direction (G-3): C "Market Board" look with A's range-bar answer block in place of C's yellow-highlighted figure (owner, 2026-11-14) | Owner prefers the friendlier look but wants the evidence-first range display | Build in Sprint 2 from tokens re-derived for the hybrid; accent colour to pass a 3:1 contrast check; "AVG" confirmed by owner 2026-11-14 as the price-range answer block |
| DL-016 | **Supersedes DL-015.** Public look follows the owner's reference site as a whole style: black page, monospace headings, coral accent, grey nav pill with an attached coral button, point-cloud hero, mailing-style footer layout (owner, 2026-11-14) | Owner reviewed the VISTA.IO reference and chose its full style over the white Market Board look | Original code only, no copied assets. The range-bar answer block stays (restyled dark, coral band). Thai body text uses a Thai-capable sans because monospace faces have no Thai glyphs; monospace is for headings, nav, buttons and figures. No mailing list or contact block is built (no backend, no invented contact data). Contrast and reduced-motion rules still apply |

## Reconcile plan for branches (OWNER decision)

State on 2026-11-14 (after `git fetch`): `studio` and `office` = `4f6713c`; `origin/main` = `4b84d19`, which
adds only CI workflows, `docs/DEPLOYMENT_PACKAGE_DRY_RUN.md` and `scripts/build_deployment_package.py` (463 lines, no app code).
`studio` has **no** commits that `origin/main` lacks.

Done (2026-11-14): checkpoint branch created from the working tree, baseline committed (`5b05a31`), `origin/main` merged in locally without conflicts (`0e48e69`).
The branch was pushed to `origin` on 2026-11-14 (head `07e0133`, no force). Remaining owner choice: open a PR (the only way `ci-tests.yml` runs) and/or fast-forward `studio`. `studio`/`main` untouched.

## Open owner gates

| Gate | Decision needed | Blocks |
|---|---|---|
| G-1 | Open a PR from the pushed checkpoint branch and/or move `studio` (DL-006); pushed 2026-11-14; `ci-tests.yml` runs only on a PR | First real CI run |
| G-2 | Re-UAT of Admin tables after the library swap | Epic E1 OWNER acceptance |
| G-3 | ~~Choose a public visual direction~~ **Closed 2026-11-14**: DL-015 (C look) superseded by DL-016 (owner reference style, dark mono, coral) | Sprint 2 build |
| G-4 | Owner review of REAL listing-level evidence | Sprint 3, any public price |
| G-5 | Rotate credentials that appear in the raw LINE export; supply a sanitised research subset if wanted | Safe use of LINE research |
| G-6 | Legal/ops direction for blacklist, 5% fee, escrow (currently out of scope) | Any post-beta marketplace work |
| G-7 | Approve one small download of an official Font Awesome 5.x release to fix `.html` font 404s | S1-03 |

Note: tracked docs contain the shared DEV database **hostname and username** (not passwords). Low risk, but consider whether the
repository visibility warrants scrubbing them.
