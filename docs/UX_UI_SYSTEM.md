# ModInspect UX/UI System and Brand CI

Status: **DIRECTION RE-CHOSEN (G-3, DL-016, owner, 2026-11-14): the owner reference style (black page, monospace headings, coral accent, grey nav pill with attached coral button, point-cloud hero, link-list footer) supersedes the C look. Built on all public pages; the range-bar answer block stays, restyled dark with a coral band. Thai body copy uses a Thai-capable sans (monospace faces have no Thai); no font file is bundled yet. The paragraph below is history.**

Earlier status: **DIRECTION CHOSEN (G-3, owner, 2026-11-14): C "Market Board" with A's range-bar answer block.** FIRST SLICE BUILT: tokens (`--mb-*` in `public/assets/app.css`, `body.mb`), `components/range-answer.php`, `PriceRangeLayout`. Page structure waits for the owner reference site; no font file is bundled yet (system stack, Noto Sans Thai if installed). Sections 1-2 remain proposals; section 3 and 4 token values are to be re-derived for this hybrid at implementation (Sprint 2). Not yet built.

Hybrid brief: take C's look (clean white page, warm grey text, friendly rounded controls, Noto Sans Thai) and replace C's yellow-highlighted price figure with A's answer block: the Q1 / median / Q3 range drawn as a horizontal band with a median tick, figures in tabular numerals, and the asking-price caveat, sample count, freshness and confidence directly under it. Guardrails: (1) the band is the one accent on the page and must reach 3:1 contrast against its track (a pale yellow on white will not, so the accent colour is chosen at implementation with a contrast check); (2) keep the neutral, evidence-led tone so C does not drift into a marketplace look; (3) status is text plus colour, never colour alone; (4) the preview numbers were placeholders, no figure is shown without approved REAL data and the honest no-data state stays.
"CI" in this file means **Corporate Identity**. Engineering CI is `docs/CI_TEST_MATRIX.md`.
Existing references: `design/` prototype screens, `docs/DESIGN_SYSTEM.md` (Admin, Concept-based), `docs/UX_UI_AUDIT.md` (older).

## 1. Audit of the current public site (2026-11-14, isolated test DB, signed in as a test admin)

Evidence: browser at 1440 and 375 wide on Home and Product Detail. Deal Checker and Search were not viewed in a browser.

| # | Finding | Severity | Page |
|---|---|:-:|---|
| P1 | Honest no-data state works: "ยังไม่มีราคาตลาด / ข้อมูลราคายังไม่เพียงพอ", no invented price | Good | Home, Detail |
| P2 | At 375px the header overflows: admin identity chip and logout wrap into tall boxes and push the main nav off screen | High | all public |
| P3 | Admin identity ("UAT Admin · admin", logout) shows in the public header; public and admin chrome are mixed | Medium | all public |
| P4 | Footer shows internal text "External spend target: 0 THB" and prototype links with "(กำลังพัฒนา)" | Medium | footer |
| P5 | Home lists 40-odd near-identical cards that all say "no market price yet"; no ordering by data availability | High | Home |
| P6 | Hero headline breaks mid-word in Thai ("คอมมือ / สอง"); no word-aware wrapping | Medium | Home |
| P7 | No logo/wordmark system, tiny "MODINSPECT" overline, POC disclaimer box competes with search | Medium | Home |
| P8 | Product Detail is clear but the left spec table and right status card are visually equal; the answer is not the loudest thing | Low | Detail |
| P9 | Sign-in button icon on the login page renders as a tofu glyph; Font Awesome CSS points at `*.html` font files (404, falls back to `.woff`) | Low | login, admin |

## 2. Per-page contract (public)

| Page | Persona | Top question | Primary action | One main answer | Empty / error | Mobile |
|---|---|---|---|---|---|---|
| Home | Buyer | "รุ่นนี้มือสองประมาณเท่าไร" | Search | The search itself, then products that **have** data | No data anywhere: say so once, suggest Deal Checker | single column, search first |
| Search / Price Index | Buyer | "รุ่นไหนตรงกับที่เจอ" | Open product | Exact variant identity, data availability | No match: suggest nearest variants, never merge | list, not cards |
| Product Detail | Buyer/Seller | "ช่วงราคา แหล่ง ความใหม่ เชื่อได้แค่ไหน" | Open Deal Checker | Q1 / median / Q3, sample count, freshness, confidence | Insufficient: explain threshold, next actions | answer above specs |
| Deal Checker | Buyer | "ราคานี้โอเคไหม" | Check | Neutral band position with caveats | Insufficient data is a first-class result | one-hand form |
| Methodology | Any | "คำนวณอย่างไร เชื่อได้ไหม" | none | Asking vs sold, confidence, exclusions | n/a | readable article |

