# ModInspect

> **เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง**

**ModInspect** is a Thai-first used-PC market intelligence platform built with Pure PHP 8.1 MVC.

The project started under the working name **Used PC Price Radar**. The current direction is broader: instead of being only a used-part price lookup website, ModInspect is being developed around a reusable data foundation for product master data, market observations, evidence, transparent price references, deal evaluation, seller tools, and later buyer/seller matching.

**Domain:** `modinspect.com`  
**Current stage:** Local prototype + Phase 4A UX/UI product audit and design foundation; Phase 3H waits for owner-supplied REAL calibration data  
**Primary stack:** PHP 8.1+, Pure MVC, PDO, MySQL/MariaDB, Bootstrap, Apache Rewrite

---

## Product Direction

ModInspect is designed as a **used-hardware market intelligence and trade-enablement platform**.

The public-facing product should stay simple:

- Check current used-PC price references.
- Understand price ranges instead of relying on one arbitrary "average price".
- Inspect condition, warranty, test evidence, and other factors that may justify price differences.
- Evaluate whether a deal is below, within, or above the observed market range using neutral labels.
- Help sellers price products with better context.
- Later support optional local buyer/seller matching for hand-to-hand transactions.

The current architecture baseline is `ModInspect_Master_Blueprint_v1.1.md`; v1.0 remains as historical reference.

Current handoff/status lives in `docs/PROJECT_STATUS.md`. UX/design-system baseline documents live in `docs/UX_UI_AUDIT.md`, `docs/DESIGN_SYSTEM.md`, and `docs/USER_FLOWS.md`.

The long-term core asset is not the UI or a scraper. It is:

```text
Canonical Product Master
+ Product Aliases / Variants
+ Time-series Market Observations
+ Evidence / Provenance
+ Price Snapshots
+ First-party Transaction Feedback
+ Community Contributions
```

---

## Core Architecture Principles

### 1. Data-first, not crawler-first

Collectors and AI providers are replaceable workers.

The application must not depend on one marketplace, one search engine, or one AI provider to remain functional.

### 2. Product Master is separate from Market Observation

A product such as:

```text
AMD Ryzen 7 5700X3D
```

exists once in Product Master.

Prices, condition, warranty, source, location, and observation dates are stored as separate market observations over time.

### 3. AI assists collection and extraction

AI can help with:

- Web/source discovery
- Search/research
- Listing classification
- Product resolution
- Alias detection
- Structured extraction
- Evidence interpretation
- Review prioritization

AI output is not automatically treated as market truth.

### 4. Price calculation is deterministic

LLMs do **not** calculate the authoritative market price.

The Price Engine should calculate transparent statistics such as:

- Median
- Q1 / Q3
- Minimum / maximum after validation
- Sample size
- Freshness
- Source diversity
- Confidence
- Historical snapshots

### 5. Asking price and sold price are different data types

Never present asking-price observations as completed-sale data.

Verified or user-reported sold prices must remain distinguishable from asking prices and may later receive different weighting.

### 6. Evidence and provenance matter

Accepted observations should be traceable back through the acquisition lifecycle where practical:

```text
Source
→ Candidate
→ Evidence
→ AI Extraction
→ Validation
→ Review Decision
→ Accepted Observation
→ Price Snapshot
```

---

## Current Automated Data Collection Direction

The next major engineering goal is to prove that ModInspect can collect usable market data automatically while reducing human work to review and exceptions.

Target pipeline:

```text
Product Master
      ↓
Coverage / Refresh Planner
      ↓
Collection Job
      ↓
Search / Research Provider
      ↓
Candidate Pool
      ↓
Evidence Fetch / Capture
      ↓
AI Extraction
      ↓
Product Resolution
      ↓
Deterministic Validation
      ↓
┌─────────────┬───────────────┬─────────────┐
│ Green       │ Amber         │ Red         │
│ Accepted    │ Human Review  │ Reject/Hold │
└──────┬──────┴───────┬───────┴─────────────┘
       ↓              ↓
      Accepted Market Observations
                     ↓
                Price Engine
                     ↓
               Price Snapshot
                     ↓
               Public Features
```

