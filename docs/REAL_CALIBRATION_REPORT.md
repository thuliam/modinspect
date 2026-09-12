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

## Verification

- `php -l app\Models\Dashboard.php`: pass.
- `php -l app\Views\admin\review.php`: pass.
- `php cli\data_integrity_check.php`: `ok=true`, `problem_count=0`.
- `php cli\auth_status.php`: zero users/admins, admin routes protected.
