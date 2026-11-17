# ModInspect Session Handoff

## CURRENT HANDOFF (2026-11-15, after R0 + T1 slice). Read this first; sections below are history.

- **Repo:** `C:\laragon\www\modinspect`, branch `checkpoint/claude-baseline-2026-11-14`. Remote heads: `origin/main` 4b84d19, `studio`/`office` 4f6713c (not moved). Owner's untracked files `MODINSPECT_LADIP_TECHNICAL_DESIGN_PROPOSAL.md` and `design/ci/` must stay untouched and uncommitted.
- **Runtime:** use the absolute PHP path `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe` (`php` is not on the cmd PATH). Tests: `MODINSPECT_ENV_FILE=.env.testing php tests/run.php` (41 steps, exit 0 on 2026-11-15). The CI test DB is built from the tracked `modinspect.sql`: every new table must be added there as well as to `database/schema.sql` and a migration.
- **DB:** shared DEV is the cloud DB in `.env` (not production). Isolated tests use `.env.testing` on `127.0.0.1/modinspect_test`. Never mutate DEV except on an explicit owner instruction.
- **Owner rules (AGENTS.md "UI Rule"):** no hand-written CSS/JS when a library/template can do it; ask before substituting a supplied template; downloads need approval; zero cost; admin account is the owner's (email login; password reset only via `cli/reset_admin_password.php`, typed by the owner).
- **Delivered this session:** `cli/price_trust_audit.php` (read-only, exit 1 on P0), `Product::evidence()`, `components/evidence-explorer.php` on the product page, trend chart only with 2+ months, `tests/price_trust_test.php`, `docs/PRODUCT_INTELLIGENCE_PROVIDER_MATRIX.md` (research only, nothing called), PRD/roadmap/tracker rebaseline, DL-032.
- **Verified (auto):** full suite exit 0; audit on DEV: 46 public prices, 46 replay MATCH, 0 P0, 0 P1; evidence explorer rendered on a DEV product page. **Not verified:** CI on GitHub for the latest commits, owner visual acceptance (none), golden resolver rerun.
- **Data truth:** 420 approved observations, all owner text (grade C, one source); 88 REAL (Priceza) still pending, 0 approved; 46 recorded public snapshots, all low confidence after confidence-v2 (were 35 low / 11 medium), 41 legacy snapshots never public.
- **Owner gates open:** T1-6 confidence policy for 100 % owner-text evidence; U2 visual acceptance of public pages and the new Admin; I3 provider/licence approval; G-4 review of REAL evidence; G-5 rotate credentials exposed in the raw LINE export; G-7 Font Awesome official download; PR #4 merge; any deploy.
- **Update (later 2026-11-15):** owner chose option A. confidence-v2 (owner-text-only cap) is live, 11 DEV snapshots recalculated, U2 first slice delivered (DL-033). **Next:** owner visual review of public pages; then rest of U2 (708px, a11y/contrast, photos, home polish) or the options the owner picks.
- **Parallel agents:** Long Sprint LS-1 is split between Claude and Codex: read `docs/SPRINT_BOARD.md` (lock, tasks, status) and, for Codex, `docs/CODEX_LS1_PROMPT.txt`. Respect the file-ownership table.
- **Safety:** no deploy, no spend, never print or commit the raw LINE export, no auto-approval of REAL observations.

## 0. READ THIS FIRST — current handoff (2026-11-14, Claude takeover)

Everything from section 1 down is the **September OFFICE to STUDIO handoff and is historical**. Current truth is
`docs/PROJECT_STATUS.md` section 0, then `docs/SPRINT_TRACKER.md`.

- Branch/HEAD: work is on `checkpoint/claude-baseline-2026-11-14` (pushed to origin 2026-11-14, no PR yet; CI runs only on a PR): baseline `5b05a31`, merge of `origin/main` `0e48e69`, then the Sprint 1 commit(s). `studio`/`office` untouched at `4f6713c`. Do not reset, force-push or clean; the owner decides push/PR/moving `studio` (G-1).
- Open uncommitted work: none of mine. The untracked `MODINSPECT_LADIP_TECHNICAL_DESIGN_PROPOSAL.md` belongs to the owner (parked, documentation only).
- Active program: Sprint 0 complete; Sprint 1 engineering slice done (commit, CI authored, HTTP E2E, public gate fix); waiting for the owner re-UAT, a push to run CI, and the next Sprint choice.
- Safe commands (no secrets):
  - `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe` is the PHP to use (not on the Git Bash PATH).
  - Tests (32 steps, includes a real-HTTP E2E that starts its own PHP server and writes committed rows to the TEST db only): `MODINSPECT_ENV_FILE=.env.testing php tests/run.php` (isolated `127.0.0.1/modinspect_test`). **Always** set `MODINSPECT_ENV_FILE=.env.testing` for CLI that touches the DB; without it the app reads `.env` = shared Cloud DEV.
  - Vendor check: `php cli/admin_vendor_manifest.php --verify`.
  - Browser testing: run `php -S 127.0.0.1:8099 -t public` with `MODINSPECT_ENV_FILE` pointing at an env file whose DB is the local test DB **and** whose `APP_URL` is `http://127.0.0.1:8099` (form actions and redirects use `APP_URL`; the default `.env.testing` URL sends logins to the Apache site on port 80). Create a throwaway admin in the test DB only.
- Admin tables: official DataTables 1.13.11. Table source attribute is `data-source-url` (not `data-ajax`); `ajax.data` must extend the request.
- Owner gates: G-1 push/PR/move studio, G-2 Admin re-UAT, G-3 visual direction (CLOSED 2026-11-14, DL-016: owner reference style, dark mono, coral), G-4 REAL evidence review (88 pending, 0 approved), G-5 rotate credentials from the raw LINE export, G-6 legal direction (blacklist/fee/escrow stay out of scope), G-7 Font Awesome download approval.
- Known not-done: Font Awesome `.html` font URLs (404, needs G-7), eligibility rule copied in ~10 places (S1-13), no runtime probe of `/.env` 403 (S1-14), no public POST rate limiting (S1-15), tablet header two rows tall, `ci-tests.yml` PHP matrix not yet run on GitHub, no positive public price read (needs owner-approved REAL evidence).
- A read-only count query was run once against shared DEV (SELECT only, in a READ ONLY transaction). One evaluator CLI (`cli/evaluate_validation_quality.php`, SELECT-only) also ran against DEV by mistake of omitted env selection; it performs no writes.
- 2026-11-14 security review: the project root had no `.htaccess`, so `.env`, `.git`, `*.sql` were downloadable on the Studio Apache; fixed by a root `.htaccess` (see `docs/SECURITY_REVIEW_2026-11-14.md`). Test PHP 8.1/8.4 locally with a temporary `php.ini` via `PHPRC` (Laragon enables no DB extension for them).

## 1. Handoff Context

- Source machine/session: OFFICE
- Destination machine/session: STUDIO
- Handoff date/time: 2026-09-09 23:54 ICT
- Current Git branch: `office`
- Current HEAD commit hash: `a4e494100e2ee202c93c696c23d01c620cb89f54`
- Working tree before creating this handoff file: clean, with ignored local `storage/import/` state.
- Working tree after creating this handoff file: dirty until `docs/SESSION_HANDOFF.md` is committed.
- Uncommitted files after this handoff update:
  - `docs/SESSION_HANDOFF.md`

