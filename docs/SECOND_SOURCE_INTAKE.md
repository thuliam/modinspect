# Second-source intake (how a price can reach "medium" confidence)

Every public price today comes from one kind of evidence: owner-collected text without a listing URL (`OWNER_TEXT_NO_URL`).
Confidence-v2 caps such a sample below 60 ("low"), however large. A sample becomes "medium" only when at least one included observation comes
from a different, listing-level source. This page is the shortest safe way to add one. It costs nothing: you collect the rows yourself,
no crawler or paid service is involved.


> **WARNING (found 2026-11-17, DL-078):** rows imported from this minimal template are accepted by the importer but are classified as a generic source and **excluded from the used price** (`INSUFFICIENT_PROVENANCE`), because the template carries no immutable evidence snapshot. Following the steps below today will NOT make any price "medium". `tests/second_source_snapshot_gate_test.php` pins this. The owner decision that fixes it is in `docs/K3_SECOND_SOURCE_OPTIONS.md` ("K-3c").
## What counts

- One row per listing you can point to: a listing URL (Facebook Marketplace item, Pantip topic, shop product page, ...) **or** a stable listing reference.
- The source is taken from the URL's domain, so rows from different domains are different sources and raise source diversity.
- Asking prices only. Sold or final prices are a different price type and are never mixed in.

## Minimal file

Use `examples/import/second_source_minimal.csv` (header only, on purpose: a template with example rows could be imported by accident).

| column | meaning |
|---|---|
| `source_listing_url` | link to the listing (no personal profile links) |
| `source_item_id` | a short stable reference, for example the item id in the URL |
| `listing_title` | the visible title as posted |
| `asking_price` | number, THB |
| `observed_at` | `YYYY-MM-DD HH:MM:SS`, the day you saw it |
| `condition_hint` | optional: `used`, `new`, `like new` |

Do not put seller names, phone numbers, addresses, chat text or profile photos in the file (AGENTS.md: minimise seller PII).

## Steps

1. Save your file under `storage/import/` (git-ignored). Never commit real rows.
2. Dry run, nothing is written:
   `php cli/import_market_evidence.php --file=storage/import/second_source.csv --dataset=real --dry-run`
   Check `valid_rows`, `invalid_rows` and `row_errors`. A row without a listing reference, a non-URL, or a mock/test domain is rejected.
3. Import (writes candidates for human review, never straight into a price):
   `php cli/import_market_evidence.php --file=storage/import/second_source.csv --dataset=real`
4. Review each row in the admin Review Queue and approve it as a person. Real rows are never auto-approved by `owner_intake_approve.php`.
5. Recalculate the snapshot. The admin page `/admin/snapshots/{id}` then shows whether the "what is missing for medium" blocker is gone.

## What this does not change

- Prices never come from community submissions or from this file directly: only approved observations enter a snapshot (deterministic PHP).
- The 88 pending Priceza (REAL) observations are untouched and are reviewed separately.
- Rows from a source that asks to stop or blocks access are paused and recorded as an incident; there is no bypass.
- Fixture or owner-text figures say nothing about live-market quality; calibration keeps them apart from reviewed live data.

