# Data Model

Existing schema mapped to Blueprint domains:

| Domain | Current tables | Status |
|---|---|---|
| Users/Roles | `users` with role enum | Local account auth and static RBAC are implemented for Admin. Roles: `admin`, `reviewer`, `operator`, `user`. |
| Login Attempts | `login_attempts` | Bounded login abuse protection stores email hash, IP, timestamp, and success flag without credentials. |
| Product Master | `product_categories`, `brands`, `products` | Implemented for seeded sample products; local DB now has >=10 CPU and >=10 GPU POC records. |
| Variants/Aliases | `product_variants`, `product_aliases` | Tables exist; local CPU/GPU POC records have >=5 aliases each. |
| Sources | `data_sources` | Source policy fields now track key, enabled/paused state, collection method, reliability, evidence quality, freshness expectation, budgets, notes, and last success/failure. |
| Jobs | `collector_jobs` | DB-backed mock collection, Coverage Planner job creation, mock scheduled runner state transitions, run IDs, and attempt counts are implemented; live provider scheduling is disabled. |
| Run Summaries | `collection_runs` | Runner-level summaries persist run id, source/provider, execution mode, duration, job counts, row counts, lane counts, and error count. |
| Candidate Store | `raw_price_observations` | Existing candidate-like table. |
| Evidence | `raw_price_observations.evidence_path`; `market_evidence` | Private evidence metadata is persisted by mock pipeline. |
| Extraction | `extraction_runs` | Rule-based fixture extraction is persisted by mock and fixture-backed provider pipeline tests. |
| Validation | In-code generic and CPU/GPU rule packs; persisted via `observation_review_decisions.reason_codes`, `rule_result`, and `price_observations.quality_flags` | Deterministic validation versions and quality flags are recorded for review/snapshot safety; no separate rules table exists yet. |
| Review | `price_observations.verified_status`; `observation_review_decisions`; `review_corrections` | Admin approve/reject/exclude actions exist with audit logging; supported field-level corrections are stored as review-layer artifacts. |
| Review Calibration Analytics | Derived from `price_observations`, `observation_review_decisions`, `review_corrections`, `raw_price_observations`, `collector_jobs`, and `data_sources` | Offline/Admin analytics report lifecycle, lane outcomes, clean-Green precision, correction frequency, quality flags, source/provider yield, and fixture-vs-live readiness. No analytics table exists. |
| Offline Evidence Import | `data_sources`, `collector_jobs`, `collection_runs`, `raw_price_observations`, `market_evidence`, `extraction_runs`, `observation_review_decisions`, `price_observations` | CSV/JSON local file intake is implemented as an acquisition adapter. Dataset mode is explicit TEST/REAL; imported REAL evidence remains pending until human review. No dedicated import table exists. |
| Accepted Observations | `price_observations` with `approved` status | Approved rows use validated final reviewed values; original raw/evidence/extraction records are preserved. |
| Price Snapshots | `price_indices`, `price_histories`, `price_snapshot_observations` | Deterministic snapshot service calculates from eligible approved observations, excludes mock/test sources from public cohorts, and persists formula/cohort versions, confidence version/breakdown, hash, manifest, and included/excluded observation membership for new snapshots. |
| Community Contributions | Future conceptual `community_submissions` domain | Approved architecture only; not implemented and no migration exists. Community input is evidence/signals, not voting. |
| Deal Checks | `deal_checks` | Implemented form/result persistence. |
| Build Planner | `spec_builds`, `spec_build_items` | Implemented prototype. |
| B2B | `shops`, `shop_inventory_*` | Demo inventory groundwork. |
| Source Incidents | `source_incidents` | Auditable source operational incidents for blocked, rate-limited, auth-required, changed, repeated-failure, pause, disable, and other events. |
| Audit | `audit_logs` | Review decisions, coverage planning, scheduled runner batches/jobs, requeue, source controls, and source incidents write audit records; broad admin audit integration is still incomplete. |

Do not replace these table names just to match Blueprint terms.

Phase 3A snapshot provenance:

- `price_indices.formula_version`: deterministic price formula identifier, currently `price-engine-v1`.
- `price_indices.cohort_version`: cohort/filter identifier, currently `cohort-v1`.
- `price_indices.quartile_method_version`: percentile method identifier, currently `linear-percentile-v1`.
- `price_indices.confidence_method_version`: confidence method identifier, currently `confidence-v1`.
- `price_indices.confidence_score` and `price_indices.confidence_label`: deterministic confidence output for the snapshot.
- `price_indices.calculation_hash`: stable SHA-256 hash from formula/cohort/confidence versions, ordered included observation IDs, snapshot-time prices/dates, price type, sample size, Q1, median, Q3, and confidence inputs/output.
- `price_indices.calculation_manifest`: compact JSON summary of considered IDs, included IDs, excluded IDs/reasons, sample size, Q1, median, Q3, confidence components, source distribution, freshness metrics, evidence metrics, reason codes, weights, and policy caps.
- `price_indices.provenance_status`: `recorded` for Phase 3A+ snapshots, `legacy_unavailable` for older snapshots without membership.
- `price_snapshot_observations`: queryable per-snapshot membership records. It stores included/excluded state, deterministic exclusion reason, calculation price for included records, snapshot-time status/type/listing/source/date, and weight.

Legacy snapshots must not receive inferred observation membership.