Remote:

```text
origin  https://github.com/thuliam/modinspect.git
```

Do not change branches during handoff unless the owner explicitly requests it.

## 2. Current Architecture Baseline

- Blueprint: `ModInspect_Master_Blueprint_v1.1.md`
- `AGENTS.md` is mandatory context.
- `docs/PROJECT_STATUS.md` is the human-readable implementation source.
- `context/project_state.json` is the machine-readable status source.

Repository/runtime state is authoritative over old reports, old chat history, or summaries.

## 3. Verified Completed Milestones

According to `docs/PROJECT_STATUS.md` and current repository state:

| Phase | Status |
|---|---|
| Phase 0 â€” Foundation / Test Harness | PASS |
| Phase 1 â€” Product Master POC | PASS / POC gate reached |
| Phase 2A â€” Coverage Planner | PASS |
| Phase 2B â€” Mock Scheduled Runner | PASS |
| Phase 2C â€” Collection Operations Foundation | PASS |
| Phase 2D-A â€” Live Provider Infrastructure / Preflight | PASS |
| Phase 2D-I â€” Application Integration & UI Synchronization | PASS |
| Phase 2D-J â€” Stability / Regression / Bug Hunt | PASS |
| Phase 3A â€” Snapshot Provenance & Formula Versioning | PASS |
| Phase 3B â€” Review Correction Workflow & Observation Quality Controls | PASS |
| Phase 3C â€” CPU/GPU Category-Specific Validation Rules & Quality Metrics | PASS |
| Phase 3D â€” Admin Authentication & RBAC | PASS |
| Phase 3E â€” Price Confidence, Freshness & Source Diversity | PASS |
| Phase 3F â€” Review Operations & Calibration Analytics | PASS |
| Phase 3G â€” Offline Real-World Evidence Intake | PASS |
| Phase 4A â€” UX/UI Product Audit & Design Foundation | PASS |

Do not reproduce old milestone reports unless needed; use `docs/PROJECT_STATUS.md` for the concise state.

## 4. Waiting / Blocked Milestones

Phase 2D-B â€” Controlled Live Provider POC:

- Status: WAITING FOR CREDENTIALS / APPROVAL
- No real Gemini API key is configured.
- Live collection remains disabled by default.
- Do not make a live request automatically.
- Do not use paid or metered providers without explicit owner approval.

Phase 3H â€” Reviewed Offline REAL Calibration Batch:

- Status: WAITING_FOR_REAL_SAMPLE
- Owner-supplied REAL evidence file is not yet available.
- Expected local ignored location: `storage/import/real_calibration_batch.csv` or `.json`.
- Do not fabricate REAL data.
- Do not relabel test fixtures as REAL.

## 5. Current Data State

Latest safe local CLI checks during this handoff:

| Metric | Count |
|---|---:|
| CPU products | 10 |
| GPU products | 11 |
| raw candidates / raw observations | 18 |
| evidence | 18 |
| extractions | 18 |
| reviews | 18 |
| observations | 11 |
| accepted observations | 0 |
| collection runs | 2 |
| coverage jobs queued | 0 |
| coverage jobs running | 0 |
| coverage jobs completed | 24 |
| coverage jobs failed | 0 |

Current calibration state:

- dataset label: `MOCK_TEST`
- REAL records: `0`
- accepted observations: `0`
- calibration status: `FIXTURE_BASELINE`

Data classification:

- Current committed/seeded pipeline data is MOCK / TEST.
- No reviewed REAL calibration batch is present.
- Ignored `storage/import/` may contain local files that Git will not transfer.

## 6. Current Capabilities

| Domain | Status |
|---|---|
| Product Master | IMPLEMENTED |
| Product aliases/resolver | IMPLEMENTED |
| Coverage Planner | IMPLEMENTED |
| Mock Collection Runner | IMPLEMENTED |
| Source Policy / Source Health | IMPLEMENTED |
| Offline CSV/JSON Evidence Import | IMPLEMENTED |
| Review / Correction Workflow | IMPLEMENTED |
| CPU/GPU Category Validation | IMPLEMENTED |
| Snapshot Provenance / Reproduction | IMPLEMENTED |
| Asking/Sold Separation | IMPLEMENTED |
| Price Confidence | IMPLEMENTED |
| Review Calibration Analytics | IMPLEMENTED |
| Admin Auth/RBAC | IMPLEMENTED |
| Admin Operational UI | PARTIAL but wired/auth-protected |
| Public Price UI Integration | PARTIAL but honest/gated |
| Zero-cost provider guards | IMPLEMENTED as policy/config guardrails |
| Gemini live execution | WAITING |
| Reviewed offline REAL calibration | WAITING |
| Community Feedback runtime | DOCUMENTED ONLY |

## 7. Important Locked Rules

- Data-first, provider-independent.
- Product Master is not Market Observation.
- All acquisition channels converge into the same Candidate -> Evidence -> Extraction -> Resolution -> Validation -> Review pipeline.
- Acquisition adapters never directly create trusted observations.
- The deterministic Price Engine owns market-price calculation.
- Asking price is not sold/final price.
- Original evidence and original extraction are immutable.
- Human corrections are auditable review-layer artifacts.
- Mock/test data must never contaminate public market statistics.
- Community input is evidence/signals, not voting.
- Community input never directly sets market price.
- Source blocking never triggers protection bypass/evasion.
- Admin authorization is enforced server-side.
- Human/admin actions preserve authenticated actor identity where a human session exists.

## 8. ZERO-COST POC POLICY

MODINSPECT POC EXTERNAL SPEND TARGET = 0 THB.

Without explicit owner approval:

- no paid APIs
- no paid data source
- no paid infrastructure
- no credit-card-backed provider
- no metered service with cost risk
- no subscription upgrade

If monetary cost may occur, STOP with:

```text
OWNER_APPROVAL_REQUIRED
```

## 9. Known Technical Debt / Watchpoints

- No reviewed REAL calibration batch yet.
- Validation/confidence metrics are fixture-calibrated; they do not prove live-market quality.
- No real Gemini credentials are configured.
- Live provider execution must not start automatically.
- Admin auth/RBAC exists, but no MFA/SSO/password reset or external security review exists.
- Full visual UX validation has not happened; Phase 4A was code-level and conservative.
- DB-heavy PHP tests share one MySQL database and should remain sequential unless per-worker isolation is added.
- Legacy snapshots before Phase 3A/3E lack full provenance/confidence manifests.
- Offline import is CLI-only; browser upload is not implemented.
- Current handoff branch is `office`; verify the intended branch after pulling on STUDIO.

## 10. Next Active Work

Actual repository state records that the first Phase 4B owner visual review was rejected, and a fix round is ready for renewed owner review.

Next intended product/design step:

```text
Phase 4B Owner Visual Review Gate
```

Objective:

- Owner visually reviews the four refined public pages: Home, Price Index/Search, Product Detail, and Deal Checker.
- Use rendered UI, not code-only inspection, to decide whether the Phase 4B direction is approved.

Do not perform another UX/UI milestone before owner review.

If the owner instead supplies a REAL calibration file first, resume Phase 3H using `docs/REAL_CALIBRATION_PROTOCOL.md`.

