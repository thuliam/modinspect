# ModInspect Project Status

## 1. Current Architecture Baseline

- Current Blueprint version: `ModInspect_Master_Blueprint_v1.1.md`
- Historical Blueprint: `ModInspect_Master_Blueprint_v1.0.md`
- Current ADR baseline:
  - `docs/decisions/ADR-005-community-feedback-is-evidence-not-voting.md`
- Current architecture principles:
  - Data-first, provider-independent architecture.
  - Product Master is separate from Market Observation.
  - AI/providers produce candidates/evidence; deterministic PHP code validates and prices.
  - Asking prices and sold/final prices are separate data types.
  - Raw evidence and original extraction payloads are immutable.
  - Human corrections are review-layer artifacts.
  - Price snapshots are deterministic, versioned, and reproducible for new snapshots.
  - Mock/test data must not enter public price snapshots.
  - Administrative authorization is enforced server-side.
  - Human review/admin mutations preserve authenticated actor identity where a human session exists.
  - Credentials, password hashes, session tokens, and API keys must never be logged or exposed.
  - Price confidence is deterministic, versioned, explainable, and derived from snapshot-time evidence quality.
  - Historical confidence must be reproducible from preserved snapshot-time inputs.
  - Fixture/mock evaluation establishes an engineering baseline but never proves live-market quality.
  - Calibration decisions must distinguish fixture metrics from reviewed live-data metrics.
  - All acquisition channels, including mock, offline import, and future live providers, converge into the same candidate/evidence/extraction/resolution/validation/review pipeline.
  - No acquisition adapter may directly create trusted market observations.
  - Community input is evidence/signals, not voting.
  - Source blocking must pause/disable the source and record an incident; no bypass/evasion.
  - MODINSPECT ZERO-COST POC POLICY: external spend target is 0 THB until the owner explicitly approves spending. Paid APIs, paid data sources, paid infrastructure, paid crawler/search services, subscription upgrades, metered providers, and billing/credit-card exposure require explicit owner approval; otherwise stop with `OWNER_APPROVAL_REQUIRED`.

## 2. Current Phase

- Active milestone: Phase 4A — UX/UI Product Audit & Design Foundation
- Status: PASS
- Why this milestone exists: ModInspect has strong backend/data capability, but the public and Admin UX needed a product-design audit, terminology cleanup, honest hierarchy, design-system baseline, and zero-cost policy before deeper public MVP polish.

## 3. Completed Milestones

### Phase 0 — Foundation / Test Harness

Status: PASS

Key outcomes:
- Custom PHP MVC prototype foundation verified.
- Mock provider, rule extractor, product resolver, and validator fixture path established.

Major artifacts:
- `SearchProviderInterface`
- `MockSearchProvider`
- rule-based extraction/resolution/validation tests

### Phase 1 — Product Master POC

Status: PASS / POC gate reached

Key outcomes:
- Local DB has at least 10 active CPU products and 10 active GPU products.
- CPU/GPU resolver fixture accuracy reached 100%.

Major artifacts:
- CPU/GPU product seed migrations
- product aliases
- Product Master tests

### Phase 2A — Coverage Planner

Status: PASS

Key outcomes:
- Deterministic planner identifies due/not-due CPU/GPU products.
- Planner considers sample gaps, freshness, accepted/fresh counts, priorities, active jobs, and failures.
- Mock collection jobs are created idempotently.

Major artifacts:
- `CoveragePlanner`
- `cli/coverage.php`
- `tests/coverage_planner_test.php`

### Phase 2B — Mock Scheduled Runner

Status: PASS

Key outcomes:
- Queued mock coverage jobs are claimed safely and run product-by-product through the existing pipeline.
- Completed jobs are not reprocessed.
- Failed jobs do not stop later jobs.

Major artifacts:
- `ScheduledCollectionRunner`
- `cli/run_collection_jobs.php`
- runner state-machine tests

### Phase 2C — Collection Operations Foundation

Status: PASS

Key outcomes:
- Explicit failed-job requeue.
- Collection run persistence.
- Source policy, pause/disable, incidents, and source-health reporting.
- Seeded non-empty mock smoke path.

