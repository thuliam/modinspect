# Price Engine

Current implementation:

- `app/Services/PriceEngine.php` evaluates a user-entered price against existing `q1`/`q3` from `price_indices`.
- Public pages display seeded/sample price ranges and history.
- `app/Services/Pricing/PriceSnapshotService.php` calculates Q1, median, Q3, sample size, freshness ratio, and deterministic confidence from approved asking-price observations.
- `app/Services/Pricing/SnapshotConfidenceService.php` implements `confidence-v1`, a transparent data-quality score. Confidence describes evidence quality behind a snapshot; it does not change Q1/median/Q3 and is not a deal recommendation.
- Phase 2D-J excludes approved observations linked to mock/test sources from public snapshot cohorts. Mock observations may remain in Admin/review/audit records, but they must not silently become public market truth.
- Phase 3A turns new snapshots into auditable calculation artifacts. `price_indices` stores formula/cohort/quartile/confidence method versions, a deterministic calculation hash, a compact calculation manifest, and provenance status. `price_snapshot_observations` stores each considered observation with included/excluded state and exclusion reason.
- Phase 3B adds `price_value` so non-asking observations, including sold/final reports, no longer require fake `asking_price` values. Asking snapshots continue to use the asking cohort only.
- `tests/snapshot_loop_test.php` proves pending observations create no snapshot, then approved observations create reproducible Q1/median/Q3.
- `tests/snapshot_provenance_test.php` proves membership persistence, considered-vs-included records, mock exclusion, asking/sold separation, hash stability/change detection, historical reproduction, and deliberate mismatch detection.

Target rules:

- Price snapshots must be calculated deterministically from accepted observations only.
- Asking prices and sold/final prices remain separate.
- Approved corrected observations enter snapshots through their validated final reviewed values.
- Exclude deposits, bundles, whole PCs, defective/parts listings, duplicates, invalid prices, and wrong products from normal single-component cohorts.
- Exclude category-invalid quality flags such as `WRONG_PRODUCT`, `MOBILE_GPU`, `WANTED`, `WHOLE_PC`, `BUNDLE`, `DEPOSIT`, and `DEFECTIVE` from normal asking-price cohorts.
- Exclude mock/test-source observations from public market-price cohorts unless a future explicit calibration/test mode is being used outside public output.
- New snapshots must persist formula/version metadata, observation membership, confidence components, and snapshot-time confidence inputs for reproducibility. Legacy snapshots created before Phase 3A are marked `legacy_unavailable` and must not be given fabricated provenance.
- Confidence must remain deterministic, versioned, explainable, and derived from snapshot-time evidence quality. Historical confidence must not decay merely because wall-clock time passes after the snapshot was created.

Current version identifiers:

```text
formula_version: price-engine-v1
cohort_version: cohort-v1
quartile_method_version: linear-percentile-v1
confidence_method_version: confidence-v1
```

Confidence v1 components:

| Component | Weight | Meaning |
|---|---:|---|
| Sample size | 30% | More eligible independent observations improve trust, but tiny cohorts cannot become HIGH. |
| Freshness | 30% | Uses snapshot-time age metrics and the 30-day freshness window. |
| Source diversity | 25% | Rewards independent sources and penalizes single-source or dominant-source concentration. |
| Evidence quality | 15% | Uses observation/source evidence quality available at snapshot time. |

Policy caps:

- Fewer than 3 included observations: `insufficient`.
- Fewer than 5 included observations: cannot exceed LOW/MEDIUM boundary.
- Single-source cohorts cannot become HIGH.
- Highly concentrated source cohorts cannot become HIGH.
- Severely stale cohorts cannot become HIGH.
- Predominantly weak evidence cannot become HIGH.

Reason codes include `LOW_SAMPLE_SIZE`, `SUFFICIENT_SAMPLE`, `FRESH_OBSERVATIONS`, `STALE_DATA`, `SINGLE_SOURCE`, `HIGH_SOURCE_CONCENTRATION`, `GOOD_SOURCE_DIVERSITY`, `WEAK_EVIDENCE`, and policy-cap codes.

Validation rule versions that can appear in snapshot input lineage through review decisions:

```text
generic_validation_version: generic-validation-v1
cpu_validation_version: cpu-validation-v1
gpu_validation_version: gpu-validation-v1
```

Offline reproduction:

```text
php cli/reproduce_price_snapshot.php --snapshot-id=123
php cli/price_confidence_report.php --snapshot-id=123
```

Both commands are read-only. Reproduction recalculates Q1/median/Q3, confidence score/label/components, and the calculation hash from stored snapshot-time membership/manifest data, then reports `MATCH`, `MISMATCH`, `NOT_FOUND`, or `LEGACY_PROVENANCE_UNAVAILABLE`.

AI must not generate authoritative market prices.

Community feedback must not generate authoritative market prices either. Future community submissions, including `SOLD_PRICE_REPORT`, are evidence/review candidates until accepted through the controlled observation pipeline.

Locked rule:

```text
Community Submission
→ Evidence / Review
→ Accepted Observation
→ Deterministic Price Engine
→ Price Snapshot
```

No community score, consensus count, or admin-entered correction may directly update the published price index.
