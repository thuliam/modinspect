# Collection

Collection is data-first and provider-independent.

Allowed current mode:

- Mock provider.
- Local fixtures.
- Dry-run CLI.
- DB-backed mock CLI persistence.
- Deterministic extraction/resolution/validation tests.

Disabled until explicit approval:

- Paid APIs.
- Live web search/collection.
- Automated crawling of third-party marketplaces.
- Any bypass of source protections.

Pipeline contract:

`provider -> candidate -> evidence -> extraction -> product resolution -> validation lane -> review state`

DB-backed mock command:

```text
php cli/collect.php --mock --query=5700x3d
```

The command is idempotent by source/reference hash. Duplicate raw candidates do not create duplicate evidence, extraction, review, or observation rows.

Coverage planning:

```text
php cli/coverage.php --dry-run --limit=5
php cli/coverage.php --dry-run --summary --limit=5
php cli/coverage.php --create-jobs --limit=25
php cli/coverage.php --create-jobs --limit=25 --attempt-cooldown-minutes=60
```

The Coverage Planner scans active CPU/GPU products and reports accepted sample count, fresh sample count, target count, sample gap, freshness gap, last accepted observation, last collection attempt, queued/running jobs, recent failures, due state, priority, and due reasons. Job creation uses the existing `collector_jobs` table and prevents duplicate queued/running `coverage_mock_collection` jobs for the same product and mock source. The scheduler-facing default also applies a recent-attempt cooldown so a just-run product is not immediately queued again.

Future Coverage Planner input:

- Community anomaly signals from the approved Community Data Feedback Loop.
- Example: multiple recent evidence-backed reports that a product price appears lower/higher than the current snapshot may increase collection priority.
- These reports are priority signals only; they do not directly become accepted observations or price snapshots.

Scheduled mock runner:

```text
php cli/run_collection_jobs.php --dry-run --limit=5
php cli/run_collection_jobs.php --limit=5
php cli/run_collection_jobs.php --job-id=123
```

The runner consumes queued `coverage_mock_collection` jobs for the mock fixture source only. It claims each job with a conditional `queued -> running` update, then sends the product-scoped query through the existing mock provider, candidate persistence, evidence, rule extraction, product resolution, validation, and review-lane pipeline. It does not insert accepted observations directly and does not auto-approve Green records during calibration.

Job state model:

```text
queued -> running -> completed
queued -> running -> failed
```

Completed jobs are never reprocessed by the runner. Failed jobs remain `failed` until explicitly requeued. A failure in one job is recorded and does not stop later jobs in the same bounded run.

Runner audit:

- Per-job completion/failure writes `audit_logs`.
- Each non-dry-run batch writes a compact row to `collection_runs` and a `collection_runner_run` audit record with run id, counts, lane distribution, job results, and duration.
- Result summaries are also stored on `collector_jobs.error_message` for the prototype; per-job provenance remains on candidate/evidence/extraction/review rows.

Failed-job requeue:

```text
php cli/requeue_collection_job.php --job-id=123 --dry-run
php cli/requeue_collection_job.php --job-id=123
```

Only failed jobs can be requeued by default. Requeue clears `run_id`, `started_at`, `completed_at`, `raw_count`, `valid_count`, and `error_message`, returns the job to `queued`, and preserves `attempt_count`. It does not delete prior candidates, evidence, extraction runs, reviews, or observations.

Source operations:

```text
php cli/source_control.php --source-id=1 --action=pause --reason="manual review"
php cli/source_control.php --source-id=1 --action=disable --reason="source no longer allowed"
php cli/source_control.php --source-id=1 --action=incident --incident-type=blocked --reason="blocked by source"
php cli/source_health.php --source-id=1
```

Source policy lives on `data_sources`: source key, enabled/paused state, allowed collection method, reliability score, evidence quality, freshness expectation, request/cost budgets, policy/risk notes, last success, and last failure.

Source incidents live in `source_incidents`. Supported operational incident types include blocked, rate limited, authentication required, source structure changed, repeated extraction failure, manually paused, and manually disabled. The system records incidents and pauses/disables sources; it does not implement bypass or evasion behavior.

Source health rules:

- `paused`: source disabled or paused.
- `unknown`: enabled source with no completed/failed attempts.
- `degraded`: at least 2 consecutive failed jobs, or failed attempts above 25%.
- `healthy`: enabled, not paused, attempted, and below degraded thresholds.

Scheduler-ready commands:

```text
php cli/coverage.php --create-jobs --limit=25 --attempt-cooldown-minutes=60
php cli/run_collection_jobs.php --limit=10
php cli/source_health.php
```

The runner is bounded and not a daemon. External schedulers should invoke these commands periodically. The database-backed job claim prevents duplicate processing when overlapping invocations race for the same queued job.

Stability checks:

```text
php cli/data_integrity_check.php
```

The integrity checker is read-only. It reports orphan/provenance drift across products, aliases, jobs, raw candidates, evidence, extraction runs, reviews, observations, snapshots, and mock-source approved-observation risk. It does not repair data automatically.

Live provider POC infrastructure:

```text
php cli/live_collection_poc.php --provider=gemini --product-id=1 --max-requests=3 --dry-run
php cli/live_collection_poc.php --provider=gemini --product-id=1 --max-requests=3 --preflight
```

Phase 2D-A adds provider infrastructure only. The Gemini adapter, provider registry, deterministic query planner, and request guard exist, but default configuration prevents live execution. Do not run non-dry live collection until Phase 2D-B receives explicit approval.

Hard request guard blockers:

- `LIVE_COLLECTION_DISABLED`
- `PROVIDER_DISABLED`
- `SOURCE_DISABLED`
- `SOURCE_PAUSED`
- `MISSING_API_KEY`
- `REQUEST_LIMIT_REACHED`
- `PRODUCT_LIMIT_REACHED`
- `PAID_CALL_BLOCKED`

Gemini POC query generation is deterministic and versioned as `live_query_v1`. For each product it uses canonical product name, top alias, model name, Thai used-market intent, and a Thailand used-market query. Provider output is mapped to the existing `SearchCandidate` contract and then enters the same candidate, evidence, extraction, product resolution, validation, and review-lane pipeline. Live Green records remain pending/review-gated; there is no auto-accept.

Provider operational failures map into source incidents where possible:

- `RATE_LIMITED` -> `rate_limited`
- `AUTHENTICATION_FAILURE` / `MISSING_API_KEY` -> `authentication_required`
- `MALFORMED_RESPONSE` -> `source_structure_changed`
- other provider failures -> `other`

Validation lanes:

- Green: high-confidence and rule-clean, but still review-gated during calibration.
- Amber: ambiguous or weak evidence; human review required.
- Red: rejected/quarantined for wanted, deposit-only, whole-PC, defective, duplicate, invalid price, or wrong product cases.

Category-specific validation:

- Generic validation remains separate from category rule packs.
- Current validation versions are `generic-validation-v1`, `cpu-validation-v1`, and `gpu-validation-v1`.
- CPU rules are suffix-aware for nearby models such as `5700X` vs `5700X3D`, `5800X` vs `5800X3D`, `5600` vs `5600G`/`5600X`, and Intel suffixes such as `F`, `K`, and `KF`.
- GPU rules are suffix-aware for nearby desktop models such as `RTX 3070` vs `RTX 3070 Ti`, `RTX 4070` vs `RTX 4070 SUPER`/`4070 Ti`, and Radeon `XT`/`XTX` suffixes.
- Desktop GPU collection flags laptop/mobile GPU listings as `MOBILE_GPU`.
- Board-partner context such as ASUS TUF, ROG Strix, MSI Gaming X, Gigabyte Gaming OC, ZOTAC, and GALAX is preserved as context and does not by itself force Amber.
- Explicit bundle, whole-PC, wanted/buying, deposit/partial-payment, defective/for-parts, missing-price, multiple-price, weak-evidence, and wrong-product cases produce deterministic quality flags.

Offline validation quality evaluation:

```text
php cli/evaluate_validation_quality.php
```