Major artifacts:
- `SourceOperationsService`
- `SourceHealthService`
- `CollectionRunRepository`
- `cli/requeue_collection_job.php`
- `cli/source_control.php`
- `cli/source_health.php`

### Phase 2D-A — Live Provider Infrastructure / Preflight

Status: PASS

Key outcomes:
- Gemini adapter infrastructure exists.
- Provider registry and hard request guard exist.
- Dry-run/preflight paths are network-free.
- Default configuration blocks live execution.

Major artifacts:
- Gemini provider adapter
- provider registry
- live query planner
- `cli/live_collection_poc.php`
- network-free live-infrastructure tests

### Phase 2D-I — Application Integration & UI Synchronization

Status: PASS

Key outcomes:
- Visible routes/actions audited.
- Admin dashboard uses real DB/service state.
- Collector jobs, sources, review queue, price views, provider readiness, and mock labels synchronized with backend reality.
- Unsupported UI actions are disabled/clearly marked.

Major artifacts:
- `docs/UI_INTEGRATION_AUDIT.md`
- `tests/ui_integration_test.php`

### Phase 2D-J — Stability / Regression / Bug Hunt

Status: PASS

Key outcomes:
- Fixed mock contamination of public snapshots.
- Review decisions became replay-safe.
- Collection claim concurrency regression coverage added.
- Data integrity checker added.

Major artifacts:
- `docs/QA_BUG_HUNT_REPORT.md`
- `cli/data_integrity_check.php`
- `tests/stability_bug_hunt_test.php`

### Phase 3A — Snapshot Provenance & Formula Versioning

Status: PASS

Key outcomes:
- New snapshots persist formula/cohort/quartile/confidence versions.
- New snapshots persist calculation hash and manifest.
- New snapshots persist included/excluded observation membership and reasons.
- Historical reproduction CLI added.

Major artifacts:
- `database/migrations/010_snapshot_provenance.sql`
- `price_snapshot_observations`
- `cli/reproduce_price_snapshot.php`
- `tests/snapshot_provenance_test.php`

### Phase 3B — Review Correction Workflow & Observation Quality Controls

Status: PASS

Key outcomes:
- Field-level corrections are auditable and validated.
- Original raw candidate, evidence, and extraction payloads remain immutable.
- Approved observations use final reviewed values.
- Asking/sold schema debt reduced with neutral `price_value`.
- Corrected observations integrate with snapshot provenance.

Major artifacts:
- `database/migrations/011_review_corrections.sql`
- `ReviewCorrectionService`
- `review_corrections`
- `tests/review_correction_test.php`

### Phase 3C — CPU/GPU Category-Specific Validation Rules & Quality Metrics

Status: PASS

Key outcomes:
- Generic validation remains separate from CPU/GPU category rule packs.
- CPU/GPU resolver matching is suffix-aware and avoids unsafe nearby-model substring matches.
- Mobile GPU, bundle, whole-PC, wanted, deposit, defective, multiple-price, weak-evidence, wrong-product, and board-partner context flags are deterministic.
- Pipeline-created observations persist validation quality flags.
- Snapshot eligibility excludes critical category-invalid quality flags from normal asking-price cohorts.
- Offline golden-dataset evaluator reports product-resolution accuracy, classification accuracy, Green coverage/precision, false-Green rate, wrong-nearby false-match rate, and rule triggers.

Major artifacts:
- `ModelMarker`
- `CpuValidationRules`
- `GpuValidationRules`
- `ValidationRuleRegistry`
- `cli/evaluate_validation_quality.php`
- `tests/fixtures/validation_golden_dataset.php`
- `tests/category_validation_test.php`

### Phase 3D — Admin Authentication & Role-Based Access Control

Status: PASS

Key outcomes:
- Admin routes require local account authentication.
- Static RBAC permissions protect Admin pages and state-changing review/source/job actions server-side.
- Login uses PHP `password_hash`/`password_verify`, session ID rotation, idle/absolute timeout checks, logout invalidation, and bounded login-attempt throttling.
- Initial admin users are created explicitly by CLI; no default password or seeded credential is shipped.
- Authenticated actor identity reaches review corrections, review decisions, source controls/incidents, and audit logs.

