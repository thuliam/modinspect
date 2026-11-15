# ModInspect — Production deployment runbook (HostAtom, Linux, UTC+7)

Status: **runbook only. Nothing here has been run against hosting.** Deploying needs a separate, explicit owner go-ahead (DL-014, deployment package
policy in `docs/DEPLOYMENT_PACKAGE_DRY_RUN.md`). Agents never hold hosting, FTP, database or DNS credentials.

## 1. Target (from the read-only preflight)

| Item | Value |
|---|---|
| Future host name | modinspect.thuliamdev.com (after separate owner authorisation) |
| Account root | `/home/thuliam/domains/thuliamdev.com/modinspect` |
| **Web document root** | `.../modinspect/public` (never the project root) |
| PHP | 8.3 (declared minimum 8.1), Apache with `mod_rewrite`; routes go through `public/index.php` |
| Time zone | Asia/Bangkok (UTC+7). The app sets it in `app/bootstrap.php` and pins the DB session to the PHP offset |
| Do not touch | the main domain `public_html`, other sites, the default `public/index.html` placeholder without approval |

## 2. Gates that must be green before anyone uploads

1. `main` contains the reviewed PR and **CI is green on PHP 8.1, 8.3 and 8.4** (`ci-tests.yml`: regression suite, secret scan, admin vendor guard).
2. `ModInspect Deployment Package - Dry Run` produced `modinspect-preview.zip` + `manifest.json` + `checksums.sha256`; the checksum was compared.
   The package is a strict allowlist of `app/`, `config/` and `public/` (it excludes `public/assets/products-mfr/`, whose usage rights are unconfirmed).
3. Owner decisions recorded in `docs/DECISION_LOG.md`: G-5 (credentials that appeared in the LINE export are rotated), G-7 (Font Awesome, see section 6),
   photo and logo rights (section 7).
4. `php cli/secret_scan.php` reports nothing. No `.env*` file is in the package.
5. A fresh database backup exists (section 4) and the rollback owner is named.

## 3. Production configuration (created on the server by the owner, never committed)

Create `.env` in the account root (one level above `public/`), copying `.env.example`, then set:

```
APP_ENV=production
APP_URL=https://modinspect.thuliamdev.com
DB_HOST=<host from hosting panel>
DB_PORT=3306
DB_DATABASE=<production database>
DB_USERNAME=<dedicated user, not root, only this database>
DB_PASSWORD=<generated, 20+ chars>
MODINSPECT_LIVE_COLLECTION_ENABLED=false
MODINSPECT_PAID_PROVIDER_CALLS_ENABLED=false
GEMINI_PROVIDER_ENABLED=false
GEMINI_API_KEY=            # leave empty: the zero-cost POC policy forbids paid or metered providers
```

- `APP_ENV=production` hides PHP errors from visitors and logs them instead (`app/bootstrap.php`; a missing value also counts as production).
- Use HTTPS only. Turn on HSTS at the host **after** the certificate works on every page (a wrong HSTS cannot be undone quickly).
- File permissions: code read-only for the web user, `storage/` (logs, cache, sessions, import) writable and **outside** the document root.
- The site must not be reachable through the account root: confirm `https://.../app/bootstrap.php` and `https://.../.env` return 403/404.

## 4. Database

1. Back up first: `mysqldump --single-transaction --routines <db> > modinspect-YYYYMMDD-HHMM.sql` (store it off the server).
2. New database: import `database/schema.sql`, then apply each file in `database/migrations/` in order with
   `php cli/migrate.php database/migrations/<file>.sql` (already-applied migrations must be skipped: record which ones ran).
3. Seed only what the product needs (`database/seed.sql` contains fixture rows: do **not** load fixtures or TEST/MOCK evidence into production).
4. Create the first admin with `php cli/create_admin_user.php` over SSH or a one-off script; the password is typed by the owner, never pasted into chat.
5. Never point tests at production: the test guard refuses any database not named `modinspect_test` on 127.0.0.1.

## 5. Upload and cut-over

1. Download the package artifact from the green workflow run; verify `sha256sum -c checksums.sha256`.
2. Upload over FTPS/SFTP to a **new release folder**, e.g. `.../modinspect/releases/2026-11-xx`; keep the previous one.
3. Point the document root (or a `current` symlink if the plan allows) at `releases/<new>/public`; the hosting panel change is the cut-over.
4. Smoke checks (all must pass; paste the output in the release note):

```bash
# GET, not HEAD: the router answers HEAD requests with 404
B=https://modinspect.thuliamdev.com
for p in / /price /deal-checker /methodology /login /favicon.ico; do printf '%s ' "$p"; curl -s -o /dev/null -w '%{http_code}
' "$B$p"; done   # all 200
curl -s -o /dev/null -w 'admin %{http_code}
' "$B/admin"                  # 302 to /login
curl -s -o /dev/null -w '.env %{http_code}
' "$B/.env"                    # 403 or 404
curl -s "$B/" | grep -c 'PHP Warning\|Fatal error'                          # 0
curl -sD - -o /dev/null "$B/" | grep -i 'x-content-type-options\|x-frame-options'   # both present
```

5. Open `/`, `/price`, a product page, `/deal-checker`, `/login` in a browser at desktop and phone width; check the console for errors and missing assets.
6. Confirm the home page shows the honest "no market price yet" state until reviewed evidence exists (no price may appear without an approved snapshot).

## 6. Font Awesome (gate G-7)

The bundled Font Awesome 5.0.9 CSS points at `*.html` font files (404, falls back to `.woff`) and lacks newer icons. Before launch the owner approves
**one** download of an official Font Awesome Free 5.x release (state file names, source, size; licence CC BY 4.0 / OFL 1.1 / MIT), then
`public/assets/admin/vendor/MANIFEST.json` and `docs/THIRD_PARTY_LICENSES.md` are updated and `php cli/admin_vendor_manifest.php --verify` must pass.

## 7. Rights and attribution to settle before public launch

- Product photos: 7 Wikimedia Commons photos are credited on the methodology page. Manufacturer photos in `products-mfr/` are staging only.
- Brand logos: used only to name the brand of a listed product, with a trademark note; confirm the brand guidelines you want to follow.
- Hardware artwork cropped from the approved reference is low resolution and temporary.

## 8. Security headers (already in `public/.htaccess`)

`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy` are set when `mod_headers` is available.
A Content-Security-Policy is **not** set yet: the pages use a small inline script (motion mode) and inline styles for art sizing. Adding CSP
means moving those into files first (tracked as follow-up), then a report-only trial.

## 9. Rollback

1. Re-point the document root to the previous release folder (seconds).
2. If a migration ran: restore the dump taken in section 4 (only with the owner's approval, because it discards data written since).
3. Re-run the smoke checks; write the incident in `docs/DECISION_LOG.md`.

## 10. Owner checklist (nothing is deployed until all are ticked)

- [ ] G-5 credentials rotated
- [ ] G-7 Font Awesome decision made
- [ ] Rights settled (photos, logos)
- [ ] Production `.env` created on the server, DB user is not root
- [ ] Backup taken, rollback owner named
- [ ] Written go-ahead to deploy to modinspect.thuliamdev.com
