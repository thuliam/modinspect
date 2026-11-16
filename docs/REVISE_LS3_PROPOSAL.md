# Revise sprint: what exists, what is missing, and the proposed LS-3

Date: 2026-11-17. Purpose: start the revise session the owner asked for ("there are still many features I think you missed"). This document does the part an agent can do without the owner: it checks every requirement and blueprint feature against the running code, lists what is really missing, and proposes the next sprint with options and a Recommended pick for each decision. The owner's own list of missed features goes into section 5; nothing in sections 3 to 4 is committed scope until the owner approves it.

Method: `docs/PRODUCT_REQUIREMENTS.md` (tags CONFIRMED / HYPOTHESIS / DEFERRED / NEEDS_OWNER_DECISION), `docs/IMPLEMENTATION_GAP_ANALYSIS.md`, Blueprint v1.1 section titles, the route table in `public/index.php`, and live checks of the prototype pages on the local Apache. A feature counts as built only when a route, a service or a test exists.

## 1. What exists today (verified)

| Area | Built | Evidence |
|---|---|---|
| Buyer | search by model with variant discipline, product page with range/median/sample/freshness/confidence/evidence explorer, honest no-data state, Deal Checker, compare, build, guides, methodology, monthly price trend (hidden below 2 months) | routes `/price`, `/price/{slug}`, `/deal-checker`, `/compare`, `/build`, `/guide/{slug}`, `/methodology`; resolver golden set 27 cases; edge-case tests |
| Data pipeline | owner-text and file intake, dedupe across batches, resolver, validation lanes, review queue with corrections, deterministic snapshots with replay, confidence-v2, province log with repost flag, GPU tier-order flags | `cli/owner_intake_*`, `OfflineEvidenceImportService`, `SnapshotConfidenceService`, `PriceLadderCheck`, 49 public prices, all replay MATCH |
| Admin | dashboard, products, aliases, observations, review queue and analytics, price index, snapshot evidence with history and a "missing for medium" card, data health, trends and locations, sources and incidents, collector jobs and imports, articles, audit log; RBAC with three roles | 38 guarded routes (route guard test) |
| Audit | every admin write is audited: product save/toggle, alias save/delete, article save/archive in the controller; review decide/correct, source actions, imports and job requeue inside their services | checked method by method on 2026-11-17 |
| Operations | backup and guarded restore drill, `/health`, robots/sitemap, compression and cache rules, CI on PHP 8.1/8.3/8.4, parity and secret scans | LS-2 H-8..H-13 |
| Prototypes, honestly labeled | seller dashboard and advisor ("partial"), local match ("not open"), market report ("partial"), B2B shop with sample store data ("demo data") | live pages; no invented prices or users were found |

## 2. Where the documents are stale

- `docs/IMPLEMENTATION_GAP_ANALYSIS.md` still says alias maintenance has "no create/edit/review actions" and other admin actions "are not audited". Both are no longer true (aliases are created and deleted in the admin; section 1 lists the audit coverage). Corrected in this commit.
- `docs/PRODUCT_REQUIREMENTS.md` carries the 2026-11-14 evidence column (41 products, "no eligible snapshot exists yet"). Today: 99 active products, 49 public prices. Not rewritten here: the owner-facing status lives in the board and the checklist.

## 3. What is really missing

Grouped by who it helps. "Gate" marks what an agent cannot do alone.

### A. Buyer-facing
| ID | Missing | Source | Note / gate |
|---|---|---|---|
| M-1 | Used-hardware condition checklist inside Deal Checker (neutral, never a certification) | consolidated #7 PROPOSED | buildable now; wording must be reviewed by the owner |
| M-2 | Release date, model age, specs, named benchmark, source-specific rank | consolidated #6 PROPOSED | specs and release dates can come from public facts; **benchmarks need a licence (gate)** |
| M-3 | Price alerts or a watchlist | consolidated #11 DEFERRED | needs buyer accounts or email: privacy copy (gate) and a mail service (cost: zero-cost policy) |
| M-4 | Typo-tolerant and Thai/English search with a measured quality bar | R-01 | no search-quality test exists beyond the resolver golden set |
| M-5 | A price history chart of snapshots per product (the monthly trend exists) | R-18 | data is there (snapshot history, H-6) |
| M-6 | English language option | none | HYPOTHESIS: nobody asked for it yet |

### B. Data engine
| ID | Missing | Source | Note / gate |
|---|---|---|---|
| M-7 | Community feedback loop: report a wrong price/model, voluntary sold-price report, moderation | R-05, R-11, consolidated #8 | not built (no schema, route or UI); **scope and privacy copy need the owner (gate)** |
| M-8 | Admin queue of unresolved listings with alias suggestions | LS-2 H-1/H-2 found 79 unresolved rows | buildable now; replaces guessing aliases blind |
| M-9 | A second, listing-level source in production and a human review of REAL rows | A-4 of the beta checklist | **owner data (gate)** |
| M-10 | Sold-price handling end to end (asking vs sold are separated in the schema, there is no sold snapshot UI) | gap analysis | depends on M-7 |
| M-11 | CPU and RAM plausibility flags (GPUs only today) | H-4 backlog | buildable now |
| M-12 | Refresh planning by demand/priority (Blueprint 8.x tiers, triggered refresh) | Blueprint | collection is manual; automation needs live providers (zero-cost gate) |

