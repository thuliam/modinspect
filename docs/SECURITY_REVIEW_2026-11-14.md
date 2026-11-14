# Security review, 2026-11-14

Scope: web exposure, RBAC and CSRF on every route, uploads, session and error handling, output escaping, secrets. Method:
reading the code, a static route audit (now a regression test), probing the Studio Apache with HTTP status checks (content was
never read), and reading the Apache access log. This is a code and configuration review, not a penetration test.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| S-1 | **Critical** | The project root had no `.htaccess`. With Laragon serving `http://host/modinspect/public/`, the whole project folder was web-reachable: `.env` (shared DEV database credentials), `.env.testing`, `modinspect.sql`, `storage.zip`, `context/`, `docs/`, `app/` and **`.git/config`** returned HTTP 200 on the LAN address | **Fixed**: root `.htaccess` allows only `/public` (everything else 403), verified live; regression test added |
| S-2 | High | Test scripts were web-executable (`tests/syntax_check.php` returned 200 to LAN host 192.168.1.8; `tests/run.php` ran when probed) | Fixed by S-1. Tests only ever use `.env.testing` (guarded), so DEV was not written to |
| S-3 | Medium | No security headers; `X-Powered-By` disclosed | Fixed: nosniff, X-Frame-Options SAMEORIGIN, Referrer-Policy, Permissions-Policy, HSTS when HTTPS, X-Powered-By removed |
| S-4 | Medium | No central error handling: with `display_errors` on, visitors would see paths, SQL and stack traces | Fixed: only `APP_ENV` = local/testing displays errors; any other value (or a missing one) logs and returns a generic 500 |
| S-5 | Medium | Public Deal Checker stored `condition_level` straight into an ENUM column (hostile value gives a DB error) and accepted any warranty length | Fixed: whitelist, clamp 0-120 months |
| S-6 | Low | Upload size was only checked after the file was stored (PHP allows 2 GB) | Fixed: 5 MB cap checked first, with a specific message |
| S-7 | Info | No public rate limiting on `POST /deal-checker` (creates DB rows) | Open, see backlog |
| S-8 | Info | `expose_php` is on at server level; `.git` is still present inside the web-served tree (now blocked) | Open: set `expose_php=Off` in the host `php.ini`; deploy with the document root at `/public` |

## Checked and found sound

- 57 routes audited by a test: every `/admin` handler (including those that delegate to helpers) calls `requirePermission`, and every POST verifies CSRF. The test fails if a future route misses either.
- No unescaped output found by a heuristic scan of 531 view expressions (the 72 flagged were literal arrays, `urlencode` or ints).
- Uploads: extension whitelist (csv/json), server-generated filename, `move_uploaded_file`, confirm step bound to the same file hash.
- Sessions: strict mode, HttpOnly, SameSite=Lax, Secure over HTTPS, ID rotation on login, destroy on logout.
- Deal check ids use `random_int`. No keys or `.env` files are tracked; an automated scan (8 rules, self-tested) is clean.

## Evidence on past exposure

The Apache access log for 2026-09-11 to 2026-11-14 (83,774 lines) shows no external request for `.env`, `.git`, `*.sql` or `storage.zip`. The only requests to sensitive paths were this review's own probes, plus one request for `tests/syntax_check.php` from 192.168.1.8 (a machine on your LAN). That is evidence, not proof (one log, one server). Because the DEV database host accepts remote connections, rotating the DEV database password is still prudent if there is any doubt about who reached the LAN, and is mandatory if this tree was ever served from another host. Rotating the credentials found in the raw LINE export remains gate G-5.

## Backlog (not done)

1. Public rate limiting for `POST /deal-checker` and `POST /login` beyond the existing login throttle.
2. A runtime check (CI job with Apache, or a post-deploy probe) that `/.env`, `/.git/config` and `/modinspect.sql` return 403/404.
3. Content-Security-Policy (inline scripts exist, so it needs a nonce pass first).
4. Move `modinspect.sql` and `storage.zip` out of the project root.
5. Single source for the public price-eligibility rule (currently copied in about 10 places).
