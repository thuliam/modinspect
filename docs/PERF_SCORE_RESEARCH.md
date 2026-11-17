# Performance score research (draft for owner review)

Read 2026-11-17. **Nothing here is in the database or on any page of the site.** This is a research note for the owner's decision on the "tier" and radar features (see `docs/OWNER_DECISIONS_TH.md`). Prices stay separate from these figures, and performance figures never feed `PriceSnapshotService`.

## Method and limits

- One source so far: Tom's Hardware, two pages, each read once in the in-app browser (tables read from the page, no bulk fetching, no scripts run against the site):
  - GPU: <https://www.tomshardware.com/reviews/gpu-hierarchy,4388.html>, page says "last updated June 25, 2026". Raster table, 11 games, geometric mean, Ryzen 7 9800X3D test bench, native resolution, no upscaling. All scores are percentages of the RTX 5090 at 1080p Ultra (5090 = 100).
  - CPU: <https://www.tomshardware.com/reviews/cpu-hierarchy,4312.html>, page says "last updated November 14, 2026". Gaming table: 17 games, RTX 5090 at 1080p with a High/Ultra mix, 100 = Ryzen 7 9850X3D. Single- and multi-thread tables are application tests, 100 = best chip in that table.
- **Licence / reuse:** the pages carry no statement allowing reuse of the tables, and the site is an affiliate-funded publication. Whether showing a figure derived from them on our site is acceptable is **unresolved** and is the owner's (and E-1 legal review's) call. The proposed use below is our own index with the source cited, not a copy of their table.
- **One source is not a cross-check.** A second independent source is needed before any figure is used. None has been read yet.
- Figures are real numbers read from the pages. Where a catalog model is not on the page, the cell is empty. Nothing was filled from memory or estimated.

## GPUs: raster score, percent of RTX 5090 (catalog 29, covered 25)

"Index" = mean of the 1080p and 1440p scores (proposal only; 4K left out because VRAM size dominates there). Models with two memory variants on the page are shown for both because the catalog name does not say which one is meant.

| Catalog model | 1080p | 1440p | Index |
|---|---|---|---|
| RTX 5090 | 100.0 | 100.0 | 100.0 |
| RTX 4090 | 90.1 | 85.7 | 87.9 |
| RTX 5080 | 81.9 | 76.7 | 79.3 |
| RX 9070 XT | 76.9 | 69.7 | 73.3 |
| RTX 5070 Ti | 76.2 | 69.8 | 73.0 |
| RTX 4070 Ti | 66.3 | 58.6 | 62.5 |
| RTX 5070 | 65.1 | 57.6 | 61.4 |
| RTX 4070 Super | 62.2 | 54.5 | 58.4 |
| RTX 3090 | 60.3 | 54.7 | 57.5 |
| RTX 3080 Ti | 58.7 | 53.3 | 56.0 |
| RX 7800 XT | 58.1 | 50.7 | 54.4 |
| RTX 3080 | 54.8 | 49.0 | 51.9 |
| RTX 4070 | 54.7 | 46.5 | 50.6 |
| RTX 5060 Ti 16GB / 8GB | 51.6 / 49.3 | 43.9 / 41.0 | 47.8 / 45.2 |
| RX 9060 XT 16GB / 8GB | 48.2 / 45.7 | 40.2 / 37.3 | 44.2 / 41.5 |
| RTX 3070 Ti | 46.4 | 40.0 | 43.2 |
| RTX 4060 Ti 16GB / 8GB | 43.8 / 43.2 | 36.2 / 35.2 | 40.0 / 39.2 |
| RTX 5060 | 43.4 | 35.8 | 39.6 |
| RTX 3070 | 42.8 | 34.8 | 38.8 |
| RX 6700 XT | 38.9 | 32.5 | 35.7 |
| RTX 3060 Ti | 36.4 | 30.5 | 33.5 |
| RTX 4060 | 35.1 | 28.4 | 31.8 |
| RTX 3060 (12GB) | 30.2 | 25.0 | 27.6 |
| RX 6600 XT | 30.8 | 24.3 | 27.6 |
| RTX 3050 | 21.9 | 17.8 | 19.9 |
| **Not on the page** | GTX 1650, GTX 1660 Super, RTX 2060, RX 6500 XT | | |

The four missing cards are older. The page says its older suites are on other pages with a different test system, so those numbers would not be comparable without care.

## CPUs (catalog 27, only partly covered)

Three tables, three different meanings. The gaming table is measured with an RTX 5090 at 1080p, so it shows CPU headroom, not what a buyer with a mid-range GPU would see. "proxy" means the page lists a close sibling, not the exact catalog SKU.

| Catalog model | Gaming (9850X3D=100) | Single-thread (app) | Multi-thread (app) |
|---|---|---|---|
| Ryzen 7 9800X3D | 97.0 | 87.6 | 54.7 |
| Ryzen 7 7800X3D | 85.6 | 77.3 | 42.8 |
| Core i9-13900K | 76.8 | 92.4 | 77.9 |
| Core i7-14700KF | 76.4 (proxy: 14700K) | 90.1 (proxy) | 72.2 (proxy) |
| Core i5-13600K | 70.9 | 82.4 | 48.3 |
| Ryzen 9 7900X | 69.2 | | |
| Ryzen 7 9700X | | 90.6 | 45.4 (65 W) |
| Core i5-14400 | 58.0 | 75.4 | 31.4 |
| Core i5-13400F | | 74.0 | 32.0 |
| Core i3-13100F | | 71.0 | |
| Core i5-12400F | | 69.5 | 24.0 |
| Core i3-12100F | | 67.3 (DDR4-3200) | |
| Ryzen 7 5700X | | 67.0 | 29.5 |
| Ryzen 5 5500 | | 60.0 | 21.9 |
| Core i3-14100F | | | 19.2 (DDR4-3200) |
| **Not on the page at all** | Ryzen 5 5600, 5600X, 7600, 7500F, 8500G; Ryzen 7 5700X3D, 5800X; Ryzen 7/5 2700X, 2600X; Ryzen 3 3400G; Core i5-14400F, 13500 | | |

Gaming-table coverage is 7 of 27, so CPUs cannot be tiered from this one page. Several budget parts that the build page leans on (5600, 5700X3D, 12400F) have no gaming figure here.

## What this means for the tier and radar features

1. **GPU:** one source covers 25 of 29. Enough to prototype a GPU axis, not enough to publish.
2. **CPU:** coverage is too thin. A second source is needed, or the owner supplies the missing scores (option A).
3. **Mixing numbers from different tables is not allowed.** The three CPU columns use different baselines (9850X3D, 270K Plus, 9950X3D2). They can be shown side by side but never averaged without a stated formula.
4. **A tier needs a pairing rule** (a CPU score and a GPU score from different tables). Proposal: GPU index and CPU gaming index are normalized to a 0 to 100 scale separately, then the lower of the two limits the "gaming" axis, so a strong GPU with a weak CPU does not read as top tier. This is a design idea to review, not a decision.
5. Every displayed figure should carry source name, page date and the label "index from public test results, not a guarantee of performance".

## Suggested next steps (all read-only until you approve)

- Read a second source (for example PassMark pages for individual CPUs and GPUs, one page per model, honouring each site's terms; stop on any block). Check the licence line before using it.
- You decide on legal acceptability of using these sources at all, and whether to fall back to owner-entered scores for the gaps.
- Only after that: add a score column with a source reference, tests first, then the tier and radar on `/build`.