### Provider-independent design

Provider-specific logic should sit behind adapters so the core system can switch or combine providers such as:

- Gemini / Google Search
- OpenAI Web Search
- Perplexity
- Tavily / Brave Search
- Public APIs
- Supervised browser sampling
- User/seller submissions
- Local Ollama models for low-cost extraction/classification

The production system should be able to disable a provider or source without breaking Product Master, observations, or pricing logic.

### Zero-Cost POC policy

Until the owner explicitly approves spending, ModInspect external spend target is:

```text
0 THB
```

Do not enable paid APIs, paid data sources, paid infrastructure, paid crawler/search services, subscription upgrades, metered providers that may incur charges, or providers requiring billing/credit-card exposure. If a future milestone may incur monetary cost, stop and report `OWNER_APPROVAL_REQUIRED`.

---

## Human Review Model

During calibration, collected records should be reviewed heavily to build a reliable evaluation dataset.

Later, observations can be routed into confidence lanes:

### Green

High-confidence observations that pass deterministic rules.

May be auto-accepted only after the pipeline has demonstrated sufficient accuracy, with post-audit sampling.

### Amber

Ambiguous records that require review, for example:

- Product variant uncertain
- Bundle vs single component unclear
- Price unusually high/low
- Warranty unclear
- Weak evidence
- Product match confidence below threshold

### Red

Automatically reject or quarantine obvious invalid data such as:

- Wanted/buying posts
- Deposit-only prices
- Whole-PC listings when collecting a component
- Unrelated products
- Broken or unusable evidence
- Duplicate observations
- Invalid currency/price

The operating goal is:

> **Automation collects; the owner reviews exceptions rather than manually researching every product.**

---

## Data Feasibility POC

Before expanding the public product, ModInspect should prove the collection pipeline with a small controlled dataset.

Initial target:

```text
Categories:
- CPU
- GPU

Product Masters:
20+

Accepted Observations:
500+

Goals:
- Automatic acquisition
- Evidence retention
- Product normalization
- Review queue
- Price snapshot calculation
- Measurable collection quality
```

Important metrics include:

- Valid observation rate
- Product-resolution accuracy
- Price-extraction accuracy
- Duplicate rate
- Human correction rate
- Human review time
- Source freshness
- Source dependency
- Provider usage/cost
- Cost per valid observation

---

## Run with Laragon / STUDIO

1. Copy `.env.example` to `.env` and adjust database credentials if needed.
2. On STUDIO, the project is served by Laragon from `C:\laragon\www\modinspect` and is edited from the client as `N:\modinspect`.
3. The current shared DEV database is HostAtom/DirectAdmin Cloud MariaDB: `thsv16.hostatom.com:3306` / `thuliam_modinspect`.
4. Store the DB password only in local `.env`. Do not commit, print, or document it.
5. Ensure Apache `mod_rewrite` is enabled and `AllowOverride All` applies to the Laragon project directory.
6. Open:

```text
http://192.168.1.10/modinspect/public/
```

Laragon SSL is temporarily disabled because `vpnserver_x64` owns TCP/443. Do not re-enable or change HTTPS as part of ordinary development tasks.

The default base URL is configurable with `APP_URL`.

## Automated Tests

Automated tests must never use the shared Cloud DEV database.

Use a local isolated TEST database:

```text
DB_HOST=127.0.0.1
DB_DATABASE=modinspect_test
```

1. Copy `.env.testing.example` to `.env.testing` and adjust local MySQL credentials if needed.
2. Bootstrap or rebuild the local test DB:

```text
php tests/bootstrap_test_db.php --fresh
```

3. Run the sequential test suite:

```text
php tests/run.php
```

Tests load `.env.testing` through `tests/bootstrap.php` and fail closed with `UNSAFE_TEST_DATABASE_BLOCKED` if the Cloud DEV host `thsv16.hostatom.com` or Cloud DEV database `thuliam_modinspect` is detected.

