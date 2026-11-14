# ModInspect Data and Research Policy

Baseline date: 2026-11-14. Complements `docs/COLLECTION.md`, `docs/REAL_CALIBRATION_PROTOCOL.md` and `AGENTS.md`.

## Price categories (never mix)

| Category | Meaning | May feed public price? |
|---|---|---|
| Listing-level asking price | A specific public listing with its own URL/identity, captured with evidence | Yes, after human approval and sufficiency rules |
| Search-result-level asking price | A price seen in a search result without independent listing evidence | **No** unless policy thresholds are legitimately met (currently ineligible) |
| Seller-claimed sold/final price | Voluntary report of a completed sale | Distinct type with its own trust controls; never mixed with asking |
| Research note / personal saved listing | Owner-saved text such as LINE notes | **No.** Research and test fixtures only |
| MOCK / TEST | Fixtures and import templates | **Never** public |

## Owner-saved LINE research

- The raw export contains unrelated passwords, API keys and personal data. It must never be committed, pasted into docs or logs,
  uploaded to an external service, or processed automatically. It was **not present** in the Claude workspace on 2026-11-14.
- Only an owner-provided **sanitised** subset (no seller identifiers, phone numbers, private chats, e-mails, credentials or exact
  addresses) may be used. The owner should rotate any credential that appears in the raw file.
- Examples seen in the owner's brief (about a dozen GPU/CPU/SSD asking prices dated 2026-11-12 to 2026-11-14) are **bounded examples**,
  not market observations. They lack listing URLs, immutable evidence and seller identity, and some lack exact variants
  (VRAM size, SSD capacity). They are intentionally **not reproduced here** so nobody anchors on them.

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
