# ModInspect Sprint Tracker and Progress Scorecard

Baseline date: 2026-11-14. Estimates are in **ideal hours** (consistent throughout). Unverified code earns 0.
Owner acceptance is tracked separately from automated verification and is **never** assumed.

## Progress formula

```
EPIC_PROGRESS = 100 * sum(weight of ACCEPTED criteria) / sum(weight of all in-scope criteria)
OVERALL       = sum(epic_weight * epic_progress) / sum(epic_weight)
```

Two columns are reported: `AUTO` (automated/browser evidence in this baseline) and `OWNER` (explicit owner acceptance on this
baseline). Historical owner UAT from September is **not** carried over because the Admin table library changed on 2026-11-14.

## Scorecard (first measured baseline, no earlier formal baseline exists)

| Epic | Weight | Criteria earned / total | AUTO % | OWNER % |
|---|---:|---|---:|---:|
| E1 Operational core | 25 | 16 / 16 | 100 | 0 |
| E2 Public UX and journeys | 25 | 5 / 16 | 31.3 | 0 |
| E3 Trusted market data | 25 | 8 / 18 | 44.4 | 0 |
| E4 Security, QA, CI, deployment | 20 | 18 / 23 | 78.3 | n/a |
| E5 Docs and operations | 5 | 5 / 5 | 100 | n/a |
| **Overall (engineering AUTO)** | 100 |  | **64.6** | **0** |

`Last baseline -> Current measured -> Delta`: 55.7 (AUTO, after Sprint 1 slice) -> 64.6 (AUTO) -> +8.9. Scope changes in this delta: E2 denominator 15 -> 16 (U7 added), E4 denominator 16 -> 23 (Q8, Q9, Q10 added). OWNER stays 0.

Other dimensions (not folded into the percentage):
- Implementation coverage: high for Admin/pipeline, low for public experience.
- Functional QA coverage: 35 suite steps (tests + CLI checks) green on PHP 8.1, 8.3 and 8.4 against the isolated test DB, including a real-HTTP end-to-end test and a security regression test; browser checks of Products and of 5 public pages at 3 widths.
- Owner UX acceptance: 0 (public visual direction not chosen).
- Real-data readiness: **0 approved REAL observations**, 88 REAL pending, 41 snapshots all `legacy_unavailable` (shared DEV, read-only counts 2026-11-14).
- Release readiness: HOLD. No staging, no production isolation. `.github/workflows/ci-tests.yml` is authored but has **never run** on GitHub (branch pushed, but it needs a PR to trigger).

## Criteria detail

Weights: 1 minor, 2 standard, 3 major, 4 critical. `A` = auto-accepted, `-` = not earned.

### E1 Operational core
| ID | Criterion | W | Status | Evidence |
|---|---|--:|:-:|---|
| O1 | Admin shell/routes render with server-side RBAC | 2 | A | `ui_integration_test`, `auth_rbac_test` |
| O2 | Nine operational tables on official server-side DataTables (draw/search/sort/page/filters) | 3 | A | Browser on isolated server: Products 41 -> search 5700X3D 1 -> CPU 10 / GPU 11 / CPU+inactive 0; 8 other tables init serverSide |
| O3 | Import template -> dry run -> confirm -> pending review | 2 | A | `admin_import_workflow_test` |
| O4 | Review decision/correction/approve with audit | 2 | A | `review_decision_test`, `review_correction_test` |
| O5 | Deterministic, reproducible snapshots with provenance | 2 | A | `price_snapshot_test`, `snapshot_provenance_test` |
| O6 | Reproducible assets, clean checkout boots | 2 | A | `cli/admin_vendor_manifest.php --verify`, simulated clean checkout (356 files, no `.env`) |
| O7 | HTTP end-to-end TEST path: login(CSRF) -> dry run -> confirm -> review queue -> human approve -> public read (no mock contamination) | 3 | A | `tests/http_e2e_flow_test.php` (real PHP server, isolated DB). Positive public price read needs owner-approved REAL evidence (see T4) |

