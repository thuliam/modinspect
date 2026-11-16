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

## Owner-listed features (to be filled by the owner)

(none yet)
