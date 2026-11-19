# Security review 2026-11-19 (LS-17 A2)

Scope: work added in LS-14 to LS-16 that a visitor can reach. That covers member sign-in (Google, Microsoft, Facebook), seller photo upload and serving, `POST /build/preview`, and the new query strings on `/price` and `/compare`. The method was reading the code and probing a local Apache with curl. No live provider was called and no DEV database was written.

## Findings

| # | Severity | Where | Finding | Status |
|---|---|---|---|---|
| S-1 | Medium | `ModuleController::compare` | Every `m[]` entry triggered one product lookup until four were found, so one URL with 400 unknown entries took **5.6 s**. A handful of such requests could keep the PHP workers busy. | Fixed: at most `MAX_MODELS*2` distinct entries are looked at (now 0.2 s). `tests/ls17_hardening_test.php` |
| S-2 | Low | `Product::search`, `/price` | The search query had no length cap. Each word adds LIKE and REGEXP conditions with alias subqueries, so 1,200 words took 0.8 s against 0.16 s for a normal search. | Fixed: `SearchQuery::clip` caps the query at 80 characters, which is above the longest catalog name. Both the controller and the model use it. |
| S-3 | Low | `/price`, `/deal-checker`, seller advisor | A value sent as an array (`?q[]=x`, `?product[]=x`) raised "Array to string conversion" warnings, one per product on the deal form. Production hides them (S-4 of 2026-11-14) but they fill the log. | Fixed: `Controller::queryString()` and string guards in the two views |

## Checked and fine

- **Member sign-in.**
  - The state is single-use and checked with `hash_equals` within a TTL.
  - PKCE verifier and nonce are passed to the token exchange.
  - The code length is capped.
  - The session id is regenerated after login.
  - Start and callback are rate limited, and start is CSRF-protected.
  - Logout and account deletion need CSRF, and deletion also needs an explicit confirm.
- **Seller photos.**
  - Size limit, MIME type from `finfo`, and `getimagesize` are checked.
  - Every image is re-encoded with GD, so embedded payloads are dropped.
  - File names are random hex. The serve path accepts only `^[a-f0-9]{32}$` / `^[a-f0-9]{24}\.jpg$`, so there is no traversal.
  - A photo is served only to its owner, a moderator, or anyone when the listing is public. Responses use `nosniff` and `Cache-Control: private`.
- **`POST /build/preview`.** CSRF, a rate limit of 300 per 10 minutes, no-store, and the server re-validates on save (LS-15).
- **New sort and filter values.** `sort` is checked against an allow list and `priced` is compared to `'1'` only. Pair links are built with `http_build_query` and escaped.
- **Error display.** Off unless `APP_ENV` is local or testing.

## For the host (owner, not code)

- The session cookie gets `Secure` only when PHP sees `HTTPS`. If the host terminates TLS in front of PHP (proxy or CDN), set `HTTPS=on` or the forwarded header in the host configuration, or the cookie travels without `Secure`.
- A Content-Security-Policy is still missing because inline scripts remain (see `public/.htaccess`). This is unchanged from earlier reviews.
