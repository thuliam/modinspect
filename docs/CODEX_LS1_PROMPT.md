# Codex prompt for Long Sprint LS-1 (copy and paste into Codex)

You are the **Codex lane** of ModInspect Long Sprint LS-1, working in parallel with another agent (Claude) on the same repository.
Repository root: `C:\laragon\www\modinspect` (also `https://github.com/thuliam/modinspect`).

## Read first, in this order
1. `AGENTS.md` (project rules, UI rule, zero-cost policy)
2. `docs/SPRINT_BOARD.md` (the protocol, the file-ownership lock, your tasks D-1 to D-8, handoff requests)
3. `docs/SESSION_HANDOFF.md` top section (current state), `docs/PROJECT_STATUS.md` last sections, `docs/DATA_RESEARCH_POLICY.md`

## Setup
- Create your branch from the current checkpoint head: `git switch checkpoint/claude-baseline-2026-11-14 && git switch -c codex/ls1-data`.
- PHP is not on the shell PATH: use `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe`.
- Tests: `MODINSPECT_ENV_FILE=.env.testing php tests/run.php` (isolated DB `modinspect_test`). Must exit 0 before every commit that touches PHP.
- The CI test DB is built from the tracked `modinspect.sql`: a new table must be added to `database/schema.sql`, to a migration, **and** to `modinspect.sql`.

## Your lane
Data, backend and tests. You may edit `cli/**`, `app/Services/**`, `app/Models/**`, `database/**`, `modinspect.sql`, tests you create, and the data docs listed on the board.
You must **not** edit `app/Views/**`, `public/assets/**` or `app/Controllers/**` (the Claude lane owns them). If you need something there, add a row to "Handoff requests" on the board and continue with the next task.

## How to work
- Claim a task on the board (`CLAIMED`, your name, time UTC+7) in its own commit, then do it. Commit prefix `[codex]`. Update the board row with evidence (commit SHA, test name, command result) in the same commit as the work.
- Work through D-1 to D-8 in order. If one is blocked, write the blocker in the row and move on. Do **not** ask the owner questions during the sprint; the owner reads the board at the end.
- Do not merge into `main`. Do not push unless the owner says so; Claude merges your branch into the checkpoint branch at sprint end.

## Hard limits
- No deploy, no spending, no paid or metered service, no new library download, no CDN.
- Never read, print or commit the raw LINE export. Use only the sanitized output `storage/research/line_facebook_2026_loc/research_listings_sanitized.csv` (git-ignored). Never output seller names, phones, URLs, addresses or message text.
- Do not touch the 88 REAL (Priceza) pending observations. Community or owner data never sets snapshots directly: approvals go through `Dashboard::decideObservation` (the existing scripts do this).
- Shared cloud DEV (`.env`) is read-only except the writes named in D-1 (import, approval under actor id 54, snapshot recalculation for affected products) and migrations you were told to apply. Everything else uses `.env.testing`.
- Price confidence stays deterministic and versioned (currently `confidence-v2`, owner-text-only cap at 59). Do not change thresholds.
- Keep PHP 8.1+ compatibility, prepared statements, small typed classes, additive migrations.

## Finish
Update your board rows, append a dated section to `docs/PROJECT_STATUS.md` (your lane only), and stop. Report in one message: tasks DONE with evidence, tasks BLOCKED with the reason, DEV write counts before/after, and the exact test command results.
