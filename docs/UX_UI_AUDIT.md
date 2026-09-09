# UX/UI Audit

Phase 4A audit date: 2026-09-09

Scope: code-level audit of registered public, seller, B2B, and admin routes in `public/index.php`, templates under `app/Views`, and CSS under `public/assets/app.css`.

Validation limit: CODE-LEVEL RESPONSIVE AUDIT ONLY. Browser screenshot/pixel validation was not performed in this milestone.

## Summary

Public UX already avoids the most dangerous failure mode: it does not show mock/test or pending data as real market truth. The main issue is product clarity and information hierarchy. The app has many route-backed modules, but the highest-value user flow is still simple:

```text
Search Product -> Market Price Answer -> Price Range + Confidence -> Supporting Context -> Deal Check / More Detail
```

Admin UX is operationally complete for the current backend but dense. The dashboard should increasingly prioritize what needs action today before showing general counts.

## Issue Matrix

| ID | Priority | Page | Issue | User impact | Recommendation | Implementation scope | Status |
|---|---|---|---|---|---|---|---|
| UX-001 | P1 | Global header/footer | Branding still used `PC Price Radar` in visible UI. | Product identity is inconsistent with ModInspect baseline. | Use ModInspect consistently. | Low-risk copy change. | Fixed |
| UX-002 | P1 | Public pages | Public copy exposed internal terms such as accepted observations and snapshot. | Normal buyers/sellers must understand value without data-pipeline vocabulary. | Use “ข้อมูลที่ผ่านการตรวจแล้ว” and reserve internal terms for Admin/Methodology. | Low-risk copy change. | Fixed |
| UX-003 | P1 | Global CSS/header | External Google Fonts/Material Symbols requests existed. | Breaks offline/zero-cost expectations and creates third-party dependency. | Use local/system font stack for POC; do not require paid/external assets. | Low-risk CSS/header change. | Fixed |
| UX-004 | P1 | Admin dashboard | Metrics are count-heavy and do not clearly order “what needs action today.” | Admin can miss pending reviews, failed jobs, incidents, or paused sources. | Reorganize future dashboard into Attention Required, System State, Market Data. | Design recommendation; larger UI edit deferred. | Documented |
| UX-005 | P1 | Review Queue | Table is information dense with correction and decision controls in one row. | Reviewers must scan too many columns horizontally. | Move toward card/detail review flow with next pending navigation; keep no bulk approval. | Future focused UX implementation. | Documented |
| UX-006 | P2 | Public navigation | Compare, Build, Shop, Seller, Local Match have partial/deferred capability but remain discoverable. | Users may overestimate product completeness. | Primary nav should prioritize Search/Price and Deal Checker; secondary/future modules should be lower prominence or clearly tagged. | Design recommendation; no full nav redesign yet. | Documented |
| UX-007 | P2 | Product Detail | Price card mixes public answer with confidence internals. | Users may read confidence component details before understanding the price answer. | Primary: product, range, median, confidence label, sample/freshness. Secondary: methodology/confidence details. | Design recommendation. | Documented |
| UX-008 | P2 | Price Index | Empty and insufficient states are honest but could give clearer next actions. | Users may hit a dead end when no product/snapshot exists. | Offer search examples and route to methodology/deal checker only when useful. | Future copy/component pass. | Documented |
| UX-009 | P2 | Admin Price Indices | Provenance/hash details are useful but dense. | Operational user may struggle to distinguish “price quality” from audit metadata. | Add compact summary first; keep hash/provenance in expandable detail later. | Future Admin UX. | Documented |
| UX-010 | P2 | Review Analytics | Dataset warning exists but MOCK/REAL separation needs strong visual treatment. | Fixture metrics may be mistaken for live quality. | Use a persistent MOCK/TEST warning band and label all KPI cards. | Future visual refinement. | Documented |
| UX-011 | P2 | Forms | Some icon/text controls depend on ligature text when icon font unavailable. | Offline rendering may show icon names instead of icons. | Replace high-value icon-only semantics with text+icon or local SVG/icon strategy later. | Future component pass. | Documented |
| UX-012 | P2 | Mobile/admin tables | Admin and product-builder tables/cards use wide layouts and min-width tables. | Mobile admin review will require horizontal scroll. | Keep responsive wrappers; owner must visually inspect mobile review/admin flows. | Code-level risk identified. | Documented |
| UX-013 | P3 | Badge/status system | Green review lane, HIGH confidence, healthy source all use status-like colors. | Color alone can blur different status meanings. | Always pair color with text and domain label. | Design system rule. | Documented |
| UX-014 | P3 | Public terminology | English words like Deal Checker, Confidence, Live provider appear mixed with Thai. | Acceptable for prototype but can feel inconsistent. | Define Thai/English terminology table and apply gradually. | Design system rule. | Documented |
| UX-015 | P3 | Local Match | Placeholder is honest but still shows product cards below. | May imply location matching is partly active. | Keep disabled state; consider removing product cards later. | Future copy/layout pass. | Documented |