---

## Current Project Structure

```text
modinspect/
├── app/
│   ├── Core/          # Router, DB connection, base controller, CSRF
│   ├── Controllers/   # Request handlers
│   ├── Models/        # PDO queries
│   ├── Services/      # Pricing / evaluation / application rules
│   └── Views/         # Thai-first responsive templates
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── public/
│   ├── index.php
│   └── assets/
│
├── design/
├── storage/
└── .env.example
```

As the automated data pipeline is implemented, the architecture should evolve toward clear acquisition, evidence, observation, review, and pricing boundaries without forcing unnecessary framework or enterprise complexity into the prototype.

---

## Implemented Prototype MVP

Current prototype functionality includes:

- Home and popular products
- Searchable/filterable price index
- Product detail
- Quartile price range
- Median/reference pricing
- Confidence and freshness display
- Price history
- Deal Checker
- Neutral price labels
- Methodology/transparency page
- Prepared statements
- Output escaping
- CSRF protection
- Strict session-cookie defaults

### Audited Implementation Boundary

As of 2026-09-07, this repository is a custom PHP MVC prototype with seeded/sample data. Public routes and local admin views exist, but automated market acquisition is not yet live.

Implemented:

- Route-backed Home, Price Index, Product Detail, Deal Checker, Methodology, Compare, Build, Seller Price Advisor, Seller, Local Match, Market Report, Shop/B2B, and Admin prototype screens.
- Product Master, variants, aliases, sources, jobs, raw observations, observations, price indices, price histories, users/roles, audit logs, and shop inventory tables.
- Phase 0 fixture-only acquisition contracts: mock provider, rule-based extraction, product resolver, validator lanes, and CLI dry run.
- DB-backed mock collection persistence for candidates, private evidence metadata, extraction runs, review decisions, and pending observations.
- Deterministic price snapshot service that calculates Q1/median/Q3 from approved asking-price observations.
- CPU/GPU POC catalog gate in the local DB: at least 10 active CPUs and 10 active GPUs, with at least 5 aliases per active CPU/GPU POC product.
- Deterministic Coverage Planner for CPU/GPU sample gaps, freshness gaps, last observations, last collection attempts, active queued/running jobs, and recent failures.
- Mock scheduled collection runner for queued `coverage_mock_collection` jobs with safe claiming, bounded batch execution, dry-run mode, explicit failed-job state, attempt counts, persisted run summaries, and run audit logs.
- Collection operations foundation: explicit failed-job requeue CLI, source pause/disable controls, source incident logging, source policy fields, and deterministic source-health reporting.
- Phase 2D-A live-provider infrastructure for Gemini/Google Search POC: provider registry, deterministic query planner, hard request guard, Gemini adapter, preflight/dry-run CLI, fixture response tests, and zero live requests by default.
- Phase 2D-I application integration: audited visible routes/actions, wired Admin operations dashboard to real DB counts, wired collector-job requeue and source controls to existing services, made review queue list/action real pending records, and disabled unsupported UI actions explicitly.
- Phase 2D-J stability pass: duplicate review submissions are blocked, mock-source observations are excluded from public snapshot cohorts, collection job claiming has an atomic regression check, and a read-only data integrity checker is available.
- Phase 3A snapshot provenance: new snapshots persist formula/cohort/quartile/confidence method versions, calculation hash, manifest JSON, and relational included/excluded observation membership.
- Phase 3B review correction workflow: reviewers can save auditable field-level corrections for supported observation fields before approve/reject/exclude. Original raw evidence and extraction payloads remain immutable, final approved values are validated, and sold/final observations can use `price_value` without fake `asking_price`.
- Phase 3C category-specific validation: deterministic CPU/GPU rule packs add suffix-aware model checks, mobile GPU detection, bundle/whole-PC/wanted/deposit/defective/multiple-price flags, validation rule versions, and an offline golden-dataset evaluator.
- Phase 3D Admin authentication and RBAC: Admin routes require login, static role permissions protect review/source/job actions server-side, authenticated actor identity reaches review corrections and audit logs, and initial accounts are created by CLI with secure PHP password hashing.
- Phase 3E price confidence: new snapshots use `confidence-v1`, a deterministic component model for sample size, freshness, source diversity, and evidence quality. Confidence is persisted in the snapshot manifest, included in reproduction/hash checks, shown on public price views, and inspectable in Admin/CLI.
- Phase 3F review operations and calibration analytics: review lifecycle, lane outcomes, clean-Green precision, correction frequency, quality-flag frequency, source/provider yield, and POC gate readiness are reported offline with explicit MOCK/TEST vs REAL separation.
- Phase 3G offline evidence intake: CSV/JSON files can be imported as an offline acquisition adapter through the same candidate, evidence, extraction, resolver, validation, review, correction, and snapshot-provenance pipeline. Dataset mode defaults to TEST; REAL must be explicit and still requires human review.
- Phase 4A UX/UI product audit and design foundation: public/admin UX audit, design-system baseline, user-flow documentation, zero-cost policy, offline asset dependency cleanup, ModInspect branding consistency, and public terminology cleanup.

