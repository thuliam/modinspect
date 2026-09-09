# ModInspect User Flows

Phase 4A baseline: 2026-09-09

## Public Flow A: Find Product Price

```text
Home / Price Search
  -> search or choose product
  -> Product Detail
  -> if enough reviewed data exists:
       show product identity, Q1/Median/Q3, sample size, freshness, confidence
     else:
       show "ข้อมูลตลาดยังไม่เพียงพอ"
  -> optional Deal Checker or Methodology
```

Primary user questions:

- What product am I looking at?
- What is the observed used-market range?
- How trustworthy is the result?
- What can I do next?

## Public Flow B: Check A Deal

```text
Deal Checker
  -> enter/select product and asking price
  -> compare against deterministic snapshot when available
  -> show below / within / above observed range, or insufficient data
  -> link to Product Detail / Methodology
```

Language must stay neutral: below range, within range, above range, premium asking price, insufficient data. It must not imply fraud or guarantee appraisal value.

## Admin Flow A: Review Market Evidence

```text
Admin login
  -> Review Queue
  -> inspect evidence, source, original extraction, final reviewed values, flags, lane
  -> optionally correct supported fields with reason
  -> approve / reject / exclude
  -> audit log records actor and decision
  -> approved observation can later feed deterministic snapshots
```

Rules:

- No bulk approval.
- Corrections do not overwrite raw evidence or original extraction.
- Mock/test provenance cannot be corrected into real market evidence.

## Admin Flow B: Investigate Collection Failure

```text
Admin login
  -> Dashboard attention section
  -> Collector Jobs / Sources / Incidents
  -> inspect failed jobs, paused/degraded sources, source health
  -> requeue failed job or pause/resume/record incident where authorized
  -> next scheduled runner invocation processes eligible queued jobs
```

Operator and Admin users may work this flow according to RBAC. Reviewer-only users must not perform high-impact source/job controls.

## Admin Flow C: Inspect Price Snapshot / Confidence

```text
Admin login
  -> Price Indices
  -> inspect snapshot range, sample size, confidence label
  -> inspect confidence components, reason codes, provenance counts, hash
  -> optionally run reproduce CLI for audit verification
```

Admin/provenance detail should not be pushed into public Product Detail except as concise methodology or confidence explanation.

## Deferred Flow: Reviewed Offline REAL Calibration

```text
Owner places REAL CSV/JSON under storage/import/
  -> dry-run import
  -> import as REAL
  -> Review Queue
  -> human decisions/corrections
  -> calibration report
```

This flow is waiting for an owner-supplied real sample. Test fixtures must not be relabeled as REAL.
