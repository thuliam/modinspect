# Content-Security-Policy: enforcing (owner decision 2026-11-19)

State: `public/.htaccess` sends `Content-Security-Policy` (enforcing). It was report-only from LS-19 until the owner asked for it to be switched on in LS-21. A page that tries to run an inline script, load a script, style, font or picture from another site, or post a form to another site is blocked by the browser.

## Why it was safe to switch

- No view (public, Admin, sign-in) has an inline `<script>`, inline event handler or `javascript:` link. `tests/ls20_csp_ready_test.php` fails the day one appears.
- A browser sweep of 35 pages (22 Admin, 13 public and sign-in) with a `report-uri` file logger found **zero violations**, before and after switching. The logger and the blocking were proven by a deliberate inline script: with the enforcing header it did not run and was logged with `disposition: enforce`.
- After switching, the pages were also used, not just loaded: Select2 boxes, trend chart, product jump bar, Compare category lock, Build preview POST, home animations, Admin tables and filters, review page.
- Charts and the Build page read JSON data blocks (`type="application/json"`), which a CSP does not treat as script.

## What it allows

Scripts: this site only (no inline, no eval). Styles: this site plus inline `style=""` attributes (reveal delays, Select2 sizing need them). Images: this site and `data:`. Fonts, XHR: this site. Forms may post to this site and to the three sign-in providers (Google, Microsoft, Facebook). Nothing may frame the site except itself. Browsers also post violations to `/csp-report`.

## Seeing what it blocks

Violations arrive at `POST /csp-report` (`SecurityController`, rate limited to 60 per 10 minutes per client, body capped at 4 KB, nothing stored) and become one line each in the PHP / server error log:

```
[modinspect-csp] directive=script-src-elem blocked=inline page=https://<domain>/price/x disposition=enforce
```

Only the directive, what was blocked and the page path are kept (no query strings, control characters removed). Look for `[modinspect-csp]` in the log in the first days after a deploy and after any change to the pages. A blocked page usually shows up as a feature that "does nothing" (a dropdown that stays plain, a chart that is missing); the log line says which source to move into a file or allow.

## Rollback (seconds, no code deploy)

In `public/.htaccess`, change the header name on the one line, nothing else:

```
Header always set Content-Security-Policy "default-src 'self'; ..."
```
becomes
```
Header always set Content-Security-Policy-Report-Only "default-src 'self'; ..."
```

Then reload a page and confirm the header. If a page breaks, note its URL and the `[modinspect-csp]` line (or the browser console message "Refused to ...") before rolling back.

## Host notes

- If the host does not read `.htaccess` for headers, the same value goes where the host sets response headers; `report-uri /csp-report` assumes the document root is `public/`.
- Run `php cli/smoke_check.php https://<domain> --links` and open the home page, a product page, Compare, Build, the Deal Checker and `/admin` with the browser console open after the first deploy.
- A proxy or CDN that injects its own script (analytics, a chat widget) will be blocked. That is the policy doing its job; add the host to `script-src` deliberately or leave it blocked.

## Later, if wanted

Replace `'unsafe-inline'` for styles with a nonce (needs a small change in the layouts and the inline `style=""` attributes), and add `upgrade-insecure-requests` once HTTPS works on every page.
