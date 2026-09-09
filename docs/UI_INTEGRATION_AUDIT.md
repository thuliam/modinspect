# UI Integration Audit

Phase 2D-I audit date: 2026-09-09

Scope: all visible public, seller, B2B, and admin routes currently registered in `public/index.php`. No live provider call was made.

| Page | Action | Frontend Exists | Backend Exists | Works | Fix |
|---|---|---|---|---|---|
| `/` | Search product | Yes | Yes | WORKING | Uses `/price?q=...` |
| `/` | Upload image | Yes | No | PLACEHOLDER | Replaced link with disabled "อัปโหลดรูป — ยังไม่เปิดใช้งาน" |
| `/` | Market counters | Yes | Yes | WORKING | Replaced hard-coded totals with real Product/Review/Accepted counts |
| `/` | Partner ads | Yes | No | PLACEHOLDER | Replaced fake offers with disabled empty state |
| `/price` | Search/filter | Yes | Yes | WORKING | Existing GET filter retained |
| `/price` | Product cards | Yes | Yes | WORKING | Cards only show prices when accepted snapshot is available |
| `/price/{slug}` | Price snapshot | Yes | Yes | PARTIAL | Shows Q1/Median/Q3 only when accepted observations exist; otherwise honest empty state |
| `/price/{slug}` | Save/share | Yes | No | PLACEHOLDER | Disabled with "กำลังพัฒนา" labels |
| `/price/{slug}` | Compare | Yes | Yes | WORKING | Links to compare route |
| `/price/{slug}` | Deal Checker handoff | Yes | Yes | WORKING | GET handoff now preserves product/price fields |
| `/deal-checker` | Submit deal | Yes | Yes | WORKING | PriceEngine now refuses unaccepted snapshot data |
| `/compare` | Product comparison | Yes | Yes | PARTIAL | Uses accepted-snapshot gate and shows insufficient state |
| `/build` | Save build | Yes | Yes | PARTIAL | Builder generation filters to accepted-snapshot products; unavailable slots remain disabled |
| `/seller-price-advisor` | Price advice | Yes | Yes | PARTIAL | Works only when accepted snapshot exists; speed selector disabled |
| `/methodology` | Static transparency | Yes | Yes | WORKING | No change |
| `/local-match` | Nearby search | Yes | No | NOT IMPLEMENTED | Disabled controls and removed fake nearby listings |
| `/market-report` | Market report | Yes | Yes | PARTIAL | Uses accepted-snapshot gate; live provider and auto-accept marked OFF |
| `/seller` | Seller dashboard | Yes | No | PLACEHOLDER | Replaced hard-coded seller KPIs with partial/disabled status |
| `/shop` | Inventory summary | Yes | Yes | PARTIAL | Real inventory rows retained; hard-coded margin risk removed |
| `/shop/inventory` | Add item/filter | Yes | No | PLACEHOLDER | Disabled add/filter controls |
| `/admin` | Operations dashboard | Yes | Yes | WORKING | Wired real DB counts, source health, provider readiness |
| `/admin/products` | Product list | Yes | Yes | WORKING | Add action disabled as not implemented |
| `/admin/product-aliases` | Alias list | Yes | Yes | WORKING | Add action disabled as not implemented |
| `/admin/price-observations` | Observation filter | Yes | Yes | WORKING | Wired status/search GET filter |
| `/admin/review-queue` | Approve/reject/exclude | Yes | Yes | WORKING | Lists all pending rows and posts to existing decision flow |
| `/admin/review-queue` | Field correction | Yes | No | NOT IMPLEMENTED | Disabled "แก้ไขรายฟิลด์ — ยังไม่เปิดใช้งาน" |
| `/admin/review-analytics` | Review calibration metrics | Yes | Yes | WORKING | RBAC-protected analytics from review/correction/source lineage with MOCK/TEST warning |
| `/admin/price-indices` | Index list | Yes | Yes | WORKING | Recalculate button disabled as CLI-only |
| `/admin/sources` | Health/readiness | Yes | Yes | WORKING | Wired source health and Gemini readiness |
| `/admin/sources` | Pause/resume/enable/disable/incident | Yes | Yes | WORKING | Posts to `SourceOperationsService` with CSRF |
| `/admin/collector-jobs` | Job list | Yes | Yes | WORKING | Shows status, attempts, run ID, errors |
| `/admin/collector-jobs` | Requeue failed job | Yes | Yes | WORKING | Posts to explicit requeue service; unavailable for non-failed jobs |
| `/admin/articles` | Create article | Yes | No | PLACEHOLDER | Disabled create action |
| `/admin/audit-logs` | Audit list | Yes | Yes | WORKING | Replaced placeholder with real audit log table |

Result: no known visible dead buttons remain. Visible incomplete actions are disabled and labeled.
