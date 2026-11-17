# Front-end library options: Bootstrap 4.1.0 and jQuery 3.3.1 (beta checklist C-6)

**Status 2026-11-17: option B is done.** The owner approved the jQuery download; jQuery is now 3.7.1 (DL-088, `docs/LS9_REPORTS.md`). Bootstrap 4.1.0 is unchanged and option C is still open. The text below is the brief as written before the approval.

For the owner's decision. **Nothing was downloaded or changed when this was written.** Any download needs your approval (AGENTS.md UI rule); this brief names what would be fetched so you can approve it or not.

## What the project ships today

- `jquery-3.3.1.min.js` (86,927 bytes) and Bootstrap 4.1.0, pinned with hashes in `public/assets/admin/vendor/MANIFEST.json`. jQuery loads on the public pages, the admin pages and the sign-in page (`layouts/footer.php`, `admin_footer.php`, `auth_footer.php`). DataTables 1.13.11 (official) and Font Awesome sit beside them. The admin skin is the DropX template.

## Known advisories (what the sources say)

- **jQuery 3.3.1:** prototype pollution in `jQuery.extend(true, ...)`, fixed in 3.4.0 (CVE-2019-11358); two XSS issues when untrusted HTML reaches `.html()`, `.append()` and similar, fixed in 3.5.0 (CVE-2020-11022, CVE-2020-11023). Source for these three: the search result summary of the NVD records on 2026-11-17; the jQuery release notes (<https://blog.jquery.com/2019/04/10/jquery-3-4-0-released/>, <https://blog.jquery.com/2020/04/10/jquery-3-5-0-released/>) were **not opened** in this session.
- **Bootstrap 4.1.0:** XSS through the tooltip and popover `data-template` attribute, fixed in 4.3.1 (CVE-2019-8331; [Bootstrap release post](https://blog.getbootstrap.com/2019/02/13/bootstrap-4-3-1-and-3-4-1/)). The search could not confirm whether 4.1.0 itself is inside the affected range, nor any other Bootstrap 4.1.0 advisory; I will not claim more than that.

## Is the project exposed? (checked in our own code, 2026-11-17)

- Bootstrap behaviours used in views: only `data-toggle="collapse"` (6) and `data-toggle="dropdown"` (2). No tooltip, popover or scrollspy, and no `data-parent` or `data-container`. The advisory's path (a tooltip or popover template built from user text) does not exist in our markup.
- Our own scripts (`site.js`, `spec-builder.js`, `mi-dropx.js`) do not call `.html()`, `.append()`, `.prepend()`, `.before()`, `.after()` or `$.extend(true, ...)`. Exposure through our code is therefore not shown.
- **Not audited:** how DataTables and the DropX scripts use those jQuery functions internally with data from the server. The data comes from our own server (the admin tables), not from visitors, which keeps the risk low but is not a proof.

Conclusion: no exploit path found in our code; the libraries are still old, and a scanner will flag them.

## Options

| | A. Keep, record the risk | B. Update jQuery only to 3.7.1 | C. Update jQuery and Bootstrap (4.6.2, last 4.x) | D. Bootstrap 5, drop jQuery |
|---|---|---|---|---|
| Fixes jQuery CVEs | no | yes (3.5.0 and later) | yes | removed by dropping jQuery |
| Fixes the Bootstrap advisory | no | no | yes | yes |
| Download to approve | none | `jquery-3.7.1.min.js`, about 87 KB, from the official jQuery CDN file or the npm package | the above plus `bootstrap.min.css` (about 160 KB) and the Bootstrap JS bundle (about 80 KB) from getbootstrap.com or npm | Bootstrap 5 files, and a replacement for every jQuery-based part |
| Work | none | swap one file, update the manifest hash, run the suite and the CI vendor guard, compare 14 pages at two widths (the method used in DL-036) | the same plus a visual comparison of the admin skin, because DropX was built for Bootstrap 4.1 | very large: DataTables and DropX depend on jQuery |
| Risk | scanners keep flagging it | low: jQuery 3.x is backward compatible, DataTables 1.13 supports it | medium: Bootstrap 4.1 to 4.6 changes some form and component styles; the template may shift | high |
| Cost | 0 THB | 0 THB | 0 THB | 0 THB, but a long sprint |

Sizes are approximate and from memory; the real numbers would be read from the files at download time and recorded in `docs/THIRD_PARTY_LICENSES.md` with their licences (both are MIT).

## Recommendation

**B now, C only after a DropX compatibility check.** B removes three of the four known advisories at low risk and touches one file. C waits until you decide whether the DropX skin may change, since that is your supplied template. D is not worth it at this stage. If you choose A, say so and I will record it as an accepted risk in the beta checklist.

## What I need from you

1. Approve (or not) downloading `jquery-3.7.1.min.js` from the official source named above.
2. Say whether the DropX admin skin may change, which decides when C is considered.