Major artifacts:
- `database/migrations/012_admin_auth_rbac.sql`
- `AuthService`
- `AuthorizationService`
- `AuthController`
- `cli/create_admin_user.php`
- `cli/auth_status.php`
- `tests/auth_rbac_test.php`

### Phase 3E — Price Confidence, Freshness & Source Diversity

Status: PASS

Key outcomes:
- New snapshots use `confidence-v1`.
- Confidence is calculated from sample size, freshness, source diversity, and evidence quality.
- Confidence components, reason codes, weights, caps, source distribution, and freshness metrics are persisted in `calculation_manifest`.
- Snapshot reproduction verifies confidence score, label, component provenance, and confidence-sensitive hash.
- Public price views show honest confidence labels/reasons; Admin Price Indices exposes component scores.

Major artifacts:
- `SnapshotConfidenceService`
- `cli/price_confidence_report.php`
- `tests/price_confidence_test.php`

### Phase 3F — Review Operations & Calibration Analytics

Status: PASS

Key outcomes:
- Review lifecycle metrics are derived from persisted observations, review decisions, and corrections.
- Lane outcomes report clean Green approval separately from Green approval after correction.
- Quality flags and corrected fields are aggregated for extractor/resolver tuning.
- Source/provider review-quality metrics are separated from operational Source Health.
- MOCK/TEST rows are labeled and excluded from live KPI readiness.
- Current seeded baseline reports `FIXTURE_BASELINE`, not live readiness.

Major artifacts:
- `ReviewCalibrationService`
- `cli/review_calibration_report.php`
- `/admin/review-analytics`
- `tests/review_calibration_test.php`

### Phase 3G — Offline Real-World Evidence Intake & Calibration Sample

Status: PASS

Key outcomes:
- CSV and JSON market-evidence files can be imported by CLI as a provider-neutral offline acquisition adapter.
- Dataset classification is explicit; default is TEST and REAL must be declared.
- Imported REAL evidence remains pending/reviewable and never directly creates accepted observations, review decisions, or snapshots.
- Import provenance, source policy checks, duplicate re-import protection, collection run audit, review corrections, snapshot provenance, and calibration analytics are verified.

Major artifacts:
- `OfflineEvidenceImportService`
- `OfflineFileProvider`
- `cli/import_market_evidence.php`
- `examples/import/market_evidence_template.csv`
- `examples/import/market_evidence_template.json`
- `tests/offline_import_test.php`

### Phase 4A — UX/UI Product Audit & Design Foundation

Status: PASS

Key outcomes:
- Public and Admin UX were audited from user/operator perspectives.
- Public wording now avoids unnecessary pipeline terms on core user-facing pages.
- ModInspect branding is consistent in the global shell.
- External Google font/icon dependencies were removed from the local POC UI.
- Design-system baseline, user flows, owner visual-review gate, and zero-cost POC policy were documented.

Major artifacts:
- `docs/UX_UI_AUDIT.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/USER_FLOWS.md`
- `tests/ux_structure_test.php`

## 4. Current System Capabilities

