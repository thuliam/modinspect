# ModInspect Codex Context

## Product Identity

ModInspect is a Thai-first used-hardware market intelligence and trade-enablement platform.

Public message: "เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง".

The durable asset is Product Master + aliases/variants + time-series market observations + evidence/provenance + price snapshots + first-party transaction feedback + community contributions.

## Source Of Truth

1. Existing running code and database schema are the truth for what exists.
2. `ModInspect_Master_Blueprint_v1.1.md` defines current target architecture and delivery direction. `ModInspect_Master_Blueprint_v1.0.md` remains historical reference.
3. `README.md` summarizes the current product and implemented-vs-planned boundary.
4. `design/` contains UI references; preserve current prototype screens unless the task requires UI change.

Do not assume a feature is implemented because a document mentions it.

## Current Phase

Phase 0 foundation is complete. Phase 1 CPU/GPU catalog POC count and alias gate has been reached with seeded fixtures. Phase 2A Coverage Planner is implemented for mock-only job scheduling. Phase 2B Mock Scheduled Runner consumes queued coverage jobs through the existing mock pipeline. Phase 2C Collection Operations Foundation adds failed-job requeue, run summaries, source policy, source incidents, source pause/disable controls, and source-health reporting. Phase 2D-A live-provider infrastructure/preflight exists for Gemini, but live execution is blocked until explicit user approval. Phase 2D-I UI synchronization, Phase 2D-J stability, Phase 3A snapshot provenance, Phase 3B review correction workflow, Phase 3C CPU/GPU validation, Phase 3D Admin authentication/RBAC, Phase 3E price confidence/freshness/source diversity, and Phase 3F review operations/calibration analytics are complete.

## Architecture Rules

- Keep Product Master separate from Market Observation.
- Keep asking prices separate from sold/final prices.
- AI may assist extraction/resolution/review priority, but deterministic PHP code owns validation and price calculation.
- Provider-specific behavior belongs behind interfaces/adapters.
- Raw third-party evidence is private/admin by default.
- Administrative authorization is enforced server-side; UI visibility is never the authorization boundary.
- Human review and administrative mutations must preserve authenticated actor identity where a human session exists.
- Never log or expose credentials, password hashes, session tokens, or API keys.
- Community submissions are evidence/signals and must never directly set market-price snapshots.
- Price confidence is deterministic, versioned, explainable, and derived from snapshot-time evidence quality; it never changes observed prices.
- Historical confidence must be reproducible from preserved snapshot-time inputs.
- Fixture/mock evaluation establishes an engineering baseline but never proves live-market quality.
- Calibration decisions must distinguish fixture metrics from reviewed live-data metrics.
- MODINSPECT ZERO-COST POC POLICY: until the owner explicitly approves spending, external spend target is 0 THB. Do not enable any paid API, paid data source, paid infrastructure, paid crawler/search service, subscription upgrade, metered provider that may incur charges, or provider requiring billing/credit-card exposure. If a future milestone may incur monetary cost, stop and report OWNER_APPROVAL_REQUIRED. Do not silently enable paid/free-trial billing.
- If a source blocks or requests stop, pause it and record the incident. Do not implement bypass/evasion.
- Minimize seller PII; do not collect names, phones, exact addresses, private chats, or profile photos for price intelligence.

## Stack And Conventions

- PHP 8.1+ custom MVC, no framework.
- PDO with prepared statements for database access.
- Routes are registered in `public/index.php`.
- Autoloading maps `App\` to `app/`.
- Controllers extend `App\Core\Controller` and render PHP views under `app/Views`.
- Keep classes small, typed, and explicit.
- Use additive SQL migrations under `database/migrations`; do not rename existing tables/columns casually.
- Every completed milestone must update `docs/PROJECT_STATUS.md` before it can be reported PASS.

## Testing

- Prefer deterministic fixture tests before live providers.
- Run PHP syntax checks on changed PHP files.
- Run `tests/phase0_pipeline_test.php` for the mock acquisition pipeline.
- Do not enable paid APIs, live crawling, or uncontrolled search without explicit user approval.

## Docs Routing

- Product or scope decisions: read `README.md`, `docs/PRODUCT.md`, and the Blueprint.
- Data/schema changes: read `database/schema.sql`, `database/seed.sql`, `docs/DATA_MODEL.md`.
- Collection/provider work: read `docs/COLLECTION.md`.
- Pricing changes: read `docs/PRICE_ENGINE.md` and `app/Services/PriceEngine.php`.
- Architectural changes: read `docs/ARCHITECTURE.md` and `docs/decisions/`.
