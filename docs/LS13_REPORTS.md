# LS-13 report (2026-11-17)

Scope approved by the owner: S-1 to S-6. Nothing was written to the DEV database; the only DEV access was read-only (SELECTs and form submissions that save nothing). No spend.

## S-1 Complete-PC categories in the daily collection list

`CollectionTargets::categoryNeeds` and `renderCategoryNeeds`, printed after the product list by `php cli/collection_targets.php` (text only; the CSV keeps its shape). For each of the 8 PC categories it uses the Build page's own rule for "priced" (`SpecBuilderService::isPriced`). A category with no priced part stays locked on `/build`; for those it names up to five catalog parts to collect first, closest to a price first, with the rows still needed and the search words, and sends a part that already holds enough rows to the review queue instead of asking for more listings.

On DEV today: 4 of 8 categories are locked: motherboard (13 parts), PSU (5), case (3), cooling (2). Finding: the PSU "PSU 850W 80 PLUS Gold" already holds 3 usable rows, so it needs review, not collection. Evidence: `tests/collection_category_needs_test.php` (red first; 7 mutations caught); the existing `tests/collection_targets_test.php` still passes.

## S-6 Default pick on save

`SpecBuilderService::generate` no longer picks two hard-coded models (an i5-12400F or a Ryzen 7 5700X3D, and an RTX 3070) when someone saves with nothing chosen. It now uses the balanced plan of the recommender over the priced catalog. Behaviour for a visitor who chose parts is unchanged. `tests/spec_builder_estimate_test.php` was changed first to expect the new behaviour; mutations to the default preset (saving, full) and to the selection step are caught (the first run left one survivor because the fixture made balanced and full equal; a fixture where they differ was added).

## S-3 Radar labels

The axis labels are set to 13 px through Chart.js `pointLabels` (the default was small); no CSS. Test first (`tests/spec_radar_test.php`). Checked in the browser on the isolated test database: the chart option is 13 and the radar draws in the real theme. The saved test row was deleted.

## S-2 Recommend button across presets and uses

- **Sweep against the real DEV catalog (read-only):** 240 combinations (4 budgets × 5 uses × 4 resolutions × 3 presets): 0 problems. Every pick is a priced part of the right category, results are deterministic, no locked category is ever picked, an office build never gets a GPU, no plan is over budget, and the saving, balanced and full totals rise in that order (for example gaming 1080p at 25,000: 11,630, 15,675, 18,635).
- **Through the real form in the browser (DEV, nothing saved):** gaming 1080p balanced at 25,000 (done in LS-11), office 1440p full at 30,000 (CPU, RAM and storage, no GPU, reserve ฿11,400), AI 4K saving at 60,000 (i5-12400F, RTX 4070 SUPER, RAM, storage, ฿30,135). The chosen use, resolution, preset and budget were kept after the round trip.
- Not checked in the browser: the other combinations (covered by the sweep, not by a person looking at the page).

## S-4 Pilot scope

`docs/PILOT_SCOPE_TH.md`: a proposal, not a decision. It separates pilot from public beta, lists what stays mandatory (credentials rotated, HTTPS, production `.env`, backup, a short privacy notice, no search indexing) and what may be relaxed, who does what, and when the pilot counts as closed. It needs the owner's word "pilot".

## Not verified, said plainly

- The wording of the new report lines (in Thai) and of the pilot document is mine and has not been reviewed by the owner.
- The "no search indexing" item in the pilot document is not implemented; it waits for the owner's go.
- The radar label size was checked as a chart option and visually at desktop width only, not again at 375 px.

## Close-out

Full sequential suite exit 0 (all new tests registered and passing). Secret scan clean on tracked files; the new files were searched separately and only prose mentions turned up. Committed as 57460ad (LS-11 to LS-13 together, with the two earlier documentation commits) and pushed to the PR #4 branch. At the time of writing the PR monitor showed 4 checks passing and the PR mergeable, but that read was taken right after the push and may still describe the previous head; the monitor wakes the session on a failure.
