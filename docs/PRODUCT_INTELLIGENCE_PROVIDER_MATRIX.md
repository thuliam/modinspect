# Product Intelligence provider matrix (research only)

Created 2026-11-15 for Sprint I3. **No provider has been called, scraped or integrated.** The rows below record what the owner handoff
(`ModInspect_CLAUDE_MASTER_ALL_IN_ONE_v2`, 2026-11-15) states about each source. Nothing was network-verified in this session, so every
licence/rate-limit cell is a claim to confirm from the provider's own page before use. Status vocabulary:
`APPROVED_POC` / `LINK_ONLY` / `LICENSE_REQUIRED` / `REJECTED` / `UNKNOWN`. Nothing is `APPROVED_POC` yet: that needs the owner.

Rules that apply to every row: zero-cost POC policy (AGENTS.md); no scraping behind CAPTCHA or login; no external call on a page view
(controlled CLI enrichment into a cache); a provider failure must never break a price page; benchmark scores are never price evidence.

| Provider | Useful for | Documented access (per handoff, unverified) | Commercial reuse | Status | Last checked | Evidence / next step |
|---|---|---|---|---|---|---|
| AMD / Intel / NVIDIA official spec pages | release date, cores, clocks, TDP, VRAM | Public web pages; no free public API assumed | Page visibility is not reuse permission; read each site's terms | UNKNOWN | not checked | Cite per fact (URL + capture date); quote only facts, not marketing text |
| Wikidata | structured release date/architecture with citations | Open (CC0 data) | Allowed for data; coverage and citations vary | UNKNOWN | not checked | Verify each exact variant and its source before storing |
| TechPowerUp GPU/CPU database | detailed specs, release | Advertises REST/MCP and a curated research subset | **Developer commercial use needs explicit licence review** (https://www.techpowerup.com/database-licensing/) | LICENSE_REQUIRED | not checked | Ask for written terms before any use; do not assume the free research API covers a public product |
| Geekbench Browser API | CPU/GPU compute scores | API key required; documented as under development (https://www.geekbench.com/api/api.html) | Display rights need separate approval | LICENSE_REQUIRED | not checked | Request access; never scrape result pages |
| PassMark | CPU/GPU scores | Site restricts redistribution of published scores for public comparison/ranking | Written permission needed | LINK_ONLY | not checked | Outbound link with attribution only until permission |
| OpenBenchmarking / Phoronix / open GPU datasets | open test results | Per dataset | Per dataset licence; a public repo is not permission | UNKNOWN | not checked | Evaluate coverage, environment and licence dataset by dataset |
| UserBenchmark | UX reference for compare/rank pages | May block automated access (CAPTCHA) | No documented public API/licence | LINK_ONLY | not checked | Reference for layout only; never bypass the block, never copy rankings |

## Data model direction (not built)

`product_external_ids`, `product_specifications` (category-specific keys), `product_release_info` (date + precision + citation),
`product_benchmarks` (suite, version, unit, configuration, source URL, licence, captured date), `product_enrichment_runs`.
All keyed to the canonical Product Master id and exact variant; none writes to price tables.

## POC scope when licensing allows

10 existing CPU/GPU products with clearly different variants; release date and key specs from a cited source; benchmarks only where the
licence allows display. A source link is better than an invented score or a "world rank". Rank must always name source, suite, version,
population and as-of date.

## Owner decisions this blocks

- Approve one provider for a POC call (and confirm it costs 0 THB), or choose link-only for all.
- Confirm whether manufacturer facts typed by hand with a citation are acceptable as the first POC source.