### C. Admin and operations
| ID | Missing | Source | Note / gate |
|---|---|---|---|
| M-13 | User management screen (create, disable, change role, reset), MFA | gap analysis "Admin security" | buildable now except MFA delivery (email/app) |
| M-14 | The weekly routine as a screen (upload sanitized file, see the plan, approve) instead of the command line | LS-2 H-14 | buildable now; must keep dry-run first and the audit trail |
| M-15 | Rate limiting for public POSTs and the sitemap | S-7, N-3 | buildable now |
| M-16 | A "beta readiness" panel fed by the same commands as the checklist | LS-2 H-15 | buildable now |
| M-17 | Scheduled backup, off-machine copy, uptime monitor, TLS on HostAtom | D-2, D-3, C-3 | **host setup (gate)** |

### D. Platform and quality
| ID | Missing | Note / gate |
|---|---|---|
| M-18 | Heading levels in the footer and card lists | template pass with a visual diff |
| M-19 | WebP/AVIF images and responsive srcset | needs an image tool (download approval) |
| M-20 | Library upgrade (Bootstrap, jQuery) with a refreshed hash manifest | **download approval (gate)** |
| M-21 | Independent security check | not the code author |

## 4. Decisions, options and Recommended

**D-1 Community feedback (M-7).** Option 1: design only (decision record, schema proposal, privacy copy draft, no code). Option 2: build a minimal "report a problem" form that stores a moderated signal and never touches a snapshot. Option 3: also build sold-price reports. **Recommended: 1 now, 2 after the owner approves the privacy copy.** Reason: the data rule (signals are evidence, never prices) is easy to get wrong, and the privacy wording is the owner's call.

**D-2 Accounts and watchlists (M-3).** Option 1: none until a second source exists. Option 2: anonymous "save this check" links that already exist (Deal Checker results by uuid) plus a documented limit. Option 3: real accounts with email. **Recommended: 1.** Reason: alerts only help if prices change and are trusted; today every price is one kind of evidence, and accounts add privacy and mail-cost duties.

**D-3 Intake as a screen (M-14).** Option 1: keep the command line. Option 2: a guarded admin screen over the same services (upload, dry run, plan, approve with the existing rules). **Recommended: 2.** Reason: it removes the only step that needs a technical operator and keeps every rule in one place.

**D-4 Specs and benchmarks (M-2).** Option 1: specs and release dates from public facts only, no benchmarks. Option 2: also benchmark ranks from a licensed source. **Recommended: 1.** Reason: a licence is a money and legal decision (provider matrix is drafted, nothing is signed).

**D-5 Unresolved listings (M-8).** Option 1: admin queue with alias suggestions the reviewer accepts or rejects. Option 2: automatic aliases. **Recommended: 1.** Reason: automatic aliases are how a Ti becomes a plain model (the resolver bug of LS-2 H-1).

## 4b. Proposed LS-3 queue (what the long run can do alone)

Order is by value per risk. S, M and L are rough commit counts (S 1 to 2, M 3 to 6, L 7 or more), not durations.

| ID | Task | Size | Gate |
|---|---|---|---|
| J-1 | Unresolved listings queue with alias suggestions (M-8, D-5) | M | none; alias writes go through the existing alias admin with audit |
| J-2 | User management screen with audit and tests (M-13, without MFA) | M | none |
| J-3 | Intake screen over the existing services (M-14, D-3) | L | none; first slice is dry run and plan only |
| J-4 | Rate limiting for public POSTs and sitemap cache (M-15) | S | none |
| J-5 | Search quality: a golden query set with typos and Thai/English forms, measured before any change (M-4) | M | none |
| J-6 | Deal Checker condition checklist, label unchanged (M-1) | M | owner reviews the wording at the end |
| J-7 | Snapshot price history chart per product (M-5) | S | none (library already vendored) |
| J-8 | CPU and RAM plausibility flags on Data health (M-11) | S | none |
| J-9 | Beta readiness panel in admin (M-16) | S | none |
| J-10 | Community feedback design record and schema proposal (D-1 option 1) | S | owner approval before any build |
| J-11 | Footer and card heading levels with a visual diff (M-18) | S | none |
| J-12 | Specs and release dates from public facts for the catalog, no benchmarks (M-2, D-4 option 1) | L | owner sees the list before any DEV write |

Not in the queue because only the owner can do them: second source and human REAL review (M-9), host setup (M-17), library upgrade and images (M-19, M-20), independent security check (M-21), privacy and terms copy.

## 5. Owner's list of missed features (to be filled)

Plain words are enough. The agent maps each line to a requirement tag and to M-/J- rows above, or adds a new row.

| # | Feature in the owner's words | Who benefits | Must / Should / Could | Mapped to |
|---|---|---|---|---|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

Questions that would change the plan most:
1. Is the first real audience buyers, sellers, or shops? (it decides whether M-1/M-5 or the seller tools come first)
2. Is a second listing-level source realistic in the next weeks, and from where? (it decides whether M-7 and M-10 matter yet)
3. Is there any budget at all, for example a mail service or a licensed benchmark source? (today: 0 THB)
