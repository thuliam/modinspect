# ModInspect — Master Architecture, Data Strategy & Delivery Blueprint

**Version:** 1.1  
**Status:** Architecture baseline for implementation  
**Primary objective:** Build an automated, evidence-backed used-PC market intelligence system that can collect data continuously, structure it into reliable master/market data, calculate transparent price references, and later support buyer/seller matching and B2B analytics.  
**Working domain:** `modinspect.com`  
**Primary technical direction:** Pure PHP 8.1+ MVC + MySQL/MariaDB + CLI/cron workers + provider adapters  
**Reference studied:** BYB NPD Radar architecture and operating controls. BYB is used as a source of design lessons only; this document is a fresh ModInspect design and is not a BYB handoff or port.

---

# Changelog

## v1.1

- Added Community Data Feedback Loop.
- Added community contribution data domain.
- Added multidimensional community-confidence model.
- Added contributor reputation concept.
- Added anti-manipulation principles.
- Added community signal to Coverage Planner feedback.
- Added `SOLD_PRICE_REPORT` concept.
- Added Phase 4.5 roadmap milestone.
- Added ADR-005.

## v1.0

- Initial master architecture, data strategy, and delivery blueprint.

---

# 0. Executive Decision

ModInspect should not be designed as “a website that scrapes prices.”

It should be designed as:

> **A used-hardware market intelligence and trade-enablement platform whose core asset is canonical product master data + time-series market observations + evidence + real transaction feedback.**

The public-facing product remains simple:

> **เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง**

But the system behind it is a data platform.

The critical hypothesis to prove first is not UI, SEO, Seller Center, or Local Match. It is:

> **Can ModInspect automatically acquire enough fresh market evidence, convert it into structured observations with high accuracy, and reduce human work to review/exceptions rather than manual collection?**

If this pipeline works, Price Index, Deal Checker, Seller Price Advisor, Local Match, Shop Dashboard, and future APIs can share the same data foundation.

---

# 1. What We Learn From BYB — Keep, Adapt, Reject

BYB provides useful patterns, but ModInspect has a very different domain, cadence, user base, and scale. The correct approach is selective adaptation.

## 1.1 Keep as Design Principles

### A. Automated acquisition must stop before blind truth

BYB separates automated acquisition/extraction from human-approved facts. ModInspect should preserve this concept, but adapt the human gate for high-frequency market data.

Use:

```text
Acquire automatically
→ Stage automatically
→ Validate automatically
→ Route by confidence
→ Human review only where required
→ Promote to market observation
→ Recalculate index
```

### B. Provider-switch architecture

Do not bind the business to Gemini, OpenAI, Anthropic, Perplexity, Brave, Tavily, or any one collector.

The system owns an abstraction layer; providers are replaceable workers.

### C. Source policy must be explicit

Every source has:

- access method
- allowed purpose
- evidence quality
- reliability score
- freshness expectation
- rate/cost budget
- enabled/disabled state
- blocked/paused state
- terms/risk note

### D. Staging is different from accepted market data

Raw AI output is not truth.

Every collected value must be traceable through:

```text
source
→ candidate
→ evidence
→ extraction
→ validation
→ review decision
→ accepted observation
```

### E. Audit, rollback, prompt/model versioning

When AI output changes, we must know:

- provider
- model
- prompt/schema version
- source/evidence
- extraction result
- rule result
- human correction
- final accepted value

### F. Guarded live operations

Production collection should have hard switches for:

- provider enabled
- live search enabled
- auto-promotion enabled
- source enabled
- daily budget
- maximum jobs/run
- emergency kill switch

## 1.2 Adapt for ModInspect

| BYB pattern | ModInspect adaptation |
|---|---|
| Monthly country/category cycle | Rolling product freshness and coverage queue |
| Market research job | Product/source collection job |
| Field catalog | Observation schema + product-category extraction rules |
| Source policy | Source policy + evidence quality + collection method |
| Collected-value staging | Market-observation staging |
| Published market fact | Accepted price/condition observation |
| Monthly publish | Rolling price snapshot generation |
| Theme/trend extraction | Product alias, demand signal, price/condition extraction |
| Data lake | Lightweight Acquisition Zone / evidence store |
| Agent feedback | Extraction correction + rule/prompt regression dataset |
| Human promote every fact | Confidence lanes: auto-accept only after calibration, manual exception review |

## 1.3 Do Not Port From BYB

Do **not** copy these just because they exist in BYB:

- country/month cycle architecture
- beverage trend/theme/opportunity models
- large enterprise Data Lake folder hierarchy
- LLM Wiki / RAG layer
- heavy Agent Studio before product-market validation
- multi-stage report center
- scoring frameworks unrelated to used hardware
- Laravel module architecture if Pure PHP MVC remains the chosen ModInspect stack
- manual approval for every single high-confidence observation forever

ModInspect needs a smaller, faster, rolling-data architecture.

---

# 2. Product Definition

## 2.1 Positioning

### Internal definition

**Used Hardware Market Intelligence & Trade Enablement Platform**

### User-facing definition

A platform that helps people:

- understand current used-PC price ranges
- inspect condition, warranty, and test evidence
- check whether a deal is unusual or reasonable
- price an item for sale using market evidence
- find nearby buyers/sellers for optional face-to-face handoff

## 2.2 Core User Groups

### Buyer

Needs:

- current price range
- comparison with new product alternatives
- condition/warranty context
- test evidence
- risk flags
- nearby sellers

### Seller

Needs:

- market position of asking price
- ability to justify premium through warranty/condition/tests
- suggested fast-sale / market / premium bands
- nearby buyer demand

### Shop / Reseller

Needs later:

- inventory pricing
- aging risk
- local demand
- buy-price guidance
- market trend
- CSV/API/reporting

## 2.3 Non-Goals for Initial Product

ModInspect will not initially:

- hold buyer money
- provide escrow
- guarantee products or sellers
- decide disputes
- certify hardware as fault-free
- scrape through CAPTCHA/login bypass
- auto-contact sellers
- auto-publish unverified AI claims as market truth

---

# 3. The Core Data Asset

ModInspect data must be separated into six related domains.

```text
A. PRODUCT MASTER
B. MARKET OBSERVATIONS
C. EVIDENCE
D. MARKET SIGNALS
E. FIRST-PARTY OUTCOMES
F. COMMUNITY CONTRIBUTIONS
```

