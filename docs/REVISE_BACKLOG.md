# Revise backlog (features the owner thinks are still missing)

The owner said, at the start of LS-2 (2026-11-16): "there are still many features I think you missed; finish this sprint first, then we revise."
This file collects candidates while LS-2 runs, so the revise session starts from evidence instead of memory. Nothing here is committed scope.
Source of truth for scope stays `docs/PRODUCT_REQUIREMENTS.md` and the Blueprint; AGENTS.md says a feature is not implemented just because a document mentions it.

## How entries are added

- Each LS-2 task appends what it found that is outside its own scope (one line: what, where seen, why it matters).
- The owner adds the features they have in mind (plain words are enough); the agent maps them to requirements in the revise session.

## Candidates found so far

- H-1: 3 resolver-vs-extractor conflicts remain (i5-14600K to i7-14700KF, an RTX 3070 title to RX 6700 XT, one RTX 5060 to 5060 Ti): needs a marker rule for CPU suffix digits and a look at noisy titles.
- H-1: resolver recall is 62.3 % for CPU and 87.4 % for GPU on the research rows; most unresolved models are simply not in the Product Master (H-3), the rest need aliases (H-2).
- H-1: existing pending/approved observations were resolved with the old rule; a re-resolution audit of past assignments is a possible feature (read-only report first).
- H-3: the research rows are spread thin: 60 absent models, almost all with 1-2 rows. A coverage-driven product (which models to price first) needs a demand signal; none exists yet (no search logs reviewed).
- H-4: the ladder check covers GPUs only; a CPU ladder (X3D, F/K variants) and a RAM speed/capacity ladder need their own rules. Candidate feature: show the flags on the admin Data health page (H-5).
- H-4: `price_indices` accumulates a row per recalculation; there is no retention/archiving rule and no public price history chart beyond the monthly trend.
- H-5: Data health shows 137 pending of which about 88 are REAL (Priceza) rows waiting since September; a REAL review session is the longest-lead item on the roadmap. Candidate feature: split the pending number by origin (owner text vs REAL) and by age on this page.
- H-5: there is no per-source (not per-key) health view on this page; that needs a privacy decision on how much source identity admins may see.
- H-6: history is capped at the last 8 snapshots and only shows the median; a per-product chart of snapshots and a way to see which observation ids were added are possible features (the second would show prices and dates only).
- H-7: only two roles besides admin (reviewer, operator) exist and `audit.view` is admin-only; there is no role/permission management screen and no per-user audit export. Candidate feature: admin user management UI (create/disable users, change role) with its own audit trail.
- H-7: the local dev server cannot serve routes that end in .csv/.json without Apache rewrites; a small router script for local tests would remove this harness exception.

## Owner-listed features (to be filled by the owner)

(none yet)
