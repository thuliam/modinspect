# LS-29 quality checks (2026-11-21)

Measured on a local PHP server (`php -S`) reading the shared DEV database with GET requests only. Pages: `/`, `/price`, `/deal-checker`, `/build`, `/compare`, `/upgrade`, `/condition-guide`, `/watching`, `/methodology`, `/glossary`.

## Keyboard and ARIA (script plus real Tab key presses in the built-in browser)

| Check | Result |
|---|---|
| `lang="th"`, one `h1`, one `main`, skip link | present on all 10 pages |
| Images without `alt`, buttons or links without a name, inputs without a label | none |
| Positive `tabindex`, duplicate ids | none |
| Heading level jumps (footer headings carry `aria-level`) | none |
| Focusable elements that are not native links, buttons or form fields | none, so every focusable element is covered by the focus rules |
| Visible focus ring on a real Tab press | links: solid 2 px lime outline (`:focus-visible` matched) |
| Rules for buttons, inputs, selects and textareas | in `app.css` line 888 (`body.mb ...:focus-visible`) and `mi-theme.css` line 50; read from the CSS, not pressed one by one |

One dead rule: `mi-theme.css` lines 299 and 305 use `var(--ci-focus)`, which is defined nowhere, so those two declarations do nothing. The ring still shows because the more specific rules above supply it. Not changed (nothing is broken); if the owner wants the file tidy, either define `--ci-focus` in `ci-tokens.css` or delete the two rules.

Not done and not possible for the agent: a real screen-reader pass (NVDA/VoiceOver) and Select2 keyboard behaviour on a phone. A person should run the walk-through below.

Screen-reader walk-through for a tester (about 15 minutes): (1) open `/` and listen to the order: skip link, menu, search, tabs (the "all/CPU/GPU" group should announce pressed or not pressed); (2) open `/deal-checker`, fill the form with the keyboard only and submit; the result should be reachable by headings; (3) open `/build`, choose a part with the Select2 dropdown using arrow keys and Enter; (4) on a result page, find the "ผลที่เกิดขึ้น" form (once migration 028 is applied) and save an answer; (5) note anything that is announced twice, not announced, or out of order.

## Load weight (gzip of the JS and CSS a page loads)

| Page | JS | CSS | Files |
|---|---|---|---|
| `/`, `/price`, `/deal-checker` | 101 KB | 59 KB | 15 |
| `/build` (adds Chart.js 70 KB) | 169 KB | 59 KB | 16 |

Largest items on the home page: `bootstrap.bundle.js` 43 KB (the file is the unminified bundle, 211 KB raw), jQuery 3.7.1 30 KB, `bootstrap.min.css` 21 KB, Select2 20 KB, Font Awesome CSS 13 KB, `mi-theme.css` 12 KB.

Findings:
1. `bootstrap.bundle.js` is not minified. The minified file of the same version is roughly 24 KB gzip, about 19 KB less on every page. It needs a download, which needs the owner's approval (name: Bootstrap bundle min, same version as the current file, source: the project's own release, size: about 80 KB raw). Not done.
2. Select2 (20 KB) loads on every page; it is the owner's LS-18 request, so it stays. Loading it only where a dropdown exists would save 20 KB on pages without one; that is a code change for the owner to decide.
3. `spec-builder.js` is small enough not to appear among the largest files, so the earlier F08 worry is not supported by the numbers.
4. These are file sizes, not a timed load: no network throttling or real-device timing was done.
