# UI hand-written code audit (2026-11-15)

Standing owner rule (AGENTS.md "UI Rule"): no hand-written CSS/JS when a library, plugin or supplied template can do the job.
This is the honest inventory of what agents wrote by hand, and what replaces it.

| File | Lines | What it is | Status / replacement |
|---|---:|---|---|
| `public/assets/app.css` | 1527 | Legacy public CSS from the first prototype, still loaded before the themes | **Candidate to delete** once every public page is verified without it; Bootstrap 4.1 + `mi-theme.css` already restyle the same elements. Needs a page-by-page check, not done yet |
| `public/assets/mi-theme.css` | 610 | Public theme layer (CI tokens, hero, cards, deal checker, brand logos, trend/similar lists) | Mostly hand-written. Replaceable in part by Bootstrap utilities in markup; brand tokens/hero/logo styling stay (no library covers them). **Reduction pending owner decision on a public template** |
| `public/assets/ci-tokens.css` | 32 | CI colour tokens + self-hosted font-face | Allowed (design tokens) |
| `public/assets/mi-admin.css` | 107 | Old admin dark overrides (CI look) | Superseded by `mi-dropx.css` token re-pointing; still loaded because Bootstrap component overrides (forms, DataTables, pagination) live there. Merge or delete after admin review |
| `public/assets/mi-dropx.css` | 150 | Admin skin copied from the DropX template's own values (tokens, nav states, gradient cards, table/badge look) | Template-derived; layout is Bootstrap utilities in the PHP markup. Residual custom part = what Bootstrap 4.1 cannot express (flex shell, sticky header, nav active state) |
| `public/assets/mi-dropx.js` | 41 | Theme toggle, mobile drawer, fullscreen, Ctrl K nav filter | Glue (allowed), no library does these for Bootstrap 4 |
| `public/assets/site.js` | 83 | Public reveal/count-up/tabs/motion toggle (jQuery) | Could be replaced by a small library (e.g. AOS) if the owner approves a download |
| `public/assets/spec-builder.js` | 49 | Admin product spec form helper | Pre-existing, small |
| trend chart | was ~60 lines SVG + 10 CSS rules | Hand-drawn SVG trend chart | **Replaced** by Chart.js 4.4.4 (MIT, self-hosted in `public/assets/vendor/chartjs/`, owner approved 2026-11-15); remaining glue ~15 lines in `components/trend-chart.php` |
| `app/Views/admin/*` tables | - | Bootstrap tables + DataTables (already libraries) | OK |

## Not done yet (needs an owner decision)

- No public-site template has been supplied; the public theme stays hand-written until one is (or the owner approves shrinking it to Bootstrap utilities).
- `app.css` removal needs a regression pass over all public pages.
- Login page (`mi-auth.css`, 16 lines) still uses the old look.

## Update 2026-11-15 (Module A)

- `app.css`: 1527 -> 1207 lines after static dead-rule pruning (`scripts/prune_dead_css.php`, test `tests/ui_prune_css_test.php`); `mi-theme.css` 623 -> 605. Computed styles unchanged on all checked public pages.
- Finding: `app.css` is not removable. The live public theme sits on its `body.mb` layer and base rules; deleting the file would only relocate hand-written CSS. Removing it for real means rebuilding public pages on Bootstrap utilities or adopting a public template (owner decision).
- `mi-dropx.css` (admin) is template-derived; `admin.css` hard-coded light colours were converted to theme variables so dark mode works.

## Update 2026-11-16 (public rebuild assessment)

- Layout-only rules (replaceable by Bootstrap utilities): `app.css` 210 of 541 (39%), `mi-theme.css` 88 of 480 (18%). The remainder is theme (colour, background, border, type) that Bootstrap 4 does not supply for a dark identity.
- Not converted: ~15 views would need markup rewrites for a small CSS saving and regression risk. Owner options are recorded on `docs/SPRINT_BOARD.md` row C-7 (accept the theme layer, approve a dark Bootstrap 4 theme download, or supply a public template).

## Update 2026-11-16 (shadowed-rule removal)

- `app.css` 1207 -> about 1000 lines and 49,564 -> 41,160 bytes; `mi-theme.css` 605 -> about 545 lines and 62,279 -> 56,406 bytes, by removing rules proven redundant in a browser (no computed-style change on 14 pages x 2 widths, transitions off). Tool: `scripts/remove_shadowed_css.php`.
- Lesson recorded: measure with CSS transitions disabled, otherwise computed styles lag and real differences look like none.
- A dark Bootstrap theme as base was evaluated and rejected (only 14 of 455 home elements differ, little to delete).