### E2 Public UX
| ID | Criterion | W | Status | Evidence |
|---|---|--:|:-:|---|
| U1 | Brand CI direction chosen by owner | 3 | - | Directions drafted in `docs/UX_UI_SYSTEM.md` |
| U2 | Public design tokens/system implemented | 3 | - | |
| U3 | No price shown without an eligible snapshot (honest no-data) | 2 | A | Browser Home/Detail; snapshot gating tests |
| U4 | Usable mobile 390 header/navigation | 2 | A | Fixed: two legacy CSS rules hid nav links below 1000px/800px, header pinned to 56px, staff chip removed. Browser at 1440/768/375 on 5 pages: no overflow, nav complete |
| U5 | Accessibility and contrast review | 2 | - | NOT_RUN |
| U6 | Owner visual acceptance | 3 | - | |
| U7 | No internal text, unfinished-module links or staff identity on public pages | 1 | A | `ui_integration_test` assertions; browser check |

### E3 Trusted data
| ID | Criterion | W | Status | Evidence |
|---|---|--:|:-:|---|
| T1 | Evidence/provenance pipeline implemented and tested | 3 | A | collection/import/provenance tests |
| T2 | Resolver/validator golden set meets targets | 2 | A | 101 cases, 100% classification, 0 false green |
| T3 | REAL listing-level evidence approved by the owner for a cohort | 4 | - | 0 approved |
| T4 | Public range published for a cohort with sufficiency | 4 | - | |
| T5 | Source coverage and freshness operations | 2 | - | |
| T6 | Public price gate excludes mock/fixture/test sources and legacy-provenance snapshots | 3 | A | Fixed in `Product.php` (was leaking after approving TEST rows); proved red->green by `http_e2e_flow_test` |

### E4 Security, QA, CI, deployment
| ID | Criterion | W | Status | Evidence |
|---|---|--:|:-:|---|
| Q1 | Isolated test DB guard fails closed | 2 | A | `test_database_guard_test` |
| Q2 | Full regression suite green on isolated DB | 3 | A | `tests/run.php` exit 0 (32 steps) after repairing 5 stale tests, twice in a row and from a fresh DB |
| Q3 | Vendor assets pinned and guarded | 2 | A | `admin_assets_integrity_test` |
| Q4 | CI runs suite + vendor guard on clean checkout | 2 | A | GitHub check green on PR #4 (PHP 8.3 + MariaDB 10.6) after 3 portability fixes |
| Q5 | Staging environment, backup, rollback runbook | 3 | - | |
| Q6 | Secrets hygiene (no keys/`.env` tracked, raw LINE export absent) | 2 | A | Pattern scan clean; `.env*` untracked |
| Q7 | Browser DataTables contract gate in CI | 2 | - | Browser proof is manual |
| Q8 | Suite green on PHP 8.1, 8.3 and 8.4 with zero deprecations; CI matrix authored | 2 | A | Run locally on all three (35 steps). The matrix workflow has not run on GitHub yet |
| Q9 | Security review: web exposure closed, RBAC/CSRF audit as a test, headers, error handling | 3 | A | `docs/SECURITY_REVIEW_2026-11-14.md`; `tests/security_hardening_test.php` |
| Q10 | Automated secret scan with a self-test, run in CI | 2 | A | `cli/secret_scan.php`, `tests/secret_scan_test.php` |

### E5 Docs and operations
| ID | Criterion | W | Status |
|---|---|--:|:-:|
| D1 | One canonical current state (PROJECT_STATUS top block) | 2 | A |
| D2 | Handoff and `project_state.json` in sync | 2 | A |
| D3 | Requirements, roadmap, tracker, UX system, decision log | 1 | A |

## Task board

