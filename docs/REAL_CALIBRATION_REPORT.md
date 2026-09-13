# REAL Calibration Report

## Phase 3H-R1 First REAL Market Calibration Batch

Status: `PARTIAL - IMPORTED / WAITING_FOR_HUMAN_REVIEW_AND_ADMIN_ACCESS`

Updated: 2026-09-12 ICT

## Scope

- Objective: get the first traceable REAL market evidence into the existing offline import and human-review pipeline.
- Architecture path used: `REAL market source -> Evidence -> Extraction -> Product Resolution -> Validation -> Review Queue`.
- Price Engine changes: none.
- Snapshot generation: not run, because no REAL observation has been human-approved yet.
- External paid/API provider usage: none. Gemini/live providers remained disabled and unused.
- New external spend: 0 THB.

## Products Sampled

| Product ID | Product | REAL observations pending review |
|---:|---|---:|
| 76 | AMD Radeon RX 6600 XT | 2 |
| 20 | AMD Ryzen 5 5600 | 5 |
| 1 | AMD Ryzen 7 5700X3D | 6 |
| 71 | NVIDIA GeForce RTX 3060 Ti | 9 |
| 2 | NVIDIA GeForce RTX 3070 | 10 |
| 74 | NVIDIA GeForce RTX 4070 | 10 |

## Source

| Source key | Domain | Method | Rows |
|---|---|---|---:|
| `offline_real_priceza_com` | `priceza.com` | public search-page evidence manually captured into offline CSV | 42 |

Source pages:

- `https://www.priceza.com/s/ราคา/rtx-3060-ti-มือสอง`
- `https://www.priceza.com/s/ราคา/rtx-3070-มือสอง`
- `https://www.priceza.com/s/ราคา/rtx-4070-มือสอง`
- `https://www.priceza.com/s/ราคา/ryzen-5-5600-มือสอง`
- `https://www.priceza.com/s/ราคา/ryzen-7-5700x3d-มือสอง`
- `https://www.priceza.com/s/ราคา/rx-6600-xt-มือสอง`

Skipped sources:

- Facebook public/search results: skipped because access presented login/blocking friction; no bypass attempted.
- Shopee direct listing pages: skipped as primary source because Priceza public search pages already exposed the relevant listing title/price/reference and direct pages were noisy/login-adjacent.

## Import File

- Path: `storage/import/real_calibration_batch.csv`
- Git policy: under ignored raw import storage; do not commit raw seller-sensitive evidence unless owner explicitly approves.
- PII minimization: no phone numbers, exact seller addresses, private chat content, profile photos, or unnecessary personal identifiers stored.
- Unknown condition/warranty values were left blank unless the visible title carried that information.

## Import Results

Dry run:

- Command: `php cli/import_market_evidence.php --file=storage/import/real_calibration_batch.csv --dataset=real --dry-run --limit=50`
- Records scanned: 42
- Valid rows: 42
- Invalid rows: 0
- Duplicate rows: 0
- Source-policy blocked: 0
- Estimated candidates: 42

Actual import:

- Command: `php cli/import_market_evidence.php --file=storage/import/real_calibration_batch.csv --dataset=real --limit=50`
- Run ID: `offline-import-20260912205830-4610c685`
- Candidates created: 42
- Evidence created: 42
- Extractions created: 42
- Review decisions created: 42
- Price observations created: 42
- Validation lanes: 37 green, 5 amber, 0 red
- Review decision state: 42 `review_required`

Duplicate/idempotency check after import:

- Post-import dry run records scanned: 42
- Duplicate rows: 42
- Estimated candidates: 0
- New rows that would be created: 0

## Review / Snapshot State

- REAL rows in review queue: 42
- Approved REAL observations: 0
- Corrected REAL observations: 0
- Rejected/excluded REAL observations: 0
- Accepted REAL observations: 0
- MOCK/TEST rows promoted to REAL: 0
- REAL rows auto-approved: 0
- Public-eligible REAL snapshots created: 0
- Existing price snapshots remain legacy/unreviewed relative to this batch.

## Validation And Quality

Review calibration CLI result for `--provider=offline_file_import`:

