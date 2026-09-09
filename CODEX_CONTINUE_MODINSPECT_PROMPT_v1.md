# Codex Continuation Prompt — ModInspect

You are taking over active development of an existing local project:

```text
C:\xampp8.1\htdocs\modinspect
```

Act as a **Senior Software Architect + Senior PHP/Backend Engineer + Data/AI Pipeline Engineer**.

Your job is **not** to rebuild the project from scratch and not to blindly copy architecture from any other project.  
Your job is to inspect the existing ModInspect implementation, understand the two new project documents, reconcile documentation with the real code/database, establish durable Codex project context, and then continue implementation in the correct priority order.

---

# 1. Read these sources first

Read these files completely before changing code:

```text
README.md
ModInspect_Master_Blueprint_v1.0.md
```

Then inspect the existing repository:

```text
app/
config/
database/
design/
public/
.env.example
```

Also inspect any existing:

```text
storage/
tests/
cli/
scripts/
composer.json
.htaccess
```

if present.

Important source-of-truth hierarchy:

1. **Existing running code + current database/schema** = source of truth for what is already implemented.
2. **ModInspect_Master_Blueprint_v1.0.md** = target architecture and delivery direction.
3. **README.md** = current project/product summary and implemented-vs-planned boundary.
4. **design/** = UI/product reference for screens and visual behavior.
5. Do not assume a feature is implemented merely because it appears in a document.
6. Do not delete or rewrite working functionality just to make the code look like the blueprint.

The Blueprint is already an adaptation for ModInspect.  
Do **not** port BYB-specific architecture, Laravel modules, country/month cycles, Data Lake hierarchy, LLM Wiki/RAG, or beverage-market concepts into this repository.

---

# 2. Product definition you must preserve

ModInspect is:

> **A used-hardware market intelligence and trade-enablement platform.**

User-facing message:

> **เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง**

The long-term core asset is:

```text
Canonical Product Master
+ Product Aliases / Variants
+ Time-series Market Observations
+ Evidence / Provenance
+ Price Snapshots
+ First-party Transaction Feedback
```

The project is **data-first, not crawler-first**.

The application must not depend on a single:

- marketplace
- search engine
- crawler
- AI provider
- research provider

Collectors/providers must be replaceable adapters.

---

# 3. Non-negotiable architecture rules

Preserve these rules throughout implementation.

## Product Master vs Market Observation

Product Master contains slow-changing canonical product facts.

Market Observation contains time-sensitive market facts such as:

- asking price
- sold price when separately verified/reported
- condition
- warranty
- box/accessories
- source
- coarse location
- evidence level
- observation timestamp

Never overwrite Product Master with market observations.

## Asking price vs sold price

Never mix:

```text
asking_price
```

with:

```text
sold_price / final_price
```

They are different evidence classes and must remain distinguishable in storage, calculations, UI, reports, and tests.

## AI is not the Price Engine

AI may assist with:

- source discovery
- search/research
- listing classification
- product resolution
- alias discovery
- evidence extraction
- structured extraction
- review prioritization

AI must **not** produce the authoritative market price.

Market reference values must be calculated deterministically by application code from accepted observations.

## Evidence and provenance

Where practical, an accepted observation must be traceable through:

```text
Source
→ Candidate
→ Evidence
→ Extraction
→ Validation
→ Review Decision
→ Accepted Observation
→ Price Snapshot
```

Raw AI output is staging/candidate data, not accepted market truth.

## Provider independence

Provider-specific behavior must sit behind interfaces/adapters.

The core application must remain functional when a provider is disabled.

Future providers may include:

```text
Gemini / Google Search
OpenAI Web Search
Perplexity
Tavily
Brave Search
Public APIs
Supervised browser sampling
User submissions
Seller submissions
Shop feeds
Local Ollama
```

Do not hard-wire business logic to any one provider.

## Source stop rule

If a source blocks automated collection or requests that collection stop:

```text
Pause the source
Record the incident
Do not bypass protections
Reduce freshness/confidence as observations age
Redistribute future jobs to other enabled sources
```

Do not implement CAPTCHA bypass, login bypass, rotating-account evasion, or similar anti-block workarounds.

## Data minimization

Do not collect/store unnecessary seller PII for price intelligence.

Avoid by default:

- seller full name
- phone number
- exact home address
- private chat
- profile photo
- unnecessary account IDs

## Public vs admin evidence

Raw third-party evidence should be private/admin by default.

Do not turn ModInspect into a public mirror of external listings.

---

# 4. Current technical direction

Keep the project lightweight unless inspection proves a stronger reason to change.

Preferred baseline:

```text
PHP 8.1+
Pure PHP MVC
PDO
MySQL / MariaDB
Bootstrap
Apache mod_rewrite
PHP CLI workers / scheduled jobs
Provider APIs through HTTP adapters
```

Python is optional only when a specific worker/data-processing capability clearly benefits from it.

Do not introduce Laravel, Symfony, a vector database, Kubernetes, queues, Redis, or a heavy agent framework merely because they are common architecture patterns.

Avoid premature enterprise infrastructure.

---

# 5. FIRST TASK — audit the real repository before implementation

Before modifying architecture, perform a complete repository audit.

Determine:

### Existing application
- Existing MVC conventions
- Router behavior
- Controllers
- Models
- Services
- Views
- Helpers
- Configuration
- Session/CSRF/security behavior
- Error handling

### Existing database
Read:

```text
database/schema.sql
database/seed.sql
```

Identify what already exists for:

- categories
- brands
- products
- product variants
- aliases
- sources
- collector jobs
- raw candidates
- normalized/staging observations
- accepted observations
- price history/snapshots
- users/roles
- audit logs
- seller/local-match groundwork

Do not create duplicate tables because the Blueprint uses different conceptual names.

Map the **existing schema** to the **Blueprint domains** first.

### Existing features
Verify implementation status for at least:

```text
Home
Price Index
Product Detail
Price History
Deal Checker
Methodology
Compare
Build
Seller Price Advisor
Local Match
Seller
Shop/B2B
Admin screens
```

Classify each as:

```text
IMPLEMENTED
PARTIAL
PLACEHOLDER
NOT IMPLEMENTED
```

### Existing routes
Inspect actual route configuration rather than assuming README routes are correct.

### Existing UI/design
Inspect `design/` and do not break current visual work unless necessary for the data-feasibility scope.

---

# 6. SECOND TASK — establish durable Codex project context

The repository currently has no `AGENTS.md`.

Create a concise root-level:

```text
AGENTS.md
```

It must contain durable rules Codex should automatically follow in future sessions.

Do not copy the entire Blueprint into AGENTS.md.

AGENTS.md should include:

- product identity
- source-of-truth hierarchy
- current phase
- non-negotiable architecture rules
- stack constraints
- coding conventions discovered from the actual repo
- database safety rules
- AI/provider rules
- pricing rules
- security/source-risk rules
- testing expectations
- which docs to read for which type of task

Then create or update a lightweight context structure:

```text
docs/
├── PRODUCT.md
├── ARCHITECTURE.md
├── DATA_MODEL.md
├── COLLECTION.md
├── PRICE_ENGINE.md
└── decisions/
    ├── ADR-001-data-first-provider-independent.md
    ├── ADR-002-product-master-vs-observation.md
    ├── ADR-003-deterministic-price-engine.md
    └── ADR-004-confidence-review-lanes.md

context/
├── project_state.json
├── architecture_rules.yaml
└── glossary.json
```

Only create files that provide useful persistent context.

Do not duplicate 2,000 lines of Blueprint text into multiple documents.

Each file should be concise and implementation-oriented.

### project_state.json

Represent the real audited current state, not assumptions.

Include at least:

```json
{
  "project": "ModInspect",
  "phase": "...",
  "implemented": [],
  "partial": [],
  "next_priorities": [],
  "deferred": [],
  "known_risks": []
}
```

### architecture_rules.yaml

Capture machine-readable constraints such as:

```yaml
pricing:
  llm_authoritative: false
  asking_and_sold_separate: true

collection:
  provider_independent: true
  staging_required: true
  evidence_required: true

review:
  auto_accept_enabled: false
```

During the initial calibration period, keep Green auto-accept disabled unless existing code already has a safe, tested implementation.

---

# 7. THIRD TASK — reconcile Blueprint vs current implementation

Create:

```text
docs/IMPLEMENTATION_GAP_ANALYSIS.md
```

It must contain a practical matrix:

| Area | Current repo | Blueprint target | Gap | Priority | Action |
|---|---|---|---|---|---|

Cover at least:

- Product Master
- Variant/Alias management
- Provider abstraction
- Source policies
- Collection jobs
- Candidate store
- Evidence
- AI extraction contract
- Product resolver
- Validation
- Review queue
- Accepted observations
- Price Engine
- Price snapshots/history
- Source health
- Quality metrics
- Auditability
- Public UI
- Admin security
- Seller
- Local Match
- B2B

Do not implement low-priority features merely because they are listed.

---

# 8. Delivery priority

The next business hypothesis to prove is:

> Can ModInspect automatically acquire enough fresh market evidence, turn it into reliable structured observations, and reduce the owner’s work to reviewing exceptions rather than manually collecting market data?

Therefore use this delivery order.

---

## Phase 0 — Architecture/Test Harness

If missing, implement the smallest required foundation for:

- deterministic contracts
- source/provider representation
- candidate/staging representation
- evidence representation
- accepted observation representation
- audit/run log
- provider interface
- mock provider
- CLI worker entry point
- basic tests/fixtures

Exit test:

```text
A mock collection job can travel end-to-end:
provider
→ candidate
→ extraction/staging
→ validation
→ review state
```

Do not call paid/live providers yet.

---

## Phase 1 — Product Master POC

Target:

```text
10 CPU
10 GPU
```

Implement or complete:

- canonical products
- variants where needed
- aliases
- admin maintenance
- product resolver
- test titles/fixtures

Target:

```text
20 active canonical products
>= 5 useful aliases/product where applicable
resolver accuracy >= 95% on the test set
```

Do not fabricate product master facts merely to reach the count.

Seed only facts supported by existing seed/reference material or clearly marked test fixtures.

---

## Phase 2 — Automated Acquisition POC

Only after Phase 0/1 contracts are stable.

Implement architecture for:

```text
Coverage Planner
Collection Jobs
Primary Provider Adapter
Fallback Provider Adapter
Candidate Store
Evidence
Structured Extraction
Product Resolution
Deterministic Validation
Review Queue
Source Health Metrics
```

### IMPORTANT: free/safe-first

Do not enable paid APIs or live external collection without explicit user approval.

Start with:

```text
Mock provider
Fixtures
Dry-run mode
```

If an existing free provider integration is already present, keep it guarded and disabled by default until tested.

Secrets belong in `.env`, never in source code or database rows intended for normal application data.

---

# 9. Review lanes

Design the validation result so it can support:

## Green
High-confidence records that pass rules.

For the POC:

```text
DO NOT blindly auto-accept.
```

Store/routable as Green, but require controlled calibration/audit before enabling unattended acceptance.

## Amber
Needs human review.

Examples:

- uncertain variant
- bundle ambiguity
- outlier price
- unclear warranty
- weak evidence
- uncertain product match

## Red
Reject/quarantine.

Examples:

- wanted/buying post
- deposit-only price
- whole-PC listing when collecting one component
- unrelated product
- broken evidence
- duplicate
- invalid currency/price

Review records must preserve the reason code and, where applicable:

```text
AI value
rule result
human value
reviewer/action
prompt/model/schema version
timestamp
```

---

# 10. Golden Dataset / regression fixtures

Create a test/evaluation structure appropriate to the current repo.

Preferred:

```text
tests/
fixtures/
evals/
```

Build an initial small foundation now, designed to grow toward 200–500 manually reviewed examples.

Include representative cases such as:

- normal component listing
- bundle
- full PC
- deposit
- wanted post
- defective item
- alias variation
- premium variant
- Thai/English mixed title
- ambiguous warranty

Expected structured output must be explicit.

Track design for:

```text
product resolution accuracy
price extraction accuracy
listing-type accuracy
invalid-listing recall
warranty extraction accuracy
human correction rate
auto-accept precision
cost per valid observation
```

Do not claim these metrics pass until measured.

---

# 11. Price Engine rules

Inspect the existing `app/Services` pricing logic before modifying it.

Preserve working behavior where correct.

The eventual Price Engine should operate only on accepted observations and support:

```text
Q1
Median
Q3
valid sample size
fresh-sample ratio
source count/diversity
confidence
snapshot date
formula/version
```

Outliers, bundles, deposits, whole PCs, invalid prices, wrong products, and inappropriate cohorts must not corrupt the reference range.

Every published/generated snapshot should eventually be reproducible from its accepted observation set.

Do not let an LLM invent or directly publish market-price numbers.

---

# 12. Preserve the existing public prototype

The README indicates existing prototype functionality such as:

- Home/popular products
- Price Index
- Product Detail
- quartile/median display
- confidence/freshness
- price history
- Deal Checker
- Methodology/transparency
- security groundwork

Do not break these while building the data foundation.

If code inspection shows README is inaccurate, update documentation to match reality instead of pretending the feature exists.

---

# 13. Do not prioritize these yet

Do not spend substantial implementation time now on:

```text
Local Match expansion
Marketplace transactions
Escrow
Payments
Chat
Seller ratings
Advanced Seller Center
Large B2B dashboard
Paid API product
Vector DB / RAG
Social-listening enterprise platforms
Complex agent orchestration
Marketing polish
```

Existing placeholder pages/routes may remain.

Do not delete them unless they actively break the app.

---

# 14. Security rules

Current Admin screens are local-prototype only.

Do not expose them publicly without authentication/authorization.

Preserve or improve:

- PDO prepared statements
- output escaping
- CSRF
- secure session defaults

Before public deployment the project will still need:

- authentication
- role authorization
- admin middleware
- rate limiting
- upload validation
- secret handling
- monitoring
- backups
- HTTPS/secure cookies
- location privacy
- retention rules

Do not commit `.env` or API keys.

---

# 15. Coding approach

Use the existing project style unless it is objectively broken.

Prefer:

- small classes
- clear responsibilities
- dependency boundaries
- PDO prepared queries
- deterministic rules
- explicit status fields
- idempotent workers
- structured logs
- no silent failure
- no destructive schema rewrite

If schema changes are needed:

1. inspect current schema first
2. preserve existing data
3. provide additive migration/SQL where possible
4. update seed data only when necessary
5. document the change

Do not casually rename existing tables/columns to match conceptual names in the Blueprint.

---

# 16. Execution behavior

Work autonomously through the audit and safe foundation tasks.

Do not stop merely to explain what you plan to do.

You may inspect files, run commands, edit files, and run tests.

However, stop and request approval before:

- enabling a paid API
- making a real paid-provider call
- enabling uncontrolled live crawling/search
- deleting user/project data
- replacing major existing architecture
- exposing Admin publicly
- performing source-access bypass/evasion

If a command or dependency is uncertain, inspect the repository/environment instead of guessing.

---

# 17. Verification

After changes, verify as much as the local environment allows.

At minimum:

1. PHP syntax check for changed PHP files
2. Load/inspect relevant routes
3. Test DB-compatible code where possible
4. Run any existing test harness
5. Add focused tests/fixtures for new deterministic logic
6. Confirm existing public pages were not unintentionally broken
7. Confirm no secret/API key was committed
8. Confirm no live provider was enabled unintentionally

If Apache/MySQL are unavailable, clearly state which verification could not be executed.

---

# 18. Required final deliverables

When this work session is complete, provide:

## A. Repository audit summary

```text
What existed before
What was incomplete
What was inconsistent with docs
```

## B. Files created/changed

List every relevant file and why.

## C. Context layer

Confirm:

```text
AGENTS.md
docs/*
context/*
ADRs
```

created/updated.

## D. Implementation progress

State exactly which Blueprint phase is now reached:

```text
Phase 0
Phase 1
Phase 2
...
```

Do not overclaim.

## E. Tests/verification

Show commands and results.

## F. Remaining gaps

Prioritized, not a generic backlog.

## G. Next recommended action

Give one concrete next implementation milestone.

Also update:

```text
README.md
context/project_state.json
docs/IMPLEMENTATION_GAP_ANALYSIS.md
```

when the actual implementation state changes.

---

# 19. Immediate instruction

Start now.

First:

1. Read `README.md`.
2. Read `ModInspect_Master_Blueprint_v1.0.md`.
3. Inspect the entire current project tree and current DB schema/seed.
4. Audit the real implementation against the documents.
5. Create the persistent Codex context layer (`AGENTS.md`, concise docs/context/ADRs).
6. Produce the implementation-gap analysis.
7. Continue with the **smallest safe Phase 0/Phase 1 work that is actually missing**, without breaking the existing prototype.
8. Use mock/fixture/dry-run paths for acquisition work; do not spend money or enable uncontrolled live collection.
9. Run verification.
10. Return a concise engineering handoff with exact files changed, tests, current phase, and next milestone.

Do not rewrite the application from scratch.

Do not blindly implement the entire Blueprint in one pass.

Prefer a working, testable data-foundation slice over a large amount of speculative code.