| ID | Task | Pri | Est h | Status | Evidence / next |
|---|---|:-:|--:|---|---|
| S0-01 | Source/runtime/DB recovery audit | P0 | 3 | DONE | `docs/PROJECT_STATUS.md` canonical block |
| S0-02 | Repair stale regression tests (5 defects) | P0 | 4 | DONE | suite green |
| S0-03 | Track and hash-pin admin vendor assets; manifest tool + test | P0 | 4 | DONE (uncommitted) | owner to commit |
| S0-04 | Replace hand-written DataTables adapter with official 1.13.11; fix init contract (`data-source-url`, `ajax.data` merge) | P0 | 5 | DONE (owner re-UAT pending) | browser proof |
| S0-05 | Fix Admin freshness unit (8,500% display) | P1 | 1 | DONE | test added |
| S0-06 | Requirements/roadmap/tracker/UX/decision/CI docs | P1 | 5 | DONE | |
| S0-07 | Public UX audit with screenshots | P1 | 2 | DONE | findings in `docs/UX_UI_SYSTEM.md` |
| S1-01 | Reconcile branches and commit baseline | P0 | 2 | DONE, pushed to origin (no PR) | branch `checkpoint/claude-baseline-2026-11-14`: `5b05a31` + merge of `origin/main` |
| S1-02 | Owner re-UAT of Admin tables (official DataTables) | P0 | 1 owner | READY_FOR_OWNER_REVIEW | |
| S1-03 | Fix Font Awesome `.html` font URLs (needs a verified official 5.x download) | P2 | 2 | PLANNED | needs download approval |
| S1-04 | Mark non-sortable columns (`data-orderable=false`) on Action/Controls/Details headers | P2 | 1 | DONE | 7 tables; view change only |
| S1-05 | CI: vendor guard + suite on MariaDB 10.6 service, no secrets | P1 | 5 | AUTHORED, NOT RUN | needs a PR to trigger (workflow is not on main); local simulation (fresh DB bootstrap + suite) green |
| S1-06 | HTTP E2E TEST path (O7) | P1 | 6 | DONE | `tests/http_e2e_flow_test.php` |
| S1-07 | Login/Imports icons blank: `fa-search-dollar` and `fa-file-csv` do not exist in bundled FA 5.0.9 | P2 | 1 | DONE | replaced; new guard fails on any missing icon. The earlier tofu glyph was a CORS artifact of my test APP_URL, not an app defect |
| S1-08 | Fix public gate leak: mock/fixture/test sources and legacy snapshots could surface as a public price | P0 | 3 | DONE | DL-007; `Product.php`; E2E |
| S1-09 | `examples/import/market_evidence_template.csv` was a stale 25-column sample | P2 | 1 | DONE | regenerated from `ImportFieldContract::csvTemplate()`; E2E now requires an exact match |
| S1-10 | Security review (Review role): close the web-exposure hole, headers, error handling, input whitelists, route RBAC/CSRF audit as a test | P0 | 10 | DONE | `docs/SECURITY_REVIEW_2026-11-14.md` |
| S1-11 | CI: PHP 8.1/8.3/8.4 matrix, secret scan, licence inventory (`docs/THIRD_PARTY_LICENSES.md`) | P1 | 6 | DONE locally; matrix not yet run on GitHub | |
| S1-12 | Public defects: nav links hidden below 1000px, header clipping, staff chip and internal text on public pages, 41 identical empty cards, Thai headline wrapping, PHP 8.4 `fgetcsv` deprecations that broke redirects | P1 | 10 | DONE | browser at 1440/768/375 |
| S1-13 | Single source for the public price-eligibility rule (about 10 copies) | P2 | 3 | PLANNED | review finding |
| S1-14 | Runtime probe that `/.env`, `/.git/config` return 403/404 (CI with Apache or post-deploy) | P2 | 3 | PLANNED | |
| S1-15 | Rate limiting for public POST routes | P2 | 4 | PLANNED | |

## Rebaseline 2026-11-15 (master prompt v2): sprint program R0 / T1 / U2 / I3 / D4 / C5 / R6

The 2026-11-14 scorecard above is **historical** (its data lines are stale: it says 41 products, 0 approved REAL). Sprint 0 and the Sprint 1
engineering slice stay DONE. This section reuses them and adds the forward sequence. Calendar windows are **provisional** (they assume a
start on 2026-11-16 and one implementation agent; the owner has not confirmed a start date, so planned windows are not commitments).

