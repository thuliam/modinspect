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
| G-3 | Choose a public visual direction (A, B or C in `docs/UX_UI_SYSTEM.md`) | Sprint 2 |
| G-4 | Owner review of REAL listing-level evidence | Sprint 3, any public price |
| G-5 | Rotate credentials that appear in the raw LINE export; supply a sanitised research subset if wanted | Safe use of LINE research |
| G-6 | Legal/ops direction for blacklist, 5% fee, escrow (currently out of scope) | Any post-beta marketplace work |
| G-7 | Approve one small download of an official Font Awesome 5.x release to fix `.html` font 404s | S1-03 |

Note: tracked docs contain the shared DEV database **hostname and username** (not passwords). Low risk, but consider whether the
repository visibility warrants scrubbing them.