The evaluator runs the CPU/GPU golden fixture dataset through the extractor, resolver, category validators, and lane mapper. It reports CPU/GPU product-resolution accuracy, overall classification accuracy, Green coverage, Green precision, critical invalid false-Green rate, wrong-nearby false-match rate, and per-rule trigger counts. Tests and evaluator fixtures are mock/test data only.

Review correction workflow:

- Reviewers may correct only supported observation fields before final decision: product, price, price type, listing type, condition, warranty months, and observed date.
- Each correction requires a reason and is stored in `review_corrections`.
- Approval applies validated final reviewed values to `price_observations`; reject/exclude decisions do not create accepted market observations.

Review calibration analytics:

- `cli/review_calibration_report.php` reports review lifecycle, lane outcomes, clean-Green precision, correction rates, quality-flag frequency, corrected-field frequency, source/provider yield, and calibration readiness.
- Admin exposes the same report at `/admin/review-analytics` for roles with `review.analytics.view`.
- Valid observation yield is defined as real approved eligible observations divided by real reviewed acquisition candidates.
- MOCK/TEST rows are labeled and excluded from live KPI readiness.
- Fixture/mock evaluation establishes an engineering baseline only; it must not be represented as live-market quality.
- Analytics do not auto-accept Green records, tune rules automatically, or create bulk approval actions.
- Original candidate, evidence, and extraction payloads remain unchanged.
- Mock/test source provenance cannot be corrected into real-source provenance; mock observations stay flagged and excluded from public snapshot cohorts.

Metric terminology:

- Golden Dataset Green precision comes from `cli/evaluate_validation_quality.php`. It measures deterministic validator output against curated CPU/GPU fixture expectations before human review.
- Review Calibration clean-Green precision comes from `cli/review_calibration_report.php`. It measures reviewed acquisition outcomes: Green records approved without material correction divided by reviewed Green records.
- These metrics intentionally measure different populations. Do not change either formula merely to make the numbers match.

Offline evidence import:

```text
php cli/import_market_evidence.php --file=examples/import/market_evidence_template.json --dataset=test --dry-run
php cli/import_market_evidence.php --file=storage/import/real_sample.csv --dataset=real --limit=100
```

Phase 3G adds a provider-neutral offline file adapter for CSV/JSON evidence intake. This is an acquisition adapter, not a trusted-observation importer:

`local file -> OfflineFileProvider -> candidate -> evidence -> extraction -> product resolution -> validation lane -> review state`

The import service never creates accepted observations, review decisions, or snapshots directly. Imported REAL rows remain pending/reviewable until the normal review/correction workflow approves, rejects, or excludes them.

Supported formats:

- JSON: either an array of records or an object with a `records` array.
- CSV: header row required.

Supported import fields:

- Required or strongly preferred: `source_type`, `source_url` or `source_reference`, `title`, `listing_text`, `observed_at`.
- Optional: `displayed_price`, `currency`, `source_domain`, `seller_type`, `condition_hint`, `warranty_hint`, `external_listing_id`, `product_hint`, `notes`.

Structured fields from the file are acquisition hints only. Existing deterministic extraction, resolver, validation, review, and correction code remains authoritative.

Dataset classification:

- Default dataset is `TEST`.
- `--dataset=real` is required for REAL classification.
- REAL rows require an external URL/reference, must not use mock/test/fixture sources, and still enter human review.
- TEST rows and templates are clearly fixture data and cannot be treated as production-quality market truth.

Dry-run behavior:

- Parses and validates rows.
- Reports records scanned, valid/invalid rows, duplicate rows, source distribution, product hints, and estimated candidate count.
- Performs no database mutation.

Duplicate protection:

- Re-imported rows are skipped using deterministic source key plus raw candidate hash derived from source, URL/reference, title/text, and price text.
- Duplicate imports report skips instead of duplicating candidates/evidence/extractions/reviews.

Import provenance:

- Provider is recorded as `offline_file_import`.
- Evidence excerpts include import run id, dataset classification, and row number.
- Candidate metadata retains source reference/domain and row index.
- Import batch summaries are persisted through `collection_runs` for non-dry runs.

Real evidence storage guidance:

- Keep real local samples outside committed fixtures, preferably under ignored `storage/import/` or `var/import/`.
- Do not commit actual seller data or unnecessary personal information.
