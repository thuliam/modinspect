# Security review refresh, 2026-11-16 (LS-2 H-13)

Scope: everything added since `SECURITY_REVIEW_2026-11-14.md`: admin pages (data health, snapshot evidence and history), public `robots.txt`, `sitemap.xml`
and `/health`, the session-length setting, the backup and restore tools, `.htaccess` changes, the new CLIs, and the pinned front-end libraries.
Method: reading the code, route and guard tests (`tests/admin_route_guard_test.php`), live HTTP checks against the local Apache, and a file permission check.
This is a code and configuration review by the same agent that wrote most of the code, not a penetration test and not an independent audit.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| N-1 | Medium | `AUTH_IDLE_TIMEOUT_SECONDS` / `AUTH_ABSOLUTE_LIFETIME_SECONDS` (added for DL-040) were honoured on every environment: a development `.env` copied to production would give an admin a 7-day session | **Fixed**: the overrides are ignored unless `APP_ENV` is `local` or `testing` (a missing `APP_ENV` counts as production); `tests/auth_session_lifetime_test.php` covers production, staging, empty and missing |
| N-2 | Medium | Backup files (password hashes, raw listing text) were created with the default umask (0644 on Linux) in a 0775 folder | **Fixed**: file 0600, folder 0700; the drill test asserts owner-only permissions on non-Windows (it runs on the Linux CI runner) |
| N-8 | **High** | (found 2026-11-17) Product, alias and article write handlers were guarded by `products.view`, which reviewer and operator roles also hold: a reviewer could edit the product master, aliases and articles | **Fixed** in LS-3 J-0: new `products.manage` permission (admin only), test-first `tests/rbac_master_data_write_test.php`. The 2026-11-14 review and the route guard test (permission exists, handler guarded) could not see it: a guard on the wrong permission still looks guarded |
| N-3 | Low | `/sitemap.xml` is public and runs one query per request with no cache | **Fixed** in LS-3 J-4: a 10-minute file cache serves the sitemap; Deal Checker and Build POSTs are rate-limited (S-7 of 2026-11-14 closed with it; caveat: behind a proxy REMOTE_ADDR is the proxy address) |
| N-4 | Low (advisory) | The pinned libraries are old: Bootstrap 4.1.0 and jQuery 3.3.1 have public advisories (a tooltip/popover template XSS fixed in Bootstrap 4.3.1; a prototype-pollution issue in jQuery `extend` fixed in 3.4.0) | Open, **not verified as exploitable here**: exploitability depends on feeding user text into tooltip templates or deep `extend`; upgrading means downloading new vendor files (owner approval) and updating the hash manifest |
| N-5 | Info | `/health` is public | Accepted: returns only ok/unavailable and database ok/unavailable, 503 on failure, no host, path or error text (test) |
| N-6 | Info | Canonical and `og:url` use the request path | Accepted: the host comes from `APP_URL`, never from the `Host` header; the value is HTML-escaped |
| N-7 | Info | `Cache-Control: immutable` for one year | Accepted: applied only to URLs that carry `?v=<number>` and end in a static extension; PHP pages keep `no-store` |

## Checked and found sound

- Every one of the 38 `/admin` routes (the handler or the helper it calls, two levels deep) calls `requirePermission` with a permission that exists; every POST verifies the CSRF token; an unauthenticated request to 36 of them is turned away over real HTTP (the other 2 end in `.csv`/`.json` and are covered by the static scan; on Apache the live request answered a login redirect).
- New SQL: all constant or prepared with integer ids; the only interpolated identifiers (database and table names in the backup tool) are validated against `^[A-Za-z0-9_]+$` or come from `information_schema`.
- New views escape every dynamic value (`htmlspecialchars` or an integer cast); the data health and snapshot pages carry no source key, URL or listing text (tests assert it on the data).
- Backup and restore: the database password travels in the child process environment (`MYSQL_PWD`), never in a command line; a restore refuses the source database, any name not ending in `_test` or `_restore`, and any name with other characters; processes are started with argument arrays (no shell).
- CLI tools added in LS-1/LS-2 print counts, row ids and model keys only; none prints seller text, names or URLs.
- Secret scan: 401 tracked files, 8 rules, clean. `storage/backups/` is now git-ignored.

## Not covered

Rate limiting and abuse controls (S-7 stays open), a penetration test, dependency scanning beyond the two advisories above, server hardening of the real host (TLS, `expose_php`, file permissions on deployment), and backups held off the machine.
