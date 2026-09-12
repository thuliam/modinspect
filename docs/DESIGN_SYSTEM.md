# ModInspect Design System

Phase 4A baseline: 2026-09-09

This is a product-design foundation for the current Pure PHP MVC prototype. It documents the baseline and safe conventions; it is not a full visual redesign.

## Principles

- Public users should get the price answer first, then confidence and methodology.
- Admin users should see attention-required work before passive counts.
- Status must use text plus color; color alone is never the only signal.
- Unfinished features must be visibly disabled or labeled as not enabled.
- Mock/test data must be labeled and must not look like production market truth.
- No external font/icon/CDN dependency is required for the zero-cost POC.

## Typography

- UI font: `--font-ui`, currently system Thai-safe stack: `"Segoe UI", Tahoma, Arial, sans-serif`.
- Monospace/status font: `--font-mono`, currently `"Consolas", "Courier New", monospace`.
- Public copy should avoid internal data-pipeline terms where possible.
- Admin copy may use technical terms when they support auditability.

## Spacing And Radius

- Prefer an 8px spacing rhythm for page and component layout.
- Use compact cards/panels; cards should normally stay at 6px radius in the current CSS.
- Do not place UI cards inside other UI cards.
- Use table wrappers for wide operational data instead of forcing mobile overflow into the page.

## Buttons And Actions

- Primary action: one clearly dominant action per task area.
- Secondary action: outline/ghost style.
- Dangerous action: explicit danger styling and clear label.
- Disabled future action: visible disabled control with text such as `กำลังพัฒนา`, `ยังไม่เปิดใช้งาน`, or `ต้องตั้งค่า Provider ก่อน`.
- State-changing Admin actions must use CSRF and server-side authorization.

## Forms

- Every input needs a visible label.
- Validation errors should be near the affected form or in a clear alert.
- Do not fake a successful action if backend capability is absent.
- Material review corrections require a correction reason.

## Tables

- Use `.table-card` or equivalent overflow wrappers for Admin tables.
- Include table headings.
- Empty tables should show a reason and next action, not a blank body.
- Dense audit/provenance tables are Admin-only.

## Status Badges

| Domain | Values | Meaning |
|---|---|---|
| Review lane | GREEN / AMBER / RED | Validation routing; not auto-acceptance. |
| Confidence | LOW / MEDIUM / HIGH | Snapshot evidence quality; not deal quality. |
| Source health | HEALTHY / DEGRADED / PAUSED / UNKNOWN | Operational source state. |
| Dataset | MOCK / TEST / REAL | Evidence origin class. REAL is not automatically verified. |
| Review decision | PENDING / APPROVED / REJECTED / EXCLUDED | Human review outcome. |

## Color Semantics

- Success/healthy: operation is functioning or data passed a gate.
- Warning/review: ambiguity, limited confidence, pending action.
- Danger/problem: failed, blocked, invalid, rejected.
- Neutral/info: metadata, methodology, audit detail.

Different domains must keep labels visible so `GREEN`, `HIGH`, and `HEALTHY` are not confused.

## Confidence Labels

Public UI should show:

- `HIGH`: enough fresh, diverse, stronger evidence.
- `MEDIUM`: useful but limited by sample size, diversity, freshness, or evidence quality.
- `LOW`: insufficient, stale, single-source, or weak evidence.

Admin UI may show component scores, reason codes, weights, caps, and source distribution.

## Phase 4B Public Components

The Phase 4B public flow uses these conventions for Home, Price Index/Search, Product Detail, and Deal Checker:

- Product cards show product identity first, then `ช่วงราคาที่พบ`, `ราคากลาง`, confidence label, and sample count when market data is sufficient.
- Product cards and detail pages show `ข้อมูลตลาดยังไม่เพียงพอ` instead of inventing price ranges when accepted data is not sufficient.
- Confidence badges keep `HIGH`, `MEDIUM`, or `LOW` visible alongside Thai explanatory text; numeric scores stay secondary/Admin-only.
- Public detail pages should not expose raw observation IDs, calculation hashes, validation rule versions, or provenance internals.
- Deal Checker wording must remain neutral: below range, in range, slightly above range, premium asking price, or insufficient data.
- Primary public navigation should keep Price Index/Search, Deal Checker, and Methodology prominent; prototype modules stay secondary or clearly marked as coming soon.

## Review Lanes

- GREEN: strong product resolution, valid standalone component evidence, no critical flags.
- AMBER: human-resolvable ambiguity.
- RED: structurally invalid or unsafe for normal market observation.

Human review remains mandatory. Do not add approve-all-Green.

## Empty States

Every empty state should answer:

- what happened
- whether it is expected
- what the user/admin can do next

Examples:

- Public no snapshot: `ข้อมูลตลาดยังไม่เพียงพอ`.
- Live provider not configured: `ต้องตั้งค่า Provider ก่อน`.
- No REAL calibration sample: tell the owner to place a CSV/JSON file under `storage/import/`.

## Public Terminology