Partial or not yet implemented:

- Admin product/alias create/edit actions remain read-only/disabled.
- Public price displays require accepted observations behind the latest snapshot; otherwise the UI shows "ข้อมูลตลาดยังไม่เพียงพอ".
- Live provider collection is not enabled; queued coverage jobs are consumed by the mock scheduled runner only.
- Gemini live-provider execution is infrastructure-only until explicit approval; default env blocks live calls.
- Evidence capture/fetching and AI provider integrations are not enabled.
- Authentication/authorization middleware is required before Admin can be exposed publicly.

Run the Phase 0 fixture smoke test:

```text
php tests/phase0_pipeline_test.php
php tests/phase1_catalog_test.php
php tests/review_decision_test.php
php tests/price_snapshot_test.php
php tests/coverage_planner_test.php
php tests/snapshot_loop_test.php
php tests/snapshot_provenance_test.php
php tests/review_correction_test.php
php cli/collect.php --mock --dry-run --query=5700x3d
php cli/collect.php --mock --query=5700x3d
php cli/coverage.php --dry-run --limit=5
php cli/coverage.php --dry-run --summary --limit=5
php cli/coverage.php --create-jobs --limit=25
php cli/coverage.php --create-jobs --limit=25 --attempt-cooldown-minutes=60
php cli/run_collection_jobs.php --dry-run --limit=5
php cli/run_collection_jobs.php --limit=5
php cli/run_collection_jobs.php --job-id=123
php cli/requeue_collection_job.php --job-id=123 --dry-run
php cli/requeue_collection_job.php --job-id=123
php cli/source_health.php
php cli/source_control.php --source-id=1 --action=pause --reason="manual review"
php cli/source_control.php --source-id=1 --action=incident --incident-type=blocked --reason="blocked by source"
php cli/data_integrity_check.php
php cli/reproduce_price_snapshot.php --snapshot-id=123
php cli/price_confidence_report.php --snapshot-id=123
php cli/evaluate_validation_quality.php
php cli/review_calibration_report.php
php cli/import_market_evidence.php --file=examples/import/market_evidence_template.json --dataset=test --dry-run
php cli/import_market_evidence.php --file=storage/import/real_sample.csv --dataset=real --limit=100
php cli/create_admin_user.php --email=admin@example.com --password-env=MODINSPECT_ADMIN_PASSWORD
php cli/auth_status.php
php cli/live_collection_poc.php --provider=gemini --product-id=1 --max-requests=3 --dry-run
php cli/live_collection_poc.php --provider=gemini --product-id=1 --max-requests=3 --preflight
php cli/snapshot.php
```

The current schema also contains groundwork for:

- Collector jobs
- Raw / normalized observations
- Product aliases
- Price histories
- Articles
- Users
- Roles
- Audit logs
- Admin / data-pipeline workflows

---

## Application Routes

### Public

