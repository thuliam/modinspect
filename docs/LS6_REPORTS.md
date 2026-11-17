# LS-6 reports (2026-11-17)

Scope approved by the owner: M-1 to M-6. Nothing was written to the DEV database; every database test ran on the isolated test database and was rolled back. No paid service, no live collection.

## M-1 Daily file helper, and two import hazards found on the way

`php cli/prepare_owner_rows.php --in=<loose csv> [--out=storage/import/second_source.csv] [--default-date=YYYY-MM-DD] [--force]`

Reads the owner's loose rows (link, title, price, date; Excel "CSV UTF-8" is fine) and writes the strict import template. Offline: no database, nothing fetched from any link. It prints counts and row numbers with error codes only, never a title, price or link. The output must stay inside `storage/import/` (not in git) and an existing output is never replaced without `--force`.

What it does: canonical listing URL (mobile host and share-link tracking removed, only the parameters that identify a Facebook page kept); item id derived from the URL when the URL names one listing (`FB-`, `PT-`, `KD-`), otherwise reported; price made numeric (a price that is not a number is reported, never guessed); date-only values get `00:00:00`; `--default-date` fills only rows with no date and the count says how many; a repeated item in one file is rejected; the same words and price under another item id is a warning only; extra columns (seller, phone, location) are dropped and reported by name, not value. Domain, item id, phone number and profile-link checks reuse `CaptureMethod::problems`, so there is one rule set.

Two hazards in the importer itself, found by reading it, pinned by `tests/import_real_file_hazards_test.php` (red first, every fix mutation-checked):

1. **Excel byte-order mark.** The first header cell kept the BOM, so `capture_method` was not recognised. The row then passed validation as a plain real row with no frozen snapshot, and would have sat outside every price with no clear error. The BOM is now stripped for CSV and JSON.
2. **Mock guard rejected real pages.** The "mock/test" check looked for the letters `test` anywhere in the listing URL, so a fan page called `contestshop` or a slug with `latest` was rejected as a mock source. The URL host is still checked by substring; the path and query are now checked by whole words. `test`, `shop-mock`, `fixture_shop`, `test.facebook.com` and `?mock=1` are still rejected.

Tests: `tests/owner_row_preparer_test.php` (6 mutations caught), `tests/import_real_file_hazards_test.php` (3 mutations caught).

## M-2 Dress rehearsal of the daily route

`tests/daily_flow_rehearsal_test.php` on the test database, rolled back: Excel-style raw file (BOM, CRLF, own headers, tracking, `8200 บาท`, a seller column) goes through the helper, a dry run, the real import, a second import of the same file (creates nothing), approval of four rows, a snapshot, the confidence cap, the reproduce check, the public evidence count and the calibration report. Result: four rows included in the used price, `HUMAN_RECORDED_EVIDENCE_CAP` applies and the score stays at or below 79 (never "high"), the snapshot reproduces exactly, the public note counts four transcribed rows. Fixture data: it proves the route works, not how live listings will behave. Mutations caught: freeze disabled, BOM kept by the helper.

Finding (low risk, not changed): `ReviewCorrectionService::applyApprovedValues` reports success only when MySQL says a row changed. An approval in the very second of the import changes no column (`updated_at` included) and returns false. A person never approves that fast; the test waits one second. A script that imports and approves in one second would see a false failure.

## M-3 Calibration split by capture method

`ReviewCalibrationService::report()` now has `capture_methods`: `owner_transcription`, `machine_capture`, `owner_text`, `other_real` and `fixture`, each with candidates, reviewed, approved, rejected, excluded and corrected. Fixtures are their own bucket and the four live buckets add up to the real records, so a fixture can never be counted as live. This is additive; the existing fields are unchanged and the admin analytics page was not touched (no new UI). Read it with `php cli/review_calibration_report.php`. `tests/calibration_capture_method_test.php` measures deltas against the same report, includes a machine-capture row, and catches three mutations.

## M-4 Phone width, hover states, tab order

Method: pages loaded in a 375 px and a 320 px frame in the built-in browser against the test database (a temporary local server, stopped afterwards). Measured per page: horizontal overflow, targets under 24 px, text under 11 px, zoom blocked, positive tabindex, tab order against visual order. Hover was checked by CSS rule on all pages and then by real mouse hover on six elements of the product page (the rule check alone gave false alarms because legacy Bootstrap rules are shadowed by the theme).

| Check | Result |
|---|---|
| Overflow at 375 px | none on 6 pages |
| Overflow at 320 px | `/build` overflows by 5 px (part and brand names); the other 5 pages none |
| Zoom blocked, positive tabindex | none |
| Targets under 24 px | none, except the skip link (hidden until focused) and Deal Checker checkboxes whose labels are 108 to 277 px wide |
| Text under 11 px | product page "no market price yet" badge 10 px; `/build` note "market data is not enough yet" 10 px and the brand glyph in a part card 8 px |
| Hover contrast | **one real defect, fixed:** `btn-outline-secondary` on public pages turned white text on lime on hover (contrast 1.32). Four buttons in three views now use the theme's own `btn-ghost` (lime on dark, 12.5 after). Pinned by `tests/public_button_theme_test.php` (red before the change) |
| Tab order | `/build` on a phone: the save-and-analyse button is visually near the top but comes after the whole parts list in the page order. Not changed (needs markup or layout work) |
| Dark mode | the site is one dark theme; it ignores the visitor's light/dark preference and declares no `color-scheme`. Not a defect, a note |

Not covered: screen-reader behaviour (not run), text over images, an axe run, hover states beyond the six real hovers, other widths between 320 and 375. These are script checks on fixture data, not a user test.

Open findings for the owner: `/build` focus order on phones, `/build` 5 px at 320 px, the 10 px and 8 px text. Each needs a markup or layout change; they are not made here because the UI rule asks for approval before custom UI work.

## M-5 Beta MUST rows: who can close them

`docs/BETA_READINESS_CHECKLIST.md` has a new table. Twelve MUST rows are open and none can be closed by code alone: six need only the owner (A-4, A-5, B-1, C-2, E-1, E-3), six also need the real host (C-3, C-4, D-2, D-3, D-4, D-5). The verdict stays NO-GO.

## M-6 CI

CI is green for commit `f3a54d7` (tests and offline package, run 2026-11-17 07:43 UTC). The final commit of this sprint is checked after the push.

## Correction (LS-7, 2026-11-17)

Two statements above were revised later; see `docs/LS7_REPORTS.md`. The `/build` 5 px overflow at 320 px is fixed. The "focus order on phones" finding is **retracted**: the summary panel is a sticky bar pinned to the bottom of the screen and is also last in the page, so tab order matches reading order. The "approval in the import's own second reports false" note is fixed (N-2).