## 3.1 Product Master

Slow-changing canonical facts.

Examples:

- category
- manufacturer
- family
- model
- variant
- core specifications
- release date
- MSRP/reference launch price when available
- aliases
- compatibility relationships

## 3.2 Market Observations

Time-sensitive facts observed in the market.

Examples:

- asking price
- condition
- warranty remaining
- box/receipt
- listing type
- location at coarse level
- source
- observed time
- availability status

One product master can have thousands of observations.

## 3.3 Evidence

Proof supporting an observation.

Examples:

- source URL
- search result
- screenshot
- page excerpt
- seller-submitted image
- benchmark result image
- warranty receipt image

Evidence is not the same thing as the accepted structured observation.

## 3.4 Market Signals

Signals that influence collection priority but should not automatically become price facts.

Examples:

- search interest
- buy/wanted mentions
- sell/clearance mentions
- new alias/slang
- product popularity
- price-drop discussions
- new community/source discovery

## 3.5 First-Party Outcomes

This is the long-term moat.

Examples:

- seller marked sold
- reported negotiated price
- buyer confirmed purchase
- local match connected
- listing expired unsold
- days-to-sell
- seller asking vs reported final price

Long-term value grows when ModInspect moves from only `asking_price` toward verified or corroborated transaction outcomes.

## 3.6 Community Contributions

Community contributions are user-submitted corrections, observations, and evidence about product data or market data.

Examples:

- price appears lower than the current snapshot
- price appears higher than the current snapshot
- product/model/variant information is wrong
- specification information is wrong
- market data appears stale
- condition or warranty context is missing
- user has evidence of an actual sold/final price
- invalid listing or duplicate evidence report

Locked principle:

> **Community input is evidence, not a vote.**

Users must never directly change the published market price. Community reports enter an evidence/review pipeline and may become accepted market observations only after controlled validation and review. The deterministic Price Engine remains the only mechanism that recalculates market references.

Target flow:

```text
Published Product / Price Snapshot
        ↓
User Feedback
        ↓
Community Submission
        ↓
Evidence + Structured Fields
        ↓
Community Confidence / Scoring
        ↓
Priority / Review Queue
        ↓
Admin Review
        ↓
Accepted Market Observation
        ↓
Deterministic Price Engine
        ↓
New Price Snapshot
```

Admin must not manually type or set a new market price as the result of a community report. The correct action is to accept evidence or accept an observation, then let the deterministic Price Engine recalculate the public reference.

### Feedback UX Concept

A future product page may ask:

```text
ข้อมูลนี้ตรงกับที่คุณเจอไหม?
```

Possible actions:

- ใกล้เคียงตลาด
- ฉันเจอราคาต่ำกว่านี้
- ฉันเจอราคาสูงกว่านี้
- ข้อมูลสินค้าไม่ถูกต้อง
- ข้อมูลดูเก่าแล้ว
- ฉันมีราคาซื้อขายจริง

Price feedback may optionally include reported price, condition, warranty remaining, source type, source URL, screenshot/evidence, observed date, product/variant, and notes. Exact UX and required fields remain future implementation decisions.

### Feedback Type Taxonomy

Initial conceptual taxonomy:

```text
PRICE_LOWER
PRICE_HIGHER
PRICE_SIMILAR
PRODUCT_INFO_WRONG
VARIANT_WRONG
SPEC_WRONG
MARKET_DATA_STALE
LISTING_INVALID
CONDITION_CONTEXT
WARRANTY_CONTEXT
SOLD_PRICE_REPORT
```

`SOLD_PRICE_REPORT` is strategically important because it can help ModInspect distinguish asking price from actual/final transaction price and eventually measure asking-to-sold gaps. It is not verified transaction truth by default.

### Community Submission Domain

Future conceptual table/domain:

```text
community_submissions
- id
- user_id nullable
- product_id
- variant_id nullable
- feedback_type
- reported_price nullable
- condition_code nullable
- warranty_months nullable
- source_type nullable
- source_url nullable
- evidence_id nullable
- submitted_at
- evidence_score
- contributor_trust_score
- consensus_score
- freshness_score
- community_confidence_score
- status
- reviewed_at nullable
- reviewed_by nullable
- review_reason nullable
```

Potential statuses:

```text
pending
needs_review
accepted
rejected
duplicate
expired
```

This is approved architecture only. Do not create a migration until the Community Data Feedback Loop milestone.

### Community Confidence Dimensions

Do not model community trust as one unexplained score.

Use distinct dimensions:

- Evidence Score: strength of evidence, from no evidence through source URL, screenshot, identifiable listing/source, or stronger first-party transaction evidence.
- Contributor Trust Score: historical reliability of the contributor.
- Consensus Score: whether independent submissions support a similar observation.
- Freshness Score: recency of the reported market information.

These dimensions may derive a Community Confidence Score later. Exact formulas and thresholds must be calibrated. No community score may directly update the price index.

Illustrative policy:

```text
low confidence       → store only
medium confidence    → pending
high confidence      → review queue
very high confidence → high-priority review
```

Even very high-confidence submissions become evidence/review candidates, not direct price updates.

### Contributor Reputation

Future internal levels may include:

```text
New
Reliable
Trusted
Expert
```

Reputation should be based on measurable outcomes such as submissions, accepted submissions, rejected submissions, duplicate/spam rate, evidence quality, and correction accuracy. This is for internal weighting, abuse resistance, and review prioritization, not a social-network feature.

### Anti-Manipulation Principles

Community feedback creates manipulation risk. Protect against:

- mass price manipulation
- sellers attempting to inflate market prices
- buyers attempting to depress market prices
- duplicate reports
- duplicate URLs
- duplicate screenshots/evidence
- coordinated submissions
- new-account spam
- Sybil/multi-account behavior
- repeated reporting of the reporter's own listing
- extreme-price brigading

Ten unsupported votes must not automatically outweigh one strong piece of market evidence. Consensus must consider independence and evidence quality, not only count. Abuse controls should be privacy-conscious and avoid unnecessary personal-data collection.

### Community Signal to Collection Priority

Community feedback should also become a market signal for automated collection priority.

Example:

```text
Multiple recent users report:
"RTX 3070 market price appears lower than ModInspect"
        ↓
community anomaly signal
        ↓
product data-staleness suspicion
        ↓
Coverage Planner priority increases
        ↓
AI/Search Collector rechecks product
        ↓
new independent market evidence
        ↓
review / accepted observations
        ↓
updated snapshot
```

This creates a feedback loop between users, community signals, collection priority, automated research, and accepted market observations. This is a future Coverage Planner input, not current implementation.

### Community Submission vs First-Party Outcome

A community submission and a first-party transaction outcome are not automatically equivalent.

Example:

```text
User report: "I saw a 5700X3D sold for 5,900"
```

This may initially be a community `SOLD_PRICE_REPORT` with a verification level. If sufficiently verified through a future transaction/outcome mechanism, it may become stronger first-party transaction evidence. Do not merge unverified reports into verified sold-price data.

### Public Transparency

Future public output may show:

```text
Market observations: 42
Verified community contributions: 11
Last updated: 2 hours ago
```

Only accepted community-derived observations should affect public market statistics. Raw, rejected, and pending reports must not be represented as accepted market evidence.

Future UI may include:

```text
เจอข้อมูลต่างจากนี้? ช่วยอัปเดตตลาด
```

### Strategic Value

External collectors cannot observe the whole market. Users are distributed throughout the market.

Community contributions can:

- detect stale data
- discover missing prices
- discover aliases/variants
- detect product-data errors
- provide evidence from sources collectors cannot consistently reach
- surface actual transaction-price signals
- trigger fresh automated research

This can become a long-term data moat if it remains evidence-backed, auditable, and resistant to manipulation.

---

# 4. Product Master Architecture

## 4.1 Hierarchy

```text
Category
  ↓
Brand / Manufacturer
  ↓
Product Family
  ↓
Product Model
  ↓
Variant
```

Example:

```text
CPU
└─ AMD
   └─ Ryzen 7
      └─ Ryzen 7 5700X3D
```

GPU example:

```text
GPU
└─ NVIDIA GeForce
   └─ RTX 3070
      ├─ ASUS TUF Gaming OC
      ├─ ASUS ROG Strix OC
      ├─ MSI Gaming X Trio
      └─ Gigabyte Gaming OC
```

## 4.2 Alias Dictionary

The alias layer is essential to collection accuracy.

Example aliases for one product:

```text
5700x3d
r7 5700x3d
ryzen5700x3d
amd 5700 x3d
ryzen 7 x3d 5700
```

Each alias should track:

- target product/variant
- normalized text
- source
- confidence
- reviewed status
- first/last observed date

AI may suggest aliases automatically, but new aliases should enter staging first.

## 4.3 Product Master Acquisition

Product master creation should also be AI-assisted.

Pipeline:

```text
Category seed
→ manufacturer/model discovery
→ official/reference source research
→ AI structured extraction
→ duplicate/product-resolution check
→ master-data review
→ active canonical product
```

Priority of master-data sources:

1. manufacturer official specification
2. trusted structured reference/data feed
3. retailer/reference source for missing commercial fields
4. AI inference only as a suggestion, never canonical fact without evidence

## 4.4 Variant Strategy

Store variants even when there are not enough samples for a separate index.

Price publication rule:

- always resolve base product where possible
- record variant when identifiable
- publish base-model index first
- publish variant-specific index only when sample count and diversity are sufficient
- otherwise display variant as context/premium factor, not a fake precise index

---

# 5. Automatic AI Data Collection — Target Operating Model

This is the most important system in the project.

## 5.1 Design Goal

The desired daily experience is:

```text
Nightly/continuous automation runs
        ↓
AI/search workers collect candidates
        ↓
System validates and stages results
        ↓
Low-quality data is rejected automatically
        ↓
Only exceptions appear in Admin Review
        ↓
Owner reviews/corrects later
```

The founder should **not** manually search every product.

## 5.2 Collection Roles

Do not make one “AI Agent” do everything.

Split responsibilities.

### 1. Coverage Planner

Decides **what needs data now**.

Inputs:

- product popularity
- last successful collection
- current valid sample count
- confidence score
- observed price volatility
- user search demand
- seller/buyer interest
- source health

Output:

```text
collection_jobs
```

### 2. Source Scout

Finds **where useful data exists**.

Best suited to:

- Deep Research
- broad web research
- weekly source discovery
- identifying forums, shops, communities, feeds, APIs
- finding new model aliases and market vocabulary

This is not the daily price collector.

### 3. Routine Search Collector

Runs frequent product-specific queries.

Examples:

```text
"5700X3D มือสอง"
"ขาย Ryzen 7 5700X3D"
"5700X3D ประกัน"
"RTX 3070 TUF มือสอง"
```

Provider examples can include:

- search-grounded LLM APIs
- search APIs
- official marketplace/retailer APIs when available
- public allowed sources

### 4. Evidence Fetcher

Attempts to convert a search candidate into inspectable evidence.

It should determine:

- URL reachable?
- page date detectable?
- title/price visible?
- source type?
- evidence freshness?

### 5. AI Extractor

Converts evidence into strict structured data.

Example output:

```json
{
  "product_candidate": "AMD Ryzen 7 5700X3D",
  "asking_price": 6290,
  "currency": "THB",
  "listing_type": "single_component",
  "condition": "used_good",
  "warranty_months": 5,
  "has_box": true,
  "has_receipt": null,
  "is_wanted_post": false,
  "is_deposit": false,
  "is_defective": false,
  "is_whole_pc": false,
  "location_text": "Bangkok",
  "evidence_confidence": 0.94
}
```

### 6. Product Resolver

Matches extracted text to canonical product/variant.

Uses:

- aliases
- token normalization
- category constraints
- brand/model rules
- local LLM for ambiguous titles

### 7. Deterministic Validator

PHP rules decide whether data can proceed.

The validator—not the LLM—owns hard business rules.

### 8. Human Review

Only handles:

- ambiguous product
- unexpected price
- unclear listing type
- conflicting evidence
- new alias
- new source
- low-confidence extraction

### 9. Price Engine

Consumes accepted observations only.

---

# 6. Deep Research and Social Listening

## 6.1 Deep Research Role

Deep Research should run as a **Scout**, not as the high-volume collector.

