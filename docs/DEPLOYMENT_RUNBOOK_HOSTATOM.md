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
2. **Existing database** (the shared DEV, a host that already runs the app): apply only the migrations it has not had yet, in order, with
   `php cli/migrate.php database/migrations/<file>.sql` (or phpMyAdmin), and record which ones ran.
3. **New, empty database** (LS-19 A1): `database/schema.sql` is the full current structure (`tests/fresh_install_schema_test.php` keeps it complete), so
   do NOT run the migrations whole on top of it (their CREATE/ALTER fail with "duplicate column"). The order is:
   a. import `database/schema.sql`;
   b. from `database/seed.sql` only the catalog rows (`product_categories`, `brands`, `products`, `product_aliases`), never its fixture prices, histories, demo shop or example sources;
   c. the data statements (INSERT/UPDATE/DELETE) of every migration in order.
   `cli/rehearse_fresh_install.php <name>_rehearsal --production` does exactly this on an empty local database and checks the result
   (106 products, manufacturer specs, 8 categories, no public price); the CI job "Fresh install rehearsal" runs it on MariaDB 10.6 for every PR.
   Copy that order for the host (or run the same steps from a database dump made by the rehearsal); do not improvise another one.
4. Create the first admin with `php cli/create_admin_user.php` over SSH or a one-off script; the password is typed by the owner, never pasted into chat.
5. Never point tests at production: the test guard refuses any database not named `modinspect_test` on 127.0.0.1.

## 5. Upload and cut-over

1. Download the package artifact from the green workflow run; verify `sha256sum -c checksums.sha256`.
2. Upload over FTPS/SFTP to a **new release folder**, e.g. `.../modinspect/releases/2026-11-xx`; keep the previous one.
3. Point the document root (or a `current` symlink if the plan allows) at `releases/<new>/public`; the hosting panel change is the cut-over.
4. Smoke checks (all must pass; paste the output in the release note):

