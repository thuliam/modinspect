# Second-source intake (how a price can reach "medium" confidence)

Every public price before LS-4 came from one kind of evidence: owner-collected text without a listing URL (`OWNER_TEXT_NO_URL`).
Confidence-v2 caps such a sample below 60 ("low"), however large. A sample can only rise when at least one **included** observation
comes from a different, listing-level source. It costs nothing: the owner collects rows by hand, no crawler or paid service is involved.

> **History (DL-078, 2026-11-17):** the earlier version of this page told you to use `examples/import/second_source_minimal.csv`. Rows from that file are
> a generic source and are excluded from used prices (`INSUFFICIENT_PROVENANCE`); `tests/second_source_snapshot_gate_test.php` pins that. **Use
> `examples/import/owner_listing_urls.csv` below instead** (owner decision P1, DL-079).

## How it works (one pattern for people and agents)

Every route (owner CSV, agent collector, admin upload) ends in the same place: an import record carrying `evidence_snapshot_json` + `evidence_hash`,
classified by `ObservationProvenanceService`, filtered by `PriceSnapshotService`, scored by `SnapshotConfidenceService`. The `capture_method` column says who made the evidence:

| capture_method | Who | Snapshot | Counts as |
|---|---|---|---|
| `owner_transcription` | you typed the listing into the template | the importer **freezes your row** at import (canonical JSON + SHA-256), evidence typed "snippet", evidence level 3 | listing level, only for allow-listed marketplaces; confidence capped below "high" |
| `machine_capture` | an agent saved the page itself | the record must bring its own snapshot and 64-hex hash, the importer never invents one | listing level, no human-recorded cap |

A frozen snapshot proves what you recorded and when, **not** that the page said so; a Facebook post can disappear, so a later check may be impossible.
The confidence page says this in its caps: `HUMAN_RECORDED_EVIDENCE_CAP` (evidence entirely typed by people, owner text and/or transcriptions, never above 79).
Mixing in one machine capture removes that cap. The method version stays `confidence-v2`: the cap is additive and does not affect data without the marker.

## The file

Use `examples/import/owner_listing_urls.csv` (header only, on purpose: a template with example rows could be imported by accident).

| column | meaning |
|---|---|
| `capture_method` | `owner_transcription` |
| `source_listing_url` | link to the item or post. Allowed domains: facebook.com, pantip.com, kaidee.com (and their subdomains). **No personal profile links** (`profile.php`, `/people/`): rejected |
| `source_item_id` | a short stable id taken from the URL, for example the number after `/marketplace/item/`; required |
| `listing_title` | the visible title as posted |
| `asking_price` | number, THB |
| `observed_at` | `YYYY-MM-DD HH:MM:SS`, the day you saw it |
| `condition_hint` | optional: `used`, `new`, `like new` |

Do not put seller names, phone numbers, addresses, chat text or profile photos in the file (AGENTS.md: minimise seller PII). Asking prices only.

## Daily routine (about 10+ rows a day)

1. Append the day's rows to a file under `storage/import/` (git-ignored). Never commit real rows. Short Thai steps: `docs/OWNER_DAILY_COLLECTION_TH.md`.
2. Dry run, nothing is written:
   `php cli/import_market_evidence.php --file=storage/import/second_source.csv --dataset=real --dry-run`
   Check `valid_rows`, `invalid_rows`, `row_errors`. Typical codes: `OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED`, `OWNER_TRANSCRIPTION_REQUIRES_ITEM_ID`, `OWNER_TRANSCRIPTION_PERSONAL_PROFILE_LINK`, `UNKNOWN_CAPTURE_METHOD`.
3. Import (writes candidates for human review, never straight into a price):
   `php cli/import_market_evidence.php --file=storage/import/second_source.csv --dataset=real`
   The same file can be uploaded on the admin Imports page.
4. Review each row in the admin Review Queue and approve it as a person (the owner; the actor is recorded). Real rows are never auto-approved by `owner_intake_approve.php`.
5. Recalculate the snapshot. `/admin/snapshots/{id}` shows which caps apply and what is still missing.

## What this does not change

- Prices never come from community submissions or from this file directly: only approved observations enter a snapshot (deterministic PHP).
- The 88 pending Priceza (REAL) observations are untouched and are a separate new-price reference (DL-077); they never enter the used price.
- Rows from a source that asks to stop or blocks access are paused and recorded as an incident; there is no bypass.
- Fixture or owner-text figures say nothing about live-market quality; calibration keeps them apart from reviewed live data.
- A future agent collector plugs in through `SearchProviderInterface` and the same record contract (`capture_method=machine_capture` with its own snapshot); live collection stays blocked until the owner approves a provider and its cost (zero-cost policy).