| Internal term | Public term |
|---|---|
| accepted observation | ข้อมูลที่ผ่านการตรวจแล้ว |
| snapshot | ชุดข้อมูลราคาล่าสุด |
| confidence | ความน่าเชื่อถือของข้อมูล |
| cohort | ชุดข้อมูลราคา |
| provenance | ที่มาของข้อมูล |
| candidate | รายการที่พบ |

Keep `calculation hash`, `validation rule version`, raw observation IDs, and detailed provenance primarily in Admin/Methodology.

## Current Status

Phase 4A documents the baseline and applies only low-risk consistency fixes. Full visual validation requires owner review of the pages listed in `docs/UX_UI_AUDIT.md`.

## Admin UI Rebuild - Concept Design System

Updated: 2026-09-12

Status: READY FOR OWNER VISUAL REVIEW.

Reference:

- Concept Bootstrap 4 Admin Dashboard Template by Colorlib.
- Local reference inspected from `X:\concept` / `\\192.168.1.10\htdocs\concept`.
- Attribution is preserved in `public/assets/admin/README.md`; copied assets remain limited to the admin namespace.

Admin shell:

- Fixed top navbar with ModInspect Admin branding, current page context, public-site link, logged-in identity, role, and logout action.
- Persistent dark left sidebar on desktop with grouped navigation: Dashboard, Catalog, Market Data, Collection, Content, and System.
- Content pages use a page title/context area followed by cards, tables, filters, or forms.
- Admin-specific CSS/JS lives under `public/assets/admin/` and does not replace the public frontend design layer.

Admin technology:

- Bootstrap 4 markup and local Bootstrap assets.
- jQuery for sidebar toggle, tooltips, and lightweight table filtering.
- Font Awesome for navigation and action icons.
- DataTables Bootstrap styling assets are local; runtime initialization is guarded because the Concept reference used a CDN for the core DataTables script and the zero-cost POC should not add CDN/external calls.

Review console:

- Review Queue defaults to REAL + pending, paginates at 25 records, and sorts AMBER before GREEN.
- Queue rows show only ID, dataset, product, listing title, asking/listing price, source, validation lane, warning count, observed date, and Review action.
- Review Detail shows one observation with summary, original evidence, extraction/resolution, collapsed correction controls, and explicit approve/reject/exclude actions.
- GREEN remains a review acceleration signal only; it never means auto-approved.
- AMBER reasons must be human-readable, with raw rule details in collapsible Advanced / Technical Details.

Admin tables:

- Default columns should be operationally meaningful; hide hashes, raw JSON, and long provenance payloads behind detail or disclosure patterns.
- Use text plus badge color for dataset, review status, source health, and validation lane.
- Keep actions in the rightmost column and use primary, secondary, and danger styling consistently.

## Admin UI Visual Fix Round

Updated: 2026-09-12

Status: READY FOR OWNER VISUAL REVIEW.

Sidebar contrast:

- The dark sidebar must override Bootstrap's `.navbar-light .navbar-nav .nav-link` color with admin-sidebar-specific selectors.
- Normal menu labels use light neutral text on the dark navy background.
- Icons use the same readable contrast family as labels.
- Hover, focus, and active states use a stronger blue-tinted background, visible left rail, and white text/icons.
- Section headings stay muted but legible; they must never render as black on the dark sidebar.

Review Queue thumbnails:

- Queue rows include a compact 64px visual slot before the observation ID.
- Priority is existing Product Master image, then category placeholder icon.
- Existing Product Master images come from `products.image_path` and local files under `public/assets/images/`.
- Current REAL Priceza/offline import evidence does not store listing thumbnail URLs, and current CSV/JSON import headers do not define thumbnail/image fields.
- Do not fabricate listing images or mutate pending REAL evidence for decoration.
- Future thumbnail capture should add explicit public thumbnail provenance to the offline import contract and persistence model before any UI backfill.

## Admin Review Detail Fix Round

Updated: 2026-09-13

Status: READY FOR OWNER VISUAL REVIEW.

Review Detail layout:

- Keep Review Queue accepted; do not redesign it unless a shared component bug requires it.
- Review Detail should use a main evidence/resolution workspace plus a right-side decision panel on desktop.
- Approve / Reject / Exclude is the primary page action and should be reachable in the first desktop viewport for normal records.
- The decision panel may be sticky on desktop, but must not float over or hide content.
- Correction controls are secondary and must stay collapsed by default in a compact `Edit / Correct Data` disclosure.
- Do not reserve a full empty panel for collapsed correction controls.

Source and imagery semantics:

- Original Market Evidence owns the single primary `Open Source` action.
- Do not duplicate source CTA buttons across the header and evidence card.
- Product Master imagery must be labeled `Product Master image`.
- Do not imply Product Master imagery is a listing photo or source/evidence thumbnail.

Validation presentation:

- GREEN means validation found no critical issue; it still requires explicit human review.
- AMBER must show a human-readable reason near the decision action.
- Validation version markers, rule IDs, and raw payloads belong in collapsed Advanced / Technical Details.
- LOW_CONFIDENCE should identify the failing confidence dimension when available. Observation #2111 is AMBER because product-resolution confidence recomputed to `0.8637`, below the Green threshold of `0.95`, even though extraction confidence and stored observation confidence display as 95%.
