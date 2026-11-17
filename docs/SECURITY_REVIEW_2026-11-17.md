# Security review refresh, 2026-11-17 (LS-5 L-6)

Scope: everything added since `SECURITY_REVIEW_2026-11-16.md`: the unresolved-listings queue and its stale tab, the report CLIs (`unresolved_proposals`, `pending_gap_report`, `reresolve_stale_dry_run`), the ops probe and backup pruning, the Priceza new-price guard, the owner-transcription capture method (importer, provenance, confidence), the public confidence labels. Method: reading the code, mutation-checked tests, one real incident. Same limits as before: a code review by the agent that wrote most of the code, not a penetration test.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| N-9 | **High** | A failed database connection chained PDO's own exception. PHP printed its stack trace, which holds the constructor arguments, so **part of the DEV database password was printed** (happened in LS-4 when a CLI ran with a PHP build lacking the MySQL driver). Any uncaught connection error on a host with `zend.exception_ignore_args=Off` would have exposed it on the page or in a log. | **Fixed**: the exception is no longer chained and carries only the SQLSTATE; `tests/db_error_no_secret_test.php` (failed first, passes now) asserts the password, user and database name never appear. **Owner action: rotate the DEV DB password** (it appeared in this session's log). |
| N-10 | Medium | Owner-transcription rows are typed by a person from marketplace posts, whose titles often carry a seller's phone number. The frozen snapshot, evidence excerpt and raw title would store it (private/admin only, but AGENTS.md says to minimise seller PII). | **Fixed**: a title containing a Thai phone number is rejected (`OWNER_TRANSCRIPTION_TITLE_HAS_PHONE_NUMBER`); personal profile links (`profile.php`, `/people/`) are rejected too. Detection is a pattern, not proof: names and addresses typed into a title are not detected, which the owner guide says. |
| N-11 | Medium | Priceza rows classified listing level would have entered the used asking median once approved (a data-integrity hole, not a leak). | **Fixed** (DL-077): `NEW_PRICE_REFERENCE_SOURCE` exclusion, tested and mutation-checked. |
| N-12 | Low | The unresolved-listings queue replays the resolver for every undecided row on each page view (369 rows, fast). An authenticated admin could slow the page if the queue grows large. | Accepted for now; cache if the queue passes a few thousand rows. |
| N-13 | Info | `cli/ops_probe.php --base=` makes outbound requests to an operator-supplied URL. | Accepted: operator-run CLI, `http(s)` only, no redirects followed, never reachable from the web. |
| N-14 | Info | The capture method proves what the owner recorded and when, not that the page said so. | Accepted by the owner (P1); stated in the confidence caps, the public page note and the guide. |

## Checked and found sound

- New admin handlers keep `requirePermission` and CSRF (the route guard test still passes over all admin routes); the queue's new `stale` status is an allow-listed value.
- All new SQL is constant or prepared; the one `LIKE '%CAPTURE_METHOD=owner_transcription%'` is a literal.
- New CLIs print counts, product names and ids only (checked by reading and by running them on DEV read-only); none prints a title, URL, price text or seller field.
- The importer still reads only files inside the project root; `dataset=real` rows cannot use mock/test sources; owner-transcription rows need an allow-listed domain, an item id and no profile link.
- Secret scan: clean at the last run (457 files, 8 rules).

## Not covered

No independent audit, no penetration test, no load test, no review of the host (HostAtom) configuration, TLS, backups off the server, or the Bootstrap 4.1 / jQuery 3.3 advisories (N-4 stays open). Colour contrast and screen-reader behaviour are still not checked.
