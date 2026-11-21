# ModInspect Session Handoff (one page, rewritten 2026-11-21 at LS-30)

Full history of every earlier handoff, unchanged: `docs/archive/SESSION_HANDOFF_HISTORY.md`. Detailed milestone status: `docs/PROJECT_STATUS.md`. Decisions: `docs/DECISION_LOG.md` (latest DL-120). Open owner decisions: `docs/OWNER_DECISION_PACK_LS28.md`.

## Where things are
- Repo `C:\laragon\www\modinspect`. Work only on branch `studio` (other branches: `main`, `office`). CI runs only on a pull request; merging to `main` is the owner's word, never mine.
- PHP is not on the PATH: use `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe`.
- Tests: `MODINSPECT_ENV_FILE=.env.testing php tests/run.php`. Anything that touches a DB must use `.env.testing` (isolated `modinspect_test`). Rebuild it with `php tests/bootstrap_test_db.php --fresh`; it loads the tracked `modinspect.sql`, so every new table goes into `modinspect.sql`, `database/schema.sql` and a migration.
- DEV is the shared cloud DB in `.env` (`thuliam_modinspect`). Migrations 022 to 028 were applied to DEV on 2026-11-21 at the owner's order (LS-30 A) with `php cli/migrate_pending.php --apply`, after two backups. Check state any time: `php cli/migrate_pending.php --from=22` (dry run). Host/production database: not touched.

## Standing rules (never relax without the owner)
No deploy, no spend (zero-cost POC), no writes to DEV without an explicit order, no push or merge without the owner's word. No hand-written CSS/JS where a library can do it; downloads need approval. Community, seller and first-party signals never set price snapshots. Seller check stays OFF (HG-14, HG-18). Never log or expose secrets. Commits end with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Features behind switches (all OFF by env flag)
Community reports, community forum, seller check, public seller listings, member sign-in providers. How to open and close each: `docs/MODULE_SWITCHES_TH.md`. With the flags off those routes return 404 (verified on DEV after the migrations).

## Tools you can run (all dry-run or read-only unless `--apply`)
`cli/migrate_pending.php`, `cli/predeploy_report.php`, `cli/alias_audit.php`, `cli/retention_purge.php`, `cli/migration_status.php`, `cli/intake_validate.php`, `cli/intake_daily_run.php`.

## Recent work
- LS-28: alias audit, 49-query golden search set, post-purchase outcome card (migration 028), owner decision pack.
- LS-29: guarded migration runner, pre-deploy report, outcome card on `/admin/data-health`, ARIA/keyboard and weight checks (`docs/reviews/ls29_quality_checks_2026-11-21.md`).
- LS-30: A) migrations 022 to 028 on DEV; found and fixed a bug where `cli/migrate.php` split SQL at a `;` inside a `--` comment (`tests/migrate_splitter_test.php`). B) drafts for the lawyer, retention, moderators and OAuth in `docs/LS30_GATED_ITEMS_DRAFTS_TH.md`. C) this one-page handoff, Select2 and CSS cleanup (see PROJECT_STATUS LS-30).

## Owner gates still open
Draft consent and privacy wording and lawyer review (HG-14), voice guide, M1 wording (HG-4), retention periods, who moderates, OAuth apps (HG-13), brand name (HG-5), credential rotation (G-5), Admin re-UAT (G-2), second-hand price data (R-3), deploy target, download of `bootstrap.bundle.min.js`, a screen-reader pass by a person, opening any closed module, merge to `main`.
