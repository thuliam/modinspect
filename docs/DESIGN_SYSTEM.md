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
