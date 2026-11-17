# K-3 Second evidence source: options (LS-4)

Date: 2026-11-17. Read-only look at shared DEV; nothing was written, no source was contacted. Policy applied: external spend 0 THB, no bypass of any block, no seller personal data.

## Why a second source matters (from the code, not a guess)

`SnapshotConfidenceService` caps a price at 59 ("low") when **every** included observation comes from an owner-text source (`OWNER_TEXT_ONLY_CAP`), and caps a single-source sample at 74. One included observation from any other source removes the owner-text cap; reaching "medium" or better also depends on sample size, freshness and source concentration, so removing the cap does not by itself guarantee a higher label. Also, 22 products stay pending because they have fewer than 3 usable rows; 34 more rows would clear all of them (`docs/LS4_REPORTS.md`).

## What DEV already holds (read-only, 2026-11-17)

Asking observations by source and status: owner-text approved 481 rows, owner-text pending 38, **Priceza (REAL) pending 88**, mock pending 11. **0 Priceza rows are approved.** The 88 are listing-level evidence that is already imported and waiting for a person.

| Product (pending Priceza rows) | rows | price range (THB) | owner-text approved now |
|---|---:|---|---:|
| RTX 4070 | 21 | 13,500 to 21,900 | 4 |
| RTX 3070 | 19 | 6,000 to 10,000 | 13 |
| RTX 3060 Ti | 17 | 7,050 to 64,900 | 7 |
| Ryzen 7 5700X3D | 12 | 7,000 to 12,590 | 3 |
| Ryzen 5 5600 | 9 | 2,790 to 4,901 | 4 |
| Ryzen 7 5800X | 6 | 4,790 to 10,000 | 0 |
| RX 6600 XT | 3 | 5,000 to 6,199 | 0 |
| RTX 4070 Ti | 1 | 24,000 | 0 |

The RTX 3060 Ti range (7,050 to 64,900) shows the rows are not clean: some are certainly not single used cards (a bundle or a different item). Condition is "unknown" on almost all of them.

## Options

| | Option | Cost | What it gives | Risk and limits |
|---|---|---|---|---|
| A | A person reviews the 88 pending Priceza rows already in DEV | 0 THB, no new collection | Up to 5 products with owner-text data (4070, 3070, 3060 Ti, 5700X3D, 5600) lose the owner-text-only cap once one row is approved; RX 6600 XT and Ryzen 7 5800X get rows (6600 XT would reach the 3-row minimum; 5800X has none today) | These are Priceza search-page asking prices, **not merchant listing pages**, mostly retail shop prices with unknown condition. Mixing them with used asking prices could pull a used-market price up. The reviewer must decide per row (and the owner must decide the rule: see question 1). Real rows are never auto-approved. |
| **B (Recommended)** | Owner-collected rows with listing URLs (Facebook Marketplace item, Pantip topic, shop page), following `docs/SECOND_SOURCE_INTAKE.md` and the header-only template | 0 THB, owner's time | Different domains count as different sources; fills the pending products with true used-market listings (34 rows clear all of them) | Manual labour. URLs must not be profile links; no seller names, phones or chat text. |
| C | Community submissions (ADR-006 option A) | 0 THB, build work | A later, ongoing signal | Evidence only, never sets a price; needs moderation owner and retention decisions first; no volume until there are visitors. |
| D | Automated collection from marketplaces | 0 THB in cash, high in risk | Volume | Marketplace terms very likely restrict automated collection (not verified in this run); a block means the source is paused and recorded, no bypass; needs an owner and legal decision. **Not recommended now.** |
| E | Paid data, API or crawler service | Money | Fast volume | **OWNER_APPROVAL_REQUIRED**, excluded by the zero-cost policy. Not started. |

## Recommendation

1. **B is the real path to used-market confidence (Recommended).** True used listings with URLs, different domains, and 34 rows clear every pending product. The template and steps exist.
2. **A is worth doing only as follows:** review the 88 Priceza rows as a *new-price reference* (they do not enter the used median, see question 1), or, if the owner chooses to let plausible rows enter the median, as a quick way to lift the owner-text cap on five products. Either way I can first prepare a read-only review pack (per product: rows, flag against the owner-text median under the 60% to 160% rule, obvious bundles, price spread). I will not approve anything.
3. C after the ADR-006 decisions; D and E are not proposed.

## Questions for the owner

1. **Used vs new:** Priceza lists shops, mostly new stock. For a used-hardware price check, should Priceza rows (a) be treated as a separate "new-price reference" that never enters the used-price median, or (b) enter the median when a reviewer marks the row as plausible used or like-new? Recommended: **(a)**, because mixing new and used would make the used price look higher than the market. Under (a) the Priceza rows do not lift the confidence of the used price at all (context only), which is why B is the Recommended path; say if you prefer (b).
2. Who reviews: you, or another reviewer account (the review lane needs a person, the actor is recorded).
3. For B: which sources can you collect from yourself, and roughly how many rows per week?

## What this document does not claim

It does not say that approving the Priceza rows will make any price "medium": that depends on the cap rules above and on the rest of the sample. The condition, new-versus-used and bundle problems of the Priceza rows were seen only from their price ranges, not from a row-by-row read.