Phase 3E snapshot confidence:

- `confidence-v1` is implemented in `SnapshotConfidenceService`.
- Component dimensions are sample size, freshness, source diversity, and evidence quality.
- Labels are stored in the existing `price_indices.confidence_label` enum: `high`, `medium`, `low`, or `insufficient`.
- Confidence is audit metadata about data quality. It never changes observed prices, accepted observations, or quartile formulas.
- Snapshot-time confidence details live in `price_indices.calculation_manifest`; no separate confidence table was added.

Phase 3B review corrections:

- `review_corrections` stores one latest correction per observation/field with original value, corrected value, reason, actor, timestamp, and optional final review decision linkage.
- Correctable fields are limited to current semantics: `product_id`, `price`, `price_type`, `listing_type`, `condition_level`, `warranty_months`, and `observed_at`.
- `price_observations.price_value` is the neutral numeric value used for non-asking price types. `asking_price` is nullable and remains populated for asking observations for backward compatibility.
- `price_observations.quality_flags` stores deterministic diagnostics such as `MISSING_PRICE`, `BUNDLE`, `WHOLE_PC`, `DEPOSIT`, `DEFECTIVE`, `DUPLICATE`, `STALE`, `WEAK_EVIDENCE`, `MISSING_URL`, and `MOCK_SOURCE`.
- Corrections never overwrite `raw_price_observations`, `market_evidence`, or `extraction_runs.extracted_data`.

Phase 3C category validation and quality metrics:

- Validation rule versions are recorded in review reason codes: `generic-validation-v1`, `cpu-validation-v1`, and `gpu-validation-v1`.
- Category quality flags include `WRONG_PRODUCT`, `VARIANT_AMBIGUOUS`, `MOBILE_GPU`, `BUNDLE`, `WHOLE_PC`, `WANTED`, `DEPOSIT`, `DEFECTIVE`, `MISSING_PRICE`, `MULTIPLE_PRICES`, `WEAK_EVIDENCE`, `DUPLICATE`, `STALE`, and contextual `BOARD_PARTNER_CONTEXT`.
- Pipeline-created pending observations persist deterministic quality flags on `price_observations.quality_flags`.
- Snapshot eligibility reads persisted quality flags and excludes critical invalid flags from normal asking-price cohorts.
- Golden validation fixtures live in `tests/fixtures/validation_golden_dataset.php`; evaluator output is produced by `cli/evaluate_validation_quality.php`.

Phase 3D Admin auth/RBAC:

- `users.role` supports `user`, `reviewer`, `operator`, and `admin`.
- `users.password_hash` stores PHP `password_hash` output only; plaintext passwords are never stored.
- `login_attempts.email_hash` stores a SHA-256 hash of normalized email for throttling and does not store raw passwords.
- `AuthService::currentUser()` returns only safe identity fields and never exposes password hashes.
- `audit_logs.user_id` is used for authenticated human review/admin actions where available.
- Test/fixture users are created inside transactions and are not seeded as default credentials.

Phase 3F review calibration analytics:

- No schema change was added.
- Analytics derive original lane from the earliest `observation_review_decisions` row per observation.
- Final outcome comes from `price_observations.verified_status`.
- Correction frequency comes from `review_corrections`.
- Source/provider quality comes from raw observation source and collector job lineage.
- MOCK/TEST segregation is deterministic from source/provider naming and keeps fixture metrics out of live KPI readiness.

Phase 3G offline evidence import:

- No schema change was added.
- `OfflineEvidenceImportService` and `OfflineFileProvider` converge CSV/JSON intake into the same provider-neutral candidate pipeline as mock/live adapters.
- Per-source import policy uses `data_sources` records keyed as `offline_{dataset}_{domain}`.
- Each imported row creates a short-lived `collector_jobs` record with job type `offline_file_import`; source/job/run provenance is retained through raw observations, evidence, extractions, reviews, and pending observations.
- Non-dry import batches persist a `collection_runs` row with provider/job type `offline_file_import`.
- Evidence excerpts include import provider, import run id, dataset classification, and file row number.
- REAL dataset classification requires explicit CLI mode and a real external reference; REAL still means unverified candidate/evidence, not accepted market truth.
- Template files under `examples/import/` are TEST DATA only. Real local evidence should stay in ignored `storage/import/` or `var/import/`.

Phase 2D-A extends the provider-neutral in-code candidate contract with optional snippet/provider/model/query/fetched metadata. No new database table was added for this metadata; private evidence excerpts preserve candidate title plus snippet where available.

Future `community_submissions` may include product/variant, feedback type, optional reported price, condition, warranty, source URL, evidence reference, submitted time, evidence score, contributor trust score, consensus score, freshness score, community confidence score, status, review metadata, and review reason.

Potential statuses: `pending`, `needs_review`, `accepted`, `rejected`, `duplicate`, `expired`.

Potential feedback types: `PRICE_LOWER`, `PRICE_HIGHER`, `PRICE_SIMILAR`, `PRODUCT_INFO_WRONG`, `VARIANT_WRONG`, `SPEC_WRONG`, `MARKET_DATA_STALE`, `LISTING_INVALID`, `CONDITION_CONTEXT`, `WARRANTY_CONTEXT`, `SOLD_PRICE_REPORT`.

Do not add this schema until the Phase 4.5 Community Data Feedback Loop milestone.