Suggested cadence:

- weekly for active categories
- monthly for mature categories
- on-demand when coverage suddenly degrades

Tasks:

- discover new sources
- identify newly launched products entering the used market
- find new aliases/slang
- detect platform/source changes
- find communities/forums/stores with useful public evidence
- summarize market anomalies for investigation

Output should be `source_candidates`, `product_candidates`, and `market_signals`, not a final price index.

## 6.2 Social Listening Role

Social listening is primarily a **demand and discovery signal**.

Track:

- mentions of model aliases
- “หา/รับ/ต้องการ” type intent
- “ขาย/ปล่อย” type intent
- recurring faults/issues
- price-drop talk
- hot products

Do not treat a social mention as price evidence unless the item includes inspectable price/listing evidence and passes the normal observation pipeline.

## 6.3 Social Listening Lite for MVP

Avoid enterprise listening platforms initially.

Start with search-based signals:

```text
<model> + ขาย
<model> + ปล่อย
<model> + รับ
<model> + หา
<model> + มือสอง
```

Store counts and detected source domains as `market_signals`.

These signals can raise or lower Collection Priority.

---

# 7. Provider-Independent Acquisition Architecture

## 7.1 Interfaces

The application should depend on interfaces, not vendor SDK logic.

```php
interface SearchProviderInterface
{
    public function search(SearchRequest $request): SearchResultSet;
}

interface ResearchProviderInterface
{
    public function research(ResearchRequest $request): ResearchResult;
}

interface ExtractionProviderInterface
{
    public function extract(ExtractionRequest $request): ExtractionResult;
}

interface VisionProviderInterface
{
    public function extractImage(VisionRequest $request): ExtractionResult;
}
```

Implementations can change:

```text
GeminiSearchProvider
OpenAIWebSearchProvider
PerplexitySearchProvider
BraveSearchProvider
TavilySearchProvider
LocalQwenExtractionProvider
OpenAIExtractionProvider
GeminiVisionProvider
```

## 7.2 Provider Waterfall

Do not call every provider for every product.

Example:

```text
Primary free/low-cost provider
     ↓
Enough valid candidates?
     ├─ Yes → stop
     └─ No
          ↓
Secondary provider
     ↓
Still insufficient?
     ├─ No → stop
     └─ Yes
          ↓
Escalation / research job
```

## 7.3 Premium Model Role

Powerful/expensive models should be escalation tools.

Use them for:

- difficult listing decomposition
- ambiguous model resolution
- source discovery
- complex research
- prompt/rule debugging

Do not use premium reasoning models for every routine listing.

---

# 8. Collection Priority Engine

Not all products should be refreshed equally.

## 8.1 Suggested Priority Factors

```text
priority =
    popularity_score
  × freshness_need
  × low_sample_need
  × volatility_factor
  × user_demand_factor
  × source_availability_factor
```

Actual implementation can use a normalized weighted score rather than literal multiplication.

## 8.2 Tiers

### HOT

- high search/user demand
- volatile price
- new product
- low current freshness

Refresh: daily or more often when justified.

### WARM

- stable popular product

Refresh: every 2–3 days.

### COLD

- low-volume older product

Refresh: weekly or on user request.

## 8.3 Triggered Refresh

A user Deal Check can trigger a data-gap event:

```text
User asks about product
→ current index low confidence/stale
→ enqueue priority refresh
```

This creates demand-driven collection without constantly spending API quota.

---

# 9. Candidate and Evidence Lifecycle

## 9.1 Candidate State

```text
new
→ evidence_pending
→ extracted
→ validated
→ review_required
→ accepted
→ rejected
→ expired
```

## 9.2 Evidence Levels

Suggested initial levels:

| Level | Evidence | Typical use |
|---|---|---|
| A | screenshot/page evidence clearly shows product + price | strongest asking-price evidence |
| B | reachable URL with parseable product + price | strong |
| C | search-grounded result with partial page verification | medium |
| D | search snippet only | discovery / low weight |
| E | AI summary without inspectable evidence | not accepted as price observation |

## 9.3 Evidence Retention

Store only what is needed.

Recommended:

- source metadata and hashes: long-term
- screenshots/raw page captures: limited TTL, e.g. 30–90 days unless user-submitted evidence requires longer operational retention
- personal seller identifiers: do not collect unless the product feature explicitly needs them and consent/legal basis is clear
- accepted aggregate market observation: long-term

---

# 10. Validation and Review Lanes

The system must satisfy the requirement: **automation first, owner checks later**.

A single “everything requires manual approval” workflow will not scale.

## 10.1 Calibration Phase

During the first 500–1,000 observations:

- automated collection runs fully
- every observation is staged
- system auto-rejects obvious invalid data
- human reviews all accepted candidates or statistically meaningful samples
- correction data becomes the evaluation set

Do not enable unattended auto-promotion yet.

## 10.2 Production Confidence Lanes

After calibration:

### Green Lane — Auto-Accept + Post-Audit

Requirements example:

- known trusted source
- evidence A/B
- product resolution >= 0.98
- price extraction confidence >= 0.98
- no invalid-listing flags
- price inside configured sanity bounds
- no new alias/source
- provider/schema version already validated

Action:

```text
auto-promote
→ index may use it
→ random post-audit sample
→ fully reversible
```

### Amber Lane — Human Review Before Accept

Triggers:

- confidence below green threshold
- new alias
- new source
- large price deviation
- variant ambiguity
- warranty/condition conflict
- snippet-only evidence

### Red Lane — Automatic Reject / Quarantine

Examples:

- wanted post
- deposit-only price
- whole PC when collecting a component
- counterfeit/unknown model
- duplicate
- zero/invalid price
- defective/parts listing when collecting normal used market cohort
- unsupported currency

## 10.3 Review Feedback

Every correction should store:

```text
AI predicted value
human corrected value
reason code
provider
model
prompt/schema version
source type
product/category
```

This becomes the regression/evaluation dataset.

---

# 11. Price Observation Taxonomy

Do not merge different economic meanings.

```text
asking_price
reported_final_price
verified_sale_price
shop_buy_price
trade_in_price
new_retail_price
```

MVP public index should primarily represent:

> **ช่วงราคาประกาศมือสองที่ตรวจพบ**

