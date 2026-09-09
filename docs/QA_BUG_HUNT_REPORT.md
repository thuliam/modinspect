# Phase 2D-J QA Bug Hunt Report

Date: 2026-09-09

Scope: hostile regression testing of already-implemented local prototype behavior. No live provider request, paid API, browser automation, or network acquisition was used.

## Issues

| ID | Severity | Component | Reproduction | Root cause | Fix | Regression test | Status |
|---|---|---|---|---|---|---|---|
| 2DJ-001 | HIGH | Admin review queue | Approve a pending observation, then submit approve/reject/exclude again for the same observation ID | `Dashboard::decideObservation()` updated by ID without requiring the current state to remain `pending` | Review decisions now require current `verified_status='pending'` and use a conditional update before audit/review rows are inserted | `tests/stability_bug_hunt_test.php` duplicate approval and reject-after-approve checks | Fixed |
| 2DJ-002 | CRITICAL | Price snapshot/public market truth | Approve three observations from a mock source, then recalculate a price snapshot | Snapshot cohort accepted all approved observations regardless of mock/test source provenance | Snapshot cohort and public accepted-count query now exclude observations linked to mock sources while keeping them auditable | `tests/stability_bug_hunt_test.php` mock-contamination check | Fixed |
| 2DJ-003 | MEDIUM | Parallel test execution | Run DB-heavy PHP test scripts in parallel against the same local MySQL database | Tests share one mutable database and several fixed fixture IDs; long transactions can lock `collector_jobs`, `data_sources`, `collection_runs`, `raw_price_observations`, and related pipeline tables in conflicting order | Production claim logic reviewed; added atomic-claim regression. Test isolation remains sequential for DB-heavy plain PHP scripts until isolated DBs/fixture IDs are introduced | `tests/stability_bug_hunt_test.php` conditional claim check | Documented |
| 2DJ-004 | LOW | Data integrity observability | No single command existed to report orphan/provenance drift across product, job, evidence, extraction, review, observation, and snapshot rows | Integrity checks were distributed across tests and manual SQL | Added read-only `cli/data_integrity_check.php` | CLI run returns `ok: true` with zero problems on current DB | Fixed |
| 2DJ-005 | LOW | Gap documentation | Gap analysis still said source health had no Admin dashboard after Phase 2D-I wired it | Documentation stale after UI synchronization | Updated gap text to reflect current Admin source-health wiring and remaining incident-resolution gap | Documentation review | Fixed |

## Deadlock Investigation

The previous MySQL deadlock was not reproduced in sequential execution. The most plausible root cause is test isolation, not the normal runtime job-claim path:

- Plain PHP integration tests share the same local database instead of per-test databases.
- Several tests use fixed fixture source/job IDs and wrap broad setup/pipeline assertions in transactions.
- Parallel execution can cause InnoDB lock conflicts while different tests insert/update `data_sources`, `collector_jobs`, `collection_runs`, `raw_price_observations`, `market_evidence`, `extraction_runs`, `observation_review_decisions`, and `price_observations`.
- Runtime runner claiming uses a short conditional update: `WHERE id=:id AND status='queued'`. A regression test proves only one claim can win and `attempt_count` remains correct.

Current guidance: run DB-heavy script tests sequentially unless they are moved to isolated databases or rewritten to allocate fully unique fixtures per worker.

## Remaining Gaps

- Admin remains local-prototype only; production authentication/authorization is still required before public exposure.
- Price snapshot provenance does not yet persist the exact accepted observation IDs used for each snapshot.
- Field-level review correction is intentionally not implemented.
- Incident resolution UI is not implemented.
- Live Gemini execution remains blocked by missing credentials and requires explicit approval.