| Domain | Status | What works now |
|---|---|---|
| Product Master | IMPLEMENTED | Seeded canonical products, categories, brands, aliases, CPU/GPU POC catalog gate. |
| Product Resolver | IMPLEMENTED | Deterministic alias/name resolver passes fixture and DB catalog tests with suffix-aware CPU/GPU model markers. |
| Collection Planner | IMPLEMENTED | Mock-only coverage planner identifies due CPU/GPU products and creates idempotent jobs. |
| Collection Runner | IMPLEMENTED | Mock scheduled runner safely claims queued jobs and persists candidates/evidence/extractions/reviews. |
| Source Policy | IMPLEMENTED | Sources have enabled/paused state, policy fields, budgets, notes, and incidents. |
| Source Health | IMPLEMENTED | CLI/Admin health reports attempted/succeeded/failed jobs, yield, lane distribution, duplicate rate, and status. |
| Review Pipeline | IMPLEMENTED | Pending observations can be corrected, approved, rejected, or excluded with audit trail and validation quality flags. |
| Review Analytics | IMPLEMENTED | Lifecycle, lane outcome, clean-Green precision, correction, quality-flag, source/provider yield, and readiness reports are available by CLI and Admin. |
| Offline Evidence Import | IMPLEMENTED | CSV/JSON local files import through the provider-neutral pipeline with dry-run, TEST/REAL classification, source policy, dedupe, provenance, and review-gated output. |
| Reviewed Offline REAL Calibration | NOT STARTED | Protocol exists, but no owner-supplied REAL CSV/JSON batch is present under the approved ignored import location. |
| Price Engine | IMPLEMENTED | Deterministic asking-price snapshots from eligible approved observations; critical category-invalid quality flags are excluded from normal cohorts; confidence-v1 explains snapshot data quality. |
| Snapshot Provenance | IMPLEMENTED | New snapshots have formula/cohort/confidence versions, manifest, hash, included/excluded membership, and confidence component provenance. |
| Admin UI | PARTIAL | Dashboard, sources, jobs, review queue, observations, indices are wired and auth/RBAC protected; production hardening and batch tools are missing. |
| Public UI | PARTIAL | Public price/search/detail/deal/compare/build/report pages render honest data/empty states; many advanced modules remain prototype/disabled. |
| UX/UI Design Foundation | IMPLEMENTED | Phase 4A audit, design-system baseline, user-flow docs, terminology cleanup, offline asset dependency cleanup, and owner visual-review list exist. |
| Live Provider Infrastructure | PARTIAL | Gemini adapter, registry, preflight, dry-run, and guards exist; no real credentials or live execution. |
| Community Feedback Architecture | DOCUMENTED ONLY | Approved in Blueprint v1.1 and ADR-005; no runtime/schema/UI implementation. |

## 5. Current Database / Schema State

- Latest migration: `012_admin_auth_rbac.sql`
- Important tables:
  - `products`
  - `product_aliases`
  - `data_sources`
  - `collector_jobs`
- `collection_runs`
- Design/UX documentation:
  - `docs/UX_UI_AUDIT.md`
  - `docs/DESIGN_SYSTEM.md`
  - `docs/USER_FLOWS.md`
  - `raw_price_observations`
  - `market_evidence`
  - `extraction_runs`
  - `observation_review_decisions`
  - `review_corrections`
  - `price_observations`
  - `price_indices`
  - `price_snapshot_observations`
  - `price_histories`
  - `audit_logs`
  - `source_incidents`
  - `login_attempts`
- Important derived analytics:
  - review lifecycle metrics from `price_observations` and `observation_review_decisions`
  - correction analytics from `review_corrections`
  - source/provider quality from raw observation source and collector job lineage
  - MOCK/TEST vs REAL classification from source/provider identifiers
- Important new columns/models:
  - `price_observations.price_value`
  - `price_observations.quality_flags`
  - nullable `price_observations.asking_price`
  - `price_indices.formula_version`
  - `price_indices.cohort_version`
  - `price_indices.quartile_method_version`
  - `price_indices.confidence_method_version`
  - `price_indices.calculation_hash`
  - `price_indices.calculation_manifest`
  - `price_indices.provenance_status`
  - `review_corrections`
  - `price_snapshot_observations`
  - `users.role` supports `user`, `reviewer`, `operator`, `admin`
  - `login_attempts.email_hash`, `ip_address`, `attempted_at`, `success`
- Important in-code validation versions:
  - `generic-validation-v1`
  - `cpu-validation-v1`
  - `gpu-validation-v1`
- Known schema debt:
  - Legacy snapshots before Phase 3A have no observation membership provenance.
  - Sold-price-specific reporting/UI is not implemented.
  - Validation rules are versioned in code/review reason JSON, not in a dedicated rules table.
  - Roles/permissions are static code-defined mappings, not a dynamic permission editor.
