# Catalog spec audit (LS-3 J-12)

Date: 2026-11-17. Question from the approved plan: fill the catalog's spec and release-date columns from public facts (no benchmarks)? Before writing anything, this audit checks which columns are empty, whether anything shows or uses them, and what a fill would change for a visitor.
**Nothing was written to the shared database.** One code fix came out of the audit (below).

## 1. What is empty (active products, read-only count on DEV, 2026-11-17)

Numbers are products with the field EMPTY.

| Category | Products | generation | release_year | spec_summary | tdp_watt | recommended_psu_watt | socket | chipset | memory_type | form_factor | capacity_gb |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| GPU | 29 | 0 | 0 | 0 | 0 | 18 | 29 | 29 | 29 | 29 | 29 |
| CPU | 28 | 0 | 0 | 0 | 0 | 20 | 0 | 28 | 18 | 28 | 28 |
| RAM | 18 | 2 | 17 | 0 | 18 | 18 | 18 | 18 | 1 | 18 | 3 |
| Motherboard | 12 | 12 | 12 | 0 | 12 | 12 | 0 | 0 | 0 | 0 | 12 |
| Storage | 6 | 6 | 6 | 0 | 6 | 6 | 6 | 6 | 6 | 4 | 2 |
| PSU | 4 | 3 | 3 | 0 | 4 | 4 | 4 | 4 | 4 | 2 | 4 |
| Case | 1 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 1 | 0 | 1 |
| Cooling | 1 | 1 | 1 | 1 | 1 | 1 | 0 | 1 | 1 | 0 | 1 |

Most "empty" cells are not gaps: a GPU has no socket, a RAM kit has no TDP. The meaningful holes are `recommended_psu_watt` (GPU 18, CPU 20), `memory_type` for 18 CPUs, `release_year` for RAM and motherboards, and `capacity_gb` for GPUs (the VRAM is in the Thai `spec_summary` text instead).

## 2. Does anything use these columns? (code search)

| Column | Public views | Models, services, controllers |
|---|---:|---:|
| tdp_watt, recommended_psu_watt, release_year | 0 | 0 |
| capacity_gb | 0 | 2 |
| memory_type, socket | 1 (the /build page) | 2 (`SpecBuilderService` compatibility checks) |
| chipset, form_factor | 1 | 0 to 1 |

**The only code that reads `socket` and `memory_type` is the /build compatibility check**, and it compares a CPU with a motherboard (socket) and RAM with a motherboard (memory type). Both columns are complete for motherboards and CPUs. TDP, recommended PSU wattage and release year are read by nothing: filling them would change nothing a visitor sees.

## 3. Finding: an empty value was treated as a mismatch (fixed in code)

`Klevv CRAS X RGB DDR4` (slug `klevv-cras-x-rgb-ddr4`) is the one RAM product with an empty `memory_type`. `SpecBuilderService` compared the empty value with the motherboard's `DDR4` and printed "RAM  ไม่ตรงกับเมนบอร์ด DDR4": a false incompatibility for a part that is DDR4 by name. Test first: `tests/spec_builder_compat_test.php` failed on the old code with exactly that message and passes now. Rule after the fix: **an empty value means unknown, never different.** Only two known, different values are an error; an unknown socket or memory type becomes a note ("check it yourself before buying") and never hides a real error elsewhere in the same build. No data was changed to achieve this.

Optional data fix (NOT applied, for the owner to approve): `UPDATE products SET memory_type = 'DDR4' WHERE slug = 'klevv-cras-x-rgb-ddr4';` (the name and the Thai summary both say DDR4). With the code fix above the page is already correct either way; the update only removes the "unknown" note for this part.

## 4. Options for the owner (with a Recommended pick)

| Option | What | Effect for a visitor | Risk |
|---|---|---|---|
| **A. Do not bulk-fill now (Recommended)** | keep the catalog as is; apply only the optional Klevv fix if wanted | none beyond the code fix, which already removed the false error | none; avoids maintaining about 150 hand-entered values that nothing reads |
| B. Fill the empty `recommended_psu_watt` / `release_year` from public facts | about 40 to 60 values, shown to the owner as a list before any write | none until a page displays them | a wrong figure on a page later; each value needs a source check |
| C. First design where specs would show (product page spec block, a PSU hint in /build), then fill exactly the columns that block needs | a UI task, then a data task | visible and useful | needs the owner's design decision (and a supplied template or an approved library for the layout) |

Recommendation: A now. If the owner wants specs to matter to visitors, C is the honest route: decide the display first, then fill only what it shows, with a source noted per value.

## 5. What was not done and why

No release dates or wattages were entered. Doing so would have been writing about 100 unverified figures to a shared database to fill columns that no page reads, and the owner asked to see any list before a write. The figures I know from memory (typical board power, launch years) are plausible but I have not checked them against a source in this run, so they are not proposed as data yet.