Do not label it “ราคาขายจริง” until the data supports that claim.

---

# 12. Price Engine

LLMs must not calculate the canonical index.

Price calculation is deterministic and testable.

## 12.1 Cohort Selection

Before statistics, observations must share a meaningful cohort:

- same base product
- compatible price type
- used-normal condition cohort
- correct currency/market
- freshness window
- not deposit/wanted/whole-PC/defective

Variant and warranty remain attributes for segmentation/adjustment.

## 12.2 Baseline Metrics

For every product snapshot:

```text
min observed
Q1
median
Q3
max observed
sample size
fresh sample ratio
source count
source concentration
evidence mix
```

Public market range begins with:

```text
Q1 – Q3
```

rather than a simple arithmetic average.

## 12.3 Outlier Handling

Initial robust rule:

```text
IQR = Q3 - Q1
lower fence = Q1 - 1.5 × IQR
upper fence = Q3 + 1.5 × IQR
```

Do not silently delete outliers.

Classify them:

- valid premium variant
- valid bargain
- bad extraction
- defect
- deposit
- bundle
- stale price
- unresolved

## 12.4 Weighting

Start simple and transparent.

```text
observation_weight =
 evidence_quality
 × freshness
 × source_quality
 × extraction_quality
```

Do not add many magic factors until empirical tests prove value.

## 12.5 Confidence Score v1

Initial interpretable formula:

```text
confidence =
  30% sample adequacy
+ 20% freshness
+ 20% source diversity
+ 20% evidence quality
+ 10% price agreement/stability
```

Suggested labels:

```text
>= 0.80  High
>= 0.60  Medium
>= 0.40  Low
<  0.40  Very Low / do not emphasize price
```

The formula must be versioned and validated against human market judgment.

## 12.6 Minimum Publication Rules

Draft MVP rules:

- fewer than 5 valid observations: no confident range; show insufficient data
- 5–9: low-confidence provisional range
- 10–19: medium if freshness/diversity are adequate
- 20+: potentially high depending on source/evidence mix

Sample size alone never guarantees confidence.

---

# 13. Source Strategy

## 13.1 Source Classes

### Class 1 — First-party / Permissioned

- user screenshot submissions
- seller submissions
- shop CSV/feed
- official APIs/feeds
- partner feeds

Goal: increase share over time.

### Class 2 — Public Research/Search

- search APIs
- public product/shop pages
- public forums/communities where access and usage are appropriate

Use with source-specific policies.

### Class 3 — Supervised Browser Sampling

Useful during bootstrap where search coverage is poor.

Treat as a fragile source, not the core pipeline.

Do not bypass login, CAPTCHA, or anti-bot controls.

### Class 4 — External Benchmarks

- overseas used markets
- new-retail price feeds/pages

Use for sanity checks and used-vs-new comparison, not as direct Thai used-market truth.

## 13.2 Source Health Score

Track per source:

- collection success rate
- evidence availability
- valid observation rate
- duplicate rate
- extraction error rate
- freshness
- human correction rate
- cost per valid observation
- block/limit events

Sources with poor health automatically receive lower scheduling priority or are paused.

## 13.3 Source Dependency Metric

Track:

```text
top_source_share
second_source_share
source_entropy/diversity
```

Long-term target: no single source should be able to kill the public index.

---

# 14. Database Blueprint

Use a consistent prefix such as `mi_`.

## 14.1 Product Master

```text
mi_category
mi_brand
mi_product_family
mi_product
mi_product_variant
mi_product_alias
mi_product_spec
mi_product_relation
```

### mi_product

```text
id
category_id
brand_id
family_id nullable
model_name
canonical_name
slug
generation nullable
release_date nullable
launch_msrp nullable
currency nullable
status
master_confidence
reviewed_at nullable
created_at
updated_at
```

### mi_product_alias

```text
id
product_id
variant_id nullable
alias_raw
alias_normalized
alias_type
source_id nullable
confidence
status
first_seen_at
last_seen_at
created_at
updated_at
```

## 14.2 Source & Provider

```text
mi_source
mi_source_policy
mi_provider
mi_provider_model
mi_prompt_version
```

### mi_source

```text
id
name
domain
source_class
access_method
risk_level
is_enabled
is_blocked
last_success_at
last_failure_at
valid_rate
human_correction_rate
notes
created_at
updated_at
```

## 14.3 Collection Orchestration

```text
mi_collection_job
mi_collection_run
mi_collection_query
mi_provider_run
```

### mi_collection_job

```text
id
job_type
product_id nullable
category_id nullable
source_id nullable
priority
reason
status
scheduled_at
started_at
completed_at
budget_limit nullable
created_at
updated_at
```

## 14.4 Candidate & Evidence

```text
mi_candidate
mi_evidence
mi_extraction
```

### mi_candidate

```text
id
collection_job_id
source_id
external_ref_hash
source_url_encrypted nullable
raw_title nullable
raw_snippet nullable
raw_price_text nullable
observed_at nullable
status
created_at
updated_at
```

### mi_evidence

```text
id
candidate_id
evidence_type
storage_path nullable
content_hash nullable
page_title nullable
captured_at
expires_at nullable
quality_level
metadata_json nullable
created_at
updated_at
```

### mi_extraction

```text
id
candidate_id
provider_run_id
schema_version
extracted_json
product_confidence
price_confidence
listing_confidence
overall_confidence
created_at
```

## 14.5 Staging / Review / Accepted Observation

```text
mi_observation_staging
mi_review_decision
mi_price_observation
```

### mi_observation_staging

```text
id
candidate_id
product_id nullable
variant_id nullable
price_type
price nullable
currency
condition_code
warranty_months nullable
has_box nullable
has_receipt nullable
has_test_evidence nullable
listing_type
is_deposit
is_wanted
is_whole_pc
is_defective
province_code nullable
validation_status
review_lane
confidence
created_at
updated_at
```

### mi_review_decision

```text
id
staging_id
decision
auto_or_human
reason_code
before_json
after_json nullable
reviewer_id nullable
provider_model_version nullable
created_at
```

### mi_price_observation

```text
id
staging_id
product_id
variant_id nullable
source_id
price_type
price
currency
condition_code
warranty_months nullable
evidence_level
observed_at
accepted_at
acceptance_mode
is_active
created_at
updated_at
```

