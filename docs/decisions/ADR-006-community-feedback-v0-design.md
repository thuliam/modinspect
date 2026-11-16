# ADR-006: Community feedback v0: design and schema proposal (nothing built)

**Status:** Proposed. Design record only: no table, route, form or code exists. It needs the owner's approval of scope and of the privacy wording before anything is built (LS-3 decision D-1: design first, a small form only after approval).
**Date:** 2026-11-17
**Builds on:** ADR-005 (community feedback is evidence, not voting), Blueprint v1.1 section 3.6, `docs/PRODUCT_REQUIREMENTS.md` R-05 and R-11, `docs/SECURITY_REVIEW_2026-11-16.md`.

## Why now, and why small

The data is thin (every public price rests on one kind of evidence, owner text without a link). A visitor who sees a wrong model or an old price is the cheapest second pair of eyes, and a voluntary sold price is the only way the site will ever hold a sold-price signal. But a feedback form is also the easiest way to let a seller push a price up or a buyer push it down. So v0 is deliberately narrow: structured reports only, moderated by a person, never visible to other visitors, never able to touch a snapshot.

## Invariants (inherited, not negotiable in this design)

1. A report is a **signal**. It can never create or change a price, a snapshot, a confidence label or an approval. Only the existing review pipeline, with a human decision, turns evidence into an accepted observation (ADR-005, ADR-003).
2. **Asking and sold prices stay separate.** A sold-price report is stored as `sold`, never mixed into asking cohorts.
3. **No public accusations.** Nothing in v0 names, scores or labels a seller (R-17). Reports are about a product page or an observation, not about a person.
4. **Minimal personal data.** No name, phone, address, account, profile link or free text that invites them. The reporter is anonymous.
5. **Zero cost.** No mail service, no captcha provider, no third-party script. Abuse control uses what already exists: CSRF, the file rate limiter (J-4), moderation.
6. Fixture and owner-text figures remain baselines; reports do not change that statement.

## What a visitor can report (v0 types)

| Type | Meaning | Extra structured fields |
|---|---|---|
| `wrong_product` | the product page or an observation names the wrong model or variant | what it should be: a choice from the catalog, or "not in the catalog" |
| `price_looks_off` | the range looks too high or too low | direction (higher / lower), optional price the visitor sees |
| `stale` | the data looks old | none |
| `sold_price` | voluntary: "it sold for" | price, month, condition (the existing five levels), warranty months, box and receipt flags |
| `spec_wrong` | a stated spec is wrong | which spec (choice list) |

Everything is a choice or a number; there is no free-text box in the recommended option.

## Proposed schema (NOT applied; shown so the owner can judge the data we would keep)

```sql
CREATE TABLE community_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('wrong_product','price_looks_off','stale','sold_price','spec_wrong') NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  observation_id BIGINT UNSIGNED NULL,          -- only when the report points at one observation
  snapshot_id BIGINT UNSIGNED NULL,             -- the public snapshot the visitor was looking at (what they saw)
  payload JSON NOT NULL,                         -- the structured fields above, validated server-side, size-capped
  reporter_hash CHAR(32) NOT NULL,               -- hash of client address + daily salt: duplicate and flood control, not identity
  status ENUM('new','accepted','rejected','spam') NOT NULL DEFAULT 'new',
  moderator_id BIGINT UNSIGNED NULL,
  moderation_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  INDEX idx_reports_status (status, created_at),
  INDEX idx_reports_product (product_id, report_type)
) ENGINE=InnoDB;
```
The moderator's actions would be written to the existing `audit_logs`. `reporter_hash` uses a salt that rotates daily, so a person cannot be followed across days; retention is proposed as 90 days for rejected and spam reports, and for accepted ones until the observation they produced is superseded (owner to confirm).

## Flow

1. A small "report a problem" control on the product page opens a form for one of the five types. CSRF token, a hidden honeypot field, and the J-4 rate limiter (proposed: 5 per hour per client, 3 per product per day) sit in front of the insert.
2. The row is stored as `new`. The visitor sees a plain acknowledgement ("received; it is checked by a person; it does not change the price by itself"). Nothing is shown to other visitors.
3. An admin moderation queue (same patterns as the review queue and the unresolved-listings queue) shows reports with the product, the snapshot the visitor saw and the payload. The moderator marks `accepted`, `rejected` or `spam` with an optional note.
4. `accepted` has exactly two effects, both through existing, reviewed paths: (a) a `wrong_product` or `spec_wrong` becomes a task for the catalog (alias or spec correction by an admin); (b) a `sold_price` or `price_looks_off` with a price becomes an **observation candidate** in the review pipeline at a lower evidence grade (`COMMUNITY_REPORT`), where a reviewer approves or excludes it like any other row. Nothing else.
5. Counts per product (new reports, accepted reports) may appear on Data health for admins, so a product with many reports gets looked at first.

## Abuse model and controls

| Abuse | Control |
|---|---|
| A seller pushes a price up, a buyer pushes it down | a report never moves a price; a candidate needs a human reviewer; one reviewer decision per row; candidates from one `reporter_hash` per product per week are capped |
| Floods and bots | CSRF, honeypot, rate limits per client and per product, size caps on the payload |
| Identity leak | structured fields only, no free text (recommended option), anonymous reporter hash with a rotating salt |
| Coordinated reports | counts are an admin signal for attention, never a vote; consensus does not auto-accept |
| Links and spam in text | there is no text in the recommended option; if text is later allowed: no links, 200 characters, moderation before any display |

## Options for the owner (with a Recommended pick)

| Option | What | Cost / risk |
|---|---|---|
| **A. Structured only, anonymous (Recommended)** | the five types with choice lists and numbers, no free text, no account | smallest privacy surface and the easiest to moderate; cannot capture an explanation |
| B. Add a short free-text note (200 characters) | same plus a note | richer reports, but personal data and links can arrive; moderation must read every note before anything is shown |
| C. Require an account | reports tied to a user | trust and de-duplication improve, but the site would then store accounts, passwords and email; conflicts with the zero-cost and minimal-data rules today |

## Build plan if approved (each slice is separately testable and reversible)

1. **S1** schema (migration, `schema.sql`, `modinspect.sql`), admin queue that only lists reports, tests. No public change.
2. **S2** the public form behind a setting that is **off by default**, rate limits, honeypot, tests including "a report changes no price".
3. **S3** moderation actions and the two accepted-report effects, audit entries, tests with the review pipeline.
4. **S4** Data health counts; a retention job that deletes rejected and spam reports after the agreed period.

## What this record does not decide

The privacy and terms wording shown to visitors (draft: "We keep only the choices you make and a short-lived anonymous code to stop floods; we do not keep your name, contact or address; a person checks every report; a report does not change a price by itself") **needs the owner's approval and, if the owner wants it, a legal review before launch**. The retention periods above are proposals. Whether sold-price reports are wanted before there is a listing-level second source is the owner's call.

## Open questions

1. Which of options A, B or C?
2. Is a sold-price report worth building before a second listing-level source exists?
3. Who moderates, and how often? (A report queue nobody reads is worse than no form.)
4. Are the retention periods acceptable?
