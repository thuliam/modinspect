# ADR-005: Community Feedback Is Evidence, Not Voting

**Status:** Accepted architecture, not implemented  
**Date:** 2026-09-09

## Context

ModInspect may later let users report stale prices, incorrect product/spec data, wrong variant matches, missing condition/warranty context, invalid listings, or evidence of actual sold/final prices.

This creates a valuable feedback loop, but also creates manipulation risk. Sellers may try to inflate prices, buyers may try to depress prices, and coordinated reports could distort a simple voting model.

## Decision

Community feedback is treated as evidence and market signals, not as direct voting on market price.

Community submissions may:

- enter a controlled evidence pipeline
- receive confidence scoring across evidence, contributor trust, consensus, and freshness
- be prioritized for admin review
- become a Coverage Planner signal for fresh collection
- become accepted market observations only after validation/review

Community submissions must not directly change `price_indices`, `price_histories`, public market snapshots, or any deterministic price output.

The correct path is:

```text
Community Submission
→ Evidence / Structured Fields
→ Confidence / Review Priority
→ Admin Review
→ Accepted Observation
→ Deterministic Price Engine
→ New Price Snapshot
```

## Why

- Protects marketplace neutrality.
- Reduces price manipulation and coordinated brigading risk.
- Preserves deterministic and reproducible pricing.
- Keeps accepted observations auditable.
- Lets strong evidence outweigh mass unsupported votes.
- Gives ModInspect a future proprietary data source without weakening trust.

## Rejected Alternative

Community majority directly sets or adjusts the price index.

Rejected because vote-count pricing would be vulnerable to manipulation, hard to audit, difficult to reproduce, and incompatible with the existing rule that the deterministic Price Engine calculates public market references from accepted observations.

## Consequences

- Requires a future submission, evidence, scoring, and review pipeline.
- Requires abuse controls and duplicate detection.
- Requires contributor reputation for internal prioritization.
- Can feed Coverage Planner priority through community anomaly signals.
- Can help gather actual transaction-price signals through `SOLD_PRICE_REPORT`.
- Adds a future long-term data moat, but does not change current Phase 2 implementation.