## 11. Owner Visual Review Plan

Inspect screenshots/rendered pages for:

1. Home
2. Price Index/Search
3. Product Detail
4. Deal Checker

UX decisions should be based on actual rendered UI, not code alone.

## 12. Resume Instructions for STUDIO Session

Before continuing:

1. Confirm repository branch and HEAD after pull.
2. Run `git status`.
3. Read `AGENTS.md`.
4. Read `docs/SESSION_HANDOFF.md`.
5. Read `docs/PROJECT_STATUS.md`.
6. Read `ModInspect_Master_Blueprint_v1.1.md`.
7. Read `context/project_state.json`.
8. Inspect actual code relevant to the next milestone.
9. Run a minimal health/regression verification before modifying code.
10. Do not assume previous chat/session context exists.

Then continue only from the verified repository state.

## 13. Current STUDIO Development Topology

Verified during the 2026-09-11 STUDIO bootstrap session:

- Codex CLI is operated from the client side against the STUDIO project files.
- Current repository path is `N:\modinspect`, resolving to `\\192.168.1.10\www\modinspect`.
- The old OFFICE path `C:\xampp8.1\htdocs\modinspect` is no longer the active ModInspect runtime target.
- STUDIO runtime target is Laragon with Apache/PHP and ModInspect minimum PHP remains `>= 8.1`.
- Source code is synchronized through Git.
- Development data is now intended to be shared by OFFICE and STUDIO through the HostAtom/DirectAdmin Cloud MariaDB DEV database.
- Shared DEV database host/database: `thsv16.hostatom.com:3306` / `thuliam_modinspect`.
- The Cloud DEV database password must live only in local `.env` or another local secret mechanism. Do not print it, commit it, or store it in project documentation.
- Automated tests must not mutate the shared Cloud DEV database. DB-mutating tests require an isolated local TEST database, for example STUDIO Laragon MySQL, before execution.
- Live collection, paid provider calls, and Gemini provider execution remain disabled unless the owner explicitly approves them.

Bootstrap status after local STUDIO infrastructure verification:

- Git branch: `main`.
- HEAD: `dd646f95a4d547ac8b340144cd611101a610873c`.
- Runtime: Laragon, Apache 2.4.68, PHP 8.3.33.
- STUDIO local path: `C:\laragon\www\modinspect`.
- Client mapped path: `N:\modinspect`.
- `.env` exists, loads, and points application DEV traffic to the shared Cloud DEV database.
- PHP/PDO connection to Cloud MariaDB 10.6.27 is verified by the local infrastructure session.
- Current ModInspect Cloud schema is verified.
- Application URL: `http://192.168.1.10/modinspect/public/`.
- `/`, `/price`, and `/login` return HTTP 200.
- `/admin` correctly redirects unauthenticated users to `/login`.
- Cloud DEV destructive mutations during bootstrap: none.
- Live provider/API calls during bootstrap: zero.
- External spend: 0 THB.
- Laragon SSL is temporarily disabled because `vpnserver_x64` owns TCP/443. Do not re-enable/change HTTPS during ordinary development tasks.
- The expected application document root is `N:\modinspect\public`; `public/.htaccess` rewrites requests to `public/index.php`.

## 14. Recommended First Command/Task on STUDIO

Finish environment bootstrap before feature work:

```text
git status
git branch --show-current
git rev-parse HEAD
# On the STUDIO host, locate the actual Laragon PHP binary, then:
<laragon-php.exe> -v
<laragon-php.exe> --ini
<laragon-php.exe> -m
<laragon-php.exe> tests\syntax_check.php
```

Application `.env` uses shared Cloud DEV and must not be used for automated regression fixtures:

```text
APP_URL=http://192.168.1.10/modinspect/public
DB_HOST=thsv16.hostatom.com
DB_PORT=3306
DB_DATABASE=thuliam_modinspect
DB_USERNAME=thuliam_modinspect
DB_PASSWORD=<local secret only>
MODINSPECT_LIVE_COLLECTION_ENABLED=false
MODINSPECT_PAID_PROVIDER_CALLS_ENABLED=false
GEMINI_PROVIDER_ENABLED=false
GEMINI_API_KEY=
```

Automated tests now use explicit test configuration:

```text
.env.testing
DB_HOST=127.0.0.1
DB_DATABASE=modinspect_test
```

Run `php tests/bootstrap_test_db.php --fresh`, then `php tests/run.php`. The test bootstrap fails closed with `UNSAFE_TEST_DATABASE_BLOCKED` if the Cloud DEV host or DB name is detected.

Do not immediately implement features until branch, migrations/state, PHP/MySQL config, Cloud DB compatibility, and regression safety are understood. Then proceed to owner visual review or Phase 3H if a REAL calibration file is available.
## 15. Phase 4B Public UX/UI MVP Refinement

Updated during the 2026-09-11 STUDIO development session.

Status:

- OWNER VISUAL REVIEW REJECTED / FIX ROUND READY FOR OWNER REVIEW.
- Scope was limited to the primary public flow: Home -> Price Index/Search -> Product Detail -> Deal Checker.
- No pricing algorithm, confidence algorithm, schema, collection, review, provider, Gemini, or live acquisition behavior was changed.
- Cloud DEV destructive mutations during this UX pass: zero.
- External API/provider calls: zero.
- New external spend: 0 THB.

Public UI changes:

- Header primary navigation now prioritizes `à¹€à¸Šà¹‡à¸à¸£à¸²à¸„à¸²`, Deal Checker, and Methodology.
- Footer keeps deferred modules secondary and labels Compare/Seller tools as `à¸à¸³à¸¥à¸±à¸‡à¸žà¸±à¸’à¸™à¸²`.
- Home now presents the approved ModInspect identity/tagline, one prominent product search, real current counts, a focused product-card section, and one Deal Checker CTA.
- Price Index now has a stronger search/filter panel, CPU/GPU quick filters, clearer result summary, and product cards that show observed range, median/reference price, confidence, sample count, or an honest insufficient-data state.
- Product Detail now places product identity, observed range, median/reference price, confidence, sample/freshness context, and Deal Checker CTA ahead of history/methodology details.
- Deal Checker form/result now use neutral language and explain that condition, warranty, accessories, and seller service can justify price differences.
- Shared public CSS was extended in `public/assets/app.css` for Phase 4B layout, cards, confidence badges, empty states, form labels, focus states, and responsive behavior.

Fix round after owner visual rejection:

- Product cards are now compact and remain browsable without market-price data.
- Every rendered Price Index card includes a visible Product Detail action.
- Repeated large `à¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸•à¸¥à¸²à¸”à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¹€à¸žà¸µà¸¢à¸‡à¸žà¸­` panels were removed from list cards and replaced with compact no-price status text.
- Product Detail supports no-data products as first-class pages with category, brand, model, generation/spec context, honest market-data status, and Deal Checker availability.
- Home hero now prioritizes tagline, search, product discovery, and Deal Checker; dataset telemetry was reduced to a small note.
- Public Admin link remains available but visually secondary.

Runtime checks performed on the STUDIO URL:

- `GET /` -> 200.
- `GET /price` -> 200.
- `GET /price/amd-radeon-rx-6600-xt` -> 200.
- `GET /deal-checker` -> 200.
- `GET /login` -> 200.
- `GET /admin` unauthenticated -> 302 to `/login`.
- Public pages were checked for obvious PHP error text and selected internal public-forbidden terms; none were found in the checked pages.
- Fix-round checks: Price Index rendered 41 cards and 41 detail actions; zero repeated list `.mini-empty` panels remained; no-data Product Detail returned 200; Deal Checker GET remained renderable with product preselect.
- Read-only Cloud DEV admin-user inspection found zero admin users. No password hashes, password values, tokens, or secrets were printed.

Owner visual review required before Phase 4B can be treated as design-approved:

1. Home
2. Price Index/Search
3. Product Detail
4. Deal Checker

Do not start Phase 4C or another UX/UI milestone until the owner has reviewed the rendered pages.

## 16. Phase 3H-R1 First REAL Market Calibration Batch

Updated during the 2026-09-12 CLIENT/STUDIO development session.

Status:

- HUMAN REVIEW CONSOLE FIX READY_FOR_OWNER_REVIEW.
- Phase 4B remains paused for first REAL data calibration.
- No Phase 4C or Community Feedback work was started.

REAL import outcome:

- Import file: `storage/import/real_calibration_batch.csv` under ignored raw import storage.
- Source used: public Priceza search pages, source key `offline_real_priceza_com`.
- Products sampled:
  - product #76 `AMD Radeon RX 6600 XT`: 2 pending observations.
  - product #20 `AMD Ryzen 5 5600`: 5 pending observations.
  - product #1 `AMD Ryzen 7 5700X3D`: 6 pending observations.
  - product #71 `NVIDIA GeForce RTX 3060 Ti`: 9 pending observations.
  - product #2 `NVIDIA GeForce RTX 3070`: 10 pending observations.
  - product #74 `NVIDIA GeForce RTX 4070`: 10 pending observations.
- Dry run result: 42 valid rows, 0 invalid rows, 0 duplicates, 0 source-policy blocks.
- Actual import run ID: `offline-import-20260912205830-4610c685`.
- Actual import result: 42 candidates, 42 evidence rows, 42 extraction runs, 42 review decisions, 42 pending price observations.
- Validation lanes: 37 green, 5 amber, 0 red.
- Post-import duplicate dry run: 42 duplicate rows, 0 estimated new candidates.
- Review state: all 42 decisions are `review_required`; 0 approved, 0 rejected, 0 excluded.
- MOCK/TEST promoted to REAL: 0.
- REAL auto-approved: 0.
- Price Engine changes: none.
- Snapshot generation: not run because no REAL observation is human-approved.
- Cloud DEV destructive operations: zero reset/truncate/reseed; only the authorized additive REAL offline import was executed.
- External provider/API calls by ModInspect: zero. Gemini/live providers remained disabled and unused.
- New external spend: 0 THB.

Admin/runtime readiness:

- Read-only Cloud DEV auth inspection now finds users 1, active users 1, enabled admin users 1.
- No passwords, hashes, tokens, or secrets were printed.
- Safe Admin login identifier: `maisnc449@gmail.com`.
- Do not create a duplicate Admin account unless the owner intentionally needs another account.

Runtime mismatch discovered from CLIENT before repair:

- `curl -I http://192.168.1.10/modinspect/public/admin/review-queue` returned `HTTP/1.0 500 Internal Server Error`.
- Response server identified `Apache/2.4.54` and `PHP/7.4.30`, conflicting with expected STUDIO runtime Apache 2.4.68 / PHP 8.3.33 and repo minimum PHP 8.1.
- HTTPS/443 configuration was not changed.

Runtime restored verification from CLIENT:

- Temporary HTTP diagnostic under `C:\laragon\www\modinspect\public` confirmed the active web root and was deleted immediately after verification.
- Apache-served runtime: `Apache/2.4.68 (Win64) OpenSSL/3.0.21 PHP/8.3.33`.
- Apache-served PHP: `8.3.33`, SAPI `apache2handler`.
- Loaded `php.ini`: `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.ini`.
- LAN route checks: `/` returned `200`, `/login` returned `200`, `/admin` returned `302` to `/login`, `/admin/review-queue` returned `302` to `/login`.
- Authorized render check confirmed the Admin Review Queue can display 42 `REAL` rows and 11 `MOCK_TEST` rows with review actions available to an enabled Admin.

Verification:

- `php -l app\Models\Dashboard.php`: pass.
- `php -l app\Views\admin\review.php`: pass.
- `php cli\data_integrity_check.php`: `ok=true`, `problem_count=0`.
- `php cli\auth_status.php`: 1 active Admin user; admin routes remain protected; no hashes/secrets printed.
- `php cli\review_calibration_report.php --provider=offline_file_import`: dataset REAL, sample size 42, pending reviews 42, mock/test records 0.
- HTTP runtime diagnostic: `PHP_VERSION=8.3.33`, SAPI `apache2handler`, Laragon Apache 2.4.68.
- HTTP route check: public/login routes render; Admin routes redirect to login while unauthenticated instead of returning runtime errors.

Files changed for this milestone:

- `app/Models/Dashboard.php`: review rows now include explicit dataset label.
- `app/Views/admin/review.php`: review queue displays `DATASET: REAL`, `MOCK_TEST`, or `UNKNOWN`.
- `docs/REAL_CALIBRATION_REPORT.md`: measured Phase 3H-R1 report.
- `docs/PROJECT_STATUS.md`: updated phase/status/data/test/blocker/next-step state.
- `docs/SESSION_HANDOFF.md`: this handoff section.
- `context/project_state.json`: machine-readable status updated.

Next owner action:

- Visually review the refined Human Review Console, then begin human review of the 42 pending REAL rows.

Human Review Console Fix:

- Owner UX review rejected the dense all-in-one review queue; the fix separates the compact queue from a focused Review Detail workflow.
- Queue default is `dataset=REAL`, `status=pending`, with 25 rows per page and AMBER-first sorting.
- MOCK_TEST records remain available through filters but no longer dominate the default calibration queue.
- Queue rows show only observation ID, dataset badge, source, resolved product, truncated title, listing/asking price, validation lane, warning count, observed date, and Review action.
- Detail route: `/admin/review/{id}`.
- Detail page sections: decision summary, original market evidence, extraction/resolution, collapsed correction controls, and review decision actions.
- Correction controls remain limited to existing Phase 3B-supported fields and stay hidden behind `???????????` until opened.
- Verification confirmed the queue renders Review links without correction/decision forms; detail renders source navigation, listing/asking price semantics, correction form, and approve/reject/exclude actions.
- REAL records modified during console fix: zero. Review decisions executed: zero. Price snapshots modified: zero.

## 17. Handoff Risks

## 17. Admin UI Rebuild - Concept Design System

Updated during the 2026-09-12 STUDIO UAT development session.

Status:

- READY FOR OWNER VISUAL REVIEW.
- Phase 3H-R1 REAL calibration remains imported and waiting for human review.
- Phase 4B remains paused until REAL accepted observations and usable public snapshots exist.

Reference inspected:

- `X:\concept\README.md`
- `X:\concept\index.html`
- `X:\concept\pages\data-tables.html`
- `X:\concept\pages\general-table.html`
- `X:\concept\pages\form-elements.html`
- `X:\concept\pages\cards.html`
- `X:\concept\pages\tabs.html`
- `X:\concept\pages\login.html`
- `X:\concept\pages\user-profile.html`