- Dataset label: REAL
- Sample size: 42
- Mock/test records: 0
- Pending reviews: 42
- Green lane: 37 pending
- Amber lane: 5 pending
- Top quality flags:
  - `BOARD_PARTNER_CONTEXT`: 8
  - `LOW_CONFIDENCE`: 3
  - `VARIANT_AMBIGUOUS`: 2

Limitations:

- This is a first calibration sample, not evidence of production accuracy.
- RX 6600 XT had only 2 clean public rows from the safe source used in this run.
- Some rows need human judgment for marketplace title quality, outlier pricing, warranty claims, and variant ambiguity.
- Review analytics still cannot compute live precision/yield until a human approves/rejects/corrects rows.
- `cli/review_calibration_report.php` still reports `FIXTURE_BASELINE` while all rows are pending; this should be interpreted as no reviewed live sample yet, not as mock contamination.

## Title / Provenance Quality Gate

Read-only audit date: 2026-09-13 ICT.

Scope:

- 42 imported REAL pending observations from `offline_real_priceza_com`.
- No review decisions, corrections, approvals, rejects, excludes, snapshot recalculation, or destructive Cloud DEV operation was executed.

Findings:

- REAL rows audited: 42
- Raw imported titles containing ModInspect-generated ingestion/privacy metadata: 42
- Clean raw imported titles: 0
- Affected displayed queue/detail titles before read-model normalization: 42
- Affected extracted product/title values before read-model normalization: 42
- Affected products: AMD Radeon RX 6600 XT, AMD Ryzen 5 5600, AMD Ryzen 7 5700X3D, NVIDIA GeForce RTX 3060 Ti, NVIDIA GeForce RTX 3070, NVIDIA GeForce RTX 4070

Root cause:

- `storage/import/real_calibration_batch.csv` had clean listing text in the `title` column.
- The CSV `listing_text` column contained ModInspect-generated source/privacy notes such as `Priceza public search result`, `merchant=`, `public listing title captured only`, and `no phone profile photo private chat or exact address stored`.
- `OfflineEvidenceImportService::candidateFromRecord()` concatenated `title` and `listing_text` into the pipeline candidate title.
- The extraction layer copied that candidate title into extracted raw title data.
- The Admin read model previously displayed the stored raw title directly; it did not create the contamination.

Fix applied:

- Raw evidence and raw imported titles remain unchanged and available under Advanced / Technical Details.
- Review Queue and Review Detail now display a deterministic normalized listing title that removes only known ModInspect-generated importer/privacy metadata markers.
- Future offline import candidate titles normalize `title` and `listing_text` separately, so ingestion/provenance notes are not appended to the candidate title.
- Future import templates now keep `title`, `listing_text`, `merchant`, and `ingestion_note` separate.
- Duplicate re-import protection still recognizes the legacy first-batch raw title+listing_text hash as well as the normalized title hash.

Source provenance audit:

- Listing-specific merchant/product URL count: 0
- Priceza search-page URL count: 42
- Generic/insufficient URL count: 0
- Do not describe this batch as listing-page provenance; it is public Priceza search-result evidence with visible title/price/source references.

Current quality profile:

- Unique external reference hashes: 42
- Duplicate count: 0
- Price type: 42 asking/listing rows, 0 sold rows
- Rows per product: NVIDIA GeForce RTX 3070 = 10, NVIDIA GeForce RTX 4070 = 10, NVIDIA GeForce RTX 3060 Ti = 9, AMD Ryzen 7 5700X3D = 6, AMD Ryzen 5 5600 = 5, AMD Radeon RX 6600 XT = 2
- Validation lanes: 37 green, 5 amber, 0 red
- Missing condition: 40
- Missing warranty: 42
- Existing deterministic outlier flag: no dedicated suspicious-price/outlier rule is persisted for this batch; use existing validation lane and warning details during human review.

## Admin Readiness

Read-only Cloud DEV auth inspection:

- Users: 0
- Active users: 0
- Enabled admin users: 0
- Safe login identifier available: none

Secure STUDIO admin bootstrap command, after choosing an owner email and strong password:

```bat
cd /d C:\laragon\www\modinspect
set MODINSPECT_ADMIN_PASSWORD=<owner-chosen-strong-password>
php cli\create_admin_user.php --email=<owner-email> --name="ModInspect Owner" --role=admin --password-env=MODINSPECT_ADMIN_PASSWORD
set MODINSPECT_ADMIN_PASSWORD=
```

