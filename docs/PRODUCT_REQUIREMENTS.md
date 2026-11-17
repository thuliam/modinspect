# ModInspect Product Requirements Matrix

Baseline date: 2026-11-14 (Asia/Bangkok). Owner of business scope: the ModInspect owner.
This file reconciles owner/Messenger/LINE/Blueprint inputs. It is **not** a signed-off scope: only rows tagged
`CONFIRMED` are launch commitments. Informal brainstorming never becomes a requirement by being written down here.

Tags: `CONFIRMED` (owner direction, in MVP) · `HYPOTHESIS` (idea to validate) · `DEFERRED` (post-beta) ·
`NEEDS_OWNER_DECISION` (blocked on a business/legal call).

## Source authority

| Source | Authority | Limits |
|---|---|---|
| Owner chat directions / master resume prompt (2026-11-14) | Highest for business intent | Not legal/compliance sign-off |
| `ModInspect_Master_Blueprint_v1.1.md`, `AGENTS.md` | Architecture and invariants | v1.0 is historical |
| Messenger screenshots | Stakeholder ideas | Not scope; not a licence to collect private data |
| LINE export (`[LINE]ThuliaM #Dev.txt`) | Real asking prices the owner collected from Facebook (clarified 2026-11-14) | Text only: no listing URLs, no seller identity, asking not sold. Contains unrelated credentials and personal data, so it is processed locally only. See `docs/DATA_RESEARCH_POLICY.md` |
| Running code + database | Truth for what exists | Docs never prove a feature exists |

## Requirements

| ID | Requirement | Tag | Source | Evidence / status (2026-11-14) |
|---|---|---|---|---|
| R-01 | Search by product/model; never silently merge variants (5600 vs 5600G, 4070 vs 4070 SUPER, Ti/X3D/XT/XTX) | CONFIRMED | Owner, Blueprint | Resolver + 101-case golden set pass targets (`cli/evaluate_validation_quality.php`) |
| R-02 | Show asking-price range, median, sample count, freshness, confidence, methodology; honest insufficient-data state; never imply fair or sold value | CONFIRMED | Owner | Insufficient-data state verified in browser on Home and Product Detail; no eligible snapshot exists yet |
| R-03 | Deal Checker with condition/warranty/accessories caveat and neutral verdict | CONFIRMED | Owner | Route renders (ui_integration); not yet reviewed visually by owner |
| R-04 | Product Master + aliases separate from timestamped observations and immutable evidence | CONFIRMED | Blueprint | Implemented (41 products, 108 aliases) |
| R-05 | Source discovery and user submissions feed a reviewable pipeline; no direct unreviewed price publication | CONFIRMED | Owner, ADR-005 | Pipeline implemented for mock/offline import; community submission **not built** |
| R-06 | Usable Admin: import templates, dry run, review, corrections, snapshots, server-side DataTables, audit | CONFIRMED | Owner | Implemented; official DataTables restored this baseline; owner re-UAT pending |
| R-07 | Public UX polished, fast, mobile-aware, consistent; answer first, not telemetry | CONFIRMED | Owner | **Gap**: see `docs/UX_UI_SYSTEM.md` audit (mobile header, internal text in footer, 40 identical empty cards) |
| R-08 | Free initial audience growth before monetisation | CONFIRMED | Owner | Product posture only |
| R-09 | Brand CI (Corporate Identity) and design tokens as a single design system | CONFIRMED | Owner | Directions drafted; owner choice required |
| R-10 | Reproducible, hash-pinned UI dependencies; clean checkout boots | CONFIRMED | Owner | Delivered this baseline (uncommitted) |
| R-11 | Community reports of wrong/missing price or product; voluntary sold-price reports as moderated evidence | HYPOTHESIS | Messenger | Sprint 4 candidate; needs owner approval of scope and privacy copy. Design record: `docs/decisions/ADR-006-community-feedback-v0-design.md` (2026-11-17, nothing built) |
| R-12 | Seller listing tools / price advisor | DEFERRED | Messenger | Prototype views exist; not launch scope |
| R-13 | Buy/sell matching, local matching | DEFERRED | Messenger | Prototype views only |
| R-14 | Ads, subscriptions, premium tiers | DEFERRED | Messenger | After audience exists |
| R-15 | 5% intermediary/commission | NEEDS_OWNER_DECISION | Messenger chat | Not an approved billing policy; legal/ops design required |
| R-16 | Escrow, payment custody, money movement | NEEDS_OWNER_DECISION | Messenger | Out of scope of every current authorisation |
| R-17 | Public seller blacklist / fraud labels | NEEDS_OWNER_DECISION | Messenger | Do **not** build as public accusations. Needs evidence review, moderation, appeals, privacy/legal review |
| R-18 | Price trend chart | HYPOTHESIS | Messenger | Only when enough independent temporal evidence exists |
| R-19 | Zero external cost until owner approves spending | CONFIRMED | AGENTS.md | In force. The npm tarball download this baseline was free and owner-approved |
| R-20 | No production deploy or public release without explicit owner GO | CONFIRMED | Owner | In force (release HOLD) |

