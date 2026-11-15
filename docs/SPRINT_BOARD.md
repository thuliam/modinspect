# Sprint Board: Long Sprint LS-1 (opened 2026-11-15)

Shared board for the two agents working in parallel: **Claude** (UI, browser verification, docs of its own lane) and **Codex**
(data, backend, tests). The owner is the only approver. This file is the single place that says who does what and what state it is in.
Status values: `TODO` / `CLAIMED` / `IN_PROGRESS` / `DONE` / `BLOCKED` / `OWNER_WAIT`. Evidence is a commit SHA, a test name or a command result,
never "looks fine". Nothing is DONE without evidence.

## Protocol (read before touching anything)

1. **Own your lane's files only.** The file-ownership table below is the lock. If a task needs a file in the other lane, add a row to
   "Handoff requests" and wait; do not edit it.
2. **Claim before starting:** set the row to `CLAIMED` with your agent name and the date/time (UTC+7), commit that board change by itself.
3. **Branches:** Claude works on `checkpoint/claude-baseline-2026-11-14`. Codex works on `codex/ls1-data` created from the current checkpoint head.
   Neither agent merges into `main`. At sprint end Claude merges `codex/ls1-data` into the checkpoint branch (no conflicts expected because lanes do not share files)
   and runs the full suite. Pushing needs the owner's word.
4. **Commit messages** start with `[claude]` or `[codex]`. One logical change per commit. The board row is updated in the same commit as the work.
5. **Shared docs:** each agent edits only its own lane's rows on this board and appends to `docs/PROJECT_STATUS.md` under its own dated heading.
   `docs/DECISION_LOG.md` entries: append-only, next free DL number, never edit another agent's row.
6. **Tests:** `MODINSPECT_ENV_FILE=.env.testing php tests/run.php` must exit 0 before a commit that touches PHP. New tables go in `database/schema.sql`,
   a migration **and** `modinspect.sql` (CI builds its DB from the dump).
7. **Hard limits for both agents** (AGENTS.md): no deploy, no spend, no new library download without the owner, no secret or raw LINE export in the repo or in output,
   no change to the 88 REAL (Priceza) pending observations, no merge to `main`, no brand/colour change. Shared DEV is read-only except the writes named in a task.
8. **Blocked?** Write the blocker in the row, move to the next task. Do not wait and do not ask the owner mid-sprint; the owner reads the board at the end.

## File ownership (the lock)

| Lane | May edit | Must not edit |
|---|---|---|
| **Claude** | `app/Views/**`, `public/assets/**` (except vendor manifests), `app/Controllers/*` for view wiring, `docs/UX_UI_SYSTEM.md`, `docs/UI_HANDWRITTEN_CODE_AUDIT.md`, `tests/ui_*`, `tests/ux_*`, `tests/trend_location_test.php` | `cli/**`, `app/Services/**`, `app/Models/**`, `database/**`, `modinspect.sql`, `tests/*import*`, `tests/*resolver*` |
| **Codex** | `cli/**`, `app/Services/**`, `app/Models/**`, `database/**`, `modinspect.sql`, `tests/` files it creates, `docs/DATA_RESEARCH_POLICY.md`, `docs/COLLECTION.md`, `docs/DATA_MODEL.md` | `app/Views/**`, `public/assets/**`, `app/Controllers/**` |
| Shared (append-only) | `docs/PROJECT_STATUS.md`, `docs/DECISION_LOG.md`, this board | Everything else in `docs/` belongs to the lane that created it |

## Tasks

### Claude lane (UI and browser verification)