Admin UI outcome:

- Created a shared Concept-style Admin shell with fixed top navbar, persistent dark left sidebar, grouped navigation, and content workspace.
- Rebuilt Admin Login as a centered Bootstrap card with clear labels and validation alert area.
- Migrated Dashboard, Products, Aliases, Observations, Review Queue, Review Detail, Review Analytics, Price Index, Sources, Imports, Articles, and Audit views into the shared shell.
- Isolated Admin assets under `public/assets/admin/` with local Bootstrap 4, jQuery, Font Awesome, DataTables Bootstrap adapter, and ModInspect admin CSS/JS.
- Preserved Colorlib Concept attribution in `public/assets/admin/README.md` and `docs/DESIGN_SYSTEM.md`.

Review console outcome:

- Review Queue defaults to `dataset=REAL`, `status=pending`, 25 rows per page, and AMBER-first sorting.
- Queue rows are compact and show ID, REAL/MOCK_TEST badge, product, truncated listing title, asking/listing price, source, validation lane, warning count, observed date, and Review action.
- Review Detail route remains `/admin/review/{id}` and shows summary, original evidence, extraction/resolution, collapsed correction controls, and explicit approve/reject/exclude actions.
- Correction controls are not rendered in queue rows.
- Source evidence opens from an obvious `Open Source` action with safe new-tab attributes.

Verification:

- Temporary Apache-served render probe reported PHP `8.3.33`, SAPI `apache2handler`, and successful authenticated rendering for all rebuilt Admin views; the probe was deleted and now returns 404.
- HTTP checks on `http://192.168.1.10/modinspect/public/` returned 200 for `/` and `/login`.
- All discoverable Admin GET routes redirect unauthenticated users to `/login` and no longer return the old PHP 7.4 runtime failure.
- Read-only counts confirmed REAL pending 42, MOCK_TEST pending 11, and REAL lanes 37 GREEN / 5 AMBER / 0 RED.
- REAL records modified: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud DEV destructive operations: zero.
- External API/provider calls: zero.
- New external spend: 0 THB.

Next owner action:

- Log in and visually review the rebuilt Admin shell at `/admin`, then inspect `/admin/review-queue` and one Review Detail page before making any REAL review decisions.

## 18. Admin UI Owner Visual Review Fix Round

Updated during the 2026-09-12 STUDIO UAT development session.

Status:

- READY FOR OWNER VISUAL REVIEW.
- This was a visual fix round only; no review decisions or pricing/domain changes were made.

Owner-found defects fixed:

- Sidebar text contrast was broken because Bootstrap's `.navbar-light .navbar-nav .nav-link` selector overrode the initial admin-sidebar link color.
- Review Queue rows were text-only and lacked fast product visual identification.

Fixes:

- Sidebar selectors now explicitly target `.mi-admin-sidebar .navbar-light .navbar-nav .nav-link`, hover, focus, active, and icon states.
- Normal sidebar text is light neutral on dark navy; active and hover/focus states use a stronger blue-tinted background with white text/icons.
- Review Queue rows now include a 64px thumbnail slot before the observation ID.
- Queue thumbnails use existing Product Master `image_path` local assets when present, otherwise category Font Awesome placeholders.

Image audit:

- Product Master images exist through `products.image_path` and local files under `public/assets/images/`.
- `raw_price_observations` and `market_evidence` do not have dedicated listing thumbnail/image URL fields.
- The current offline import CSV/JSON templates do not include thumbnail/image URL fields.
- No REAL listing thumbnails were fabricated, persisted, or backfilled.

Visual QA:

- Computer Use browser surface was unavailable, so installed Chrome headless screenshots were used against the STUDIO UAT URL.
- Checked `1366x900` and `1440x900` Review Queue screenshots.
- Visual inspection confirmed readable sidebar text/icons, readable active state, Review Queue thumbnails, preserved product/title/source hierarchy, prominent asking price, and no obvious overlap/cutoff.
- The temporary token-guarded visual QA endpoint was deleted after screenshots and verified absent.

Safety:

- REAL records modified: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud DEV destructive operations: zero.
- External API/provider calls: zero.
- New external spend: 0 THB.

Next owner action:

- Log in and visually re-review `/admin/review-queue`, checking sidebar contrast and queue thumbnails first.

## 19. Admin UI Review Detail Fix Round

Updated during the 2026-09-13 STUDIO UAT development session.

Status:

- REVIEW QUEUE ACCEPTED.
- REVIEW DETAIL READY FOR OWNER VISUAL REVIEW.
- This was a visual/detail-presentation fix only; no review decisions or pricing/domain changes were made.

Owner-found defects fixed:

- Review Detail's two-column layout left too much unused space.
- Correction consumed a large empty card while collapsed.
- Approve / Reject / Exclude controls were too low in the page.
- `Open Source` appeared in more than one location.
- Product Master imagery needed explicit labeling so it was not mistaken for listing evidence.
- Observation #2111 showed 95% extraction/stored observation confidence but AMBER/LOW_CONFIDENCE, which was confusing.

Fixes:

- Review Detail now uses a main evidence/resolution workspace plus a right-side desktop decision panel.
- The decision panel is sticky on desktop and visible in the first viewport for the checked AMBER sample.
- Correction now appears as a compact collapsed `Edit / Correct Data` disclosure below the decision panel.
- Original Market Evidence owns the single `Open Source` action.
- Product imagery is labeled `Product Master image`.
- Technical validation versions and raw payloads are kept under Advanced / Technical Details.
- LOW_CONFIDENCE copy now explains product-resolution confidence rather than implying visible extraction confidence is low.

Observation #2111 read-only validation finding:

- Dataset/status: REAL pending.
- Lane/reason: AMBER / LOW_CONFIDENCE.
- Extraction confidence: `0.9500`.
- Stored observation confidence: `95.00`.
- Deterministic resolver recomputation from the immutable title matched `AMD Radeon RX 6600 XT` with confidence `0.8637` using `alias_contains`.
- Backend validation behavior is consistent with `ObservationValidator`: AMBER applies when product-resolution confidence is below the Green threshold of `0.95`.
- No validation threshold or domain semantic was changed.

Verification:

- Apache-served temporary probe reported PHP `8.3.33`, SAPI `apache2handler`, REAL pending 42, MOCK_TEST pending 11, and #2111 pending REAL.
- Chrome headless screenshots checked Review Detail at 1366x900 and 1440x900.
- Visual inspection confirmed reachable decision controls, compact collapsed correction, one source CTA, Product Master image labeling, readable AMBER reason, readable sidebar, and no obvious overlap/overflow in the checked desktop widths.
- Temporary token-guarded probes were deleted and verified absent.

Safety:

- REAL records modified: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud DEV destructive operations: zero.
- External API/provider calls: zero.
- New external spend: 0 THB.

Next owner action:

- Log in and visually review observation #2111 at `/admin/review/2111`.

## 20. REAL Batch Title / Provenance Quality Gate

Updated during the 2026-09-13 STUDIO UAT development session.

Status:

- HUMAN REVIEW CONSOLE ACCEPTED.
- REAL BATCH QUALITY GATE FIXED.
- Owner human review may start, but Priceza provenance must be interpreted as public search-page listing evidence, not listing-page or sold-transaction evidence.

