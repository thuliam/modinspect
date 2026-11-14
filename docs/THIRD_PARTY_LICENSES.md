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
| Concept Bootstrap 4 admin template patterns | n/a | MIT (Colorlib) | adapted layout/styles, see `public/assets/admin/README.md` | the template folder itself is not part of the application |

Not bundled: no CDN scripts, no web fonts fetched at runtime by the admin UI. The public site's font choices are covered by the
brand decision (gate G-3); any chosen font must be OFL/Apache licensed and self-hosted.

PHP dependencies: none (custom MVC, no Composer packages).