Runtime note:

- Client HTTP check on 2026-09-12 returned `HTTP/1.0 500 Internal Server Error` for `/admin/review-queue`.
- The response identified `Apache/2.4.54` and `PHP/7.4.30`, which conflicts with the expected STUDIO runtime of Apache 2.4.68 / PHP 8.3.33 and the repo minimum PHP 8.1.
- HTTPS/443 was not changed.

## Phase 3H-R1B Listing-Level Provenance Hardening

Read-only audit date: 2026-09-13 ICT.

Decision:

- Do not approve any of the 42 first-batch REAL rows into authoritative market observations.
- Do not generate public price snapshots from them.
- Keep the batch as `REAL` because it was observed from a real public source.
- Classify the batch as `REAL + SEARCH_RESULT_LEVEL`, not authoritative `REAL + LISTING_LEVEL`.

Provenance quality counts:

| Quality | Count |
|---|---:|
| LISTING_LEVEL | 0 |
| SEARCH_RESULT_LEVEL | 42 |
| GENERIC_SOURCE / insufficient | 0 |

Observation #2106:

- Product: AMD Ryzen 7 5700X3D.
- Stored asking price: 12,590 THB.
- Provenance quality: SEARCH_RESULT_LEVEL.
- Stored source URL kind: Priceza search page.
- Stored source item ID: none.
- Stored merchant: none.
- Exact listing identity cannot be reconstructed safely from the stored row.

Priceza feasibility finding:

- Current public Priceza search-result HTML exposes result-level identifiers such as `data-productid`, Priceza result links in the form `/r/redirect?id=<id>`, listing title text, captured price content, marketplace/merchant labels, and seller names.
- The Priceza result redirect page exposes result-level metadata including `ProductID`, `Price`, `MerchantID`, title, and an encoded merchant target URL through normal public access.
- This is sufficient for future listing/result-level offline capture only if those identifiers and `/r/redirect?id=<id>` URLs are captured at observation time.
- The existing 42 rows did not preserve those identifiers, so they must not be upgraded retroactively.

Implemented protection:

- Review Detail now displays provenance quality and warns in Thai when evidence is search-result-level.
- Server-side review approval rejects REAL observations that are not LISTING_LEVEL.
- Snapshot input excludes REAL offline observations with insufficient provenance using deterministic exclusion reason `INSUFFICIENT_PROVENANCE`.
- Offline import now accepts and preserves separate fields for `source_search_url`, `source_listing_url`, `source_item_id`, `external_ref`, `merchant`, `listing_title`, `asking_price`, `observed_at`, and `ingestion_note`.
- No database migration was required; the current read model derives quality from existing raw/evidence fields and future structured evidence metadata.

Safety:

- Existing 42 REAL records mutated: zero.
- Review decisions executed: zero.
- Price snapshots modified: zero.
- Cloud destructive operations: zero.

Next owner action:

- Confirm Priceza result-level capture strategy before collecting a replacement authoritative REAL sample.

## Phase 3H-R1C Authoritative REAL Sample Probe

Probe date: 2026-09-13 ICT.

Status: `PARTIAL`

Owner-approved provenance contract:

- Do not rely on `/r/redirect?id=<id>` alone.
- Authoritative REAL market evidence requires exact item identity plus immutable capture-time evidence.
- Priceza LISTING_LEVEL evidence must preserve source, search URL, result URL, source item ID, merchant, listing title, asking price, observed timestamp, merchant target URL when safely available, immutable evidence snapshot, and evidence hash.

Implementation:

- Added a Priceza small-probe collector using normal public HTTP access only.
- Stored the immutable evidence snapshot as compact normalized JSON in existing private evidence excerpt storage.
- Stored the evidence hash in existing `market_evidence.content_hash`.
- No new schema or external storage service was added.
- Updated provenance classification so Priceza rows fail closed unless item identity, visible fields, immutable snapshot, and hash are all present.

Probe capture result:

| Metric | Count |
|---|---:|
| Priceza result items captured | 8 |
| source_item_id captured | 8 |
| `/r/redirect?id=<id>` URL captured | 8 |
| merchant captured | 8 |
| exact title captured | 8 |
| exact asking price captured | 8 |
| observed_at captured | 8 |
| immutable evidence snapshot stored | 8 |
| evidence hash generated | 8 |
| merchant target URL captured | 8 |
| merchant target URL unavailable | 0 |