Read-only audit results:

- REAL rows audited: 42.
- Raw titles contaminated by ModInspect-generated ingestion/privacy metadata: 42.
- Clean raw titles: 0.
- REAL pending remains 42.
- Validation lanes remain 37 Green, 5 Amber, 0 Red.
- Asking/listing-price rows: 42.
- Sold-price rows: 0.
- Unique external reference hashes: 42.
- Duplicate count: 0.
- Missing condition: 40.
- Missing warranty: 42.

Root cause:

- `storage/import/real_calibration_batch.csv` had clean actual listing text in the `title` field.
- The same CSV used `listing_text` for ModInspect-generated source/privacy notes such as `Priceza public search result`, `merchant=`, `public listing title captured only`, and the no-PII note.
- `OfflineEvidenceImportService::candidateFromRecord()` concatenated `title` and `listing_text`, so those notes entered stored raw titles and extraction raw title values.
- The Admin read model displayed the stored raw title directly before this fix; it did not create the contamination.

Fix:

- Added `ListingTitleNormalizer` for deterministic removal of known ModInspect-generated importer/privacy metadata markers from display titles.
- Review Queue and Review Detail now use normalized display titles; raw stored titles remain available under Advanced / Technical Details.
- Future offline imports normalize `title` and `listing_text` separately before candidate creation.
- Future import templates separate `merchant` and `ingestion_note` from `title` / `listing_text`.
- Duplicate re-import protection recognizes both normalized future title hashes and the legacy first-batch raw title+listing_text hash.

Safety:

- Raw evidence preserved.
- Existing 42 REAL records were not mutated.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud destructive operations: zero.
- External API/provider calls: zero.
- New external spend: 0 THB.

Next owner action:

- Begin human review from `/admin/review-queue?dataset=REAL&status=pending`.

## 21. Handoff Risks

Git will not transfer machine-local runtime state:

- `.env`
- database contents
- local MySQL users/passwords
- local admin accounts created in the OFFICE database
- Git credential manager credentials
- Gemini/API credentials
- Laragon/Apache/PHP/MySQL configuration
- ignored `storage/import/` REAL or TEST files
- ignored `var/import/` files
- ignored logs/cache/session files
- any untracked files outside Git
- machine-specific absolute paths

Pulling Git on STUDIO does not reproduce runtime secrets or local test DB state. STUDIO must configure `.env` with the Cloud DEV password locally. Do not re-import or replay migrations against the Cloud DEV database unless inspection proves it is incomplete and the owner approves the recovery path.

## 22. Last Verified State

Latest 2026-09-13 override:

- Phase 3H-R1 additive REAL offline import completed with 42 pending REAL observations.
- Cloud DEV currently has one enabled Admin user.
- CLIENT HTTP checks now confirm Laragon Apache/PHP 8.3.33; Review Queue, Review Detail, and Human Review Console are owner-accepted.
- Title/provenance quality gate fixed normalized display titles while preserving raw evidence; Priceza source URLs are search-page provenance.
- Phase 4B remains paused until REAL human-approved observations and usable public snapshots exist.

- Latest migration: `012_admin_auth_rbac.sql`
- Latest regression state: PASS for Phase 3H-R1 Human Review Console focused checks; Phase 4B remains paused.
- Data integrity status during handoff: `ok: true`, `problem_count: 0`
- Latest completed milestone: Phase 3H-R1 REAL Batch title/provenance quality gate
- Current waiting milestones:
  - Phase 2D-B â€” Controlled Live Provider POC waiting for credentials/approval
  - Phase 3H-R1 - Owner human review of imported REAL rows
- Next intended step: Owner starts human review at `/admin/review-queue?dataset=REAL&status=pending`. Do not resume Phase 4B until REAL accepted observations and usable public snapshots exist.

## 23. Products Page UX / Create Form Refinement

Updated during the 2026-09-13 STUDIO development session.

Status:

- READY FOR OWNER VISUAL REVIEW.
- Scope was limited to the Products page layout and interaction.
- Public frontend redesign remains paused.

Implemented:

- Products create/edit form is hidden by default in a collapsed slide-down card.
- Existing top-right Add Product button is now the single create CTA.
- Add Product opens and resets the product form.
- Product Edit opens the same form with existing row data and Save Changes copy.
- Cancel / Close hides the form cleanly.
- Form fields use a structured Bootstrap grid.
- Active status is aligned as a switch/control inside the form grid.
- Filters and Product Master table remain the default dominant page content.
- Products server-side DataTable markup and endpoint are preserved.

Visual QA:

- Headless Chrome screenshots generated for default and form-open states.
- Viewports checked: 390x844, 708x900, 768x1024, 1024x768, 1366x900, 1440x900.
- Products content stacks cleanly on mobile/tablet and desktop alignment is improved.
- No Products-form-driven horizontal overflow was observed.

Safety:

- Backend/domain behavior changed: none.
- REAL records modified: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud destructive operations: zero.

Next owner action:

- Visually review the Products page default state and Add/Edit form behavior.

## 24. Products Page Owner-UAT Fix

Updated during the 2026-09-13 STUDIO development session.

Status:

- PASS / READY FOR OWNER VERIFICATION.
- Owner manual UAT correctly overrode the earlier automated DataTable PASS.
- Products page Search is now functionally verified, not merely JSON-shape verified.

Root cause:

- Server-side search queries reused named placeholders such as `:search` and `:q_like` multiple times while PDO native prepares are enabled, causing `SQLSTATE[HY093]: Invalid parameter number` under real search inputs.
- Browser Search also failed because the local minimal DataTables adapter debounce wrapper discarded `this`, so the visible Search input handler read from the wrong context and sent an empty search value.

Implemented:

- Admin DataTable search predicates now use unique prepared parameters per searched column.
- Products search covers full name, model, slug, brand, category, generation, and key specification.
- Dashboard Review Queue / Observations repeated search placeholders were fixed for native PDO.
- Local DataTables adapter preserves debounce context and exposes a usable API object.
- Admin filter redraw uses the server table draw API.
- Products Add/Edit slide-down form layout remains in place.
- `tests/ui_integration_test.php` now includes functional Products DataTable checks for search, impossible search, pagination, sorting, and filters.

Verification:

- Products UAT matrix: `recordsTotal=41`; `5700X3D=1`; `RX 6600 XT=1`; `Intel=5`; impossible search `0`.
- Pagination: `length=10,start=0` and `length=10,start=10` returned different row sets.
- Sorting: Product ASC/DESC and Brand sort returned distinct ordered results.
- Filters: Category, Brand, Status, Category + search, Brand + search, and Category + Brand + Status all returned valid filtered counts.
- Browser Search: visible Search input with `5700X3D` triggered redraw to one row and updated info text to `Showing 1 to 1 of 1 records (filtered from 41)`.
- Shared smoke: Aliases, Observations, and Review Queue search endpoints returned valid filtered JSON.
- Visual QA: 1366x900, 1440x900, 1024x768, and 768x1024 for default, Add Product open, Search filtered, and Edit form states.

Safety:

- Backend/domain semantics changed: none.
- Cloud authoritative REAL data modified: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud destructive operations: zero.
- Temporary public UAT probe was removed and verified HTTP 404.

