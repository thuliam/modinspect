# Sprint Board: Long Sprint LS-1 (opened 2026-11-15)

> 2026-11-16: the owner told Claude to take over the Codex lane (Codex had problems). All rows below are now Claude's; the file-ownership lock no longer separates two agents.

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
| C-1 | Admin: open every page and state, fix mismatches with the DropX layout (Bootstrap first) | DONE (Claude, 2026-11-15) | Swept 14 admin pages in light and dark at 1600px plus 390px (drawer works, no horizontal overflow) on an isolated test server with a throwaway admin (removed afterwards); automated contrast audit (WCAG AA) now 0 real failures. Fixed: sidebar "Sources" icon (fa-broadcast-tower is not in FA 5.0.9; guard test now also checks icons named in data arrays), 3 more blank icons found by that guard on public pages (fa-memory, fa-fan, fa-search-dollar), review-detail page was unreadable in dark (hard-coded white surfaces in admin.css replaced by theme variables), admin.css muted colour resolved at :root (dark value in light theme), low-contrast light-theme text/status colours, gradient stat cards darkened for AA, Bootstrap blue links. Not covered: live UAT admin (browser pane not signed in), modal contents of edit forms, file upload step of Imports. Known and unchanged: one harmless 404 per page for `fa-solid-900.html` (Font Awesome 5.0.9 CSS bug, owner gate G-7). |
| C-2 | Admin login page restyled to match the Admin shell | DONE (Claude, 2026-11-15) | Viewed on the live UAT server (login is public): same tokens, card and button as the Admin, theme-aware, 358px card at 390px, contrast audit 0 failures. A stray text fragment from my first edit was found by script and fixed. |
| C-3 | Public pages at 708px; keyboard/focus and AA contrast audit with fixes | DONE (Claude, 2026-11-15) | Live UAT server: no overflow at 390/708/768/1024/1440; contrast audit on home, /price, product, deal-checker, methodology, login: 0 failures after fixing 4 (low-confidence badge, muted notes x3); real Tab key press shows a 2px lime focus outline; skip-to-content link added. Not done: screen-reader pass. |
| C-4 | Replace repeated generic category art on product cards with licensed photos where they exist, otherwise a model-name tile (category + model in type) | DONE (Claude, 2026-11-15) | Viewed on the UAT server `/price?category=gpu`: 14 type tiles + 2 photos, no repeated artwork; full suite exit 0; no new assets |
| C-5 | Thai/English consistency of spec text on cards and detail (display layer only) | DONE | 2026-11-16: D-5 stored Thai summaries (migration 018); product detail page checked in a browser on the DEV catalog (RTX 3070: "8GB GDDR6, เหมาะกับเกม 1440p"); the 4 summaries that still contain Latin words are technical tokens only (NVMe PCIe 3.0 x4, 80 PLUS Gold). No display-layer translation hack. |
| C-6 | Mixed signals on cards (green "has market data" vs red "low confidence") reworded | DONE (Claude, 2026-11-15) | Badge now "มีช่วงราคา", lime only when confidence is medium/high (0 lime badges on the GPU list, all low); UAT screenshot; suite exit 0 |
| C-7 | Public CSS reduction (owner: "do it", 2026-11-16) | DONE within safe limits; template/markup rebuild still an owner decision (Claude, 2026-11-16) | (1) Dead-rule pruning earlier: `app.css` 1527 -> 1207 lines. (2) **Shadowed-rule removal** (`scripts/remove_shadowed_css.php`): in a real browser, for every rule that matched elements, removing it changed no computed style (measured on 14 public pages at 1440 and 390px, with transitions disabled), then removed 97 + 27 nested rules from `app.css` and 56 + 5 from `mi-theme.css` (`app.css` 49,564 -> 41,160 bytes, `mi-theme.css` 62,279 -> 56,406). Verified by swapping to the new files and comparing computed style and size of every element, with transitions off: 0 differences on all pages at 1440px and 390px except image height noise from lazy loading. Rules for :hover/:focus/::before and rules that matched nothing were never touched. A first attempt was wrong (CSS transitions hid real differences: the active nav link lost its lime colour); I found it in the swap check, switched the measurement to transitions-off and re-ran, which restored 52 rules. (3) A dark Bootstrap 4 theme (Bootswatch Darkly 4.6.2, MIT, downloaded on the owner's approval) was evaluated as the base instead of the current Bootstrap: only 14 of 455 elements on the home page differ, so replacing the base would delete little CSS (the theme layer holds colours the base does not know). Not adopted; the downloaded files were deleted from the repo. Remaining layout-only rules (39% / 18%) would need markup rewrites: only worth it with an owner-supplied public template. |
| C-8 | Render the data-lane outputs in Admin | DONE (Claude, 2026-11-16) | `/admin/market-trends` now shows the repost rate per province and an overall repost note; new `/admin/snapshots/{id}` page (stat cards, replay badge, caps, member table) linked from a new "Evidence" button in the Price Index table; unknown id gives a 404 page. Checked on an isolated server (admin, light theme, 1024px): data renders, no overflow, contrast audit only reports the gradient stat cards (known false positive). Not checked on the live UAT admin: the app browser pane lost its sign-in. |
| C-9 | Merge the Codex branch | N/A (2026-11-16) | The owner reported Codex had problems and told Claude to take over; no `codex/ls1-data` branch exists, nothing to merge. D-1..D-8 were done by Claude on the checkpoint branch. |
| C-10 | Search UX: result summary with priced count (aria-live), category chips keep the search text, empty result shows popular models that have prices | DONE (Claude, 2026-11-15) | Checked on the UAT server: `q=zzzzzz` -> 0 results + 6 suggestions; `q=ryzen&category=cpu` -> 16 found, 6 priced, chips keep q; `/price` -> 99 found, 46 priced; suite exit 0 |

### Codex lane (data, backend, tests)

| ID | Task | Status | Evidence / blocker |
|---|---|---|---|
| D-1 | **Must filter already-imported listings first (the new export is a superset; row ids differ, so the old duplicate check does not catch repeats)**. Round-4 approval: prepare from the **sanitized** CSV `storage/research/line_facebook_2026_loc/research_listings_sanitized.csv` (never the raw export), import, then `cli/owner_intake_approve.php` with the existing 60%-160% outlier rule under actor id 54; target the 24 CPU/GPU models with fewer than 3 samples | DONE (Claude took over, 2026-11-16) | `owner_intake_filter_existing.php` + `OwnerIntakeDeduper` + `tests/owner_intake_filter_test.php`. Prepared 120 CPU + 175 GPU rows from the new sanitized CSV: 227 already in the DB by title+price, and the importer's own check classed the other 68 as duplicates too (dry run: valid 0, duplicate 45+23). **Result: the new extraction adds 0 new CPU/GPU listings, so nothing was imported or approved: 0 DEV writes.** The 27 CPU/GPU models still below 3 approved samples cannot be raised from this data (110 GPU rows were skipped for missing model key: a separate enrichment topic). |
| D-2 | Duplicate-post analysis without seller identity: group by normalised text hash + price, report duplicate rate per province and per product as a service method (`App\Services\Admin\...`) plus a CLI | DONE (Claude, 2026-11-16) | migration 017 (+ schema.sql, modinspect.sql), `cli/location_log_enrich_dedupe.php`, `DuplicatePostReport`, `tests/duplicate_post_report_test.php`. DEV writes: migration 017 applied, 682 log rows enriched (216 marked repost). **Overall repost rate 31.7%; Nonthaburi 55.5% (117 of 211), Khon Kaen 53.3%, Ayutthaya 41.9%, Bangkok 26.4%.** |
| D-3 | Golden resolver test set (RTX 3050, RX 5700 XT, 3060 vs 3060 Ti, 4070 vs 4070 Ti/SUPER, Ryzen 5600 vs 5600G, 5700X3D, Intel suffixes) as a permanent test registered in `tests/run.php` | DONE (Claude, 2026-11-16) | `tests/golden_resolver_test.php`: 19 cases (3050, 3060 vs 3060 Ti, 4070 vs SUPER vs Ti, RX 5700 XT with plain RX 5700 = no match, 5600 / 5600X / 5600G, 5700X / 5700X3D, 12400 vs 12400F, unknown models = no match), all pass on the first run (no resolver failure found). |
| D-4 | Service for a per-snapshot evidence view (admin side): members, exclusion reasons, source concentration, replay result | DONE (Claude, 2026-11-16) | `SnapshotEvidenceReport::forSnapshot()` + `tests/snapshot_evidence_report_test.php` (counts, grades 4/1, concentration 0.8, replay MATCH, no source/title/URL leak). |
| D-5 | Spec summary normalisation: find mixed Thai/English or machine-generated `spec_summary` values, propose a migration with the cleaned text (additive, reviewable) | DONE (Claude, 2026-11-16) | `scripts/gen_spec_summary_thai.php` -> migration 018: 81 of 99 summaries rewritten (descriptive English to Thai, technical tokens kept, spec-class boilerplate dropped and shown once on generic-brand product pages). Applied to TEST first (suite green), then DEV (DEV write). After: 0 summaries with English descriptive words, longest 57 characters. |
| D-6 | `cli/price_trust_audit.php`: add duplicate-rate and per-source-key summary to its output; keep exit codes | DONE (Claude, 2026-11-16) | `cli/price_trust_audit.php` prints SOURCES (per source_key: spec 213, r2 123, base 60 included members) and the province-log repost rate; `--json` has `sources` and `duplicate_rate`; exit-code rule unchanged; assertions added to `tests/price_trust_test.php`. |
| D-7 | CI hygiene: confirm clean checkout builds the test DB from `modinspect.sql` with every migration reflected (script + test that fails if a migration table is missing from the dump) | DONE (Claude, 2026-11-16) | `tests/migration_dump_guard_test.php`: every table/column created by a migration must exist in modinspect.sql (all migrations) and schema.sql (015 onwards; migrations before 015 were never mirrored in schema.sql, recorded as a pre-guard gap). Demonstrated: renaming price_trend_points in a temporary change makes it fail with the exact message, restored it passes. |
| D-8 | Re-run `cli/price_confidence_report.php` and `cli/review_calibration_report.php`; write the result into `docs/REAL_CALIBRATION_REPORT.md` as a dated section, fixture and live metrics kept separate | DONE (Claude, 2026-11-16) | `docs/REAL_CALIBRATION_REPORT.md` update: fixture and live metrics in separate columns; states that the 99.72% live green precision comes from bulk rule-based approvals (0 corrections) and is not independent review evidence. |

### Continuation (Claude, 2026-11-16)

| ID | Task | Status | Evidence |
|---|---|---|---|
| E-1 | Investigate RTX 5090 low prices; price guard | DONE (no new rule) | No text signal separates the 3.5k-24k rows from the 170k-239k rows (checked as flags only); a rule I cannot justify from data would be invention. The bimodal guard (DL-038) already refuses to approve it; finding recorded in DL-039. |
| E-2 | Rank owner-fill rows by unlock value | DONE | `cli/owner_fill_priority.php`: after dedupe against DEV only 12 of 133 fill rows are actionable and none reaches the 3-row minimum. Filling the CSV will not unlock the 22 pending products; new source data is needed. |
| E-3 | Admin: what is missing for medium confidence | DONE | `ConfidenceGap` + card on `/admin/snapshots/{id}`; `tests/confidence_gap_test.php`; rendered on real snapshot #327: raw score is already above 60, the only blocker is owner-text-only evidence. |
| E-4 | High-effort review of risky server code | DONE | 3 findings: 2 fixed (asking-only basis, flags), 1 skipped by decision (price sanity for inferred-VRAM rows, E-1). |
| E-5 | CI check and close-out | DONE | CI green on a691bbd (suite and offline package, run 2026-11-16 05:04Z); PR #4 mergeable, still OPEN (owner decision). |
| F-1 | Second-source intake path | DONE | The existing `--dataset=real` importer already accepts listing-level rows and derives the source from the URL domain; added `docs/SECOND_SOURCE_INTAKE.md`, header-only `examples/import/second_source_minimal.csv`, `tests/second_source_intake_test.php` (dry run: 2 valid, no-reference and mock-domain rows rejected). Needs real rows from the owner. |
| F-2 | Admin UAT in a browser | DONE | 2026-11-16, owner signed in to the pane on `http://192.168.1.10/modinspect/public`. Checked at 1048 px (pane width, not 1440) and 375 px: `/admin/snapshots/327` (medium-gap card shows the single blocker: owner-text-only; replay MATCH; 7 members), `/admin/market-trends` (repost note 32 % of 682), `/admin/price-indices` (17 Evidence links). No horizontal overflow on any of the three at 375 px; icons render. Only console error: the known `fa-solid-900.html` 404 (G-7), the `.woff` itself loads 200. Finding: logging in on `modinspect.test` redirects to the APP_URL host `192.168.1.10`; cookies are per host, so a login on one host is not seen on the other. |
| F-3 | Public page for unpriced products | DONE (no change) | RTX 5090 page checked in a browser: states plainly that there is no market price, offers Deal Checker, search and methodology. Nothing to fix. |
| F-4 | PR #4 readiness | DONE | `docs/PR4_BODY_PROPOSED.md` (not applied to the PR), `.gitattributes` LF rules for text sources. |
| F-5 | Refresh trust and calibration reports | DONE | Update section in `docs/REAL_CALIBRATION_REPORT.md`: live sample 621, approved 481, green clean 99.76 % (not independent), 49 priced, all low. |
| G-1 | CI failure: `second_source_intake_test` returns 0 valid rows on the runner | DONE (attempt 3 of 5) | Root cause from the CI output of attempt 2: `FILE_NOT_READABLE`. The importer's `safePath()` only accepts absolute paths inside the project root on Linux (Windows drive paths bypass it), and the test wrote its fixture to the system temp dir. Test bug, not product code: the fixture now lives in git-ignored `storage/import/test`. Local fresh-DB, full-suite and no-intl runs could not reproduce it because of the Windows path rule. |

### LS-2 (Claude, started 2026-11-16, owner approved all 15, no cuts)

Run under `docs/LONG_RUN_BRIEF.md` (95% gate, 5-attempt cap, docs after every closed task). Named DEV writes approved by the owner: additive migrations for aliases (H-2) and new products (H-3, owner sees the list first), and import plus approval through `OwnerIntakePlanner` with actor 54, with before/after counts in the decision log. Features the owner thinks are still missing: `docs/REVISE_BACKLOG.md`.

| ID | Task | Status | Evidence |
|---|---|---|---|
| H-1 | Measure resolver recall on all sanitized rows (resolved vs unresolved per category) | DONE | `cli/resolver_recall_report.php` (read-only, counts and model keys only). Before: CPU recall 60.7 % / conflict 3.3 %, GPU recall 86.3 % / conflict 3.2 % (agree 74+246, unresolved 44+30, conflict 4+9 of 408 rows). The conflicts were precision bugs in the resolver's marker-only match: a plain 5060 resolved to the 5060 Ti, 6600 to the 6600 XT, 1660 Ti to the 1660 Super, and Ryzen 5600 / 3600 titles to RAM kits that share the number. Fixed in `ProductResolver::sameSuffixAndFamily` (suffix words, family words and DDR must agree), test-first: 8 new golden cases (27 total) failed on the old code and pass now. After: CPU recall 62.3 % / conflict 0.8 %, GPU recall 87.4 % / conflict 0.7 %. One old test expectation moved from WRONG_PRODUCT to UNRESOLVED_PRODUCT (the product is no longer assigned at all). Full suite green. Confidence 96 %: only the marker-only branch changed; alias matches are untouched. |
| H-2 | Alias/variant additions for unresolved models (additive migration), re-import via planner | TODO | |
| H-3 | Catalog gap list: models truly absent from Product Master, proposed additions (public specs, no prices) | TODO | |
| H-4 | Cross-model price ladder plausibility flags (flag only, never changes a price) | TODO | |
| H-5 | Admin Data health page | TODO | |
| H-6 | Snapshot history and diff vs previous snapshot | TODO | |
| H-7 | RBAC tests for every admin route and CSRF scan of every POST route | TODO | |
| H-8 | SEO and share basics: titles, descriptions, canonical, Open Graph, sitemap.xml, robots.txt | TODO | |
| H-9 | Public performance and accessibility audit in the browser, fixes with existing Bootstrap utilities | TODO | |
| H-10 | Deal Checker and /build at 390 px plus edge-case tests | TODO | |
| H-11 | DB backup/restore scripts with a restore drill on the test DB, health endpoint | TODO | |
| H-12 | Windows vs Linux parity scan (paths, case-sensitive file names) with a guard test | TODO | |
| H-13 | Security review refresh of routes and CLIs added since 2026-11-14 | TODO | |
| H-14 | Thai operator guide (weekly routine) and README implemented-vs-planned refresh | TODO | |
| H-15 | Pre-beta readiness checklist and Go/No-Go template | TODO | |

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