Pipeline result:

| Classification | Count |
|---|---:|
| LISTING_LEVEL pending observations | 6 |
| SEARCH_RESULT_LEVEL rows | 0 |
| GENERIC_SOURCE / non-observation rows | 2 |

Products represented by LISTING_LEVEL pending observations:

- AMD Ryzen 7 5700X3D
- AMD Ryzen 5 5600
- NVIDIA GeForce RTX 3070
- NVIDIA GeForce RTX 4070 Ti

Probe row notes:

- Raw/evidence rows were created for all 8 captured items.
- Two captured items failed normal validation/resolution as Red and did not become price observations:
  - Priceza item `865320369`
  - Priceza item `866874820`
- Six captured items became pending REAL LISTING_LEVEL observations:
  - #2112, #2113, #2114, #2115, #2116, #2117
- No probe row was human-approved.

Auditability:

- Stored evidence can demonstrate: Observation -> source_item_id -> immutable evidence snapshot -> title -> asking price -> merchant -> capture timestamp.
- For the two Red rows, auditability exists at raw/evidence/review level, but product-resolution/observation creation did not complete.

Existing 42-row batch:

- Still retained as REAL calibration evidence.
- Still SEARCH_RESULT_LEVEL.
- Still public-price ineligible.
- Not mutated or upgraded.

Safety:

- Price snapshots modified: zero.
- Review decisions executed by a human: zero.
- Existing 42 REAL records modified: zero.
- External API/provider calls: zero. Public Priceza pages were fetched directly through normal public access; no paid provider, login bypass, CAPTCHA bypass, or anti-bot evasion was used.
- New external spend: 0 THB.

Decision:

- Do not expand to 30-50 authoritative REAL rows yet.
- First fix the capture selection/filtering so all 6-10 probe items both preserve LISTING_LEVEL provenance and pass product-resolution/validation into pending observations.

## Phase 3H-R1C Probe Quality Fix

Probe fix date: 2026-09-13 ICT.

Status: `PASS`

Original failed probe rows:

| Raw ID | Product intent | source_item_id | Title | Price | Listing URL | Failure reason | Failure type |
|---:|---|---|---|---:|---|---|---|
| 1864 | AMD Ryzen 5 5600 | 865320369 | Ryzen 5 5600G (มือสอง) (25133630727) | 3990.0 | https://www.priceza.com/r/redirect?id=865320369 | Red validation: `UNRESOLVED_PRODUCT`; selected 5600G instead of 5600 | Bad source item selection |
| 1868 | NVIDIA GeForce RTX 4070 | 866874820 | การ์ดจอ RTX 4070 Super - ASUS มือสอง (25719416205) | 15900.0 | https://www.priceza.com/r/redirect?id=866874820 | Red validation: `WRONG_PRODUCT`; selected RTX 4070 Super instead of RTX 4070 | Bad source item selection |

Root cause:

- Both rows had item ID, redirect URL, merchant, exact title, price, observed timestamp, immutable snapshot, evidence hash, and merchant target URL.
- The failure was not a provenance classifier bug.
- The collector selected nearby but incorrect variants before product acceptance.
- The pipeline correctly failed closed and did not create price observations for those two rows.

Fix implemented:

- The Priceza probe collector now rejects previous probe IDs, duplicate item IDs, wrong model markers, mixed variants, bundle/whole-PC/accessory/wanted/deposit/defective contexts, and items that do not resolve to the intended Product Master through the existing deterministic extractor, resolver, and validator.
- The collector now preserves a `probe_run_id` through evidence metadata so audits can isolate the newest probe.
- The probe audit verifies the immutable evidence snapshot hash by recomputing `sha256(snapshot_json)`.
- The provenance standard was not weakened.

New probe result:

| Metric | Count |
|---|---:|
| New Priceza result items imported | 8 |
| Products represented | 4 |
| Unique source_item_id | 8 |
| Exact title/price/merchant verified against snapshot | 8 |
| Immutable evidence snapshot verified | 8 |
| Evidence hash verified | 8 |
| Product-resolution success | 8 |
| LISTING_LEVEL | 8 |
| SEARCH_RESULT_LEVEL | 0 |
| GENERIC_SOURCE | 0 |