Next owner action:

- Retest Products Search, filters, pagination/sort, and Add/Edit form in authenticated UAT.

## 25. Products DataTable Functional Recheck

Updated during the 2026-09-13 STUDIO development session.

Status:

- PARTIAL for authenticated owner Products UAT because Codex has no visible authenticated browser session.
- PASS for read-only UAT endpoint matrix.
- PASS for browser-adapter redraw proof.
- Products slide-down Add/Edit UX remains preserved.

Functional proof:

- Products `recordsTotal`: 41.
- Search `5700X3D`: `recordsFiltered=1`, returned AMD Ryzen 7 5700X3D.
- Search `RX 6600 XT`: `recordsFiltered=1`, returned AMD Radeon RX 6600 XT.
- Search `Intel`: `recordsFiltered=5`, returned Intel Product Master rows.
- Impossible search: `recordsFiltered=0`, returned no rows.
- Pagination `start=0,length=10` and `start=10,length=10` returned different row sets.
- Sorting Product ASC/DESC and Brand ASC returned distinct ordered results.
- Category, Brand, Status, and combined search/filter predicates returned valid filtered counts.
- Browser-adapter proof showed visible Search `5700X3D` redrew to one row and updated info text to `Showing 1 to 1 of 1 records (filtered from 41)`.

Limitation:

- `cua.getState()` returned no apps or browsers, so Codex could not operate the owner's authenticated UAT browser.
- No password request, test Admin creation, cookie/session export, auth bypass, or diagnostic auth endpoint was used.

Safety:

- Cloud authoritative REAL data modified: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud destructive operations: zero.
- Temporary public probe removed and HTTP check returned 404.

Next owner action:

- Retest Products Search in the authenticated UAT browser.

## 26. Admin Imports Owner-Ready Input Workflow

Updated during the 2026-09-13 STUDIO development session.

Status:

- PASS / IMPORTS OPERATIONALLY READY FOR OWNER UAT.
- Implementation is in place for owner-readable templates, field guidance, dry-run summary, and confirm gating.
- End-to-end isolated TEST workflow is verified through Apache/PHP against `.env.testing`.
- Public frontend redesign remains paused.

Implemented:

- Authenticated Admin download routes for CSV and JSON templates:
  - `/admin/collector-jobs/template.csv`
  - `/admin/collector-jobs/template.json`
- Centralized import field contract in `App\Services\Collection\ImportFieldContract`.
- Imports page now shows:
  - Download template actions.
  - Seven-step workflow from template download through explicit confirm.
  - Collapsible Import Format / Field Reference.
  - REAL vs TEST/MOCK explanation.
  - Provenance guidance for REAL rows.
  - Practical explanation that import templates are input formats, not data sources.
  - Upload/Dry Run form separated from Confirm Import.
  - Confirm Import card unlocked only after a Dry Run finds eligible rows for the saved uploaded file.
  - Expanded dry-run/import summary with total scanned, eligible, invalid, duplicates, source-policy blocks, rows that will import, resolver hints, source distribution, validation lanes, and row-level reasons.
- Confirm Import now reuses the exact Dry Run file path/hash/dataset/limit stored in session and rejects changed/missing files.

Supported import contract summary:

- Required: `observed_at`, plus at least `listing_title`, `title`, or `listing_text`.
- REAL conditionally requires listing-level identity through `source_item_id`, `external_ref`, `source_reference`, `external_listing_id`, `source_listing_url`, or a non-search `source_url`.
- Recommended for authoritative REAL: `source`, `source_search_url`, `source_listing_url`, `source_item_id`, `merchant`, `merchant_target_url` when public, `listing_title`, `asking_price`, `observed_at`, `evidence_snapshot_json`, `evidence_hash`.
- Optional/legacy fields remain accepted for backward compatibility: `source_url`, `source_reference`, `title`, `displayed_price`, `external_listing_id`, `source_domain`, `source_type`, `product_hint`, `probe_run_id`, `ingestion_note`, `notes`, `condition_hint`, `warranty_hint`.

Verification:

- `git diff --check` passed for touched import files.
- UAT `/` and `/login` returned HTTP 200 before and after the import workflow proof.
- Root cause of the earlier failure was not an Apache runtime outage: normal UAT routes stayed healthy, local loopback URLs targeted the wrong client machine, and the temporary HTTP runner needed the existing test redirect hook to work under Apache when `MODINSPECT_TESTING` is defined.
- Focused HTTP runner executed against active DB `127.0.0.1/modinspect_test`.
- Safe TEST/MOCK sample created with 3 rows from `ImportFieldContract::CSV_HEADERS`.
- Dry Run summary proved `scanned=3`, `eligible=3`, `invalid=0`, `duplicates=0`.
- Confirm without Dry Run was blocked.
- Confirm after the saved dry-run file changed was blocked.
- Same-context Confirm Import created 3 pending TEST/MOCK Review Queue rows.
- Imported TEST/MOCK rows were not auto-approved and were not exposed as REAL.
- Approved/public-eligible observations and price snapshot row counts remained unchanged.
- Import/Collection Jobs DataTable search, pagination, sorting, and filters passed.
- Temporary runner files were deleted and verified HTTP 404.

Safety:

- No Cloud DEV import was confirmed.
- No Cloud REAL rows were modified.
- No review decisions were executed.
- No price snapshots were modified.
- No public Priceza/provenance collection was performed.

Next owner action:

- Owner performs authenticated Admin Imports UAT with a TEST/MOCK dry run first; do not confirm a REAL import until explicitly intended.

## 2026-11-16 - Long-run brief run finished (queue empty)

- Done under `docs/LONG_RUN_BRIEF.md`: G-1 CI fix (test fixture path, DL-042). CI green on bf5daa3 (3 PHP versions + offline package). Nothing was merged or deployed.
- F-2 admin UAT done (DL-043). Waiting on the owner: real second-source rows (`docs/SECOND_SOURCE_INTAKE.md`), PR #4 description (`docs/PR4_BODY_PROPOSED.md`) and merge/deploy, credential rotation (G-5), Font Awesome download (G-7), photo/logo rights.
- Next step for any new session: read the board; if no row is open, ask the owner for the items above instead of inventing scope.

## 2026-11-16 - LS-2 checkpoint (H-1..H-10 done, H-11..H-15 open)

- Running under `docs/LONG_RUN_BRIEF.md` (95% gate, 5-attempt cap, docs after each task). Board rows H-1..H-15 are in `docs/SPRINT_BOARD.md`; DL-044..DL-052 in the decision log; ideas for the owner's revise session in `docs/REVISE_BACKLOG.md`.
- Done: resolver precision fix, catalog gap proposal (nothing added to DEV), GPU ladder flags, admin Data health, snapshot history/diff, route guard test, SEO files, image dimensions + .htaccess perf, mobile checks.
- Next: H-11 backup/restore + health endpoint, H-12 Windows/Linux parity scan, H-13 security review refresh, H-14 operator guide + README, H-15 beta checklist, then final verification and closing report.
- Rules learned this run: check every scripted test edit really asserts something; never stop a process by image name (only my own PID); a public price is the latest non-legacy snapshot per product.

## 2026-11-16 - LS-2 finished (H-1..H-15 done)