| ID | Task | Status | Evidence / blocker |
|---|---|---|---|
| C-1 | Admin: open every page and state on the live UAT server, fix mismatches with the DropX layout (Bootstrap first) | OWNER_WAIT | Needs the owner to sign in once in the app's browser pane (password never shared). Public pages already viewed on `192.168.1.10`. |
| C-2 | Admin login page restyled to match the Admin shell | TODO | |
| C-3 | Public pages at 708px; keyboard/focus and AA contrast audit with fixes | TODO | |
| C-4 | Replace repeated generic category art on product cards with licensed photos where they exist, otherwise a model-name tile (category + model in type) | DONE (Claude, 2026-11-15) | Viewed on the UAT server `/price?category=gpu`: 14 type tiles + 2 photos, no repeated artwork; full suite exit 0; no new assets |
| C-5 | Thai/English consistency of spec text on cards and detail (display layer only) | BLOCKED (waits for D-5) | Cards no longer show spec text (U2 slice); detail page still shows the stored mixed-language text until D-5 cleans the data. No display-layer translation hack. |
| C-6 | Mixed signals on cards (green "has market data" vs red "low confidence") reworded | DONE (Claude, 2026-11-15) | Badge now "มีช่วงราคา", lime only when confidence is medium/high (0 lime badges on the GPU list, all low); UAT screenshot; suite exit 0 |
| C-7 | Remove `app.css` in verified slices (library-first) | TODO | Check all public pages before each deletion |
| C-8 | Render Codex outputs: duplicate-post rate in `/admin/market-trends`, per-snapshot evidence view | TODO | Waits for D-2 and D-4 services |
| C-9 | Merge `codex/ls1-data` into the checkpoint branch, full suite, final report | TODO | At sprint end |

### Codex lane (data, backend, tests)

| ID | Task | Status | Evidence / blocker |
|---|---|---|---|
| D-1 | Round-4 approval: prepare from the **sanitized** CSV `storage/research/line_facebook_2026_loc/research_listings_sanitized.csv` (never the raw export), import, then `cli/owner_intake_approve.php` with the existing 60%-160% outlier rule under actor id 54; target the 24 CPU/GPU models with fewer than 3 samples | TODO | Allowed DEV writes: import + approval + snapshot recalculation for the affected products only. Report before/after counts. |
| D-2 | Duplicate-post analysis without seller identity: group by normalised text hash + price, report duplicate rate per province and per product as a service method (`App\Services\Admin\...`) plus a CLI | TODO | No names, phones, URLs or raw text in output |
| D-3 | Golden resolver test set (RTX 3050, RX 5700 XT, 3060 vs 3060 Ti, 4070 vs 4070 Ti/SUPER, Ryzen 5600 vs 5600G, 5700X3D, Intel suffixes) as a permanent test registered in `tests/run.php` | TODO | Closes tracker item T1-8 |
| D-4 | Service for a per-snapshot evidence view (admin side): members, exclusion reasons, source concentration, replay result | TODO | Closes the data half of T1-7 |
| D-5 | Spec summary normalisation: find mixed Thai/English or machine-generated `spec_summary` values, propose a migration with the cleaned text (additive, reviewable) | TODO | Migration only; apply to DEV only after the test DB passes |
| D-6 | `cli/price_trust_audit.php`: add duplicate-rate and per-source-key summary to its output; keep exit codes | TODO | |
| D-7 | CI hygiene: confirm clean checkout builds the test DB from `modinspect.sql` with every migration reflected (script + test that fails if a migration table is missing from the dump) | TODO | Prevents the CI failure seen on 2026-11-15 |
| D-8 | Re-run `cli/price_confidence_report.php` and `cli/review_calibration_report.php`; write the result into `docs/REAL_CALIBRATION_REPORT.md` as a dated section, fixture and live metrics kept separate | TODO | |

### Handoff requests (cross-lane, append only)

| # | From | To | Request | Status |
|---|---|---|---|---|
| 1 | Claude | Codex | Provide `App\Services\Admin\DuplicatePostReport::build()` returning per-province duplicate rates (D-2) so C-8 can render it | OPEN |
| 2 | Claude | Codex | Provide `App\Services\Admin\SnapshotEvidenceReport::forSnapshot(int)` (D-4) | OPEN |

## Done before this sprint (history, Claude lane, with evidence)

| Item | Evidence |
|---|---|
| Price trend, deal-checker context, location log (682 rows) | commits `581ca91`, `4aff215`, DL-029 |
| Admin on the DropX layout + composition fixes, Chart.js, UI rule in AGENTS.md | `e8da318`, `54254b1`, `b21d2ba`, DL-030/031 |
| Price trust audit, evidence explorer, confidence-v2, U2 first slice | `818a22e`, `def515b`, DL-032/033; CI green on `def515b` (4 checks) |

## Sprint end checklist (Claude, task C-9)

1. All Codex commits reviewed (diff read, not only test result). 2. Merge, full suite exit 0, secret scan, `price_trust_audit` 0 P0.
3. Board rows updated with evidence. 4. `PROJECT_STATUS`, `SESSION_HANDOFF`, `project_state.json`, `SPRINT_TRACKER` synced.
5. One report to the owner in Thai with three next options. 6. Push only on the owner's word.