- Migration system is raw SQL with a lightweight runner, not a full framework.
- Confidence component details are stored compactly in `price_indices.calculation_manifest`, not in separate relational component tables.
- Offline import reuses existing source/job/run/candidate/evidence/extraction/review/observation tables; no Phase 3G schema migration was added.

## 6. Current Data State

Representative current DB counts from the latest verified project handoff and subsequent transactional test runs:

| Metric | Count |
|---|---:|
| products | 21+ active CPU/GPU POC products, plus other seeded products |
| collector jobs queued | 0 |
| collector jobs running | 0 |
| collector jobs completed | 24 |
| collector jobs failed | 0 |
| candidates / raw observations | 18 |
| evidence | 18 |
| extractions | 18 |
| reviews | 18 |
| observations | 11 |
| accepted observations | 0 |
| snapshots | existing seeded/legacy rows plus any manually generated local snapshots |
| collection runs | 2 |
| users | 0 until an initial admin is created by CLI |

Data classification:

- Product/catalog data: MIXED seeded/test POC data.
- Collection pipeline rows: MOCK / TEST.
- Accepted observations: currently none in representative seeded state.
- Live provider data: NOT PRESENT.
- Offline imported real data: not committed; Phase 3G tests use transactional local files and roll back DB changes.
- Reviewed offline REAL calibration batch: NOT PRESENT / WAITING_FOR_REAL_SAMPLE.
- Community feedback data: NOT PRESENT.

## 7. Test Status

- Latest full regression result: PASS.
- Latest verified full regression suite:
  - `tests/syntax_check.php`
  - `tests/phase0_pipeline_test.php`
  - `tests/phase1_catalog_test.php`
  - `tests/coverage_planner_test.php`
  - `tests/scheduled_runner_test.php`
  - `tests/collection_operations_test.php`
  - `tests/live_provider_infra_test.php`
  - `tests/snapshot_loop_test.php`
  - `tests/price_snapshot_test.php`
  - `tests/review_decision_test.php`
  - `tests/ui_integration_test.php`
  - `tests/stability_bug_hunt_test.php`
  - `tests/snapshot_provenance_test.php`
  - `tests/review_correction_test.php`
  - `tests/category_validation_test.php`
  - `tests/auth_rbac_test.php`
  - `cli/evaluate_validation_quality.php`
  - `cli/auth_status.php`
  - `tests/price_confidence_test.php`
  - `cli/price_confidence_report.php`
  - `tests/review_calibration_test.php`
  - `cli/review_calibration_report.php`
  - `tests/offline_import_test.php`
  - `cli/import_market_evidence.php --file=examples/import/market_evidence_template.json --dataset=test --dry-run`
  - `tests/ux_structure_test.php`
  - `cli/data_integrity_check.php`
- Known test limitations:
  - Tests are plain PHP scripts, not phpunit.
  - DB-heavy tests share one local MySQL database.
  - Run DB-heavy tests sequentially unless per-worker DB isolation is added.
- Concurrency/deadlock note:
  - A previous parallel run produced a MySQL deadlock. Sequential reruns pass. Root cause is most likely shared test DB/fixed fixture IDs rather than production claim logic.
- Last data integrity checker result:
  - `ok: true`
  - `problem_count: 0`
- Latest validation-quality evaluator result:
  - fixture count: 101
  - CPU product-resolution accuracy: 100%
  - GPU product-resolution accuracy: 100%
  - overall classification accuracy: 100%
  - Green coverage: 54.46%
  - Green precision: 100%
  - critical invalid false-Green rate: 0%
- Latest confidence fixture result:
  - HIGH case: diverse, fresh, strong-evidence cohort; score 88.45
  - MEDIUM case: single-source cohort capped below HIGH
  - LOW case: tiny, stale, weak-evidence single-source cohort
  - deliberate confidence manifest corruption: `MISMATCH`
- Latest review calibration baseline:
  - current seeded CLI sample size: 11
  - dataset label: `MOCK_TEST`
  - pending reviews: 11
  - reviewed count: 0
  - clean-Green precision: not yet measurable
  - valid live yield: not yet measurable
  - calibration status: `FIXTURE_BASELINE`