## 14.6 Price Snapshots

```text
mi_price_snapshot
mi_price_snapshot_source_mix
```

### mi_price_snapshot

```text
id
product_id
variant_id nullable
price_type
cohort_version
q1
median
q3
min_price
max_price
valid_sample_size
fresh_sample_ratio
source_count
top_source_share
confidence_score
confidence_label
formula_version
snapshot_at
created_at
```

## 14.7 Market Signals & Outcomes

```text
mi_market_signal
mi_user_submission
mi_deal_check
mi_market_intent
mi_match
mi_transaction_feedback
```

### mi_transaction_feedback

```text
id
product_id
variant_id nullable
related_intent_id nullable
asking_price nullable
reported_final_price nullable
verification_level
sold_at nullable
source_type
created_at
```

## 14.8 Operations & Quality

```text
mi_quality_metric
mi_budget_usage
mi_audit_log
mi_system_flag
```

---

# 15. Technical Architecture

## 15.1 Stack Decision

Keep the current ModInspect direction simple:

```text
PHP 8.1+
Pure MVC
PDO
MySQL/MariaDB
Bootstrap 5
Vanilla JS / light jQuery
Apache rewrite
PHP CLI workers
Cron / Windows Task Scheduler in development
Provider APIs through HTTP adapters
Local Ollama optional for low-cost extraction/classification
```

No need to copy BYB's Python worker layer unless a specific future capability genuinely benefits from Python.

## 15.2 Suggested Project Structure

```text
modinspect/
├─ app/
│  ├─ Core/
│  ├─ Domain/
│  │  ├─ Catalog/
│  │  ├─ Acquisition/
│  │  ├─ Evidence/
│  │  ├─ Observation/
│  │  ├─ Pricing/
│  │  ├─ Review/
│  │  ├─ DealCheck/
│  │  ├─ Seller/
│  │  └─ Match/
│  ├─ Services/
│  │  ├─ Providers/
│  │  ├─ Collectors/
│  │  ├─ Extractors/
│  │  ├─ ProductResolver/
│  │  ├─ Validation/
│  │  └─ Pricing/
│  ├─ Controllers/
│  ├─ Models/
│  └─ Views/
├─ cli/
│  ├─ schedule.php
│  ├─ collect.php
│  ├─ extract.php
│  ├─ validate.php
│  ├─ snapshot.php
│  └─ cleanup.php
├─ config/
├─ database/
│  ├─ migrations/
│  └─ seeds/
├─ public/
├─ storage/
│  ├─ evidence/
│  ├─ logs/
│  ├─ cache/
│  └─ exports/
├─ tests/
└─ docs/
```

## 15.3 Background Pipeline

```text
Scheduler
→ Coverage Planner
→ Collection Jobs
→ Provider/Search
→ Candidate
→ Evidence
→ Extraction
→ Product Resolution
→ Rule Validation
→ Review Lane
→ Accepted Observation
→ Price Snapshot
→ Public API/UI
```

## 15.4 Idempotency

Every repeated collection run should avoid duplicate observations using combinations of:

- source external ID when available
- canonicalized URL hash
- title/price/date fingerprint
- evidence content hash
- product + price + source + observation-time heuristics

---

# 16. Admin / Data Operations UX

This is a first-class MVP feature because the founder must review automation efficiently.

## 16.1 Operations Dashboard

Show:

```text
Products due for refresh
Jobs queued/running/failed
Candidates collected
Auto-rejected
Green accepted
Amber review queue
Source failures
Provider usage/cost
Last successful snapshot
```

## 16.2 Morning Review View

Example:

```text
Last night
Products processed      20
Candidates             248
Accepted green         161
Needs review            31
Rejected                56
New aliases              8
New source candidates    3
```

The goal is to make the owner inspect exceptions in 15–30 minutes, not reproduce the research manually.

## 16.3 Review Card

Display together:

- source/evidence
- extracted structured fields
- canonical product match
- current market snapshot
- reason it was flagged
- AI confidence
- Approve / Edit / Reject
- create alias
- block source/candidate pattern

## 16.4 Source Health

Admin needs:

- enable/disable source
- pause source immediately
- valid rate
- review/correction rate
- last successful acquisition
- duplicate rate
- cost
- error history

## 16.5 Provider Controls

Store secrets outside normal application tables where practical.

Admin can configure:

- provider
- model
- priority
- daily budget
- maximum calls
- enabled tools
- prompt/schema version
- test/dry-run mode

---

# 17. Quality Engineering

AI data pipelines require evaluation data, not only functional tests.

## 17.1 Golden Dataset

Build a manually reviewed set of at least 200–500 examples containing:

- normal single-component listings
- bundles
- whole PCs
- deposit prices
- wanted posts
- defective items
- unusual aliases
- premium variants
- Thai/English mixed titles
- ambiguous warranty text

Expected structured output is stored as ground truth.

## 17.2 Metrics

Track by provider/model/schema/category:

```text
product resolution accuracy
price extraction accuracy
listing-type accuracy
invalid-listing recall
warranty extraction accuracy
auto-accept precision
human correction rate
cost per valid observation
```

## 17.3 Required Gate Before Green Auto-Accept

Suggested:

- price extraction >= 98% on audited sample
- product resolution >= 98%
- invalid listing false-accept <= 1–2%
- no critical regression across last two prompt/model versions
- rollback path tested

## 17.4 Regression Testing

Every prompt/model change runs the Golden Dataset before production enablement.

---

# 18. Security, Privacy, Source Risk

## 18.1 Data Minimization

Do not collect seller PII for price intelligence unless necessary.

Avoid storing:

- seller full name
- phone number
- exact home address
- private chat
- profile photo
- unnecessary account identifiers

## 18.2 Location

For Local Match later:

- public data uses province/district or rounded/geohash location
- exact location is never public by default
- contact exchange requires explicit action

## 18.3 Source Stop Rule

If a source blocks or requests that collection stop:

```text
pause source
→ record incident
→ do not bypass controls
→ lower freshness/confidence as data ages
→ redistribute collection jobs to other sources
```

## 18.4 Evidence Access

Raw evidence should be admin/private by default and never become a public mirror of third-party listings.

---

# 19. Cost Strategy — Free First

The project should prove utility before paying for premium AI infrastructure.