New products represented:

- AMD Ryzen 7 5700X3D
- AMD Ryzen 5 5600
- NVIDIA GeForce RTX 3070
- NVIDIA GeForce RTX 4070

New pending LISTING_LEVEL observations:

- #2118, #2119, #2120, #2121, #2122, #2123, #2124, #2125

Safety:

- Existing 42 REAL SEARCH_RESULT_LEVEL rows modified: zero.
- Previous 8 probe raw evidence rows modified: zero.
- Human approval/reject/exclude actions executed: zero.
- Price snapshots modified: zero.
- External API/provider calls: zero. Public Priceza pages were fetched directly through normal public access; no paid provider, login bypass, CAPTCHA bypass, or anti-bot evasion was used.
- New external spend: 0 THB.

Decision:

- The small probe quality fix is complete.
- The system is ready for owner-authorized expansion to 30-50 authoritative REAL rows using the same tightened capture mechanism.

## Phase 3H-R1D First Authoritative REAL Batch

Batch date: 2026-09-13 ICT.

Status: `READY FOR HUMAN REVIEW`

Collection/import:

- Run ID: `priceza-batch-20260913130814-4a8f55`.
- Dry run passed before import: 32 valid rows, 0 invalid rows, 0 duplicate import rows, 0 source-policy blocks.
- Additive REAL import created 32 raw candidates, 32 evidence rows, 32 extraction runs, and 32 review rows.
- No approvals, rejects, exclusions, or price snapshot recalculations were executed.

Quality profile:

| Metric | Count |
|---|---:|
| Total rows collected | 32 |
| Total rows imported | 32 |
| LISTING_LEVEL | 32 |
| SEARCH_RESULT_LEVEL | 0 |
| GENERIC_SOURCE | 0 |
| Unique source_item_id | 32 |
| Exact title/price/merchant verified | 32 |
| Immutable evidence snapshot verified | 32 |
| Evidence hash verified | 32 |
| Product-resolution success | 32 |
| Green | 32 |
| Amber | 0 |
| Red | 0 |
| Asking/listing price | 32 |
| Sold/final price | 0 |
| Missing condition | 30 |
| Missing warranty | 32 |
| Merchant target URL captured | 31 |
| Merchant target URL unavailable | 1 |
| Provenance failures skipped | 0 |
| Previous/duplicate source item IDs skipped | 17 |
| Bad variant/context items skipped | 80 |

Rows by product:

| Product | Rows |
|---|---:|
| NVIDIA GeForce RTX 4070 | 9 |
| NVIDIA GeForce RTX 3060 Ti | 8 |
| AMD Ryzen 7 5800X | 6 |
| NVIDIA GeForce RTX 3070 | 5 |
| AMD Ryzen 7 5700X3D | 2 |
| AMD Ryzen 5 5600 | 1 |
| AMD Radeon RX 6600 XT | 1 |

Notes:

- The batch reached 32 rows by using the high-volume existing AMD Ryzen 7 5800X Product Master in addition to the preferred CPU/GPU scope. This preserved the quality-over-quantity rule without weakening selection.
- The deterministic review queue provenance filter now supports `provenance=LISTING_LEVEL`, and row cards show provenance quality.
- Global REAL state after import: 88 pending REAL observations total; 46 LISTING_LEVEL and 42 SEARCH_RESULT_LEVEL.

Safety:

- Previous 42 REAL SEARCH_RESULT_LEVEL rows modified: zero.
- Previous probe raw evidence modified: zero.
- Human approval/reject/exclude actions executed: zero.
- Price snapshots modified: zero.
- Cloud destructive operations: zero.
- External API/provider calls: zero. Normal public Priceza HTTP access was used; no Gemini, paid provider, proxy, login bypass, CAPTCHA bypass, or anti-bot evasion was used.
- New external spend: 0 THB.

## Verification

- `php -l app\Models\Dashboard.php`: pass.
- `php -l app\Views\admin\review.php`: pass.
- `php cli\data_integrity_check.php`: `ok=true`, `problem_count=0`.
- `php cli\auth_status.php`: zero users/admins, admin routes protected.
