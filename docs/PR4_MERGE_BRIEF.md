# PR #4 merge brief (for the owner's decision, 2026-11-17)

This is a summary to help you decide. **I do not merge, and nothing here authorises a deploy.** The decision is yours.

PR: <https://github.com/thuliam/modinspect/pull/4>, `checkpoint/claude-baseline-2026-11-14` into `main`. State on 2026-11-17: open, mergeable, clean, 4 checks reported passing by the app's PR monitor (that is a cached reading; confirm on GitHub before merging).

## Size and shape (measured with `git`, `origin/main...HEAD`)

- 119 commits, all by one author account, 377 files, +29,796 / -1,163 lines. Merge base `4b84d19` (the commit on `main` that only added CI workflows and the packaging script).
- By top-level folder (files changed): `app` 109, `tests` 72, `public` 63 (23 of them admin vendor assets), `docs` 42, `cli` 30, `design` 7, `database` 6, `scripts` 4, `examples` 3.
- Database: migrations `013` to `019` are new, and `database/schema.sql` changed. `main` has none of them.
- Workflows: `ci-tests.yml` changed. The three workflows are CI on pull requests and two manual dry runs; none deploys.
- No file is deleted by the PR.

## What the PR contains, by sprint (details in each report)

| Area | Where to read |
|---|---|
| Baseline: official DataTables, pinned admin assets, repaired test suite, CI, HTTP end-to-end test, a public-price leak fixed | `docs/PROJECT_STATUS.md` |
| Admin sign-in, roles, review queue, corrections, calibration | `docs/PROJECT_STATUS.md`, `docs/SECURITY_REVIEW_2026-11-14.md` |
| Price confidence, freshness, source diversity, snapshot provenance, replay audit | `docs/PRICE_ENGINE.md` |
| Public pages (price index, product, Deal Checker, Build, methodology) | `docs/UX_UI_SYSTEM.md` |
| LS-1 to LS-3: catalog expansion, spec classes, trends, location log, rate limiting, security hardening | `docs/SPRINT_BOARD.md` |
| LS-4 to LS-8: unresolved queue, Priceza as new-price reference, owner-transcription route, importer fixes, daily helper, env preflight, retention tool, privacy draft | `docs/LS4_REPORTS.md`, `docs/LS6_REPORTS.md`, `docs/LS7_REPORTS.md`, `docs/LS8_REPORTS.md` |

## What has been checked

- Full regression suite: 92 steps, exit 0 on the local machine at commit `72bc514` (PHP 8.3, isolated test database). CI runs the suite on PHP 8.1, 8.3 and 8.4 with a clean MariaDB.
- Secret scan: 480 files, no findings.
- Every new test since LS-4 was written first and seen failing before the code existed, then made to pass, and its key lines were broken on purpose to confirm the test notices (recorded per task).
- Browser checks (built-in browser, local server, test database): contrast and keyboard focus on six public pages, phone width, hover states, and the new admin card.

## What has not been checked

- Anything on the real host (HostAtom): TLS, HSTS, backups, cron, monitoring, `.htaccess` modules, the production `.env`.
- Real market data: every approved price is still owner text from one source; all public prices carry confidence "low". Fixture results prove the code, not the market.
- A screen reader, an axe run, and the owner's visual sign-off.
- The DEV database password was exposed once in a stack trace (since fixed in code); rotating it is still open.

## Risks and choices at merge time

1. **Merge style.** The docs and decision log cite commit SHAs (for example `f3a54d7`, `fa3eeca`). A normal merge commit keeps those valid. A squash would turn 119 commits into one and orphan every cited SHA, at the cost of a longer history on `main`. I would use a merge commit, but this is your call.
2. **Nothing deploys automatically.** No workflow deploys. Merging changes `main` only.
3. **Migrations.** A host database that is behind needs migrations `013` to `019` in order before this code runs. Take a backup first (`php cli/db_backup.php`) and rehearse on a staging copy (beta checklist D-4 is still open).
4. **Production settings.** Run `php cli/env_preflight.php` on the host before going live. Remove any `AUTH_*` overrides and keep `APP_ENV=production`.
5. **Other branches.** `studio` and `office` are still at `4f6713c` and are not touched by this PR; the reconcile plan is in `docs/DECISION_LOG.md` and is also your decision.
6. **Your untracked files** (`MODINSPECT_LADIP_TECHNICAL_DESIGN_PROPOSAL.md`, `design/ci/`) are not part of the PR.

## Open owner items that the merge does not close

The twelve MUST rows of `docs/BETA_READINESS_CHECKLIST.md` (six need only you, six also need the host), the verdict is still NO-GO for a public beta, and merging does not change it. Merging is reasonable on its own as a way to move the work onto `main`; it is not a launch.