- All 15 tasks closed with evidence on the board; DL-044..DL-057; CI green on PHP 8.1/8.3/8.4 at `d0a3f02`. Nothing merged, deployed or sent; the PR #4 description was updated earlier on the owner's request.
- Owner-only items remain (see `docs/BETA_READINESS_CHECKLIST.md`, verdict NO-GO today): second listing-level source and a human review of REAL rows, credentials rotation, hosting setup on HostAtom (TLS, backups, monitoring), privacy/terms copy, image rights, library upgrade decision, catalog additions (`docs/CATALOG_GAP_PROPOSAL.md`, recommendation: add none now).
- The owner's revise session starts from `docs/REVISE_BACKLOG.md`.

## 2026-11-17 - Revise sprint started

- `docs/REVISE_LS3_PROPOSAL.md` is the working document. The owner needs to: list the features they think were missed (section 5), answer the 3 questions at the end, and approve or change decisions D-1..D-5. Until then J-1..J-12 on the board stay PROPOSED and no new build starts.
- Docs corrected: three stale rows of `docs/IMPLEMENTATION_GAP_ANALYSIS.md`.

## 2026-11-17 - LS-3 finished (J-0..J-12 done)

- Approved by the owner on 2026-11-17 (decisions D-1..D-5, queue J-1..J-12). Board rows with evidence, DL-059..DL-070, `docs/REVISE_BACKLOG.md` for what each task left open, `docs/REVISE_LS3_PROPOSAL.md` for the original plan.
- Built: master-data writes need `products.manage` (J-0, a real RBAC hole), unresolved-listings queue (migration 019), user management, rate limiting + sitemap cache, read-only owner-intake screen (rules shared with the CLI, refactors proven output-identical), search that finds joined and reordered forms (65.6 % to 100 % on a golden set), condition checklist on the Deal Checker, admin snapshot chart, CPU and RAM ladders, beta readiness panel, community feedback design (ADR-006, nothing built), heading order, catalog spec audit and a /build false-mismatch fix.
- Owner-only items now: (1) run `php cli/migrate.php` on shared DEV so the unresolved-listings actions work (migration 019); (2) review the Thai wording of the Deal Checker checklist (J-6); (3) ADR-006 options A/B/C and who moderates; (4) optional one-row data fix for `klevv-cras-x-rgb-ddr4` (see `docs/CATALOG_SPEC_AUDIT.md`); (5) catalog gap proposal (recommendation: add none); (6) whether to build import and approve from the admin screen (J-3b); (7) the earlier list in `docs/BETA_READINESS_CHECKLIST.md` (second source, HostAtom setup, privacy copy, image rights, library upgrade).
- Rules learned this stretch: the PHP `+` operator keeps the LEFT value (use `array_merge` for overrides: it bit me three times); a test that passes on first try must be shown to fail on a planted fault; never create files by a command whose path may not exist (a test once made a stray `composer.json/` folder); stop only processes I started, by PID.

## 2026-11-17 owner approval of all Recommended picks (DL-071)
- Migration 019 was applied to shared DEV (run twice, idempotent). Unresolved-listings actions are now writable.
- ADR-006 option A accepted. No catalog additions, no bulk spec fill, Klevv row not changed.
- Still open for the owner: checklist Thai wording, ADR-006 moderation owner and retention, J-3b, missed-feature list, beta MUST items.
- Note: a failed first migrate attempt (PHP 8.1 build without pdo_mysql) printed a DB stack trace into the session log that included part of the DEV DB password; consider rotating it. Use the PHP 8.3 path above.

## 2026-11-17 LS-4 (K-1, K-2, K-4, K-6)
- Reports and tooling only, see `docs/LS4_REPORTS.md`. No DEV write. PHP for DB scripts: the 8.3 path above (8.1 here has no pdo_mysql).
- K-1c done: stale rows moved to an "Already matched" tab (DEV open 210, stale 159). Key finding: 159 of 369 unresolved-queue rows are stale; a dry-run showed the importer will not re-process them (DL-073). Next: hide stale rows in the queue (code only) and decide on a re-resolve tool.
- New: `cli/unresolved_proposals.php`, `cli/pending_gap_report.php`, `cli/ops_probe.php`, `cli/db_backup_prune.php`; tests `unresolved_proposal_report`, `public_a11y_scan`, `ops_kit`; `/build` landmark fixed.
- K-1d: design for the re-resolve tool is in docs/RERESOLVE_STALE_DESIGN.md (DL-074); nothing built. Owner decides D-1..D-4.
- K-1e: dry-run shows the re-resolve is worth only 1 row (DL-075); R-3/R-4 dropped. Pending products need a second source (K-3).
- K-3: options in docs/K3_SECOND_SOURCE_OPTIONS.md (DL-076); waiting for the owner's answers on Priceza used-vs-new, reviewer, and B volume. Next possible: read-only Priceza review pack.
- K-3b (DL-077): Priceza = new-price reference, excluded from the used cohort in PriceSnapshotService. Open: reviewer (Q2), B sources and weekly volume (Q3).
- K-3c (DL-078): documented second-source template does not reach used prices; owner must choose P1/P2/P3 (Recommended P1) before the daily workflow is built.
- K-3c done (DL-079): P1 built. Owner: fill examples/import/owner_listing_urls.csv, dry run, import, review (docs/OWNER_DAILY_COLLECTION_TH.md). Agent route: record contract + capture_method=machine_capture.
- LS-5: L-3 (Thai labels/notes, wording to review) and L-6 (docs/SECURITY_REVIEW_2026-11-17.md, DB-password leak fixed) done; L-1 waits for the owner's first file, L-2 for L-1. Owner: rotate the DEV DB password.
- LS-6 (DL-081, DL-082, docs/LS6_REPORTS.md): M-1 helper `php cli/prepare_owner_rows.php --in=<loose csv>` (offline, codes only); importer now strips the Excel BOM and checks URL words not letters; M-2 rehearsal test; M-3 `capture_methods` in the calibration report; M-4 one hover defect fixed (public buttons use `btn-ghost`), open findings: `/build` focus order on phones, 5 px overflow at 320 px, 8-10 px text; M-5 owner/host split in docs/BETA_READINESS_CHECKLIST.md. Verdict still NO-GO.
- LS-7 (DL-083, DL-084, docs/LS7_REPORTS.md): `--check-db` on `cli/prepare_owner_rows.php` (read-only); `php cli/env_preflight.php` for the host `.env` (never prints values; not yet run on the host); `docs/PRIVACY_TERMS_DRAFT_TH.md` is a draft with owner blanks, not published; approval right after import now works; `/build` 320 px fixed; the LS-6 focus-order finding was a measurement artefact and is retracted. Verdict still NO-GO.
- LS-8 (DL-085, DL-086, docs/LS8_REPORTS.md): `php cli/collection_targets.php` gives the owner a Thai list of what to collect next (22 products, 33 rows, DEV); `php cli/retention_purge.php` has NO default period and is a dry run unless `--apply` (owner must choose periods first; back up before applying); `/admin/review-analytics` has a Capture Method card (not seen in a browser).
- 2026-11-17: owner will attach the first import file LATER ("remark ให้พี่แนบตอนหลัง"). L-1 stays WAITING and L-2 stays BLOCKED until then; do not ask again unprompted, remind once when closing a related task.
