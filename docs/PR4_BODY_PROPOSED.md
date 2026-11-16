# PR #4 description (applied 2026-11-16 on the owner's request)

Source of the current PR #4 description (applied with `gh pr edit 4 --body-file`; the text below the line is the body).

---

## Summary

**Sprint 0/1 baseline**
- Official DataTables 1.13.11 with hash-pinned admin vendor assets (`MANIFEST.json`, `cli/admin_vendor_manifest.php`), repaired stale tests, `ci-tests.yml` (PHP + ephemeral MariaDB, vendor guard, full suite), real-HTTP end-to-end test.
- Public price leak fixed (approved TEST-import rows could surface a seeded `legacy_unavailable` snapshot as a market price). Price calculation unchanged.

**Public site redesign (DL-015..DL-020)**
- Bootstrap 4.1 + jQuery 3.3 + Font Awesome 5.0.9 only (no new framework, no CDN), scoped `body.mi` theme, owner brand kit, self-hosted fonts, AA contrast, 44px tap targets.

**Long Sprint LS-1 (DL-021..DL-043)**
- Data: owner-text intake with cross-batch duplicate filter, province log with repost flag, golden resolver test, Thai spec summaries (migration 018), GPU rows with unstated VRAM recovered only for single-memory-size models (`GpuVramFacts`), approval planner with asking-only basis and a bimodal guard (`OwnerIntakePlanner`).
- Trust: confidence-v2 (versioned, explainable, owner-text-only cap), admin snapshot evidence page with a "what is missing for medium" card, price trust audit with source and repost summaries, calibration report keeping fixture and live metrics apart.
- UI: search UX (priced count, chips, popular suggestions), login restyle, skip link, admin AA contrast fixes, dead and shadowed CSS removed on browser evidence (zero computed-style change on 14 pages at 1440 and 390 px).
- Auth: admin session lengths configurable per environment (defaults 30 min idle / 8 h absolute unchanged).
- Docs: sprint board, decision log, `docs/SECOND_SOURCE_INTAKE.md` (the only path to "medium" confidence).

## Test plan
- [x] `MODINSPECT_ENV_FILE=.env.testing php tests/run.php` exit 0 (52 steps)
- [x] `php cli/secret_scan.php` passes; `php cli/admin_vendor_manifest.php --verify`
- [x] CI green on the latest pushed commit (suite and offline package)
- [x] Browser checks of public pages and product detail (Thai specs, empty state) on the DEV catalog
- [x] Admin UAT of the three LS-1 pages (snapshot evidence with the medium-gap card, market trends, price index) in a signed-in browser at 1048 and 375 px (DL-043)
- [ ] Owner visual review of the public pages and the remaining Admin pages (G-2)

## Known limits (honest status)
- All 49 public prices are owner-collected text without a listing URL: every confidence label is low by policy. Live-market quality is not proven; fixture and owner-text metrics are baselines only.
- 22 products have too few usable rows (21) or a bimodal sample (RTX 5090) and stay without a price.
- 88 REAL (Priceza) observations remain pending and untouched.

## Owner decisions before merge
- Merge to `main` and deploy (no deploy was done; zero external spend).
- Rotate credentials found in the LINE export (G-5); official Font Awesome download to remove the `fa-solid-900` 404 (G-7); rights for product photos and logos.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
