# Content-Security-Policy: from report-only to enforcing (owner decision)

State on 2026-11-19: `public/.htaccess` sends `Content-Security-Policy-Report-Only`. It blocks nothing. Enforcing is the owner's call.

## Why it is safe to switch now

- No view (public, Admin, sign-in) has an inline `<script>`, inline event handler or `javascript:` link. `tests/ls20_csp_ready_test.php` fails the day one appears.
- A browser sweep of 35 pages (22 Admin, 13 public and sign-in) with a `report-uri` logger found **zero violations**. The logger was proven first by a deliberate inline script, in a page and in an iframe.
- Charts and the Build page read JSON data blocks (`type="application/json"`), which a CSP does not treat as script.
- Not covered by the sweep: pages that need data the test database does not have (a review detail with many rows, for example), and the production host's own headers or any proxy. Watch the first hour after switching.

## What it allows

Scripts: this site only (no inline, no eval). Styles: this site plus inline `style=""` attributes (reveal delays, Select2 sizing need them). Images: this site and `data:`. Fonts, XHR: this site. Forms may post to this site and to the three sign-in providers (Google, Microsoft, Facebook). Nothing may frame the site except itself.

## How to enforce

In `public/.htaccess`, change the header name on the one line, nothing else:

```
Header always set Content-Security-Policy-Report-Only "default-src 'self'; ..."
```
becomes
```
Header always set Content-Security-Policy "default-src 'self'; ..."
```

If the host does not read `.htaccess` for headers, the same value goes where the host sets response headers. Then run `php cli/smoke_check.php https://<domain> --links` and open the home page, a product page, Compare, Build and `/admin` with the browser console open.

## Rollback

Change the header name back to `Content-Security-Policy-Report-Only` (seconds, no deploy of code). If a page breaks, note its URL and the console message ("Refused to ...") before rolling back; it names the exact source to allow or move into a file.

## Later, if wanted

Replace `'unsafe-inline'` for styles with a nonce (needs a small change in the layouts and the inline `style=""` attributes), and add `upgrade-insecure-requests` once HTTPS works on every page.