```text
/
/price
/price/{slug}
/deal-checker
/compare
/build
/seller-price-advisor
/guide/{slug}
/methodology
/local-match
/market-report
```

### Seller

```text
/seller
```

### B2B

```text
/shop
/shop/inventory
```

### Admin

```text
/login
/logout
/admin
/admin/products
/admin/product-aliases
/admin/price-observations
/admin/review-queue
/admin/review-analytics
/admin/price-indices
/admin/sources
/admin/sources/action
/admin/collector-jobs
/admin/collector-jobs/requeue
/admin/articles
/admin/audit-logs
```

> Admin screens require local account authentication and static role-based permissions. Production deployment still requires TLS, server hardening, backup/access policy, and external security review.

---

## Price Communication Rules

ModInspect should remain neutral toward both buyers and sellers.

Avoid labels such as:

```text
Scam
Bad seller
Too expensive
Do not buy
```

when the conclusion is based only on price.

Prefer:

```text
Below observed range
Within observed market range
Slightly above observed range
Premium price
Insufficient data
```

A higher asking price may be justified by factors such as:

- Remaining warranty
- Premium variant
- Original box/accessories
- Receipt
- Proven condition
- Test/benchmark evidence
- Shop warranty
- After-sales service

Every published price reference should aim to expose:

```text
Price range
Median
Sample size
Last updated
Freshness
Confidence
Data type: asking vs sold
Relevant limitations
```

Snapshot confidence is data-quality metadata only. It is deterministic, versioned, and derived from snapshot-time sample size, freshness, source diversity, and evidence quality. It does not change observed prices, review decisions, or the quartile formula.

---

## Product Master Direction

The canonical catalog should support:

```text
Category
Brand
Product family
Model
Variant
Specification
Aliases
Lifecycle / active state
```

Example:

```text
GPU
└── NVIDIA GeForce RTX 3070
    ├── ASUS TUF Gaming OC
    ├── ASUS ROG Strix
    ├── MSI Gaming X Trio
    └── Gigabyte Gaming OC
```

Aliases should resolve messy market text into canonical products, for example:

```text
5700x3d
R7 5700X3D
Ryzen5700x3d
AMD 5700 X3D

→ AMD Ryzen 7 5700X3D
```

---

## Observation Direction

Market data should be stored as observations instead of overwriting Product Master.

An accepted observation may contain:

```text
product
variant
source
price_type
asking_price / sold_price
condition
warranty
box/accessories
location
evidence level
observed_at
accepted_at
acceptance mode
```

This allows ModInspect to build price history and recalculate historical snapshots when pricing rules improve.

---

## Source and Evidence Strategy

Potential source classes include:

### First-party / permissioned

- Seller submissions
- User-submitted deal checks
- Future community feedback/evidence submissions
- Shop feeds
- CSV imports
- Future partner APIs

Community submissions are evidence and market signals, not votes. They may raise review priority or collection priority, but they must not directly set or adjust public price snapshots.

### Public research/search

- Search-provider results
- Public product/listing pages
- Public retailer or used-hardware sources
- Search-grounded AI research

### Supervised sampling

Human-triggered browser/screenshot collection may be used for bootstrap or research where appropriate, subject to source rules and platform restrictions.

### External benchmarks

- New-product pricing
- International used-price references
- Manufacturer specifications

External benchmarks should not automatically be mixed into the Thai used-price cohort.

---

## Source Safety Rule

If a source blocks automated access, requires bypassing protections, or otherwise becomes unsuitable:

> **Pause/disable that source. Do not build the business around bypassing the block.**

The system should continue using other acquisition adapters and retain clear freshness/confidence information when source coverage drops.

---

## Security Notes

Current prototype protections include prepared queries, escaping, CSRF, and stricter session defaults.
Admin protections now include local account login, secure PHP password hashing, session ID rotation after login, idle/absolute session timeout checks, static role permissions, server-side action authorization, bounded login attempt throttling, and authenticated actor lineage for human review/admin mutations.

Before public release, add or verify:

- TLS and secure reverse-proxy/server headers
- Rate limiting
- Secure secret handling
- Upload validation
- Evidence access controls
- Production logging/monitoring
- Database backups
- Error-page hardening
- HTTPS / secure cookies
- Data-retention rules
- Location privacy controls

Do not commit API keys or provider secrets.

---

## Location / Local Match Direction

Local Match is intended as an optional discovery tool rather than an escrow or transaction service.

The system may later support:

- Buyer intent
- Seller intent
- Province/district filtering
- Approximate distance
- Price compatibility
- Product compatibility
- Contact requests
- Hand-to-hand meetup preference

Public interfaces should not expose exact home addresses or precise coordinates by default.

---

## Future Product Layers

Once the data pipeline is proven, the same data foundation can support:

```text
Price Index
Price History
Deal Checker
Screenshot Deal Check
Product Comparison
Used-PC Build Planner
Seller Price Advisor
Seller Evidence Profile
Local Match
Buyer Demand Insights
Shop Dashboard
Market Reports
Data Export
Future API
```

---

## Development Priorities

### Phase 0 — Foundation / test harness

- Confirm architecture boundaries
- Establish fixtures/evaluation cases
- Keep current prototype stable

### Phase 1 — Product Master POC

- CPU/GPU master catalog
- Aliases
- Variants
- Admin maintenance

### Phase 2 — Automated Acquisition POC

- Collection jobs
- Provider adapters
- Candidate records
- Evidence
- AI extraction
- Product resolution
- Validation
- Review queue
- Offline CSV/JSON calibration intake

### Phase 3 — Price Engine & Admin Intelligence

- Accepted observations
- Price snapshots
- Source/freshness metrics
- Confidence
- Review analytics
- Category-specific quality metrics
- Snapshot provenance and formula versioning
- Review correction workflow and observation quality controls
- CPU/GPU category-specific validation rules
- Price confidence, freshness, and source diversity
- Review operations and calibration analytics
- Offline real-world evidence intake and calibration sample

Offline REAL Import is not Live Provider collection. It is a local file-based acquisition adapter for externally collected evidence and makes no network requests. It never creates accepted observations, review decisions, or snapshots directly.

### Phase 4 — Public Read-only MVP

- UX/UI product audit and design foundation
- Search
- Product detail
- Price index/history
- Transparency

### Phase 4.5 — Community Data Feedback Loop

- Price/data feedback
- Evidence submission
- Community review queue
- Multidimensional community confidence
- Contributor reputation foundation
- `SOLD_PRICE_REPORT`
- Community signals for Coverage Planner priority

This is approved architecture only and is not implemented yet.

### Later

- Deal Checker / screenshot intake
- Seller Center
- Local Match beta
- First-party sold-price feedback
- B2B shop tools
- Data/API products

---

## Non-Goals for the Initial Product

ModInspect is **not** initially intended to become:

- Payment processor
- Escrow service
- Transaction guarantor
- Dispute-resolution service
- Full marketplace with platform-held payments
- Automatic seller trust authority

The platform provides data, tools, evidence context, and optional discovery/matching.

---

## Documentation

The implementation should treat the repository documentation as versioned project knowledge.

Recommended project docs:

```text
AGENTS.md
README.md

docs/
├── PRODUCT.md
├── ARCHITECTURE.md
├── COLLECTION.md
├── DATA_MODEL.md
├── PRICE_ENGINE.md
├── UI_INTEGRATION_AUDIT.md
├── UX_UI_AUDIT.md
├── DESIGN_SYSTEM.md
├── USER_FLOWS.md
└── decisions/
    └── ADR-005-community-feedback-is-evidence-not-voting.md
```

Machine-readable project context may later live in:

```text
context/
├── project_state.json
├── architecture_rules.yaml
└── glossary.json
```

Evaluation fixtures should live alongside the code so collector/extractor changes can be regression-tested.

---

## Working Principle

```text
AI collects and recommends.
Rules validate.
Evidence supports.
Humans review ambiguity.
The Price Engine calculates.
ModInspect publishes transparent market references.
```