- Phase 3G offline import focused test:
  - dry-run valid/invalid rows: 3/1
  - JSON import candidates/evidence/extractions/reviews: 3/3/3/3
  - CSV import candidates: 1
  - duplicate re-import skipped rows: 3
  - imported REAL calibration status after reviewed transactional fixture: `LIVE_SAMPLE_INSUFFICIENT`
  - imported REAL snapshot reproduction: `MATCH`
- Metric terminology:
  - Golden Dataset Green precision is validator-fixture precision from `cli/evaluate_validation_quality.php`.
  - Review Calibration clean-Green precision is human-review outcome precision from `cli/review_calibration_report.php`.
  - These are intentionally different metrics.

## 8. Known Bugs / Technical Debt

- HIGH: Admin has local auth/RBAC but still needs production deployment hardening before public exposure.
- MEDIUM: DB-heavy plain PHP tests share one MySQL database and should run sequentially unless isolated.
- MEDIUM: Live provider POC lacks real Gemini credentials and remains intentionally disabled.
- MEDIUM: No correction removal UI or batch review tooling.
- MEDIUM: No sold-price-specific public/reporting UI yet.
- MEDIUM: Category-specific validation rules are fixture-calibrated and need real reviewed cases after live POC.
- MEDIUM: Confidence-v1 thresholds are deterministic and tested but still need calibration against real reviewed observations.
- MEDIUM: Review calibration analytics have no live reviewed sample yet; current values are fixture/mock baseline only.
- MEDIUM: Offline import is CLI-only; no browser upload or import review screen exists.
- MEDIUM: Phase 3H is blocked until an owner-supplied REAL CSV/JSON batch is placed under `storage/import/` or `var/import/`.
- MEDIUM: Phase 4A responsive review is code-level only; owner visual review is still required on the priority pages before full redesign.
- MEDIUM: Active hands-on review time is not captured; current latency is review-created to decision timestamp only where available.
- LOW: Legacy price snapshots created before Phase 3A have no membership provenance.
- LOW: Legacy snapshots before Phase 3E have no confidence component manifest.

## 9. External Dependencies / Credentials

- Gemini adapter: implemented.
- Gemini API key: NOT CONFIGURED.
- Gemini provider enabled: false in default/safe configuration.
- Live collection enabled: false.
- Paid provider calls enabled: false.
- OpenAI/Perplexity/Tavily/Brave providers: NOT IMPLEMENTED / NOT ENABLED.
- Browser automation/scraping: NOT IMPLEMENTED / NOT ENABLED.
- Offline import adapter: implemented and network-free.
- Zero-Cost POC policy: active; external spend target is 0 THB and any paid/metered/billing-required service requires explicit owner approval.

No secrets are stored in this document.

Admin auth state:

- Local account auth: implemented.
- Default admin credentials: not shipped.
- Current persistent users in representative DB: 0.
- Initial admin creation: `php cli/create_admin_user.php --email=admin@example.com --password-env=MODINSPECT_ADMIN_PASSWORD`.

## 10. Locked Product / Architecture Decisions

- Data-first, provider-independent.
- Product Master is not Market Observation.
- Price Engine is deterministic.
- Asking price is not sold/final price.
- Accepted observations only feed public market snapshots.
- Mock/test data cannot enter public snapshots.
- Historical price snapshots are immutable calculation artifacts.
- New snapshots must retain sufficient provenance to reproduce deterministic results.
- Price confidence is deterministic, versioned, explainable, and derived from snapshot-time evidence quality; it never changes observed prices.
- Historical confidence must be reproducible from preserved snapshot-time inputs.
- Fixture/mock evaluation establishes an engineering baseline but never proves live-market quality.
- Calibration decisions must distinguish fixture metrics from reviewed live-data metrics.
- All acquisition channels converge into the same Candidate -> Evidence -> Extraction -> Resolution -> Validation -> Review pipeline.
- Offline import is not Live Provider collection.
- REAL imported evidence is not verified truth until reviewed and accepted.
- No acquisition adapter may directly create trusted market observations.
- Critical category-invalid quality flags must not enter normal public snapshot cohorts.
- Administrative authorization is enforced server-side; UI visibility is never the authorization boundary.
- Human review and administrative mutations must preserve authenticated actor identity where a human session exists.
- Credentials, password hashes, session tokens, and API keys must never be logged or exposed.
- Original extraction/evidence is immutable.
- Human review corrections are auditable review-layer artifacts.
- Accepted observations are derived from validated reviewed values.
- AI/users provide evidence; rules validate; humans review ambiguity; Price Engine calculates.
- Community input is evidence, not voting.
- Community submissions never directly set market price.
- Source blocking never triggers bypass/evasion.
- Zero-Cost POC policy blocks paid/metered services and billing/credit-card exposure unless explicitly approved by the owner.
- Every completed milestone must update `docs/PROJECT_STATUS.md` before it can be reported PASS.

