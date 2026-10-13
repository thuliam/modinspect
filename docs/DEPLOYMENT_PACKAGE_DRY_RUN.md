# ModInspect — Deployment Package Dry Run

## Status and boundaries

This is **packaging only**. A successful workflow means source syntax passes on PHP 8.3 and an archive can be assembled and verified offline. It does **not** prove a working production app or grant permission to deploy.

- Future target after **separate owner authorization**: modinspect.thuliamdev.com
- HostAtom FTP account root (confirmed in read-only preflight): /home/thuliam/domains/thuliamdev.com/modinspect
- Web document root: /home/thuliam/domains/thuliamdev.com/modinspect/public
- PHP 8.3, Apache rewrite via public/index.php
- The main domain thuliamdev.com/public_html must not be modified.
- FTP/FTPS, DB, PHP server configuration, DNS, SSL and hosting credentials are **not** accessed by this packaging workflow.

## Manual dry-run steps (once workflow is merged)

GitHub > Actions > ModInspect Deployment Package - Dry Run > Run workflow > branch main > package-only.

PR checks can validate the package **before** merging. The GitHub Actions artifact contains:

| Artifact member | Meaning |
| --- | --- |
| modinspect-preview.zip | Strict allowlist of application code / public assets |
| manifest.json | Commit, intended future path, per-file SHA-256 checksums, exclusions and release gates |
| checksums.sha256 | SHA-256 digest of the ZIP |

The artifact expires after three days. This repository is public: treat GitHub artifacts as public. **Never put credentials, private data, customer data or production environment files in them.**

## Allowed paths in the ZIP

- app/**/*.php
- config/**/*.php (tracked repository source code only)
- public/index.php and public/.htaccess
- public/assets/**/* with approved CSS, JS, image and font extensions

The development-only public/assets/admin/README.md is excluded. Unknown files/extensions under the deployment scope fail closed rather than silently being packaged. ZIP entries undergo integrity and path checks.

## Explicitly excluded

- .env, .env.testing, environment-specific keys/passwords
- database/, root SQL dumps, migrations, seeds
- tests/, cli/, docs/, design/, examples/, context/
- storage/, session files, log files, caches, user uploads and import files
- .git/, workflows, local tools and storage.zip
- Hosting-created public/index.html placeholder (never modified by this workflow)

**Note:** storage/ is not tracked in the repository. Do not interpret its absence in the preview ZIP as proof the app will work. Confirm required writable directories (for example storage/import/) and permissions separately, preserving existing runtime data.

## Mandatory gates before any later deployment

1. Owner approves a **different deployment PR / workflow**, not this package PR.
2. Confirm document root, PHP 8.3 extensions (at minimum PDO MySQL), Apache rewrite and permissions.
3. Separately supply server-local .env **outside the web document root**. No credentials in artifacts, logs or chats.
4. Choose an **isolated production database**. Existing HostAtom Cloud DEV database is shared across development environments; never automatically run migrations, destructive tests, seeders or imports against it.
5. Verify writable runtime directories and retention/backup for uploads, imports and logs. Never overwrite runtime data during code deploy.
6. Resolve the host public/index.html placeholder: it may be served before index.php. Rename/remove only after owner approval and backup at the go-live gate.
7. Confirm behavior with safe, non-destructive HTTP checks before announcing production ready.
8. Back up existing ModInspect host files, server-local .env (secured separately) and the database (with owner approval), and record their locations/checksums.

## Draft rollback plan (NOT EXECUTABLE by this workflow)

- Freeze deployment and identify the last approved release hash.
- Restore backed-up application code and prior placeholder file if intentionally replaced.
- Preserve runtime storage/ and generated data.
- Restore database changes only from an approved compatibility/restore plan; do not assume reversing migrations is safe.
- Verify PHP 8.3, document root, HTTPS and core pages.
- Record incident, rollback version and owner acceptance.

## Acceptance result

**PASS — Package preview:** PHP 8.3 syntax passes, ZIP integrity passes, manifest matches the ZIP, and a GitHub artifact is produced.

**HOLD — Production deployment:** All external runtime/readiness gates remain pending. There are **no server-write jobs** in this PR.
