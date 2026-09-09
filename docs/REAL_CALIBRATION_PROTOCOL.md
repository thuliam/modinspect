# Real Calibration Protocol

Status: Phase 3H waiting for owner-supplied REAL sample.

This protocol defines the first offline REAL calibration experiment for ModInspect. It is a data/calibration procedure, not a new product feature.

## Purpose

Use owner-supplied real-world used-PC listing evidence to measure how the existing pipeline behaves on messy market data:

```text
Owner REAL file
-> OfflineFileProvider
-> Candidate
-> Evidence
-> Extraction
-> Product Resolution
-> Validation
-> Review Lane
-> Human Review
-> Calibration Analytics
```

Imported REAL evidence is not verified truth. It remains pending until the normal review/correction workflow approves, rejects, or excludes it.

## Batch Identity

Initial batch id:

```text
real-calibration-batch-v1
```

Record after import:

- source file safe name
- import run id
- dataset mode
- products represented
- sources represented
- imported count
- reviewed count
- review date range

## Target Size

Target first batch:

```text
30-50 REAL listings
```

A smaller batch may be used only if explicitly documented as an early pilot. A small first sample must not be treated as proof of production-scale quality.

## Product Selection

Choose approximately 4-6 existing canonical products from the current Product Master. Do not create or alter Product Master records for this protocol.

Recommended existing products:

| Category | Canonical product | Why included |
|---|---|---|
| CPU | AMD Ryzen 7 5700X3D | Nearby ambiguity with 5700X / X3D suffix handling. |
| CPU | AMD Ryzen 7 5800X | Nearby ambiguity with 5800X3D mentions. |
| CPU | AMD Ryzen 5 5600 | Nearby ambiguity with 5600X / 5600G. |
| GPU | NVIDIA GeForce RTX 3070 | Nearby ambiguity with RTX 3070 Ti and mobile GPU listings. |
| GPU | NVIDIA GeForce RTX 4070 | Nearby ambiguity with RTX 4070 SUPER / Ti. |
| GPU | NVIDIA GeForce RTX 4070 Ti | Nearby ambiguity with RTX 4070 Ti SUPER and board-partner variants. |

If the actual Product Master changes, use the repository/database as source of truth.

## Sample Composition

Include real listings as naturally observed. Do not edit listing content to manufacture edge cases.

Try to include:

- normal standalone sale
- Thai text
- English/Thai mixed text
- board-partner GPU
- bundle
- whole PC
- wanted/buying post
- defective/for-parts
- deposit/partial payment
- ambiguous model
- multiple prices
- warranty context
- condition context

## File Location

Place owner-supplied local REAL files under ignored local storage:

```text
storage/import/real_calibration_batch.csv
storage/import/real_calibration_batch.json
```

`storage/import/` and `var/import/` are ignored so raw seller evidence is not committed.

Do not place raw REAL evidence under `examples/` or `tests/`.

## CSV / JSON Fields

Use the Phase 3G import contract.

Preferred fields:

```text
source_type
source_url or source_reference
title
listing_text
observed_at
```

Optional fields:

```text
displayed_price
currency
source_domain
condition_hint
warranty_hint
external_listing_id
product_hint
notes
```

Structured fields are acquisition hints only. Existing extraction, resolver, validation, review, and correction code remains authoritative.

## Privacy

Do not intentionally collect unnecessary seller personal data.

Avoid:

- phone numbers
- exact home addresses
- private messages
- profile data
- personal profile photos

If incidental personal data exists in listing text, do not promote it into structured fields or public reports.

## Pre-Import Validation

Always run dry-run first:

```text
php cli/import_market_evidence.php --file=storage/import/real_calibration_batch.csv --dataset=real --dry-run --limit=50
```

Record:

- rows scanned
- valid rows
- invalid rows
- duplicate rows
- source distribution
- product hints
- observed-date range where available
- estimated candidates
- row-level errors

If row errors exist, review them before import. Do not silently discard invalid rows.

## Import

After dry-run is acceptable:

```text
php cli/import_market_evidence.php --file=storage/import/real_calibration_batch.csv --dataset=real --limit=50
```

No bulk approval is allowed. Imported records must enter review.

## Human Review

Every imported REAL record should receive a human review decision before calibration metrics are interpreted.

Preserve:

- original lane
- original quality flags
- original extraction
- review corrections
- final decision

Allowed decisions:

- approve
- reject
- exclude

Corrections must use the Phase 3B review correction workflow and must not overwrite raw evidence or original extraction payloads.

## Metrics

After review, run:

```text
php cli/review_calibration_report.php --provider=offline_file_import
php cli/evaluate_validation_quality.php
```

Report:

- REAL sample count
- products represented
- sources represented
- original Green / Amber / Red
- approved / rejected / excluded
- clean-Green precision
- Green approved after correction
- correction rate
- valid observation yield
- critical invalid false-Green
- top quality flags
- top corrected fields
- source/provider quality

Where human review establishes ground truth, also report:

- product resolution accuracy
- price extraction accuracy
- listing-type accuracy
- condition accuracy where evidence is sufficient

Do not count unknown or unreviewable fields as automatically wrong.

## Failure Taxonomy

Classify primary mismatch layers as:

```text
ACQUISITION
EXTRACTION
PRODUCT_RESOLUTION
CATEGORY_VALIDATION
DUPLICATE
EVIDENCE_QUALITY
REVIEW_POLICY
```

Avoid vague labels such as "AI mistake".

## Golden vs REAL Comparison

Compare the reviewed REAL sample against the deterministic Golden Dataset:

- product resolution
- Green precision
- critical invalid false-Green

Current Golden Dataset baseline:

- fixture count: 101
- CPU resolution: 100%
- GPU resolution: 100%
- overall classification accuracy: 100%
- Green coverage: 54.46%
- Green precision: 100%
- critical invalid false-Green: 0%

These fixture metrics are not proof of live-market quality.

## Status Progression

Current persistent state:

```text
FIXTURE_BASELINE
```

After imported but insufficiently reviewed REAL records:

```text
LIVE_SAMPLE_INSUFFICIENT
```

After enough reviewed REAL records for meaningful calibration:

```text
CALIBRATING
```

Do not mark `READY_FOR_SCALE` from the first 30-50 record batch unless existing conservative policy explicitly supports it.

## Reporting

Create `docs/REAL_CALIBRATION_REPORT.md` only after reviewed REAL records exist.

The report should include:

- batch id
- sample design
- products
- sources
- date range
- review counts
- metrics
- mismatch taxonomy
- notable failure patterns
- Golden vs REAL comparison
- recommended calibration changes
- confidence limitations

Do not include unnecessary seller PII or raw seller evidence in the report.

## Rule-Change Gate

Do not change validation rules because of one failed sample.

First collect mismatch statistics. If a deterministic bug affects multiple records, document the proposed fix and test it separately. Avoid overfitting to the first REAL batch.
