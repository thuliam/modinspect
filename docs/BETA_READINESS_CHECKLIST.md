# Pre-beta readiness checklist and Go/No-Go template

Purpose: one page the owner can use to decide whether a gated public beta may start (roadmap Sprint 5, `docs/ROADMAP.md`). Status values are `MET` (with evidence), `NOT MET`, `OWNER` (only the owner can decide or supply it) and `N/A`. A row is `MET` only when the evidence column names a command output, a page, a test or a commit. Nothing here is a promise that live-market quality exists: fixture and owner-text figures are engineering baselines.

Rule: **GO needs every MUST row `MET`.** A SHOULD row that is not met must be listed in the decision with an owner and a date.

## A. Data and trust

| ID | Level | Requirement | How to verify | Status 2026-11-16 | Evidence / gap |
|---|---|---|---|---|---|
| A-1 | MUST | Every public price replays to the same snapshot | `php cli/price_trust_audit.php` shows MATCH for all | MET | 49 of 49 MATCH |
| A-2 | MUST | No mock/test evidence reaches a public price | `tests/` suite, public price gate (DL-047) | MET | suite green, CI green |
| A-3 | MUST | Public prices carry honest confidence and evidence grade | product page + `/admin/snapshots/{id}` | MET | all 49 show low; owner-text caveat on every page |
| A-4 | MUST | At least one listing-level source reviewed by a person for the products offered in the beta | Review Queue; `docs/SECOND_SOURCE_INTAKE.md` | **NOT MET / OWNER** | 0 approved from listing-level sources; 88 REAL observations pending since September; all 481 approved rows are owner text. Route ready (LS-6 M-1, M-2): `cli/prepare_owner_rows.php`, owner-transcription pattern, rehearsal `tests/daily_flow_rehearsal_test.php`; waits for the owner's first real file (L-1) |
| A-5 | MUST | Calibration from reviewed live rows, not fixtures | `php cli/review_calibration_report.php`; `docs/REAL_CALIBRATION_REPORT.md` | **NOT MET / OWNER** | green-lane 99.76 % comes from rule-based bulk approvals and is not independent evidence; needs a sampled human review (gate G-4). The report now splits reviewed rows by capture method and keeps fixtures apart (LS-6 M-3), so it is ready for the first reviewed rows |
| A-6 | SHOULD | Coverage worth launching | `/admin/data-health` | NOT MET | 49 of 99 products priced; 22 pending for too few rows or a bimodal sample (RTX 5090) |
| A-7 | SHOULD | Medium or higher confidence for the headline products | `/admin/snapshots/{id}` gap card | NOT MET | needs A-4 |
| A-8 | SHOULD | Plausibility flags clean | `php cli/price_ladder_check.php` | MET | 0 flags on 44 prices checked (GPU, CPU and RAM ladders, LS-3 J-8) |

## B. Product and experience

| ID | Level | Requirement | How to verify | Status | Evidence / gap |
|---|---|---|---|---|---|
| B-1 | MUST | Owner visual acceptance of the public pages | owner review (G-2) | **OWNER** | pages exist and were checked at 375 and 1048 px; no owner sign-off recorded |
| B-2 | MUST | Mobile pages without horizontal overflow | browser check at 375 px | MET | home, price, product, deal checker, build, compare, 3 admin pages (DL-043, H-9, H-10). At 320 px `/build` overflows by 5 px (LS-6 M-4); the other five public pages do not |
| B-3 | MUST | Thai-first copy, no invented claims, prices, ratings or users | page text review | MET | checked on the pages listed above; re-check after any copy change |
| B-4 | SHOULD | Accessibility basics | script audit (alt, labels, ids, landmarks) | MET | 0 failures on home and price index; heading order fixed on the public pages and locked by `tests/heading_levels_test.php` (LS-3 J-11). LS-4 K-6 and K-6b: static scan on 9 pages, contrast and keyboard focus on 6. LS-6 M-4: phone width, hover states and tab order on 6 pages; one hover contrast defect fixed (`tests/public_button_theme_test.php`), two findings open in `docs/LS4_REPORTS.md` |
| B-5 | SHOULD | Search and share metadata | `/robots.txt`, `/sitemap.xml`, page head | MET | DL-050; no share image until rights are settled |

