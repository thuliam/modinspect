# ADR-007: Seller check (reports about a phone number or bank account)

Status: built, switched OFF (`SELLER_CHECK_ENABLED=false`). Owner decision OD-3 (2026-11-18): "ค้นเบอร์/บัญชีได้ หลังตรวจแล้ว".
Needs before it is switched on: HG-14 (a lawyer reads the public wording and the privacy text) and HG-18 (set `SELLER_CHECK_HMAC_KEY`).

## What it is

A signed-in member can report a problem with a seller by giving the seller's phone number or bank account number. An admin or reviewer checks the
report and its evidence. Anyone can then type a phone number or account number and see only: "N verified reports, of these types", or "no verified
report found". Nothing else is shown.

## Exception to the PII rule (AGENTS.md: "minimize seller PII")

This feature is a deliberate, limited exception. What is and is not kept:

| Kept | Not kept |
|---|---|
| a keyed hash (HMAC-SHA-256) of the normalised phone or account number | the number itself |
| the last 4 digits (admin screen only, to tell reports apart) | seller name, address, profile, photos of a person |
| the problem type from a fixed list, a short note (no phone, link or contact id) | free-form accusations, chat logs |
| up to 5 evidence images per report (admin only, EXIF/GPS removed, outside the web root) | evidence shown to the public |
| the reporter's member id (admin only) | anything about the reporter on a public page |

The HMAC key lives in `.env`, never in the repository or the database. Without the key the feature refuses to run. A leaked database alone does not
reveal numbers, and a number cannot be looked up from the hash without the key.

## Public wording rules

Neutral and factual. Never "scammer", "โกง", "มิจฉาชีพ", never a name, never a percentage or a verdict about a person. The result is a count of
verified reports and their types, plus: "This is what reporters told us and the team checked. It is not a court finding. If this concerns you, you can
dispute it." A disputed report is not counted while the dispute is open.

## Flow

1. Member (consent already given at sign-in) opens the report form: identifier type, number, problem type, note, evidence images. Rate limited.
2. The report is `pending`. Only admin and reviewer see it. They mark it `verified` or `rejected` (a reason is required for rejection). Audited.
3. Public lookup: normalise, HMAC, count `verified` reports grouped by problem type. Rate limited (10 per 10 minutes per address) so the page cannot be
   used to test a list of numbers quickly.
4. Dispute: the person concerned types the number (proof they know it), a short explanation and an email address (stored only on the dispute, admin
   only). The report becomes `disputed` and leaves the count. An admin resolves it: `verified` again (with a note) or `rejected`.
5. Retention: pending reports older than the period the owner sets are deleted (HG-14 sets the number). Not implemented as a job yet.

## Abuse and how it is handled

| Abuse | Answer |
|---|---|
| Fake reports about a competitor | members only, rate limit, evidence, human verification before anything is public, dispute path |
| Testing many numbers through the lookup | rate limit; the answer for an unknown number is the same page shape as for a clean one |
| Harassment by repeating reports | an admin can reject; rejected reports never count |
| Reporter identity exposure | the reporter is never shown; admin screens only |
| Leaked database | numbers are HMACs; evidence folder is outside the web root and served only to admin |

## Not decided here (owner)

Retention period, whether to show the report types or only a count, whether a lawyer wants a different form of words (HG-14).
