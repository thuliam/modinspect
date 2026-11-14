# ModInspect Data and Research Policy

Baseline date: 2026-11-14. Complements `docs/COLLECTION.md`, `docs/REAL_CALIBRATION_PROTOCOL.md` and `AGENTS.md`.

## Price categories (never mix)

| Category | Meaning | May feed public price? |
|---|---|---|
| Listing-level asking price | A specific public listing with its own URL/identity, captured with evidence | Yes, after human approval and sufficiency rules |
| Search-result-level asking price | A price seen in a search result without independent listing evidence | **No** unless policy thresholds are legitimately met (currently ineligible) |
| Seller-claimed sold/final price | Voluntary report of a completed sale | Distinct type with its own trust controls; never mixed with asking |
| Owner-collected listing text (no URL) | Facebook posts the owner copied into LINE | **No, not yet.** Research dataset; public use needs an approved provenance grade (ADR) |
| MOCK / TEST | Fixtures and import templates | **Never** public |

## Owner-collected Facebook listings (LINE keep export)

Owner clarification, 2026-11-14: the LINE keep export is **not test data**. It holds real asking prices the owner found on
Facebook (Marketplace and sale groups) and copied in as text. The owner asked for the relevant rows to be filtered into a dataset.

- The raw export also contains unrelated passwords, API keys and personal notes. It is processed **only locally**, never committed,
  never pasted into docs or logs, never uploaded. Messages that look like credentials are dropped before parsing. The owner should
  rotate any credential that appears in it (gate G-5).
- `cli/research_line_extract.php` (read-only, local catalog only) turns the export into `storage/research/line_facebook_2026/`
  (`research_listings_sanitized.csv`, `research_report.json`). That folder is git-ignored. The CSV has no seller names, phone numbers,
  URLs, e-mails, LINE ids, shop names or locations.
- Provenance grade: `OWNER_COLLECTED_TEXT` / `OWNER_TEXT_NO_URL`, price type `asking`. Rows have **no listing URL and no immutable
  evidence snapshot**, so under the code as it stands (REAL approval needs listing-level provenance) they cannot be approved yet.
  **Owner decision DL-013 (2026-11-14): option A.** `OWNER_TEXT_NO_URL` becomes a permitted, lower provenance grade that the owner
  may approve per variant. That needs one engineering change before any row can be approved: an owner-intake import path that records
  this grade and shows it on every public figure. Until that ships, the rows remain market research, not accepted observations,
  and nothing is imported into shared DEV.
- Completion (DL-013): `app/Services/Research/ListingEnricher.php` fills brand, product line and category specs (RAM generation,
  capacity, kit and speed; storage type and capacity; PSU wattage and efficiency; mainboard chipset and socket; GPU brand) only from
  what the listing says or from a fixed lookup, and flags every derived value (`BRAND_FROM_LINE`, `DDR_GEN_FROM_SPEED`,
  `SOCKET_FROM_CHIPSET`, `CAPACITY_UNIT_ASSUMED_GB`). It never guesses: a field the text does not state stays empty, and the row is
  listed in `owner_fill_needed.csv` for the owner. A post that mixes several products is marked `ambiguous` and left blank
  (`MULTI_ITEM_CONTEXT`). `enrichment_status` is complete, partial, missing or ambiguous. Fixture result only: it proves the parser,
  not the quality of the market data.
- Row-level quality flags instead of silent cleaning: `duplicate_of_row` (reposts), `listing_type=bundle`, `wanted_or_trade`,
  `defective_hint`, `condition_class=new_sealed`, `price_anomaly`, `NEARBY_MASTER_MODEL_REJECTED`, `PREFIX_INFERRED`,
  `SHORTHAND_SUFFIX_S`, `WARRANTY_ENDED_BEFORE_NOTE`. `variant_key` = model + VRAM so 8 GB and 16 GB cards are never merged.
- Dates: `note_date` is when the owner saved the text in LINE, not the post date. Warranty years are converted from Buddhist era.

## What to preserve per research row

Posted date, model and variant, asking vs sold, price, condition, warranty (seller claim vs verifiable), box/accessories,
bundle vs standalone, source and provenance grade, extraction confidence per field, duplicate status.

## Fixture ideas (deterministic, no private data)

Use synthetic or sanitised titles to extend `tests/fixtures/validation_golden_dataset.php`:
variant collisions (4070 vs 4070 Ti vs 4070 SUPER, 3080 10GB vs 12GB, 5600 vs 5600G), unknown VRAM/capacity (must stay
`VARIANT_AMBIGUOUS`), "GPU only / no box / no warranty", seller-claimed 7-day shop warranty vs manufacturer warranty, corrosion
or scratches in condition text, duplicate reposts of one listing, promotional bait prices, deposit/wanted listings.

## Acquisition rules

Public pages permitted by the source only; no login, CAPTCHA or anti-bot bypass, no proxy or account rotation, no hidden profile
scraping, no PII harvesting. A block or stop request pauses the source and records an incident. Zero new external cost without
owner approval.

## Publication rules

Public price only from provenance-qualified, human-approved REAL observations that meet the configured deterministic
sufficiency criteria; otherwise show no price. Do not bundle research rows into a "market median". Community input is a signal,
never a vote that recalculates price.