## C. Security

| ID | Level | Requirement | How to verify | Status | Evidence / gap |
|---|---|---|---|---|---|
| C-1 | MUST | Every admin route guarded; every POST has CSRF | `tests/admin_route_guard_test.php` | MET | 45 routes; master-data and user writes need `products.manage` / `users.manage` (LS-3 J-0, J-2) |
| C-2 | MUST | No secrets in the repository; exposed credentials rotated | `php cli/secret_scan.php`; owner action G-5 | **NOT MET / OWNER** | scan clean; credentials found in the raw LINE export are not yet rotated |
| C-3 | MUST | TLS on every page and HSTS enabled on the real host | hosting | **NOT MET / OWNER** | the production host is HostAtom (DL-014); TLS and HSTS have not been checked on it, and local HTTPS is disabled by design |
| C-4 | MUST | Production `.env` has `APP_ENV=production` and no `AUTH_*` overrides | host check | NOT MET | enforced in code (overrides ignored outside local/testing, DL-055); the host file still has to be checked |
| C-5 | SHOULD | Rate limiting on public POSTs and the sitemap | review S-7, N-3 | MET | Deal Checker and Build POSTs rate-limited (429 + Retry-After), sitemap cached, proven over real HTTP (LS-3 J-4); behind a proxy the limiter needs the real client address configured first |
| C-6 | SHOULD | Front-end library advisories decided | `docs/SECURITY_REVIEW_2026-11-16.md` N-4 | **OWNER** | Bootstrap 4.1.0 / jQuery 3.3.1 pinned; upgrade needs a download approval and a manifest update |
| C-7 | SHOULD | An independent security check | external | NOT MET | the reviews so far are by the author of the code |

## D. Operations

| ID | Level | Requirement | How to verify | Status | Evidence / gap |
|---|---|---|---|---|---|
| D-1 | MUST | Backup that has been restored at least once | `php cli/db_backup.php`, `php cli/db_restore.php ... --verify` | MET (tool) | drill passes locally and on the CI runner (32 tables, row counts match); no schedule and no off-site copy on a real host yet |
| D-2 | MUST | Scheduled backup with an off-machine copy and a retention rule | host cron | **NOT MET / OWNER** | needs a cron job, an off-machine copy and a retention rule set up on HostAtom. Tools and cron lines exist (`cli/db_backup_prune.php`, `docs/LS4_REPORTS.md`); nothing has been run on the host |
| D-3 | MUST | Liveness monitoring | `GET /health` plus an uptime monitor | NOT MET | endpoint exists; no monitor configured. `cli/ops_probe.php` is ready to run from the host cron (not run there) |
| D-4 | MUST | Deployment runbook rehearsed on an isolated staging database and a rollback tested | `docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md`, `docs/DEPLOYMENT_PACKAGE_DRY_RUN.md` | **NOT MET / OWNER** | package dry run exists; no staging rehearsal; deploy not authorized |
| D-5 | MUST | Compression and caching rules present on the host | response headers | NOT MET | `.htaccess` rules verified on a temporary Apache with the modules; the host's modules are unknown |
| D-6 | SHOULD | Error pages and logs reviewed on the host | host | NOT MET | generic 500 page exists in code |

## E. Legal, privacy and rights

| ID | Level | Requirement | How to verify | Status | Evidence / gap |
|---|---|---|---|---|---|
| E-1 | MUST | Privacy and terms copy approved by the owner (and legal review if the owner wants it) | owner | **OWNER** | no copy approved |
| E-2 | MUST | Seller PII minimised in data and in the UI | policy + tests | MET | provinces only; no names, phones, URLs or text on admin evidence pages (tests) |
| E-3 | MUST | Rights for product photos and logos | owner | **OWNER** | 7 photos credited from freely licensed sources; logo/brand artwork rights not confirmed |
| E-4 | SHOULD | Official Font Awesome files (removes the `fa-solid-900.html` 404) | owner approval to download (G-7) | **OWNER** | cosmetic console error only |
| E-5 | SHOULD | Cost check: external spend still 0 THB | owner | MET | no paid service enabled |

