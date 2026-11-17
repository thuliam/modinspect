# LS-9 reports (2026-11-17)

Scope approved by the owner: P-1 to P-3 (short sprint, no important code). No code changed. Nothing was written to the DEV database, nothing was downloaded, no spend.

## P-1 The Capture Method card, seen in a browser

Checked in the built-in browser on a temporary local server with the isolated test database and a test-only env file (so the sign-in form posted to the local server, not to DEV). A throwaway admin user was created for this, used to sign in, and deleted afterwards together with the env file, the server and the credential files.

Result: the card renders with the five buckets and their counts on desktop and on a 375 px phone, with no horizontal overflow and no clipped row. It matches the Source Quality card above it. Two cosmetic notes, both inherited from the existing card pattern and left alone: the small grey text runs into the label ("Fixturenot live evidence"), and on a phone the count sits close to the label. The fixture row shows 55 records, the live rows 0 (test database).

## P-2 PR #4 merge brief

`docs/PR4_MERGE_BRIEF.md`: size and shape measured from git (119 commits, 377 files, +29,796 / -1,163, migrations 013 to 019 new), what has and has not been checked, and six choices at merge time. The recommendation on merge style is a merge commit (the docs cite commit SHAs that a squash would orphan); the decision is the owner's. Nothing was merged.

## P-3 Front-end library options (C-6)

`docs/FRONTEND_LIBS_OPTIONS.md`: advisories for jQuery 3.3.1 and Bootstrap 4.1.0 as far as the sources could confirm, the exposure check in our own code (only `collapse` and `dropdown` behaviours; no `.html()`, `.append()` or deep `extend` in our scripts; vendor internals not audited), and four options. Recommendation: update jQuery only to 3.7.1 now (needs the owner's download approval), Bootstrap later after a DropX compatibility check. Nothing was downloaded.

## P-4 jQuery 3.7.1 (owner approved the download)

Downloaded `jquery-3.7.1.min.js` (87,533 bytes) from https://code.jquery.com/jquery-3.7.1.min.js into an empty folder, and the same release from https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js; the two files are byte-identical (SHA-256 `fc9a93dd241f6b045cbff0481cf4e1901becd0e12fb45166a8f17f95823f0b1a`, banner `jQuery v3.7.1`). The base64 SHA-256 matches the SRI value I remembered for this release (`sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=`); I did not open the jQuery release page to compare it there. The old file was removed, the three layouts, the manifest generator and the licence table were updated, and the manifest was regenerated (`php cli/admin_vendor_manifest.php --verify`: 22 pinned files).

Checks: full suite exit 0 (includes the real-HTTP end-to-end test); new `tests/jquery_version_guard_test.php` (minimum 3.5.0, banner equals manifest version, hash matches, every layout points at the pinned file; 3 mutations caught); in the browser on the test database with a throwaway admin (deleted afterwards): collapse opens and closes and updates `aria-expanded`, the admin dropdown opens, DataTables 1.13.11 on `/admin/products` searches and sorts with no console error and `/admin/review-queue` reloads its server-side data, the home page tabs filter and the motion toggle work. **Not exercised:** the Build part picker (the test database has no priced parts; `spec-builder.js` does not use jQuery) and Bootstrap pieces we do not use. The only console errors are the known Font Awesome `.html` font 404s (beta E-4).