| Sprint | Goal | Provisional window | Engineering | Owner gate | Notes |
|---|---|---|---|---|---|
| R0 Recovery delta | Verify branch/runtime/data, reconcile docs, brand conflict | 2026-11-16 to 11-19 | **DONE 2026-11-15** (this session) | none | Brand conflict resolved from recorded owner decisions (see UX_UI_SYSTEM) |
| T1 Price trust | Trace every public price, evidence explorer, containment | 2026-11-20 to 12-03 | **IN PROGRESS** (audit + explorer done; owner threshold decision open) | OWNER: confidence policy for owner-text-only evidence | See criteria below |
| U2 Unified public UX/CI | Adaptive results, card hierarchy, detail page, no one-point chart | 2026-12-04 to 12-17 | IN_PROGRESS (first slice 2026-11-15: adaptive results, 4-col cards, compact detail, collapsed credits; see DL-033) | OWNER visual PASS | Public theme still hand-written (docs/UI_HANDWRITTEN_CODE_AUDIT.md) |
| I3 Product intelligence | Release date/specs/benchmark POC on 10 products | 2026-12-18 to 2027-01-07 | NOT_STARTED (provider matrix drafted) | OWNER: provider/licence approval | `docs/PRODUCT_INTELLIGENCE_PROVIDER_MATRIX.md` |
| D4 Deal Checker 2.0 + condition guide | Condition/warranty context, checklists | 2027-01-08 to 01-21 | PARTIAL (deal result now shows model facts, trend, nearby models) | OWNER task UAT | No arbitrary depreciation |
| C5 Community loop | Moderated reports | 2027-01-22 to 02-04 | NOT_STARTED, deferrable | OWNER scope | Never writes snapshots |
| R6 Beta hardening | Clean checkout, staging, backup, release checklist | 2027-02-05 to 02-18 | PARTIAL (CI matrix green on PHP 8.1/8.3/8.4 at f08639a; runs after that commit were not re-checked here; dry-run package) | OWNER GO for any production deploy | Packaging is not deployment permission |

Critical path: R0 -> T1 -> U2 -> D4 -> R6. I3 may run in parallel after T1 data semantics but publishes no benchmark without licence approval.

### T1 criteria (weights 1 minor to 4 critical; `A` = auto-verified, `-` = not earned)

| ID | Criterion | W | Status | Evidence |
|---|---|--:|:-:|---|
| T1-1 | Every public price traces to a recorded snapshot whose members are approved, non-mock, asking/single-item, and the snapshot replays MATCH | 4 | A | `cli/price_trust_audit.php` on shared DEV 2026-11-15 21:49 (UTC+7): 46 public prices, 46 replay MATCH, 0 P0, 0 P1; test DB gate in `tests/price_trust_test.php` |
| T1-2 | Freshness unit is unambiguous (percentage 0..100 stored, displayed once) | 2 | A | Audit checks the range; admin and public views format it without a second multiplication |
| T1-3 | Read-only evidence explorer on the product page (date, price, evidence grade, exclusion counts, source count; no seller/URL/raw text) | 3 | A | `components/evidence-explorer.php`, `tests/price_trust_test.php`, rendered on a DEV product page |
| T1-4 | One-source limitation visible, one-point trend suppressed | 2 | A | Evidence block states "single source"; `trend-chart.php` draws only with 2+ months (`tests/trend_location_test.php`) |
| T1-5 | Legacy snapshots (41, provenance unavailable) never public | 3 | A | Public join excludes `legacy_unavailable` (Product read model); audit counts only recorded snapshots |
| T1-6 | Confidence policy for evidence that is 100% owner text from one source | 4 | A | DL-033: `OWNER_TEXT_ONLY_CAP` (confidence-v2); 46/46 public prices now low; `tests/price_trust_test.php`. OWNER accepted option A but gave no threshold: conservative cap applied, reversible |
| T1-7 | Admin evidence explorer / audit links per snapshot | 2 | - | Not built; the public explorer covers the read-only need first |
| T1-8 | Golden resolver set (RTX 3050, RX 5700 XT, 3060 Ti vs 3060, 4070 Ti/SUPER, Ryzen 5600 vs 5600G, 5700X3D) re-run | 3 | - | NOT_RUN in this session |

Progress, T1 only: earned weight 18 of 23 = 78.3 % AUTO (T1-6 now earned); OWNER 0 %. The project-level overall percentage is **NOT_BASELINED** after this rebaseline: the
weights in the master prompt (data 30, UX 20, workflow 15, intelligence 10, deal 10, release 15) differ from the earlier epic weights and no
full criteria audit has been done under them. Dimensions: DATA = REAL_PENDING (88 REAL pending, 0 REAL approved) plus 420 owner-text approved (grade C, one source);
RELEASE = NOT_READY (no staging; production not authorised).

## Parallel work board (2026-11-15)

The long sprint LS-1 is split between Claude (UI, browser verification) and Codex (data, backend, tests). Who does what, the file-ownership lock,
status and evidence live in `docs/SPRINT_BOARD.md`; the copy-ready Codex prompt is `docs/CODEX_LS1_PROMPT.txt`. This tracker keeps the sprint-level scorecard only.
