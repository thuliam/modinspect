# ModInspect CI and Test Matrix

Baseline date: 2026-11-14. "Engineering CI" here; Corporate Identity is in `docs/UX_UI_SYSTEM.md`.
Rule: PASS is claimed only for what actually ran. Everything marked NOT_RUN or MANUAL is a gap, not a pass.

## Safe local commands (Studio, Windows, Laragon PHP 8.3)

```bash
# isolated test DB (127.0.0.1/modinspect_test), selected by .env.testing; fails closed on shared DEV
php tests/bootstrap_test_db.php --fresh
MODINSPECT_ENV_FILE=.env.testing php tests/run.php
php cli/admin_vendor_manifest.php --verify
```

Always set `MODINSPECT_ENV_FILE=.env.testing` for any CLI that touches the DB. Without it the app reads `.env`, which is the
**shared Cloud DEV** database.

## What runs where

| Check | Command | Where | Status 2026-11-14 |
|---|---|---|---|
| PHP syntax (all of `app/ cli/ tests/ public/`) | `tests/syntax_check.php` | local | PASS |
| Test DB guard (blocks cloud DEV) | `tests/test_database_guard_test.php` | local | PASS |
| Pipeline, catalog, planner, runner, collection ops, live-provider infra (no network) | `tests/*` per `tests/run.php` | local isolated DB | PASS |
| Snapshot loop, price snapshot, provenance, confidence, calibration | `tests/*` | local isolated DB | PASS |
| Review decision/correction, category validation, RBAC | `tests/*` | local isolated DB | PASS (5 stale tests repaired) |
| Admin UI structure and DataTables server contract (PHP level) | `tests/ui_integration_test.php`, `ux_structure_test.php` | local isolated DB | PASS |
| Admin import workflow (TEST/MOCK only) | `tests/admin_import_workflow_test.php` | local isolated DB | PASS |
| Vendor asset manifest, official DataTables, init-attribute guard | `tests/admin_assets_integrity_test.php` | local | PASS |
| HTTP end-to-end: login(CSRF) -> dry run -> confirm -> review -> approve -> public page (starts a real PHP server on a free port with a temp env file; isolated DB only) | `tests/http_e2e_flow_test.php` | local isolated DB | PASS (new). Writes committed rows to the test DB; suite verified twice in a row |
| Golden validation set (101 cases) | `cli/evaluate_validation_quality.php` | local isolated DB | PASS |
| Browser DataTables behaviour (search/sort/page/filters/redraw) | manual, Browser pane on `127.0.0.1` with `.env`-style file pointing at the test DB | local | PASS for Products (full); other 8 tables initialise and serve; **not automated** |
| Public visual/responsive/accessibility review | manual | local | PARTIAL (Home, Detail, mobile Home); NOT_RUN for contrast/keyboard |
| Clean-checkout asset check | copy of `git ls-files -c -o --exclude-standard` into an empty folder, then manifest verify and PHP lint | local | PASS (356 files, no `.env`) |
| GitHub Actions: read-only PHP/FTPS preflight, offline package dry-run | `.github/workflows/*` | merged into branch `checkpoint/claude-baseline-2026-11-14` | NOT_RUN (nothing pushed) |
| GitHub Actions: tests + vendor guard on MariaDB 10.6 service | `.github/workflows/ci-tests.yml` | same branch | AUTHORED, NOT RUN. YAML not linted (no Python/YAML tool locally). Equivalent local path (fresh `bootstrap_test_db.php --fresh` + suite) is green |
| Staging environment, backup/rollback rehearsal | n/a | none | NOT_BUILT |
| Production deploy | n/a | none | **HOLD**, owner GO required |

## CI target (S1-05 authored; the first real run needs a push)

Clean checkout, pinned PHP 8.3, MariaDB service for the isolated test DB, run `tests/run.php` and the vendor manifest check,
upload no secrets or runtime artifacts, no deploy step. A browser DataTables contract smoke test is the next gate after that.

## Known test-harness lessons

- PHP `array + array` keeps left keys; use `array_replace` when overriding defaults.
- Tests that call Admin controllers must establish an authenticated admin session (RBAC is server-side).
- Do not hard-code machine paths; use `PHP_BINARY`.
- Prefer asserting behaviour at the data endpoint over matching page copy.
