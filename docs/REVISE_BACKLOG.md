# Revise backlog (features the owner thinks are still missing)

The owner said, at the start of LS-2 (2026-11-16): "there are still many features I think you missed; finish this sprint first, then we revise."
This file collects candidates while LS-2 runs, so the revise session starts from evidence instead of memory. Nothing here is committed scope.
Source of truth for scope stays `docs/PRODUCT_REQUIREMENTS.md` and the Blueprint; AGENTS.md says a feature is not implemented just because a document mentions it.

> 2026-11-17: the structured revise analysis is in `docs/REVISE_LS3_PROPOSAL.md` (missing features M-1..M-21, decisions D-1..D-5, proposed queue J-1..J-12). This file stays the raw list of findings from LS-2.

## How entries are added

- Each LS-2 task appends what it found that is outside its own scope (one line: what, where seen, why it matters).
- The owner adds the features they have in mind (plain words are enough); the agent maps them to requirements in the revise session.

## Candidates found so far

- H-1: 3 resolver-vs-extractor conflicts remain (i5-14600K to i7-14700KF, an RTX 3070 title to RX 6700 XT, one RTX 5060 to 5060 Ti): needs a marker rule for CPU suffix digits and a look at noisy titles.
- H-1: resolver recall is 62.3 % for CPU and 87.4 % for GPU on the research rows; most unresolved models are simply not in the Product Master (H-3), the rest need aliases (H-2).
- H-1: existing pending/approved observations were resolved with the old rule; a re-resolution audit of past assignments is a possible feature (read-only report first).
- H-3: the research rows are spread thin: 60 absent models, almost all with 1-2 rows. A coverage-driven product (which models to price first) needs a demand signal; none exists yet (no search logs reviewed).
- H-4: the ladder check covers GPUs only; a CPU ladder (X3D, F/K variants) and a RAM speed/capacity ladder need their own rules. Candidate feature: show the flags on the admin Data health page (H-5).
- H-4: `price_indices` accumulates a row per recalculation; there is no retention/archiving rule and no public price history chart beyond the monthly trend.
- H-5: Data health shows 137 pending of which about 88 are REAL (Priceza) rows waiting since September; a REAL review session is the longest-lead item on the roadmap. Candidate feature: split the pending number by origin (owner text vs REAL) and by age on this page.
- H-5: there is no per-source (not per-key) health view on this page; that needs a privacy decision on how much source identity admins may see.
- H-6: history is capped at the last 8 snapshots and only shows the median; a per-product chart of snapshots and a way to see which observation ids were added are possible features (the second would show prices and dates only).
- H-7: only two roles besides admin (reviewer, operator) exist and `audit.view` is admin-only; there is no role/permission management screen and no per-user audit export. Candidate feature: admin user management UI (create/disable users, change role) with its own audit trail.
- H-7: the local dev server cannot serve routes that end in .csv/.json without Apache rewrites; a small router script for local tests would remove this harness exception.
- H-8: structured data (schema.org Product/Offer) was not added: a price range without a seller or offer is not an Offer and could mislead search engines; needs a decision. No share image yet (rights). No English/Thai alternate pages.
- H-8: sitemap has no lastmod (snapshots have calculated_at; could be added), and there is no Search Console/analytics hookup (needs an account and privacy copy).
- H-9: hero image is a 199 KB PNG and product photos are up to 443 KB JPEG (7 photos only); WebP/AVIF conversion and responsive srcset need an image tool (owner approval for any download). bootstrap.bundle.js ships unminified (210 KB, 43 KB gzipped) because the pinned vendor manifest hashes that exact file; adding the .min file means updating the manifest.
- H-9: heading levels skip on the footer and on product-card lists; a proper fix is a small template pass with a visual diff.
- H-9: the owner's local Apache lacks mod_deflate/mod_expires; production hosting should be checked for them (runbook).
- H-10: Deal Checker only compares against the Q1-Q3 range; it has no notion of condition, warranty or box in the verdict (they are stored but unused in the label). Candidate feature: a transparent adjustment note (never silently changing the label). The result page is private by URL (uuid) with no expiry or delete control.
- H-10: /build lists 99 parts in one long list on mobile (no search/filter beyond brand); a sticky total and a part search would help.
- H-11: no scheduled backup, no off-machine copy, no retention rule and no encryption of backup files; a backup routine on the real host (cron + off-site copy + a periodic restore drill) is part of beta readiness and needs setup on HostAtom (DL-014). Point-in-time recovery needs binary logging on the host.
- H-11: `/health` checks only the database; a deeper check (migrations applied, storage writable, last collection run) is possible but would reveal more, so it would need to sit behind admin auth.
- H-12: the parity scan reads PHP only; asset file names referenced from CSS/JS/HTML (fonts, images) and the `.htaccess` rules are not scanned for case, and nothing tests file permissions or symlinks on Linux hosting.
- H-13: library upgrade path (Bootstrap 4.3.1+/jQuery 3.4+ with a refreshed hash manifest) and rate limiting for public POSTs and the sitemap are the two open security items; both are decisions or downloads for the owner. An independent review (or a scripted scanner run by someone other than the author) is still missing.
- H-14/H-15: the guide is Thai-only and assumes a technical operator at a command line; an Admin UI for the weekly routine (upload sanitized file, see the plan, approve) would remove the CLI dependency but must keep the dry-run-first rule and the audit trail. No English guide.
- H-15: the checklist is a document, not a screen; a read-only "beta readiness" panel fed by the same commands is a possible feature.
- J-0: admin product, alias and article pages still render the save/delete controls for reviewers and operators (they now get 403 on submit); hide them for roles without `products.manage`. Also review whether other write handlers are guarded by a view-level permission (the route guard test only proves a guard exists, not that it is the right one): a permission-to-action table for every POST would close this class.
- J-1: after an alias is added the listing stays unresolved and no observation is created; a "re-resolve and re-import this listing" action (through the existing import path, with the review lane rules) would close the loop. The queue shows raw title text (private evidence) truncated at 140 characters; a stricter privacy view (hash only) is possible if reviewers should not see seller text.
- J-2: no MFA, no forced sign-out of other sessions (sessions are PHP files not indexed by user; a server-side session table would allow it), no password-strength meter or breach check, no invitation/reset by email (zero-cost policy: no mail service), no per-user audit view in the UI.
- J-4: the limiter is per address and fixed-window (a burst across a window boundary can pass twice the budget); a trusted-proxy setting for HostAtom and a sliding window are possible refinements. Admin login keeps its own throttle; admin POSTs have no limiter (authenticated).
- J-3b (needs owner approval, it writes to DEV from the web): slice 2 = import the prepared rows from the screen, slice 3 = apply the approval plan from the screen with the acting admin recorded. Rules to keep: dry run first, show the plan, require a confirmation that names the counts, audit every step, never approve REAL (Priceza) rows.
- J-5: typo tolerance is not done ("rtx 3079", "ryzn 5600" return nothing); a "did you mean" line from edit distance over product names is the cheapest next step. The golden set is 34 queries written by the agent: real visitor queries (search logs do not exist yet) would be a better set. No ranking by match quality beyond popularity then name.

## Owner-listed features (to be filled by the owner)

(none yet)