## 19.1 Free/Low-Cost Strategy

Use available free/low-cost search quotas where practical.

Use local models for tasks that do not require fresh internet knowledge:

- title classification
- alias suggestion
- condition extraction from text already fetched
- duplicate reasoning
- ambiguity triage

Reserve paid APIs for:

- fresh web retrieval when free providers are insufficient
- hard extraction cases
- deep source discovery
- complex research

## 19.2 Cost Metric

The key metric is not “API cost per query.”

It is:

> **Cost per accepted valid observation**

Example:

```text
100 THB provider spend
→ 1,000 candidates
→ 400 accepted observations
= 0.25 THB / accepted observation
```

## 19.3 Hard Budget Controls

Implement from the beginning:

- max provider calls/day
- max spend/day
- max jobs/run
- provider timeout
- retry limit
- circuit breaker
- stop when sufficient sample target reached

---

# 20. MVP Delivery Plan

The build order must prove data feasibility before polishing the marketplace experience.

## Phase 0 — Architecture & Test Harness

**Goal:** establish project skeleton and deterministic contracts.

Deliver:

- repo / Pure PHP MVC skeleton
- migrations
- product master tables
- source/provider tables
- candidate/evidence/staging tables
- provider interfaces
- mock provider
- CLI worker framework
- audit/run logging
- unit test harness

**Exit gate:** a mock collection job can travel end-to-end from candidate to staged observation.

---

## Phase 1 — Product Master POC

**Scope:** 20 products: 10 CPU + 10 GPU.

Deliver:

- category/brand/product UI
- alias management
- automated master-data research assist
- review/activate flow
- product resolver

**Exit gate:**

- 20 canonical products active
- >= 5 aliases/product where applicable
- resolver accuracy >= 95% on test titles

---

## Phase 2 — Automated Acquisition POC

**Goal:** prove founder does not need to collect manually.

Deliver:

- Coverage Planner
- primary search provider adapter
- fallback provider adapter
- source policy
- candidate store
- evidence fetch
- AI extraction
- deterministic validator
- review queue
- source health metrics

**Target dataset:**

- >= 500 accepted observations
- 20 products
- at least 2 meaningful source types where possible

**Exit gate:**

- price extraction >= 95%
- product resolution >= 90% initially, then tune toward 95%+
- valid accepted yield >= 60–70%
- owner review <= 30 min/day
- no single source failure stops pipeline

---

## Phase 3 — Price Engine & Admin Intelligence

Deliver:

- robust cohort filters
- Q1/Median/Q3
- freshness
- confidence v1
- snapshot history
- price snapshot inspector
- 5700X3D and other manual benchmark cases

**Exit gate:**

- domain-expert sanity rating >= 80%
- known outlier/bundle/deposit cases do not corrupt index
- every published snapshot is reproducible from accepted observation IDs

---

## Phase 4 — Public Read-Only MVP

Deliver:

- Home
- Search
- Product page
- current price range
- sample count
- confidence
- freshness
- price history
- methodology
- used-vs-new comparison where data exists
- SEO foundations

Do not add transactional complexity yet.

---

## Phase 4.5 — Community Data Feedback Loop

**Goal:** let users contribute market corrections and evidence without turning the price index into a voting system.

Target scope:

- price feedback
- product/model/variant correction
- specification correction
- market stale-data reports
- evidence submission
- `SOLD_PRICE_REPORT` intake with verification level
- community review queue
- multidimensional confidence scoring
- contributor reputation foundation
- community anomaly signals for Coverage Planner priority

Exit gate:

- community submissions are stored as evidence/signals
- no community report directly changes a price snapshot
- accepted community-derived observations are traceable
- abuse/manipulation controls are present
- deterministic Price Engine remains authoritative for price snapshots

---

## Phase 5 — Deal Checker / Screenshot Intake

Deliver:

- manual price check
- screenshot upload
- AI extraction
- confirmation/edit screen
- deal result
- anonymous contribution consent

This feature begins converting users into first-party data contributors.

---

## Phase 6 — Seller Center

Deliver:

- condition details
- warranty
- receipt/box/accessories
- repair/mining/OC history where relevant
- test evidence
- Seller Price Advisor
- seller intent

Seller submissions become a permissioned source of market observations.

---

## Phase 7 — Local Match Beta

Deliver:

- Buy/Sell intent
- province/district/radius filters
- approximate location only
- match scoring
- contact request
- safety/report/block

Do not hold payment.

---

## Phase 8 — First-Party Outcome Loop

Deliver:

- mark sold
- reported final price
- buyer confirmation when possible
- days to sell
- match-to-transaction funnel
- verification level

This is the transition from “asking-price index” toward proprietary transaction intelligence.

---

## Phase 9 — B2B Pilot

Deliver:

- shop feed/CSV import
- inventory vs market
- local demand
- aging stock
- weekly report
- shop price alerts

Validate willingness to pay before building large B2B features.

---

## Phase 10 — Data Product

Only after product usage and data quality are proven:

- paid dashboard
- CSV/report export
- API
- historical market reports
- lead generation

---

# 21. 30 / 60 / 90 Day Execution Plan

## First 30 Days — Prove Collection

Focus exclusively on:

1. ModInspect repo + schema
2. 20-product master
3. alias resolver
4. provider abstraction
5. one automatic search collector
6. one fallback collector
7. candidate/evidence/extraction pipeline
8. review queue
9. 500-observation target
10. quality metrics

Do not spend significant time on Local Match or polished marketing pages yet.

## Day 31–60 — Prove Price Intelligence

1. Price Engine v1
2. confidence/freshness
3. source health
4. regression dataset
5. public product pages
6. Deal Checker beta
7. 50–100 product expansion only if collection metrics remain healthy

## Day 61–90 — Prove User Value

1. Screenshot Deal Checker
2. user contribution flow
3. Seller Center alpha
4. price alerts/watchlist optional
5. first affiliate experiment
6. talk to 3–5 shops
7. Local Match private design/limited pilot only if core price product is being used

---

# 22. Go / No-Go Metrics

## Data Feasibility

```text
accepted observations >= 500
price extraction >= 95%
product resolution >= 90% initially; target >= 95%
human review <= 20–30% after calibration
owner review <= 30 min/day
freshness <= 14 days for active products
```

