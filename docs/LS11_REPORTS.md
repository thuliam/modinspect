# LS-11 reports (2026-11-17)

Scope approved by the owner: A, B, C, D, F (tier engine E deliberately deferred). Everything here works without the performance figures the research helper is still collecting. Nothing was written to the DEV database: the recommend button only reads, and the saved-build page was checked on the isolated test database (the test row was deleted afterwards). No spend.

## A. "แนะนำสเปกให้" with three presets

`app/Services/SpecRecommender.php` (called through `SpecBuilderService::recommend`), form fields and controller branch on `/build`. Deterministic PHP, no AI:

1. The budget is split into shares per category by use (gaming, streaming, AI, work, office) and moved a little between CPU and GPU by resolution (4K +8 points to the GPU, 1080p −5).
2. A preset sets how much of each share is spent: ประหยัด (saving) 70%, สมดุล (balanced) 90%, ใช้งบเต็มที่ (full) 100% and then climbing in 5% steps to 150% while the plan still fits.
3. Each category takes the priced part whose median is closest to its share without going over; the cheapest priced part if none fits.
4. Categories with no priced part (today motherboard, PSU, case and cooling) keep their share as a reserve. The priced parts may only use the rest; the plan is scaled down until it fits, and the note says so when even the cheapest parts do not fit.
5. The motherboard is chosen after the CPU and the RAM after the motherboard, so socket and memory type match (unknown values never rule a part out, the same rule the existing compatibility check uses).

The recommendation ticks the visitor's own radio buttons in the same form, so the existing summary, budget bar and save button work unchanged and the visitor can change any part. **Nothing is saved until the save button is pressed.** The note under the form says the choice follows price and share of budget, not measured speed, and names the low-confidence data when the average is under 60%.

Evidence: `tests/spec_recommend_test.php` (red first, then green). 10 distinct mutations were tried; the survivors found along the way (the 90% and 70% factors, the reserve gate on the climb, the low-confidence note) were closed by new cases, and all are caught now except one equivalent mutation (the starting factor of "full" does not change the final plan because it always climbs to the largest fitting step). On DEV in the browser (read-only): budget 25,000, gaming, 1080p, balanced gave i5-14400F, RTX 3060, RAM DDR4-2666 8GB and NVMe 500GB at ฿15,675, the reserve ฿6,250 for the four unpriced categories was named, and the existing summary computed ฿9,325 left.

## B. The four form fields

Found by reading the code: "ความละเอียด" and "รูปแบบ" changed nothing the visitor could see, and "ใช้สำหรับ" only picked between two hard-coded default parts. Now:

- use and resolution drive the recommendation, and the visitor's choices are kept after a round trip (they were reset before);
- the "ใหม่ + มือสอง" field is gone. The index holds used prices only (Priceza is a new-price reference and never enters the cohort), so the choice was not real. The page says "ราคาอ้างอิงมือสองเท่านั้น (ยังไม่มีข้อมูลราคาของใหม่)" and still sends `mix_preference=used` so the saved build and its validation keep working.

Not changed: `SpecBuilderService::generate` still picks two hard-coded parts when someone presses save with nothing chosen (RULE 1, it works and is covered by tests). It can be replaced by the recommender later if the owner wants.

## C. Radar on the saved build

`app/Services/SpecRadar.php` and `app/Views/components/build-radar.php`, shown on `/build/{uuid}`. Chart.js 4.4.4 is the library already approved and shipped; the component follows the pattern of `trend-chart.php`. Four axes, each measured from the saved build (formulas in the class and in the page): coverage of the 8 categories, mean confidence of the data, share of the budget used, and stability of prices (100 minus the mean Q1–Q3 width as a percent of the median). **There is no speed axis**, and the page says so plainly, because no reviewed performance data exists yet.

Evidence: `tests/spec_radar_test.php` (formula cases, alternatives ignored, zero and empty cases). Browser check on the isolated test database with a saved build: the radar draws in the real theme at desktop width and at 375 px with no horizontal overflow. The saved row was removed.

## D. Checker for the performance CSV

`cli/validate_perf_scores.php` and `app/Services/Collection/PerfScoreCsvValidator.php`, for the CSV that returns from the research helper (`docs/GPT_HANDOFF_PERF_DATA.md`). Read-only: it reads the CSV and SELECTs the CPU/GPU slugs (or reads `--catalog=file` with no database) and prints a Thai report. It refuses rows with an unknown slug or category, a figure that is not a plain positive number, no source name or URL, a URL that is not http(s), a missing or future read date, a percentage without its 100 baseline, or a duplicate. It counts independent sources per model (the first source, Tom's Hardware, does not count), warns on a missing page date or terms link, and flags two sources that measure the same thing on the same scale and baseline but differ by more than 25%.

Evidence: `tests/perf_score_csv_validator_test.php` (13 error cases by row number, 10 mutations caught, the class never touches the database). The CLI was run against a small sample both with `--catalog` and against the DEV catalog.

A correction found on the way: the catalog holds **28** active CPUs, not 27. Core i7-12700F was missing from the handoff list and from the research table; both documents are fixed.

## E. Deferred

The tier engine (S+ to A) is not built. It needs reviewed performance data and the owner's decision on the sources and their terms. Until then no tier appears anywhere on the site.

## F. Documents

`docs/OWNER_DECISIONS_TH.md` lists the open decisions; this report, SPRINT_BOARD, DECISION_LOG (DL-091 to DL-094), PROJECT_STATUS and SESSION_HANDOFF are updated.

## Not verified, said plainly

- The Thai wording of the new labels and notes (รูปแบบการแนะนำ, ประหยัด, สมดุล, ใช้งบเต็มที่, the recommendation note, the radar labels and notes) is mine and has not been reviewed by the owner.
- Presets choose by price share, not speed; "ใช้งบเต็มที่" means the parts closest to the share, not the fastest parts.
- The recommend button was checked on DEV only for one input (25,000 baht, gaming, 1080p, balanced); the other presets, uses and resolutions are covered by the pure tests.
- The radar chart's axis labels are small (Chart.js defaults); not tuned.
- The save button was not pressed on DEV.

## Close-out

Full sequential suite (`php tests/run.php`) exit 0 with the three new tests registered and passing, and the secret scan passed (496 files, 8 rules; it scans tracked files only, so the new untracked files were checked separately with a pattern search that found only prose mentions). Nothing is committed or pushed yet. CI is read from the PR monitor after a push.
