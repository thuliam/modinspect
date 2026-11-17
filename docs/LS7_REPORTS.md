# LS-7 reports (2026-11-17)

Scope approved by the owner: N-1 to N-6. Nothing was written to the DEV database (the new `--check-db` option only reads). Database tests ran on the isolated test database and were rolled back. No paid service, no live collection.

## N-2 Approval right after import no longer reports false

`ReviewCorrectionService::applyApprovedValues` decided success from MySQL's changed-row count. An approval in the very second of the import changes no column (`updated_at` included), so the count was 0 and the approval returned false. Now a row that is still pending counts as applied. The one-second waits added to two tests in LS-6 were removed first (both tests went red), then the fix turned them green. Two mutations are caught: "always ok" (an already approved row must not be applied again) and the old behaviour. `tests/daily_flow_rehearsal_test.php` also pins that an approved row cannot be approved twice.

## N-3 `--check-db` for the daily helper (read-only)

`php cli/prepare_owner_rows.php --in=<csv> --check-db` compares the prepared rows with the real offline rows already stored (`offline_real_*` sources): `ALREADY_STORED_ITEM` (error, the row is dropped) when the same listing is stored, compared by canonical link and item id so share-link tracking does not hide it; `REPOST_OF_STORED` (warning, the row stays) for the same first 30 letters/digits of the title and the same price under another listing. Issues name the input line. It only runs SELECTs; without the flag the helper still needs no database. Code: `app/Services/Collection/OwnerRowDbCheck.php`; `tests/owner_row_db_check_test.php` (3 mutations caught, asserts nothing is written). The preparer now also returns `row_numbers` (input line of each output row).

## N-4 Production `.env` preflight

`php cli/env_preflight.php [--env-file=.env]`, to run on the host. It checks: `APP_ENV=production` set explicitly, no `AUTH_` session overrides, `APP_URL` is https on a real host, live collection / paid provider / Gemini switches off (zero-cost policy), a database password is set, no root database user (warning), no provider key (warning), no `.env` inside the web root, and the file mode (warning, skipped where unknown). It prints check names, pass/warn/fail and a reason, never a value; the test proves no configured value appears in the output. Exit 1 on FAIL. `app/Services/Ops/EnvPreflight.php`, `tests/env_preflight_test.php` (5 mutations caught; two extra URL cases were added after a mutant survived). It was run only on `.env.example` (FAIL as expected, empty values); it has not been run on the host.

## N-5 Privacy and terms draft (Thai)

`docs/PRIVACY_TERMS_DRAFT_TH.md`: a draft for the owner, not legal advice, not on any page. Facts about the system are checked from the code and listed with their source (no third-party analytics found, what Deal Checker and Build store, IP only as a hashed cache file name for visitors, IP recorded for admin logins). Everything the owner must decide is in `[brackets]`: data controller and contact, retention periods, host details, legal basis, liability limits, governing law. Publishing it on pages is separate UI work that needs approval.

## N-1 `/build` findings: one fixed, one retracted, one left

- **5 px overflow at 320 px: fixed.** One grid column in `app.css` was a bare `1fr`; it is now `minmax(0,1fr)`. Browser check at 320, 360 and 375 px: no overflow. Pinned by `tests/build_reflow_css_test.php` (red first).
- **"Focus order on phones": retracted.** LS-6 reported that the save button comes after the whole parts list in tab order. On a phone the summary panel is a sticky bar pinned to the bottom of the screen (`.spec-summary{position:sticky;bottom:0}`) and it is also last in the page, so tab order matches reading order. The earlier measurement read the pinned position at scroll 0 and mistook it for a mismatch. No change was needed.
- **8 to 10 px text: left.** A 10 px note and an 8 px glyph inside a part card. No WCAG rule sets a minimum size; changing it is a design choice for the owner.

## N-6 Verification and close-out

Full suite and secret scan are run before the commit; CI status is read from the app's PR monitor after the push.