## 11. Deferred / Future Features

| Feature | Status |
|---|---|
| Community Data Feedback Loop | APPROVED / NOT IMPLEMENTED |
| Community anomaly signal to Coverage Planner | APPROVED / NOT IMPLEMENTED |
| Contributor reputation | APPROVED / NOT IMPLEMENTED |
| Deal Checker screenshot intake | NOT IMPLEMENTED |
| Seller expansion | NOT IMPLEMENTED |
| Local Match | NOT IMPLEMENTED |
| First-party transaction outcomes | APPROVED / NOT IMPLEMENTED |
| B2B expansion | NOT IMPLEMENTED |
| API/Data products | NOT IMPLEMENTED |
| Live provider execution | WAITING FOR CREDENTIALS / APPROVAL |
| Browser upload import UI | NOT IMPLEMENTED |
| Reviewed offline REAL calibration batch | WAITING_FOR_REAL_SAMPLE |

## 12. Current Blockers

Live Provider POC is blocked by:

- No real Gemini API key configured.
- `MODINSPECT_LIVE_COLLECTION_ENABLED=false`.
- `GEMINI_PROVIDER_ENABLED=false` in safe/default configuration.
- `MODINSPECT_PAID_PROVIDER_CALLS_ENABLED=false`.

Production/public deployment is blocked by:

- No TLS/reverse-proxy/server hardening verified.
- No MFA/SSO/password reset workflow.
- No external security review.
- No production backup/access policy.

Phase 3H Reviewed Offline REAL Calibration Batch is blocked by:

- No owner-supplied REAL CSV/JSON evidence batch found under `storage/import/` or `var/import/`.
- Existing files under `storage/import/test/` are test-generated fixtures and must not be relabeled as REAL.

## 13. Next Recommended Milestone

Phase 4A Owner Visual Review Gate.

Objective:

- Owner manually reviews the six priority screens identified by the UX audit and decides which P1/P2 design recommendations should become the next implementation scope.

Why now:

- Phase 4A was intentionally code-level and conservative. Visual approval is needed before a larger public/Admin redesign pass.

Prerequisites:

- Open the local app and inspect Home, Price Index/Search, Product Detail, Deal Checker, Admin Dashboard, and Review Queue.
- Keep `docs/UX_UI_AUDIT.md` open as the issue checklist.

Explicit non-goals:

- No Gemini/live API request.
- No paid service or billing-enabled provider.
- No full visual redesign until owner review is complete.
- No auto-accept.

## 14. Resume Instructions

Before continuing:

1. Read `AGENTS.md`.
2. Read `ModInspect_Master_Blueprint_v1.1.md`.
3. Read `docs/PROJECT_STATUS.md`.
4. Read `context/project_state.json`.
5. Read the docs relevant to the active milestone.
6. Inspect actual code before changing anything.
7. Repository/runtime is source of truth over old reports.

Do not assume previous chat context exists.

## 15. Last Updated

- Timestamp: 2026-09-09 18:10 ICT
- Milestone that last updated this file: Phase 4A — UX/UI Product Audit & Design Foundation
- Latest migration: `012_admin_auth_rbac.sql`
- Latest verified regression status: PASS after Phase 4A structural UX regression checks
