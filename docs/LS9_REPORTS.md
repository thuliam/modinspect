# LS-9 reports (2026-11-17)

Scope approved by the owner: P-1 to P-3 (short sprint, no important code). No code changed. Nothing was written to the DEV database, nothing was downloaded, no spend.

## P-1 The Capture Method card, seen in a browser

Checked in the built-in browser on a temporary local server with the isolated test database and a test-only env file (so the sign-in form posted to the local server, not to DEV). A throwaway admin user was created for this, used to sign in, and deleted afterwards together with the env file, the server and the credential files.

Result: the card renders with the five buckets and their counts on desktop and on a 375 px phone, with no horizontal overflow and no clipped row. It matches the Source Quality card above it. Two cosmetic notes, both inherited from the existing card pattern and left alone: the small grey text runs into the label ("Fixturenot live evidence"), and on a phone the count sits close to the label. The fixture row shows 55 records, the live rows 0 (test database).

## P-2 PR #4 merge brief

`docs/PR4_MERGE_BRIEF.md`: size and shape measured from git (119 commits, 377 files, +29,796 / -1,163, migrations 013 to 019 new), what has and has not been checked, and six choices at merge time. The recommendation on merge style is a merge commit (the docs cite commit SHAs that a squash would orphan); the decision is the owner's. Nothing was merged.

## P-3 Front-end library options (C-6)

`docs/FRONTEND_LIBS_OPTIONS.md`: advisories for jQuery 3.3.1 and Bootstrap 4.1.0 as far as the sources could confirm, the exposure check in our own code (only `collapse` and `dropdown` behaviours; no `.html()`, `.append()` or deep `extend` in our scripts; vendor internals not audited), and four options. Recommendation: update jQuery only to 3.7.1 now (needs the owner's download approval), Bootstrap later after a DropX compatibility check. Nothing was downloaded.
