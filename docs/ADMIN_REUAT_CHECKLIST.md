# Admin re-UAT checklist (owner, ~10 minutes)

Purpose: the Admin tables now run **official DataTables 1.13.11** (they ran a hand-written adapter before). Automated tests and an
isolated-browser check pass, but only you can accept it on the real runtime. This is gate G-2.

Where: `http://192.168.1.10/modinspect/public/admin` (Studio, shared DEV database), signed in with your own admin account.
Expected counts are the shared-DEV numbers read on 2026-11-14 (41 products, 108 aliases, 99 observations, 41 price snapshots).
They may differ if the data changed since.

**Do not** click Approve, Reject, Exclude, Confirm Import, Pause or Disable while testing. Searching, sorting, paging, filtering and
opening forms (then Cancel) are all safe.

| # | Page | Do this | Expect | Pass? |
|---|---|---|---|---|
| 1 | Login | Open `/login` | Page shows a search icon in the round mark and a sign-in icon on the button (no empty boxes) | |
| 2 | Products | Open the page | "Showing 1 to 25 of 41 entries" | |
| 3 | Products | Type `5700X3D` in Search | After a short pause: 1 row, "filtered from 41 total" | |
| 4 | Products | Clear search, choose Category = CPU, **Apply Filters** | Fewer rows than 41; Category = All restores 41 | |
| 5 | Products | Click page 2; click the **Product** header twice | Different rows on page 2; order flips between A-Z and Z-A | |
| 6 | Products | Click the **Action** header | Nothing sorts (it is no longer a sort column) | |
| 7 | Products | Click **Add Product**, then Cancel | Form opens and closes; nothing saved | |
| 8 | Aliases | Open, search `5600` | Matching aliases only; count shown | |
| 9 | Observations | Search `5700` | Filtered list; REAL and MOCK_TEST labels visible | |
| 10 | Review Queue | Open | REAL pending items listed (88 expected) or an honest empty state; filters work | |
| 11 | Imports | Open; click **Download CSV Template** and **Download JSON Template** | Both download; the CSV opens with 27 columns; button icons are visible | |
| 12 | Price Index | Open | **Freshness** column shows values like `85.0%`, never above 100%; provenance shows `legacy_unavailable` | |
| 13 | Sources | Open | Sources listed (DEV has 7 data sources); **Controls** header does not sort | |
| 14 | Audit | Open, search an action word | Filtered rows; **Details** expands | |
| 15 | Any table | Open the browser console (F12) | No red JavaScript errors (a missing-favicon 404 is acceptable) | |

How to report: reply with the row numbers that failed and a screenshot (crop out anything private). If everything passes, say
"G-2 pass" and I will mark Epic E1 as owner-accepted in `docs/SPRINT_TRACKER.md`.

Note on public prices: after the 2026-11-14 fix the public site shows **no price** for any product until a provenance-recorded
snapshot exists. Seeing "ยังไม่มีราคาตลาด" on every product page is the correct result today.
