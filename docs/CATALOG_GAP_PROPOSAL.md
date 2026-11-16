# Catalog gap proposal (LS-2 H-3)

Models that appear in the sanitized research rows (extractor `model_key`) and have **no** active product in the Product Master. Counts are rows (single-item, not repost) in the research CSV, not market prices.
**Nothing was added to DEV.** The owner approved additive migrations for new products only after seeing this list: tell the agent which rows to add (or "all mainstream"), and it will create a migration with public specs only (no prices) and the matching aliases, then re-run the intake through the planner.

**Finding:** only 1 of the 60 missing models (`gtx 1080 ti`, 3 rows) reaches the 3-row snapshot minimum. Adding products would make 75 rows resolvable but would not create a new price today.

**Options for the owner**
- A. Add none now (**Recommended**): no new price results, and each product adds an empty public page. Revisit when a second source brings more rows.
- B. Add only `gtx 1080 ti`: the single model that could reach a snapshot.
- C. Add all mainstream models in the list (everything except workstation keys): better resolver coverage and a more complete catalog for later data, at the cost of about 55 mostly empty pages.

How to read: a model with few rows will not reach the 3-row snapshot minimum on its own; adding it still lets those rows resolve and become pending observations instead of staying unresolved.

## GPU (20 models, 30 rows)

| model_key | rows | 3+ rows |
|---|---:|---|
| gtx 1080 ti | 3 | yes |
| rtx 5050 | 2 | no |
| rx 6600 | 2 | no |
| gtx 1660 ti | 2 | no |
| rx 5700 xt | 2 | no |
| rtx 3090 ti | 2 | no |
| rtx 2070 super | 2 | no |
| rtx 4080 super | 2 | no |
| gtx 1050 ti | 2 | no |
| rtx a5000 | 1 | no |
| rx 9070 | 1 | no |
| rx 7900 gre | 1 | no |
| rtx 2080 | 1 | no |
| rtx 2070 | 1 | no |
| rtx 2060 super | 1 | no |
| rtx 4080 | 1 | no |
| rtx 2080 ti | 1 | no |
| gtx 1050 | 1 | no |
| rtx 4070 ti super | 1 | no |
| gtx 1650 super | 1 | no |

## CPU (40 models, 45 rows)

| model_key | rows | 3+ rows |
|---|---:|---|
| ryzen 4600g | 2 | no |
| i7-14700k | 2 | no |
| ryzen 2600 | 2 | no |
| i5-14500 | 2 | no |
| ryzen 8400f | 2 | no |
| ryzen 7600x | 1 | no |
| i7-7700 | 1 | no |
| i7-8700 | 1 | no |
| i5-14600k | 1 | no |
| i9-12900kf | 1 | no |
| ryzen 9950x3d | 1 | no |
| ryzen 9950x3d2 | 1 | no |
| i5-10400f | 1 | no |
| i3-8100 | 1 | no |
| ryzen 3600 | 1 | no |
| i7-3770 | 1 | no |
| i3-4130 | 1 | no |
| ryzen 9850x3d | 1 | no |
| i5-9400f | 1 | no |
| ryzen 5500x3d | 1 | no |
| ryzen 5900x | 1 | no |
| ryzen 3200g | 1 | no |
| ryzen 3700x | 1 | no |
| i9-14900kf | 1 | no |
| i9-14900 | 1 | no |
| ryzen 5800x3d | 1 | no |
| i3-9100f | 1 | no |
| ryzen 2400g | 1 | no |
| i7-14700 | 1 | no |
| i5-10400 | 1 | no |
| i7-13700kf | 1 | no |
| ryzen 7700x | 1 | no |
| ryzen 7700 | 1 | no |
| i7-14700f | 1 | no |
| i7-10700f | 1 | no |
| i7-9700f | 1 | no |
| i5-8600 | 1 | no |
| i5-11400 | 1 | no |
| ryzen 5600g | 1 | no |
| ryzen 7700x3d | 1 | no |

Notes: workstation or unusual keys (for example `rtx a5000`) are listed so the owner can decline them.
