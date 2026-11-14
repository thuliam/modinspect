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
| E2 Public UX and journeys | 25 | 2 / 15 | 13.3 | 0 |
| E3 Trusted market data | 25 | 8 / 18 | 44.4 | 0 |
| E4 Security, QA, CI, deployment | 20 | 9 / 16 | 56.3 | n/a |
| E5 Docs and operations | 5 | 5 / 5 | 100 | n/a |
| **Overall (engineering AUTO)** | 100 |  | **55.7** | **0** |

`Last baseline -> Current measured -> Delta`: 48.2 (AUTO, 2026-11-14 morning) -> 55.7 (AUTO) -> +7.5. Delta breakdown: O7 earned (+4.7); new criterion T6 added to E3 (scope change, denominator 15 -> 18) while earned (+2.8 net). OWNER stays 0.

Other dimensions (not folded into the percentage):
- Implementation coverage: high for Admin/pipeline, low for public experience.
- Functional QA coverage: 32 suite steps (tests + CLI checks) green on the isolated test DB, run twice in a row and from a freshly bootstrapped DB; a real-HTTP end-to-end test; browser proof for Products in full, and the other eight server-side tables initialise and serve data.
- Owner UX acceptance: 0 (public visual direction not chosen).
- Real-data readiness: **0 approved REAL observations**, 88 REAL pending, 41 snapshots all `legacy_unavailable` (shared DEV, read-only counts 2026-11-14).
- Release readiness: HOLD. No staging, no production isolation. `.github/workflows/ci-tests.yml` is authored but has **never run** on GitHub (not pushed).

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
| U4 | Usable mobile 390 header/navigation | 2 | - | **Defect found**: header overflow when signed in, nav clipped |
| U5 | Accessibility and contrast review | 2 | - | NOT_RUN |
| U6 | Owner visual acceptance | 3 | - | |

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
| Q4 | CI runs suite + vendor guard on clean checkout | 2 | - | Workflows exist only on `origin/main`; not reconciled; no DB service |
| Q5 | Staging environment, backup, rollback runbook | 3 | - | |
| Q6 | Secrets hygiene (no keys/`.env` tracked, raw LINE export absent) | 2 | A | Pattern scan clean; `.env*` untracked |
| Q7 | Browser DataTables contract gate in CI | 2 | - | Browser proof is manual |

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
| S1-01 | Reconcile branches and commit baseline | P0 | 2 | DONE locally (not pushed) | branch `checkpoint/claude-baseline-2026-11-14`: `5b05a31` + merge of `origin/main` |
| S1-02 | Owner re-UAT of Admin tables (official DataTables) | P0 | 1 owner | READY_FOR_OWNER_REVIEW | |
| S1-03 | Fix Font Awesome `.html` font URLs (needs a verified official 5.x download) | P2 | 2 | PLANNED | needs download approval |
| S1-04 | Mark non-sortable columns (`data-orderable=false`) on Action columns | P2 | 1 | PLANNED | |
| S1-05 | CI: vendor guard + suite on MariaDB 10.6 service, no secrets | P1 | 5 | AUTHORED, NOT RUN | needs a push to run; local simulation (fresh DB bootstrap + suite) green |
| S1-06 | HTTP E2E TEST path (O7) | P1 | 6 | DONE | `tests/http_e2e_flow_test.php` |
| S1-07 | Login page icon renders as tofu glyph; investigate | P2 | 1 | PLANNED | |
| S1-08 | Fix public gate leak: mock/fixture/test sources and legacy snapshots could surface as a public price | P0 | 3 | DONE | DL-007; `Product.php`; E2E |
| S1-09 | `examples/import/market_evidence_template.csv` is a stale 25-column sample (contract has 27) | P2 | 1 | PLANNED | admin download template is correct and tested |