```bash
# the router answers HEAD like GET since LS-16; the full check is: php cli/smoke_check.php "$B" --links
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

## 5b. The route that fits this account (LS-14, 2026-11-19): no shell, FTPS from GitHub Actions

Found on 2026-11-18: the HostAtom account has **no shell** (SSH accepts the login and closes the connection), so the `git clone` route in
`deploy/hostatom_release.sh` cannot run there. The subdomain `modinspect.thuliamdev.com` already exists (PHP 8.3, document root `.../modinspect/public`).
The route that works without a shell:

1. **Code:** GitHub Actions workflow `ModInspect FTPS Deploy (manual)` (`.github/workflows/ftps-deploy.yml`, runs from `main` only).
   - Run it first with `mode = plan`: it builds the allowlisted package (`app/`, `config/`, `public/`; never `.env`, never `public/assets/products-mfr/`),
     checks PHP syntax, and lists what it would upload. It writes nothing to the host.
   - Then run it with `mode = deploy` and `confirm = DEPLOY`. It uploads over verified explicit FTPS, code first and `public/` last, never deletes anything,
     never touches `.env`, `storage/` or the database, then runs `php cli/smoke_check.php` against the live site.
   - Rollback = run it again with `ref` set to the previous release tag (files are overwritten one by one; the database is not involved).
   - Secrets: `MODINSPECT_FTPS_USERNAME` (must be `modinspect@thuliamdev.com`) and `MODINSPECT_FTPS_PASSWORD` are repository secrets the owner already set.
     Not confirmed until the first `plan` run: that the FTP account's root is the `modinspect/` folder.
2. **Database (no shell, so no `cli/migrate.php`):** in DirectAdmin take a backup first (Backup and Restore, or phpMyAdmin Export), then in phpMyAdmin select
   the database and **Import** each file of `database/migrations/` that is not applied yet, in numeric order. The DEV database `thuliam_modinspect` was at
   migration **021** on 2026-11-18; migrations **022** (new-price references), **023** (members), **024** (listing facts, seller listings), **025**
   (seller reports), **026** (community reports; it also adds the low-quality evidence source `community_report`), **027** (community forum) and **028** (deal outcomes) are additive. **Update 2026-11-21 (LS-30/31):** 022 to 028 were applied to the shared DEV database `thuliam_modinspect` and checked there; the HostAtom production database has none of them. The runner now ignores `;` inside `--` comments (028 stopped on one before), but phpMyAdmin import is a separate tool: if it complains on a file, send the exact message before editing anything. A page that needs a missing table says so (admin) or has no
   effect (public) instead of failing. Never import `database/seed.sql` or TEST/MOCK data into production.
3. **Folders the app writes to** (create in File Manager, outside the document root, writable by the web user): `storage/cache/ratelimit`, `storage/cache/oauth`,
   `storage/uploads/listings`, `storage/uploads/reports`, `storage/logs`, `storage/import`. `.env` and `storage/` are created by the owner, never uploaded by the workflow.
4. **Smoke check** replaces the curl block in section 5 (same list, plus health, private paths and robots):

```bash
php cli/smoke_check.php https://modinspect.thuliamdev.com   # exit 0 = every check passed
```

5. **Optional features are closed unless the owner opens them in `.env`** (all default to off or empty):

```
# visitor sign-in: a provider appears only when both its id and secret are set (HG-13); Apple is not offered (paid, HG-15)
MEMBER_GOOGLE_CLIENT_ID=            MEMBER_GOOGLE_CLIENT_SECRET=
MEMBER_MICROSOFT_CLIENT_ID=         MEMBER_MICROSOFT_CLIENT_SECRET=
MEMBER_FACEBOOK_APP_ID=             MEMBER_FACEBOOK_APP_SECRET=
# redirect URI to register at each provider: https://modinspect.thuliamdev.com/account/callback/google | microsoft | facebook
# seller check (reports about a phone or account): off until a lawyer has read the wording (HG-14) and a long random key is set (HG-18)
SELLER_CHECK_ENABLED=false          SELLER_CHECK_HMAC_KEY=
# community reports (R-11): off until the owner approves the privacy wording; needs a key of 32+ characters (openssl rand -hex 32) before it can be switched on
COMMUNITY_REPORTS_ENABLED=false     COMMUNITY_REPORTS_HMAC_KEY=
# community forum (threads, replies, reviews): off until the community rules, the privacy wording and a moderator are settled; needs members (sign-in) to write
COMMUNITY_FORUM_ENABLED=false
# approved seller listings visible to everyone (HG-16)
SELLER_LISTINGS_PUBLIC=false
```

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
2. **A database restore is normally NOT needed (LS-20 C2).** Every migration is additive (new tables, new nullable columns, new indexes, data rows), so the previous release keeps working on the newer schema. Checked on 2026-11-19: `origin/main` (migrations 002 to 025) was served on the database built from the current `schema.sql`; its smoke check gave the same result as the new code (the only 4 failures are `robots.txt` and `sitemap.xml` on PHP's built-in server, which answers dotted URLs differently from Apache). The commits after `origin/main` change `schema.sql` only in `database/` (fresh installs), no migration file.
   Restore the dump from section 4 only if data itself is wrong, and only with the owner's approval, because it discards everything written since. `php cli/db_backup.php` and the restore drill (`tests/db_backup_restore_test.php`: back up, restore into a scratch database, 42 tables, every row count equal) are tested on every run of the suite.
3. Re-run the smoke checks (`php cli/smoke_check.php https://<domain> --links`); write the incident in `docs/DECISION_LOG.md`.

## 10. Owner checklist (nothing is deployed until all are ticked)

- [ ] G-5 credentials rotated
- [ ] G-7 Font Awesome decision made
- [ ] Rights settled (photos, logos)
- [ ] Production `.env` created on the server, DB user is not root
- [ ] Backup taken, rollback owner named
- [ ] Migrations 022 to 029 imported (phpMyAdmin) after the backup, or consciously left out (LS-31: the list grew from 026 to 028; on a host with a shell use `php cli/migrate_pending.php --from=22` for the plan and `--apply` to run it)
- [ ] `php cli/predeploy_report.php` gives PASS or PASS_WITH_WARNINGS on the machine that builds the package, and you have read the warnings
- [ ] `storage/` folders from section 5b created and writable; `.env` has `SELLER_CHECK_ENABLED=false` unless HG-14 is done
- [ ] First `plan` run read, `smoke_check` passes after `deploy`
- [ ] Written go-ahead to deploy to modinspect.thuliamdev.com