## Automation

```text
scheduled run success >= 90%
collection jobs idempotent
source failure isolation works
budget circuit breaker works
rollback works
```

## Price Quality

```text
expert sanity acceptance >= 80%
false acceptance of deposit/bundle/wanted/whole-PC <= 2–5% initially and trending down
snapshot reproducibility = 100%
```

## Business Validation

By 90 days after public MVP:

- organic/direct users are actually using product pages and Deal Checker
- returning users exist
- users voluntarily submit some deal/screenshot data
- at least several shops show interest in pricing/dashboard data
- first monetization signal exists before large paid infrastructure

Do not use traffic alone as proof; use repeated useful actions.

---

# 23. Stop / Pivot Conditions

Pause or redesign collection if:

- accepted valid rate stays below 40%
- product resolution remains below 80% after tuning
- founder must manually research instead of reviewing exceptions
- review workload exceeds ~1 hour/day
- collectors require constant source-specific repair
- one blocked source destroys most index coverage
- data cost grows faster than useful observation volume

Pause business expansion if:

- users do not care about price/evidence result
- Deal Checker is not used
- seller/shop interviews show no workflow value
- B2B interest is absent after evidence of consumer usage

---

# 24. Implementation Order — Engineering Backlog

## Foundation

- [ ] Create `modinspect` repo
- [ ] MVC/core bootstrap
- [ ] env/config loader
- [ ] DB connection / migrations
- [ ] logging
- [ ] command runner
- [ ] system flags / kill switches
- [ ] tests

## Master Data

- [ ] categories
- [ ] brands
- [ ] product family/model/variant
- [ ] product specs
- [ ] aliases
- [ ] master review UI
- [ ] product resolver

## Acquisition

- [ ] source master
- [ ] source policy
- [ ] provider interfaces
- [ ] mock provider
- [ ] primary search provider
- [ ] fallback provider
- [ ] collection scheduler
- [ ] candidate store
- [ ] evidence store
- [ ] extraction schema
- [ ] local extraction option
- [ ] validator
- [ ] dedupe
- [ ] review queue

## Pricing

- [ ] observation acceptance
- [ ] cohort filter
- [ ] robust stats
- [ ] confidence
- [ ] snapshot generation
- [ ] history
- [ ] methodology audit

## Public Product

- [ ] search
- [ ] product detail
- [ ] price history
- [ ] Deal Checker
- [ ] Screenshot Check
- [ ] contribution consent

## Seller / Network

- [ ] Seller Center
- [ ] test evidence
- [ ] Seller Price Advisor
- [ ] Buy/Sell intents
- [ ] Local Match
- [ ] contact request
- [ ] transaction feedback

---

# 25. Locked Architecture Decisions

1. **ModInspect is data-first, not crawler-first.**
2. **Product Master and temporal Market Observation are separate domains.**
3. **AI can collect/extract/recommend; deterministic code owns validation rules and price calculations.**
4. **AI output is always traceable to provider/model/prompt/evidence.**
5. **Automatic collection is mandatory; manual work is review, correction, and policy.**
6. **Human review starts strict, then becomes exception-based after measurable calibration.**
7. **Provider adapters are replaceable.**
8. **Deep Research is primarily a Scout/Discovery capability, not the routine bulk price collector.**
9. **Social Listening provides demand/source signals; it is not automatically a price source.**
10. **The public index differentiates asking price from actual sold price.**
11. **Price Engine is transparent and reproducible.**
12. **Source blocks do not trigger evasion; the source is paused and coverage degrades transparently.**
13. **First-party outcome data is the long-term strategic moat.**
14. **Community submissions are evidence/signals, not votes, and must never directly set market-price snapshots.**
15. **Pure PHP MVC remains the baseline; do not copy BYB's framework/worker stack without need.**
16. **Do not build enterprise infrastructure before the 20-product / 500-observation POC passes.**

---

# 26. Open Decisions To Resolve During Implementation

These should be tested rather than guessed now.

1. Which free/low-cost search provider gives the best **accepted observation per request** in Thailand?
2. Can local Qwen reliably extract Thai used-hardware listing text after search evidence is retrieved?
3. What evidence level is required for Green auto-accept?
4. What category-specific condition taxonomy is needed for CPU/GPU/SSD/RAM?
5. How many observations are sufficient before variant-level price publication?
6. What freshness window fits CPU vs GPU vs RAM/SSD?
7. How much source concentration is acceptable before confidence is reduced?
8. Can seller/user-submitted data be validated strongly enough to become high-weight observations?
9. Which outcome confirmation method gives useful sold-price data without creating transaction liability?
10. At what traffic/data volume does B2B dashboard development become justified?

---

# 27. Final Architecture

```text
                         MODINSPECT
                             │
                             ▼
                            USERS
                         ↙        ↘
          COMMUNITY FEEDBACK      USER/SELLER DATA
                    │                    │
                    ▼                    ▼
          COMMUNITY SUBMISSIONS   FIRST-PARTY OUTCOMES
                    │                    │
                    ▼                    │
          CONFIDENCE / REVIEW            │
              ↙            ↘             │
        REVIEW QUEUE      MARKET SIGNAL  │
              │              │            │
              ▼              ▼            │
    ACCEPTED OBSERVATION  COVERAGE PRIORITY
              │              │
              ▼              ▼
         PRICE ENGINE    AI COLLECTION
              │              │
              ▼              ▼
       PRICE SNAPSHOT ← MARKET OBSERVATIONS
              │
              ▼
      PRICE INDEX / DEAL CHECKER / SELLER ADVISOR
```

---

# 28. Immediate Next Step

Do **not** build the full public site yet.

The immediate implementation target is:

> **ModInspect Data Feasibility POC — 20 products, automated acquisition, evidence, extraction, review queue, and 500 accepted observations.**

A successful morning should look like:

```text
Last automated run
------------------
Products due              20
Products processed        20
Candidates collected     240
Evidence usable          205
Auto rejected             45
Accepted / green         120
Needs review              40
New aliases                7
New source candidates      2
Provider cost          tracked
Run status             healthy
```

The owner opens Admin, reviews the 40 exceptions, and does **not** need to perform the original market search manually.

When that is repeatable, ModInspect has earned the right to build the rest of the product.
