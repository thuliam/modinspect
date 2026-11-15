# ModInspect Roadmap

Baseline date: 2026-11-14 (Asia/Bangkok). **Dates are illustrative**, assume one accountable agent plus owner review
turnaround of about two working days, and move whenever an owner gate slips. Nothing here authorises production deploy.

## Calendar (provisional)

| Sprint | Window | Outcome | Hard dependency | Owner gate |
|---|---|---|---|---|
| 0 Recovery and baseline | 2026-11-14 to 2026-11-18 | Trusted source of truth, repaired regression suite, reproducible admin assets, requirements/roadmap/tracker | none | Review baseline report |
| 1 Operational vertical slice | 2026-11-19 to 2026-12-02 | Clean checkout boots, Admin primary flow owner-verified, one repeatable TEST path to a public read, CI runs the guards | Sprint 0 committed to a branch (owner decides) | Owner re-UAT of Admin tables (official DataTables) |
| 2 Brand CI + public redesign | 2026-12-03 to 2026-12-16 | Chosen identity, tokens, Home, Search, Product Detail, Deal Checker on one design system | Owner picks a visual direction | **Owner visual acceptance** |
| 3 Trusted data and coverage | 2026-12-17 to 2026-12-30 | Listing-level REAL evidence reviewed for 4 to 6 products; first honest published ranges | Owner performs real human review | Owner approves REAL observations |
| 4 Community feedback foundation | 2026-12-31 to 2027-01-13 | Moderated report/sold-price intake, abuse controls, FAQ | Owner approves scope and privacy copy | Scope + privacy/legal copy |
| 5 Hardening and beta readiness | 2027-01-14 to 2027-01-27 | CI gates, staging, backup/rollback, release runbook | Isolated staging DB | **Owner GO/NO-GO** |

Target gated beta: **late January 2027, conditional** on Sprint 2 visual acceptance and Sprint 3 owner review of real
evidence. If real evidence review is slow, beta is `BLOCKED_BY_REAL_EVIDENCE` and the public site stays honestly empty.

## Critical path

Owner review of REAL listing-level evidence (Sprint 3) is the longest-lead item and cannot be automated or faked.
Visual acceptance (Sprint 2) is the second gate. Engineering work in Sprints 1, 2 and 5 can overlap with owner review time.

## Scope discipline

- Beta denominator contains only `CONFIRMED` rows of `docs/PRODUCT_REQUIREMENTS.md`.
- Seller tools, marketplace/matching, ads, subscriptions, escrow, commission, seller blacklist are outside the beta denominator.
- Re-baseline weights in `docs/SPRINT_TRACKER.md` whenever scope changes and record the delta.

## Risk heat

| Risk | Heat | Mitigation |
|---|---|---|
| Zero approved REAL observations; 88 REAL pending since September | High | Owner review session; run-scoped batches; never blanket-approve |
| Branch divergence (`studio` lacks 2 CI commits on `origin/main`) | Medium | Reconcile plan in `docs/DECISION_LOG.md`; no force operations |
| Public UX not yet a coherent product (mobile header, internal text, empty-card grid) | Medium | Sprint 2 |
| Full suite was red on baseline (5 stale tests) | Resolved | Repaired this baseline; keep suite in CI |
| Credentials exposure in the raw LINE export | High (outside repo) | Owner rotates exposed credentials; never process raw export |

## Revision 2026-11-15

The earlier roadmap above is kept as history. The forward plan is the R0/T1/U2/I3/D4/C5/R6 program in `docs/SPRINT_TRACKER.md`
(provisional windows from 2026-11-16, earliest illustrative beta gate after 2027-02-18 **only if** acceptance, real-data and production gates pass;
not a delivery promise and not deployment permission). Why it changed: the data foundation grew (99 products, 420 approved owner-text observations,
46 recorded public snapshots), so trust and honest disclosure (T1) now precede visual and intelligence work.
