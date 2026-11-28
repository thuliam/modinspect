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
confidence_method_version: confidence-v3
```

Version history: `confidence-v2` (DL-033) caps evidence that is entirely owner text. `confidence-v3` (DL-122) counts one platform as one source. Stored snapshots keep the version they were calculated with and still reproduce.

## Public publication rule (DL-122)

- One rule, `App\Services\Pricing\PublicSnapshot`: the latest recorded asking snapshot is public only while every included observation is still approved, on the same model, at the same price. Otherwise the model shows no price until a new snapshot is calculated. There is no fallback to an older snapshot.
- Recalculation still needs at least 3 eligible observations; below that no new snapshot is written and the withdrawn one stays hidden.
- Source identity (`SourceIdentity`): the platform counts, not the import batch or host spelling. All Facebook hosts and Facebook owner-text batches are one source.
- Freshness wording on public pages is evaluated for the day of viewing (`PublicFreshness`). The stored score, label and prices are not changed by the passage of time.

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

## New-price reference sources (DL-077, 2026-11-17)

A row from a source listed in `App\Services\Pricing\NewPriceReferenceSources::KEYS` (today `offline_real_priceza_com`) is excluded from the used asking cohort with the reason `NEW_PRICE_REFERENCE_SOURCE`, even when approved, even when its provenance is listing level, even when its `price_type` is `asking`. It stays stored as evidence. It therefore cannot move a used price, enter the median or lift the owner-text-only confidence cap. The rule runs after the duplicate and mock checks and before the provenance check; no existing snapshot changes because no Priceza row is approved.

## Human-recorded evidence and capture method (DL-079, 2026-11-17)

`capture_method` on an import record says who made the evidence. `owner_transcription`: the importer freezes the owner's row (canonical JSON + SHA-256, evidence typed `snippet`, evidence level 3); provenance counts it as listing level only for allow-listed marketplaces (`CaptureMethod::OWNER_DOMAINS`) and never for a personal profile link. `machine_capture` (agent route) must bring its own snapshot and hash. `SnapshotConfidenceService` adds the cap `HUMAN_RECORDED_EVIDENCE_CAP` (79) when every included row is owner text or an owner transcription; one machine capture in the sample removes it. `OWNER_TEXT_ONLY_CAP` (59) is unchanged. The method version stays `confidence-v2` because the cap does not affect any data without the marker (no existing snapshot changes).

## Deal Checker verdict tiers and the "why" (DL-111, 2026-11-19)

The stored result stays one of `insufficient`, `below_range`, `in_range`, `above_range` (a database ENUM, so no migration). Until LS-20 every price above the top of the range was one bucket and the page always called it "slightly above" (ราคา ฿9,200 against a ฿1,970 to ฿2,240 range included). `App\Services\PriceEngine::tier()` now adds a severity tier for the two outside cases: how far the price is outside the edge it crossed, as a share of that edge.

| Tier | Distance outside the range | Wording (above / below) |
|---|---|---|
| slight | up to 15 % | สูงกว่า / ต่ำกว่าช่วงที่พบเล็กน้อย |
| clear | over 15 % up to 50 % | สูงกว่า / ต่ำกว่าช่วงที่พบ |
| far | over 50 % | สูงกว่า / ต่ำกว่าช่วงที่พบมาก |

The thresholds are presentation thresholds (constants `PriceEngine::SLIGHT` and `FAR`), not a valuation. `App\Services\DealExplanation` builds the "ที่มาของผลนี้" block from the saved deal row, so old saved results get it too:

- the gap from the median and from the crossed edge, in baht and percent (for example +฿7,010 (+320%) and +฿6,960 (+311%));
- the factors the visitor entered (receipt, warranty months, condition, box, test result), split into what exists and what is missing, worded for the direction: above the range they are "factors that may support a price above the market" and what is missing is "ask for this evidence"; below the range the missing items are "may be the reason the price is low";
- a statement that the factors are not turned into money. The system has no data that values a receipt, a warranty or a condition, so for a far gap it does not say the difference is reasonable; it points at the two usual checks (is it the right model and size, what does the seller say).

It never changes the verdict label, never reads or writes a price snapshot, and uses neutral wording only (`tests/deal_explanation_test.php` also checks the earlier banned words).
