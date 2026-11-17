# LS-10 reports (2026-11-17)

Scope approved by the owner: Q-1 to Q-3, focused on the `/build` page now that it is in the main menu. Nothing was written to the DEV database (only GET requests, no save button pressed). No spend.

## Q-1 `/build` with real DEV data (read-only)

The page was opened on DEV with a plain GET. What a visitor sees today:

| Category | Models listed | Selectable (has a market price) |
|---|---:|---:|
| CPU | 28 | 12 |
| Motherboard | 12 | 0 |
| GPU | 29 | 19 |
| RAM | 18 | 14 |
| Storage | 6 | 4 |
| PSU | 4 | 0 |
| Case | 1 | 0 |
| Cooling | 1 | 0 |
| Monitor | 0 | 0 (the page says the price database does not cover it yet) |

Findings:
1. **Mislabelled side menu (fixed).** The side menu said "มีข้อมูลราคา" (has price data) for motherboard, PSU, case and cooling although none of their parts could be chosen, because the label only checked that the category exists. It now says "รอข้อมูล" with the lock icon, the same as the monitor, until at least one part in the category has a market price.
2. **A complete PC cannot be built yet.** Four of eight categories have no priced part, so the page can only estimate a partial build (it already says "ใช้ได้บางส่วน" in its heading). This is a data gap, not a code defect; it closes as owner-collected listings are reviewed.
3. Three wording defects in the estimate text, found by reading the code and fixed in Q-2.

## Q-2 Tests and fixes for the estimate logic

`tests/spec_builder_estimate_test.php` (pure, no database): totals are the sums of the chosen parts' low, median and high; the reserve is budget minus the expected total and never negative; a part without a median, without a sample, or without accepted rows never counts even if its id is sent (each condition tested alone); the default pick when nothing is chosen follows the budget and the use, and an unpriced default gives an empty plan, not an invented one; the new-parts mix reminds that the index is a used-price index. The totals, reserve, filtering and default cases passed on the first run; the three wording cases failed first and were then fixed:

- the reserve note named categories with raw English keys inside Thai text ("สำหรับ motherboard, ram, storage, psu, case, cooling"); it now uses the page's own Thai names;
- the 4K sentence said "RTX 3070 เหมาะกับ 1440p" for any GPU, so an RTX 4090 was described as an RTX 3070; it now appears only when the chosen GPU is the RTX 3070 (for other GPUs the code has no data to judge, so it says nothing);
- a build over budget said only "reserve ฿0"; it now also says "เกินงบประมาณ ฿N" with the amount.

A new `SpecBuilderService::isPriced()` holds the single rule for "has a market price", used by the estimate and by the page. 10 mutations are caught (the first run left two survivors, so each of the three price conditions got its own test case). The existing `tests/spec_builder_compat_test.php` is unchanged and passes.

Not changed: the page layout, the part picker, and the saved-build page. The Thai wording of the new sentences is mine and has not been reviewed by the owner.

## Q-3 Verification

Full suite and secret scan before the commit; the new side-menu state was checked on DEV in the browser (read-only); CI is read from the PR monitor after the push.