## F. Engineering gates

| ID | Level | Requirement | How to verify | Status | Evidence / gap |
|---|---|---|---|---|---|
| F-1 | MUST | Full suite and CI green on PHP 8.1, 8.3, 8.4 | `php tests/run.php`; GitHub checks | MET | green on PHP 8.1, 8.3, 8.4 and the offline package at `d0a3f02` (a PHP 8.1 failure on the two commits before it was fixed, see board G-2); re-check at the final commit |
| F-2 | MUST | Test database rebuilt from the tracked dump includes every migration | `tests/migration_dump_guard_test.php` | MET | in the suite |
| F-3 | MUST | Windows/Linux parity scan clean | `tests/linux_parity_scan_test.php` | MET | in the suite |
| F-4 | SHOULD | Open items recorded for the next revision | `docs/REVISE_BACKLOG.md` | MET | maintained after every LS-2 task |

## Who can close each open MUST row (LS-6 M-5, 2026-11-17)

None of the twelve can be closed by code alone. Six need only the owner; six also need the real host.

| Row | Who | Already prepared in code | What is left |
|---|---|---|---|
| A-4 | Owner | owner-transcription route, `cli/prepare_owner_rows.php`, rehearsal test | send the first real file (L-1), review the rows in the Review Queue |
| A-5 | Owner | calibration report split by capture method (M-3) | reviewed live rows (100 for the CALIBRATING label) and a sampled human review of the green lane |
| B-1 | Owner | pages checked at 375 and 1048 px; contrast, focus and hover checked on 6 pages | visual sign-off; decide on the two open M-4 findings (`/build` focus order on a phone, 5 px overflow at 320 px) |
| C-2 | Owner | secret scan clean; the DB-password leak path is closed (N-9) | rotate the credentials found in the LINE export and the DEV DB password |
| E-1 | Owner | none; no copy is approved yet | approve privacy and terms copy |
| E-3 | Owner | 7 photos credited | confirm rights for logos and brand artwork |
| C-3 | Owner and host | `cli/ops_probe.php` | check TLS and HSTS on HostAtom |
| C-4 | Owner and host | code ignores `AUTH_*` overrides outside local and testing | check the host `.env` |
| D-2 | Owner and host | backup, restore verify, prune tool, cron lines | install the cron job, an off-machine copy and the retention rule |
| D-3 | Owner and host | `/health`, `cli/ops_probe.php` | set up an uptime monitor |
| D-4 | Owner and host | deployment package dry run | staging rehearsal, rollback test, deploy authorisation |
| D-5 | Host | `.htaccess` rules verified on a temporary Apache | check which modules the host has |

## Verdict as of 2026-11-16: NO-GO for a public beta (re-checked 2026-11-17: unchanged, same twelve rows)

Not met MUST rows: A-4, A-5, B-1, C-2, C-3, C-4, D-2, D-3, D-4, D-5, E-1, E-3. Most need an owner decision, a real host, or real listing-level evidence; none of them can be closed by code alone. The gated beta in `docs/ROADMAP.md` stays `BLOCKED_BY_REAL_EVIDENCE` until A-4 and A-5 are met.

## Go/No-Go decision template

Copy this block into `docs/DECISION_LOG.md` (next free DL number) when the decision is taken.

```text
Decision: GO / NO-GO / GO WITH CONDITIONS          Date: YYYY-MM-DD          Decided by (owner): ______
Scope of the beta (pages, products, audience, duration): ______
MUST rows not met at the time of decision (must be none for GO): ______
SHOULD rows not met, with owner and date for each: ______
Evidence reviewed (commit SHA, CI run, reports with dates): ______
Known limits told to users (asking prices, owner-text evidence, coverage): ______
Rollback plan (who, how, how long) and last restore drill date: ______
Stop conditions during the beta (for example a leaked credential, a wrong public price, a source asking us to stop): ______
Next review date: ______
```