Measurable success (to be instrumented later): time to first answer under 15 s on mobile; zero dead-end states; zero unsupported price claims.

## 3. Brand CI directions (choose one, gate G-3)

All three keep the product neutral, Thai-first and evidence-led. They differ in character, not in features.

| | A. Spec Sheet | B. Bench Instrument | C. Market Board |
|---|---|---|---|
| Character | calm, exact, plainspoken | precise, technical, dense | friendly, local, approachable |
| World | datasheets and price tags on paper | bench meters and logs | Thai second-hand market boards |
| Neutrals | warm paper `#F6F3EC`, ink `#1B1A17` | cool graphite `#14171B` to `#F2F4F6` | clean white, warm grey text |
| Accent | one signal colour used only for the answer (price band) | one instrument teal meaning "data present" | warm yellow highlight on key numbers |
| Type (Thai + Latin) | Sarabun or IBM Plex Sans Thai; mono for figures | IBM Plex Sans Thai + IBM Plex Mono | Noto Sans Thai; rounded numerals |
| Best fit | trust, neutrality, reads as documentation | closest to current Admin (navy Concept sidebar), strongest data credibility | best for mass audience growth |
| Risk | can feel plain | can feel cold for casual buyers | can drift toward marketplace look the brief rejects |

**Recommendation: A (Spec Sheet), with B's tabular figures and mono for numbers.** It makes the answer (the range) the
single bright element, keeps chrome quiet, and differentiates from marketplace clones. Final fonts require a licence and Thai
glyph check before adoption (all candidates are OFL).

Brand identity items still to produce after the choice: wordmark and mark (flat, no gradient), favicon, tone of voice
(neutral, no hype, no "AI-powered"), image rules (product photos only when licensed; no stock hardware renders), icon grammar
(Font Awesome, one weight, text-role colour).

## 4. Design tokens (proposal, direction A values shown)

Centralised in one file when adopted. No per-page colours or fonts.

| Token group | Rule |
|---|---|
| Spacing | 8px base, 4px half-steps; page gutter 16 mobile / 24 desktop |
| Radius | control 8, card 12, panel 16; inner = outer minus padding |
| Neutral ramp | background, raised surface, hairline (ink at 8%), text primary/secondary/muted |
| Accent | one; reserved for the price band, selection and primary action |
| Status | text plus colour, never colour alone: confidence high/medium/low/insufficient, provenance listing-level/search-result/legacy, dataset REAL/TEST |
| Type scale | 12, 14, 16, 20, 28, 40; weights 400 and 600; tabular figures for all numbers |
| Motion | colour/opacity/transform only, 120 to 200 ms, one easing curve, none looping |

## 5. Component policy

- Bootstrap **4** is the grid, forms, nav, modal and utilities for both Admin and Public until a deliberate migration is approved. Never mix Bootstrap 5 JS or markup.
- jQuery and Font Awesome stay (already in the stack, hash-pinned in `public/assets/admin/vendor/MANIFEST.json`).
- Operational tables use official server-side DataTables only. Table options must not collide with `data-*` init attributes (DL-002).
- SweetAlert2, a lightbox (GLightbox/Fancybox/PhotoSwipe) and a rich-text editor are **not** added until a story needs them; each needs a licence/version check, a pinned download and server-side sanitisation (editor).
- One small central CSS layer for tokens and genuinely app-specific layout. No per-page CSS patches.
- Public and Admin share tokens and type but not layout: Public is mobile-first and answer-first; Admin is desktop-first and dense.

## 6. Acceptance for the public redesign (Sprint 2)

Viewports 390, 768, 1024, 1366, 1440 and a large desktop; keyboard reachability; contrast at least 4.5:1 for text;
no browser errors or dead actions; screenshots without secrets or PII; honest states for both populated and zero-price products;
**explicit owner visual acceptance** (not assumed).
