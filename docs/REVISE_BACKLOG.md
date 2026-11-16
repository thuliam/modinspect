# Revise backlog (features the owner thinks are still missing)

The owner said, at the start of LS-2 (2026-11-16): "there are still many features I think you missed; finish this sprint first, then we revise."
This file collects candidates while LS-2 runs, so the revise session starts from evidence instead of memory. Nothing here is committed scope.
Source of truth for scope stays `docs/PRODUCT_REQUIREMENTS.md` and the Blueprint; AGENTS.md says a feature is not implemented just because a document mentions it.

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
- H-11: no scheduled backup, no off-machine copy, no retention rule and no encryption of backup files; a backup routine on the real host (cron + off-site copy + a periodic restore drill) is part of beta readiness and needs the hosting decision. Point-in-time recovery needs binary logging on the host.
- H-11: `/health` checks only the database; a deeper check (migrations applied, storage writable, last collection run) is possible but would reveal more, so it would need to sit behind admin auth.
- H-12: the parity scan reads PHP only; asset file names referenced from CSS/JS/HTML (fonts, images) and the `.htaccess` rules are not scanned for case, and nothing tests file permissions or symlinks on Linux hosting.

## Owner-listed features (to be filled by the owner)

(none yet)
