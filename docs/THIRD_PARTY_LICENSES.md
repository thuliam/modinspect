# Third-party components and licences

Source of truth: `public/assets/admin/vendor/MANIFEST.json` (versions, sources, SHA-256 per file). CI fails if a component has no
version, licence, source or provenance recorded (`php cli/admin_vendor_manifest.php --verify`). Update this table when the
manifest changes.

| Component | Version | Licence | Provenance | Notes |
|---|---|---|---|---|
| jQuery | 3.3.1 | MIT | copied from the Concept template; **not yet compared with the official npm tarball** | upgrade path: pin an official 3.7.x release in a deliberate change |
| Bootstrap (CSS + JS bundle) | 4.1.0 | MIT | copied from the Concept template; not yet compared with the official release | Bootstrap 4 major is intentional; never mix with 5 |
| Font Awesome Free | 5.0.9 | Icons CC BY 4.0, fonts SIL OFL 1.1, code MIT | copied from the Concept template | keep the attribution header in `fontawesome-all.css`. Known gaps: CSS points at `*.html` font files (404, falls back to `.woff`) and newer icons (`fa-file-csv`, `fa-search-dollar`) do not exist in 5.0.9. A verified official 5.x download needs owner approval (gate G-7) |
| DataTables core + Bootstrap 4 integration | 1.13.11 | MIT | official npm packages `datatables.net` and `datatables.net-bs4`, tarball SHA-512 verified at install | |
| IBM Plex Mono (Regular, Medium; woff2) | 2.5.0 | SIL OFL 1.1 (license text in `public/assets/fonts/IBMPlexMono-OFL-LICENSE.txt`) | official npm package `@ibm/plex-mono`, tarball SHA-1 152c0b1dda619ef5f032db258bda798cf278125e matched the registry; files SHA-256 Regular ba204497f16b6d334cee9d1e963a831b73e3a56e1d6300a8489d18df7214b350, Medium 33faf307fa6031fb4062276d7320a6d632de890cbb347576fd80cfa01077bc25 | owner approved the download 2026-11-14. Latin only (no Thai glyphs); Thai text uses the system Thai font. Used by the public site only |
| Plus Jakarta Sans (400/600/700/800, Latin subset woff2) and Prompt (Thai subset 400/500/600 woff2) | @fontsource 5.x (latest on jsDelivr npm mirror, 2026-11-15) | SIL OFL 1.1 (license texts in `public/assets/fonts/PlusJakartaSans-OFL-LICENSE.txt` and `Prompt-OFL-LICENSE.txt`) | downloaded once from the npm package files `@fontsource/plus-jakarta-sans` and `@fontsource/prompt` via cdn.jsdelivr.net, then self-hosted (no runtime request); owner approved 2026-11-15. Files: PlusJakartaSans-400.woff2 11816 B sha256 221a4135d06a4b33abbd535e9a0da4e565d19545ddd9267c4e678a916d54d9b6; PlusJakartaSans-600.woff2 12188 B sha256 8872bb5c9111df9bd3162c2394aee6354d782b13408a4dd5bbf65c48207206e6; PlusJakartaSans-700.woff2 12244 B sha256 2050755bf475817c96ac7d914c7f07cc3c2d11ff4b3fb4747b8d41de584aad17; PlusJakartaSans-800.woff2 11896 B sha256 5f301a8ef9c266c8b596e6793d3cc826daeff9849c5af035f5386439137955ad; Prompt-Thai-400.woff2 13192 B sha256 d10643e148806b6ec9976370cf78b43b0542b6c457ca14b5fc89a5d2fdd156f1; Prompt-Thai-500.woff2 12924 B sha256 3ed6cc86f385acb545a840d88356378551c9a8de5f59c7d19b62464e85dea55d; Prompt-Thai-600.woff2 13044 B sha256 a7f3d9ca1d17420a311da6785fbfa670c0f7a57ea421eaca4ffe354d61a43a12 | CI typography (design/ci/cybernetic_trust/DESIGN.md). Latin glyphs from Plus Jakarta Sans, Thai from Prompt via unicode-range in `ci-tokens.css`. Used by the public site only |
| Concept Bootstrap 4 admin template patterns | n/a | MIT (Colorlib) | adapted layout/styles, see `public/assets/admin/README.md` | the template folder itself is not part of the application |

Not bundled: no CDN scripts, no web fonts fetched at runtime by the admin UI. The public site's font choices are covered by the
brand decision (gate G-3); any chosen font must be OFL/Apache licensed and self-hosted.

PHP dependencies: none (custom MVC, no Composer packages).

## DropX Admin (visual language only)

- Source: DataDropX, https://datadropx.net, v1.0.x. Licence: DropX Admin License (MIT-based). Conditions kept: copyright notice here, visible credit "Designed & developed by DataDropX" with link in the admin footer, no resale.
- Used: design tokens and layout metrics re-implemented in `public/assets/mi-dropx.css`. No template code or assets are copied.