## Priority Counts

| Priority | Count |
|---|---:|
| P0 | 0 |
| P1 | 5 |
| P2 | 7 |
| P3 | 3 |

## Public Navigation Recommendation

Primary:

- Search / Price Index
- Deal Checker

Secondary:

- Product Detail from search/cards
- Methodology
- Compare
- Build
- Seller Price Advisor

Future/deferred:

- Local Match
- Seller dashboard
- Shop/B2B
- Market Report until enough accepted data exists

Do not give unfinished modules equal prominence with the core price search flow.

## Product Detail Hierarchy

Primary area:

- canonical product identity
- market range Q1-Q3
- median/reference asking price
- confidence label
- sample size
- freshness/last updated

Secondary area:

- deal-check handoff
- limitations
- asking vs sold distinction
- confidence explanation
- price history where enough data exists

Admin/Methodology-only detail:

- cohort
- provenance
- calculation hash
- formula/rule versions
- raw observation IDs

## Admin Dashboard Hierarchy

Recommended order:

1. Attention Required
   - pending reviews
   - failed jobs
   - unresolved source incidents
   - paused/degraded sources
   - no REAL calibration sample
2. System State
   - collection health
   - provider readiness
   - source health
   - latest run status
3. Market Data
   - products
   - accepted observations
   - snapshots
   - confidence status
   - calibration status

## Review Queue Recommendations

- Preserve current server-side decision/correction behavior.
- Add a future card/detail review mode for one observation at a time.
- Show original extraction and final reviewed value side-by-side.
- Keep validation reasons and quality flags visible but grouped.
- Add next/previous pending navigation.
- Do not add bulk approval or approve-all-Green.

## Responsive Audit

CODE-LEVEL RESPONSIVE AUDIT ONLY.

Observed protections:

- `table-card` uses horizontal overflow.
- Public grids collapse at mobile breakpoints.
- Builder sidebars collapse/scroll.
- Admin nav uses horizontal overflow.

Risks requiring owner visual review:

- Review Queue on ~390px mobile.
- Admin Price Indices dense provenance columns.
- Product builder sticky summary on mobile.
- Header actions when authenticated on small screens.
- Product Detail price card wrapping with long product names.

## Accessibility Baseline

Fixed:

- Removed external font dependency that could leave controls visually degraded offline.
- Global brand alt text now uses ModInspect.

Known issues:

- Some controls use visual icon spans whose fallback text is not ideal.
- Disabled controls are visible and text-labeled, but focus behavior should be manually checked.
- Dense Admin tables are semantically valid but difficult for keyboard scanning.
- Future review-card mode should improve heading and landmark structure.

## Owner Visual Review Required

Manually inspect these pages before any full visual redesign:

1. Home
2. Price Index/Search
3. Product Detail
4. Deal Checker
5. Admin Dashboard
6. Review Queue
