# Real Calibration Protocol

Status: Phase 3H-R1B listing-level provenance hardening active.

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

REAL does not automatically mean authoritative. Public-price eligibility requires sufficient observation provenance: the observation must be traceable to the exact listing/result item, exact title, exact captured price, source, and capture timestamp.

Authoritative REAL market evidence also requires immutable capture-time evidence. For Priceza this means URL + item ID + timestamp is not enough; ModInspect must preserve a compact immutable evidence snapshot and hash proving what the exact public result item displayed at capture time.

## Provenance Quality Levels

Use these levels for REAL market observations:

```text
LISTING_LEVEL
```

- Exact source listing URL, Priceza result URL, or exact source/result-item identifier is available.
- Stored title and captured price are traceable to that item.
- Immutable evidence snapshot and evidence hash are stored.
- Eligible for human approval into authoritative market observations, subject to all normal validation and review rules.

```text
SEARCH_RESULT_LEVEL
```

- Only a search/results page is available.
- The exact listing/result item cannot be independently reconstructed from stored evidence.
- May be used for pipeline, resolver, validation, and UI calibration only.
- Must not be used to create public market-price snapshots.

```text
GENERIC_SOURCE
```

- Source is known, but the observation cannot be independently traced to a listing/result item.
- Not eligible for public market-price snapshots.

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

Preferred fields for authoritative REAL intake:

```text
source
source_search_url
source_listing_url
source_item_id
external_ref
merchant
merchant_target_url
listing_title
listing_text
asking_price
observed_at
evidence_snapshot_json
evidence_hash
ingestion_note
```

Optional fields:

```text
source_type
source_url
source_reference
title
displayed_price
currency
source_domain
condition_hint
warranty_hint
external_listing_id
notes
```

Structured fields are acquisition hints only. Existing extraction, resolver, validation, review, and correction code remains authoritative.

`listing_title` or legacy `title` must contain the actual public listing title only. Do not concatenate importer provenance notes, privacy notes, merchant labels, or collection comments into the title field.

`listing_text` should contain public listing description text when it exists. If the source only provides a title, leave `listing_text` empty and use `merchant`, `ingestion_note`, `source_url`, `source_reference`, and `notes` for non-title metadata.

Known ModInspect-generated importer/privacy notes are normalized out of Admin display titles and are not appended to future offline-import candidate titles.

For future REAL imports, search-page-only rows are not sufficient for authoritative calibration. A row intended for public pricing must include `source_listing_url`, `source_item_id`, `external_ref`, `external_listing_id`, or another exact item/result reference. Do not fabricate these values from a later live search page.

For future Priceza imports intended for public pricing, every row must include:

- `source=priceza`
- `source_search_url`
- `source_listing_url=https://www.priceza.com/r/redirect?id=<source_item_id>`
- `source_item_id`
- `merchant`
- `listing_title`
- `asking_price`
- `observed_at`
- `merchant_target_url` when publicly obtainable without bypass
- `evidence_snapshot_json` preserving the visible result-item fields at capture time
- `evidence_hash` as the SHA-256 hash of that immutable snapshot

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

After enough reviewed REAL records with LISTING_LEVEL provenance for meaningful calibration:

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

## Priceza Authoritative Probe Selection

Rows intended to become authoritative Priceza LISTING_LEVEL observations must pass selection before capture/import:

- Result card must expose stable `data-productid` / source item ID.
- Result card must expose visible title, visible price, visible merchant, item-specific `/r/redirect?id=<id>`, and merchant target URL when safely obtainable through normal public access.
- The visible title must contain the exact target model marker for the intended Product Master.
- The visible title must not contain another same-category model marker, nearby variant, or mixed variant context.
- Reject whole-PC, bundle, accessory, wanted/buying, deposit/installment-only, defective/parts, laptop/mobile GPU, malformed, incomplete, and duplicate item IDs.
- The selected result must resolve to the intended Product Master using the existing deterministic extractor, ProductResolver, and ObservationValidator.

Do not weaken LISTING_LEVEL provenance to increase pass rate. If the selected item is ambiguous or fails product resolution/validation, skip it before capture or let the pipeline fail closed. Do not promote a Red/non-observation raw row into authoritative evidence.

Current verified small-probe gate:

- The first 8-row strengthened probe was PARTIAL because two rows were bad source item selections: Ryzen 5 5600G for Ryzen 5 5600, and RTX 4070 Super for RTX 4070.
- The follow-up 8-row probe used tightened selection and produced 8 LISTING_LEVEL pending observations, 0 SEARCH_RESULT_LEVEL, and 0 GENERIC_SOURCE.

## First Authoritative Batch Gate

The first authoritative REAL batch may enter human review only after:

- Dry run reports only valid REAL rows.
- Every imported row is LISTING_LEVEL.
- Every imported row resolves to the intended Product Master.
- Every imported row remains pending human review.
- SOLD count is zero unless explicit completed-sale evidence is present.
- Public price snapshots are not recalculated during collection/import.

The Admin Review Queue supports the operational filter:

```text
dataset=REAL
status=pending
provenance=LISTING_LEVEL
```

Use this filter for the first authoritative review pass. Review priority remains Amber first, then Green; no batch auto-approval is allowed.

## Admin Import Workflow

Admin Imports is the owner-facing feed-data entrypoint for offline CSV/JSON evidence files.

The workflow is:

1. Download the CSV or JSON template from Admin Imports.
2. Prepare a file from owner/manual collection, supported public-source collection output, or collector-generated/exported evidence.
3. Upload the file.
4. Choose TEST/MOCK or REAL.
5. Run Dry Run.
6. Review total rows, eligible rows, invalid rows, duplicates, provenance/source-policy failures, resolver hints, validation lanes, and row-level reasons.
7. Confirm Import only after the Dry Run summary is acceptable.

Confirm Import must use the exact file/hash/dataset/limit context from the reviewed Dry Run. A changed or missing file requires a new Dry Run.

The template is an input format, not evidence. Placeholder rows such as `EXAMPLE-ITEM-001` or `template_test` are not business observations. REAL rows must remain traceable evidence and still enter Human Review; REAL import never means auto-approved or public-priced.

Minimum implemented REAL import contract:

- `observed_at` is required.
- A visible title/text is required through `listing_title`, `title`, or `listing_text`.
- REAL rows require listing-level identity through a source item/reference field, exact listing URL, or non-search source URL.
- Authoritative REAL rows should include source, search URL where applicable, listing URL, source item ID, visible merchant, visible listing title, asking price, observed timestamp, immutable evidence snapshot, and evidence hash.
