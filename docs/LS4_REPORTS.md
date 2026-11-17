# LS-4 reports (K-1, K-2, K-4, K-6)

Date: 2026-11-17. Read-only against shared DEV. Nothing was written to the database in this sprint; no alias, product, observation or snapshot changed. No listing text appears here.

## K-1 Alias proposals for the unresolved listings

Command: `php cli/unresolved_proposals.php` (about 2 minutes on DEV; it replays the resolver for every open row).

| Count | Meaning |
|---:|---|
| 369 | open rows in the unresolved queue |
| **159** | **already matched by today's catalog and resolver.** They were decided before the catalog grew (LS-2 catalog expansion, resolver fixes), so the queue is stale for them. They need to be re-run through the importer, not given an alias. (This is a reading of the data; the re-run itself was not tried, see the decision below.) |
| 8 groups (14 rows) | a person must look: the closest catalog product differs in the full model token (14700K vs the catalog's 14700KF, 5800X3D vs 5800X, 2600 vs 2600X, 5600G vs 5600, 5500X3D vs 5500, 9700F is an Intel-style name on an AMD 9700X). Most are **catalog gaps**, not alias gaps: the exact product does not exist. |
| 191 | no usable proposal (no CPU or GPU model number in the title, for example RAM, storage, bundles) |

**Result: there is no alias the tool can call safe right now.** The earlier draft of this report called 29 groups "safe"; the cause was a check that compared only the number part of a model. Fixed in this sprint: an alias is "safe" only when family and the full model token equal the product's, and it passes the same save-time checks as the queue (suffix, resolves the listing, changes no other listing). `tests/unresolved_proposal_report_test.php` locks it (14 cases, including `i7 9700F` must never equal `Ryzen 7 9700X`).

Options for the owner (Recommended first):
1. **Re-run the 159 stale rows through the existing importer path (Recommended).** Needs a small dry-run first to prove it produces pending observations and no duplicates; the writes would be pending rows reviewed by the existing planner and `owner_intake_approve --actor=54`. Needs the owner's go-ahead because it writes to DEV.
2. Do nothing; the queue keeps showing 159 rows that no longer need an alias.
3. Hide stale rows in the admin queue (a small code change, no data written).

## K-2 Pending products

Command: `php cli/pending_gap_report.php`.

- 22 products stay pending; **none is approvable today**.
- 21 are `too_few`: 9 need 1 more usable asking row (RX 6600 XT, RX 6700 XT, RX 6500 XT, i9-13900K, i3-13100F, Ryzen 9 7900X, Ryzen 5 8500G, Ryzen 3 3400G, DDR4-2666 16GB) and 12 need 2 more.
- 1 is `bimodal` (RTX 5090, two price groups).
- **34 extra rows in total would let every pending product reach the 3-row minimum.** The nine one-row-short products are the cheapest wins for any new data source.

## K-6 Accessibility and weight scan of the public pages

Test: `tests/public_a11y_scan_test.php` (real HTTP, isolated test DB; 9 pages). It checks html lang, title, viewport, exactly one main landmark, img alt, names on links, buttons and form controls, duplicate ids, HTML size and server time. It also proves the checker flags a bare page.

- **Real finding fixed:** `/build` had two `main` landmarks (the page's own `<main class="component-picker">` inside the layout's `<main>`). It is now a `div` with the same class; no visual change (no CSS selects on the tag).
- After the fix all 9 pages pass; heaviest HTML 51 KB, slowest 0.04 s locally.
- **Not covered, stated plainly:** colour contrast, keyboard order, focus visibility and real screen-reader behaviour need a manual or browser-tool pass. Not done in this run.

## K-4 Ops kit for HostAtom (nothing deployed, nothing run on the host)

- `php cli/ops_probe.php --base=https://host`: post-deploy and uptime probe. Fails (exit 1) on a bad `/health`, a non-200 public page, a PHP error in a page, a slow page (over 3 s), `/admin` not redirecting, or `/.env`, `/app/bootstrap.php`, `/composer.json` not 403/404. Rules are in `app/Services/Ops/ProbeRunner.php`, tested with an injected fetcher in `tests/ops_kit_test.php` (6 failure cases, each producing exactly one failure).
- `php cli/db_backup.php` (existing) plus the new `php cli/db_backup_prune.php --keep=14`: deletes only `*.sql` files beyond the newest N and refuses N below 1.
- Suggested schedule (cron on the host, set by the owner; times are server time, UTC+7):

```
# daily backup at 03:10, keep 14, probe every 5 minutes (alert on exit 1 via the host's cron mail)
10 3 * * *   cd <account-root> && php cli/db_backup.php && php cli/db_backup_prune.php --keep=14
*/5 * * * *  cd <account-root> && php cli/ops_probe.php --base=https://modinspect.thuliamdev.com > /dev/null
```

- Backups hold password hashes and raw listing text: copy them off the server regularly (the host disk is not a backup). The restore drill is `php cli/db_restore.php` on a copy, never on live data first; the steps are in `docs/OPERATOR_GUIDE_TH.md` and the runbook section 4 and 9.
- Still the owner's: staging host or subdomain, host-side TLS and HSTS, who receives alerts, the off-server copy location.