## Top 3 journeys (MVP)

1. **Buyer**: Home search → exact variant → Product Detail (range, median, sample, freshness, confidence or honest no-data) → Deal Checker → Methodology.
2. **Admin**: Dashboard attention → Imports (template → upload → dry run → confirm) → Review Queue → approve/reject/exclude with audit → deterministic snapshot → public read.
3. **Contributor (later)**: report a wrong model/price or voluntary sold price → moderation → evidence pipeline (never an instant price change).

## Decisions needing the owner

See `docs/DECISION_LOG.md` section "Open owner gates".

## Consolidated requirement status (2026-11-15)

Sources: project blueprint, LINE/Messenger owner discussion, owner UI reviews, master prompt v2. Status: CONFIRMED / PROPOSED / DEFERRED / NEEDS_OWNER_DECISION.

| # | Requirement | Status | Note |
|---|---|---|---|
| 1 | Exact model/variant search with Thai/English aliases | CONFIRMED | Resolver + 222 aliases; golden set re-run pending (T1-8) |
| 2 | Product page with honest price or insufficient-data state | CONFIRMED | Evidence explorer added (T1-3) |
| 3 | Deal Checker with neutral verdicts | CONFIRMED | Model facts, trend and nearby models added; no invented depreciation |
| 4 | Evidence traceability, review, deterministic engine, MOCK/REAL separation, asking vs sold | CONFIRMED | Audit tool added |
| 5 | Practical Admin (products, aliases, sources, imports, observations, review, price index, analytics, audit) | CONFIRMED | Admin on the DropX layout; owner visual check pending |
| 6 | Release date, model age, specs, named benchmark, source-specific rank, Compare | PROPOSED | Provider licensing unresolved (matrix drafted); no benchmark data without licence |
| 7 | Used-hardware condition checklist | PROPOSED | Not built; not a certification |
| 8 | Community price report with moderation | PROPOSED / BETA+1 | Evidence only, never sets snapshots |
| 9 | Price trend | CONFIRMED (new) | Monthly buckets with 3+ samples; hidden below 2 months |
| 10 | Backend coarse-location log | CONFIRMED (new) | Province only, admin-only |
| 11 | Seller Center, watchlist, upgrade advisor | DEFERRED | |
| 12 | Marketplace, 5 % fee, escrow, public seller blacklist | DEFERRED | Needs legal/trust design and owner GO |
| 13 | Public visual direction | DECIDED | See UX_UI_SYSTEM (DL-019/DL-021); coral reference superseded |
| 14 | Confidence policy for 100 % owner-text evidence | NEEDS_OWNER_DECISION | T1-6 |

## Owner scope decision 2026-11-18

| # | Decision | Status | Note |
|---|---|---|---|
| 15 | "Ready" = price check + product information (release year, model age, specifications) + condition check; the approved homepage concept (marketplace look) is visual direction only | DECIDED (owner, 2026-11-18) | Marketplace, listings, seller tools, cart, inspection ordering, escrow and fees remain DEFERRED |
| 6 | Release date, model age, specs, benchmark, rank, Compare | PARTIAL | Release year, model age and specifications with manufacturer sources shown on the product page (first slice 2026-11-18). Benchmarks, ranks and price-to-performance BLOCKED by data licences. Compare still to do on specs and price |
| 7 | Used-hardware condition checklist | PARTIAL | Reminders for 6 categories in the deal result; the per-category guide content is not written |
