# Design: re-resolve stale unresolved rows (LS-4 K-1d)

Status: **design only. No code, no table, no data write.** Needs the owner's approval of the decisions in section 8 before step 1 is built.
Date: 2026-11-17. Related: `docs/LS4_REPORTS.md` (K-1, K-1b, K-1c), DL-072, DL-073.

## 1. Problem

159 rows of the unresolved queue (DEV, 2026-11-17) are rows the current catalog and resolver already match. They were stored as `rejected` (red lane, `UNRESOLVED_PRODUCT`, no `price_observations` row) before the catalog grew. The importer cannot recover them: its duplicate check sees the stored raw row and skips it (dry-run: 81 of 87 rejected as duplicates). Evidence that is already in the database is therefore not counted. Whoever approves a price sees fewer rows than exist; 22 products sit pending, and 34 extra usable rows would take every one of them to the 3-row minimum.

## 2. What the stored data already holds (read from `CollectionPipeline::persistCandidate`)

For each stored candidate the importer wrote: a `raw_price_observations` row (title, price text, status `rejected` when red), a `market_evidence` row, an `extraction_runs` row with the full extraction JSON (asking price, condition, listing type, deposit/defect flags, confidence), and an `observation_review_decisions` row (lane, decision, reason codes, rule result). A `price_observations` row (status `pending`) is written only when the lane is not red, a product resolved and an asking price exists. For the stale rows only that last step is missing.

## 3. Proposed tool: `cli/reresolve_stale.php` (dry-run by default)

Per stale row (undecided in the queue, owner-text source only, current resolver matches):
1. Load the **stored** extraction JSON (do not re-extract: the extractor may have changed and the point is to isolate the effect of the catalog).
2. Resolve with the current `ProductResolver`; re-validate with `ObservationValidator` using the stored extraction and the new resolution.
3. Decide, never write, in dry-run: `would_create` (lane green or amber, product resolved, asking price present), or a named skip reason.
4. With `--apply --actor=<user id>`: in **one transaction per row**, insert the `price_observations` row exactly as the importer would (`pending`, same columns, `observed_at` from the raw row) and a **new** `observation_review_decisions` row (lane, reasons from the new validation, plus the reason code `RERESOLVED_AFTER_CATALOG_GROWTH`, pointing at the same raw row and extraction run). The old red decision is kept as history, never edited. Write one audit log entry per row with the actor and the old and new decision ids.

Skip reasons the tool must report (counts only, never text): `STILL_UNRESOLVED`, `RED_AFTER_REVALIDATION`, `NO_ASKING_PRICE`, `NOT_OWNER_TEXT_SOURCE`, `ALREADY_HAS_OBSERVATION`, `REPOST_OF_EXISTING_LISTING` (same listing key as an existing observation, using `OwnerIntakeDeduper`), `BUNDLE_WANTED_OR_DEPOSIT` (reason codes on the old decision), `MOCK_OR_TEST_SOURCE`.

What it never does: touch the 88 REAL Priceza pending rows, mock or test observations, approved rows, snapshots or prices; change an alias; call a network or a paid provider; print a title, seller, URL or text; run without `--actor` when `--apply` is given.

Approval stays exactly as today: the created rows are `pending`; `cli/owner_intake_approve.php --actor=54` (OwnerIntakePlanner, 60% to 160% rule) decides, in a separate, later step the owner can run or skip. Price confidence stays owner-text-only (cap 59, "low") because the evidence grade does not change.

## 4. Safeguards

- **Idempotent:** a row that already has an observation is skipped (`ALREADY_HAS_OBSERVATION`); running twice creates nothing the second time.
- **Dry-run first, same code path:** the dry-run uses the same decision function as `--apply`, so the numbers it prints are the numbers an apply would produce (the lesson of J-3: the screen and the CLI share one plan builder).
- **Count guard:** `--apply` refuses if the dry-run count differs from `--expect=N` given by the person applying, so a changed catalog between the two steps cannot widen the write silently.
- **Limit:** `--limit` caps rows per run (default 50) so a first apply is small and reviewable.
- **Reproducibility:** because the stored extraction is reused, the same input and catalog give the same result; the new decision stores the rule result like any other.
- **Queue hygiene:** the unresolved queue query must also treat a raw row with a `price_observations` row as handled; today it only checks the decision table, so a re-resolved row would keep showing. This is a small read-side change with a test.
- **Raw status:** the raw row stays `rejected` unless the owner decides otherwise (decision D-2 below); leaving it avoids editing stored evidence but means its status text is stale.

## 5. Expected effect (an estimate, not a result)

Upper bound 159 rows. The real number is unknown until the dry-run: reposts, bundle/wanted/deposit codes and red-after-revalidation will remove some. Step 1 is therefore a read-only dry-run that prints those counts, so the owner decides on facts. Even a partial yield helps: 34 rows, spread correctly over the 21 `too_few` products, clear the pending list.

## 6. Tests (written before the tool)

1. Decision function on fixtures: green resolves to `would_create`; each skip reason fires on its own fixture.
2. Apply on the isolated test database (transaction): one observation and one new decision per eligible row; the old decision unchanged; audit entry with actor; no snapshot, no approved row, no alias changed.
3. Idempotency: a second apply creates nothing.
4. `--apply` without `--actor`, with a wrong `--expect`, or against a non-test database name outside the DEV guard: refused.
5. Dry-run equals apply: the dry-run counts equal the rows an apply then creates.
6. Queue hygiene: a re-resolved row leaves the Open and Already-matched tabs.
7. Mutation checks: break each guard (owner-text filter, repost filter, idempotency) and see a test fail.
8. Linux parity scan and the full suite stay green; secret scan clean.

## 7. Build plan (each step closes with the board, decision log and status updated)

| Step | What | Writes to DEV? |
|---|---|---|
| R-1 | Decision function + dry-run CLI + tests 1, 5, 7 | No |
| R-2 | Run the dry-run on DEV; report counts to the owner | No |
| R-3 | `--apply` + `--actor` + `--expect` + `--limit`, tests 2, 3, 4, 6 | Not yet (test DB only) |
| R-4 | Apply a first small batch on DEV (limit 20) after the owner says go; then the planner as usual | **Yes, only on the owner's go** |

## 8. Decisions for the owner (Recommended first)

- **D-1 Build R-1 and R-2 (read-only) now? Recommended: yes.** It costs no data change and turns the 159 estimate into a real number.
- **D-2 Raw row status after a re-resolve:** (a) leave `rejected` and add the new decision (Recommended: stored evidence is never edited), (b) set it to `review` to match the new observation.
- **D-3 Lanes to accept:** (a) green and amber, the same as the importer does (Recommended), (b) green only (smaller, safer, fewer rows).
- **D-4 Apply to DEV (R-4):** decide after R-2 numbers; not requested now.
- If the owner prefers not to write evidence rows from old raw rows at all, the fallback is to drop the 159 from the review lists (already done in K-1c) and collect new rows from a second source (K-3).

## 9. What this design does not claim

It was not run. The column list comes from reading `persistCandidate`; the validator call and the exact unique keys will be confirmed against the code and schema at R-1, before any write path exists. The yield is an estimate.
