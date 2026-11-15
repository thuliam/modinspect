# ModInspect — UX/UI Audit & CI Alignment Plan

วันที่: 2026-11-15 · Role: UX/UI (devteam-plus) · Profile: PHP 8.1 custom MVC + Bootstrap 4 (`body.mi` theme)
ขอบเขต: ประเมินเท่านั้น **ยังไม่แก้โค้ด** (Gate G1 → รออนุมัติแผนก่อนเข้า M2)

แหล่งอ้างอิง
- CI: `stitch_brand_design_system_generator.zip` → `cybernetic_trust/DESIGN.md` (token), `homepage_modinspect/`, `asus_rog_strix_g15_modinspect/`, `request_inspection_service_modinspect/` (code.html + screen.png)
- Reference ภาพ Home (marketplace, dark + lime)
- Live: `/modinspect/public/` และ `/price/nvidia-geforce-rtx-3070` (desktop 1440, mobile 375)
- โค้ดที่ตรวจ: `public/assets/mi-theme.css` (29 KB), `app.css`, `app/Views/layouts|home|price`

## 1. ภาพรวม

ทิศทางภาพ (ธีมมืด + ไลม์, โลโก้, hero พร้อมภาพฮาร์ดแวร์, การ์ดหมวดหมู่, Why, How it works, Metrics, CTA) **ใกล้ Reference แล้วประมาณ 70–75%** โครงสร้างเหมือนกัน
ช่องว่างหลักอยู่ที่ **design token ไม่ตรง CI**, ฟอนต์ไม่ถูกโหลดจริง, ความสม่ำเสมอของ asset, และ motion/mobile

| ด้าน | คะแนน (0–5) | หมายเหตุ |
|---|---|---|
| Layout / โครงหน้า | 4 | ตรง Reference, section ครบ |
| Color token | 2 | พาเลตต์เขียวดำ ≠ CI slate-navy; ไลม์คนละเฉด |
| Typography | 2 | CI = Plus Jakarta Sans + Thai; live ใช้ Inter แต่ไม่มี @font-face |
| Components (card/chip/button) | 3 | รูปทรงใกล้ แต่ radius/เงา/สถานะไม่ตาม CI |
| Content/asset completeness | 2 | การ์ดสินค้า placeholder "+" ซ้ำ, รูปไม่ครบ |
| Motion / performance | 2 | reveal ช้า ทำให้จอว่างตอนเลื่อนเร็ว |
| Mobile / a11y | 3 | ไม่มี overflow แต่ tap target เล็กหลายจุด |

## 2. Findings (เรียงตามความรุนแรง)

### P1 — กระทบ CI โดยตรง
1. **Palette ไม่ตรง CI** — live: `--mb-bg #0d1110`, surface `#151a18`, raised `#1c2320` (เขียวอมดำ)
   CI: canvas `#0a0d14`, surface-base `#111622`, elevated `#171f2e`, border `#222d3d` / active `#354761` (slate-navy)
2. **สี Primary ผิดเฉด** — live `--mb-accent/--mi-lime #d4f139` · CI `#bbf246`; ข้อความบนปุ่มไลม์ live `#382f32` (น้ำตาล) · CI `#0a0d14`
3. **ฟอนต์** — CI: Plus Jakarta Sans (+ Prompt/Kanit สำหรับไทย); live: ประกาศ Inter แต่ **ไม่มี @font-face/ไฟล์** จึงตกไป system font (Segoe/Tahoma) ทำให้ตัวเลข/ราคาไม่เด่นตาม CI. ตัวเลขใช้ IBM Plex Mono ซึ่งไม่อยู่ใน CI
4. **สถานะ/สีเชิงความหมาย** — CI กำหนด secondary `#22c55e` (verified), tertiary `#facc15` (rating/warn), danger `#ef4444`; live ยังไม่มี token ชุดนี้ (ใช้ Bootstrap default ปนกับไลม์)

### P2 — คุณภาพ component
5. **Radius/Elevation** — CI: card/input/button 8px, chip 999px, banner 16px; live ใช้ 10/14/16/18/20/22/24px ปนกัน. CI ใช้ tonal layering + hover `translateY(-2px)` + border เปลี่ยนสี + glow `0 0 24px -4px rgba(187,242,70,.22)`; live ใช้ gradient หลายชุดต่อ component
6. **การ์ดสินค้า "รุ่นแนะนำ"** — 4 จาก 5 ใบเป็น placeholder ไอคอน "+" เหมือนกัน, ใบเดียวมีรูปจริง (พื้นขาวชนกับธีมมืด); ข้อความ "ข้อมูลราคายังไม่เพียงพอ" ซ้ำทุกใบ ทำให้ดูยังไม่เสร็จ (Reference มีรูป render ตัดพื้นหลัง + ราคา)
7. **หน้า Price (RTX 3070)** — โครงดี (gallery + summary + info + "อ่านราคานี้อย่างไร") แต่ภาพสินค้าเป็นรูปถ่ายพื้นสว่าง, empty state ("ข้อมูลตลาดยังไม่เพียงพอ") ครองหน้า ไม่มีโครงรอข้อมูล (price band / confidence / source chips ตาม CI Inspection chip)
8. **Hero** — ไม่มี halo/สไลซ์เฉียงตามภาพ Reference; ช่องค้นหาไม่มี ring `#354761` และไม่มี mode สลับ "Search Store / Verify Report"; chip "ยอดนิยม" มีเพียง 3 รายการ
9. **Footer** — Reference มีโซเชียล, ศูนย์ช่วยเหลือ, ติดต่อ, ข้อกำหนด; live มี 3 คอลัมน์ + disclaimer

### P3 — Motion / Mobile / A11y
10. **Reveal-on-scroll ช้า/ไม่ปลอดภัย** — เลื่อนเร็วหรือบน mobile หลังโหลด 2 วิ ยังเห็นพื้นมืดว่าง (hero mobile เกือบมองไม่เห็นหัวข้อ). ต้องมี fallback (no-JS / `prefers-reduced-motion` / timeout) และลด delay
11. **Tap target** — mobile มี link/button 25 จุดที่สูง/กว้าง < 40px (chip, footer link, nav icon); CI แนะนำ ≥ 44px
12. **A11y** — ใช้ lime-on-dark ได้ดี แต่ muted `#9aa39e` และตัวหนังสือ 12–13px ใน chip/label ควรตรวจ contrast หลังเปลี่ยน token; ตรวจ focus ring (CI: border-glow)

หมายเหตุ: ภาพ CTA (`cta-hardware.png`) แสดงปกติ (ที่รายงานว่าโหลดไม่เสร็จเป็นแค่ lazy-load) — ไม่ใช่บั๊ก

## 2.1 ผลตรวจหน้าอื่น (รอบที่ 2, desktop เท่านั้น)

| หน้า | สถานะ | ข้อสังเกต |
|---|---|---|
| `/price` (รายการ) | พอใช้ | ธีมถูก แต่เป็นลิสต์ข้อความล้วน ไม่มีการ์ด/รูป/ฟิลเตอร์ chip แบบ CI; badge "ข้อมูลยังไม่เพียงพอ" ซ้ำทุกแถว |
| `/deal-checker` | พอใช้ | โครง 3 ขั้นตอน + ฟอร์มอ่านง่าย; หัวข้อ/คำอธิบายจางตอนโหลด (reveal ช้า); ไม่มี Inspection chip/verified ตาม CI |
| `/methodology` | ดี | อ่านง่าย เหมาะเป็นหน้าเนื้อหา; ควรเพิ่มสารบัญ/ไอคอนตาม CI |
| `/compare` | พอใช้ | การ์ดว่าง ("ข้อมูลตลาดยังไม่เพียงพอ") 4 ใบเหมือนกัน — ยังไม่ให้ค่ากับผู้ใช้ |
| `/seller-price-advisor` | พอใช้ | ฟอร์มตรงธีม; ปุ่มไลม์เต็มความกว้างใหญ่เกิน |
| `/seller`, `/local-match`, `/market-report` | พอใช้ | ใช้ธีมแล้ว; กล่อง prototype สีแดงชนกับ palette CI; `/local-match` ปุ่ม disabled อ่านยาก; ตาราง `/market-report` หัวตารางพื้นขาวหลุดธีม |
| `/shop` | ต้องแก้ | ตารางหัวพื้นขาว/แท็บสว่าง หลุดธีมมืด; ใช้ข้อความ `in_stock` ดิบ |
| **`/build`** | **พัง (P1)** | ไอคอน Material Symbols แสดงเป็นข้อความ (`memory`, `developer_board`, `chevron_right`, `filter_list`) เพราะไม่ได้โหลดฟอนต์ไอคอน → แถบ component แคบ ข้อความไหลเป็นแนวตั้ง; ปุ่ม "verified" และแถบ component พื้นขาว/ฟ้าหลุดธีม; หน้ายาว 7,800px. ใช้ในเทมเพลต `modules/build.php` (6), `guide.php`, `local-match.php`, `shop-dashboard.php` |
| `/login` (admin) | ต้องแก้ | Bootstrap เปล่า พื้นขาว ปุ่มฟ้า ไม่มีโลโก้/ธีมแบรนด์ |

หน้า `/admin/*` **ยังไม่ได้ตรวจ** — ต้อง login (ไม่ป้อนรหัสผ่านให้) จึงเทียบได้เฉพาะจากไฟล์ `design/admin_*` กับ view ในโค้ด; ถ้าต้องการให้ตรวจ ให้ login ค้างไว้ใน browser pane ให้ก่อน
`/guide/{slug}`, `/compare/{slug}`, `/deal-checker/result/{uuid}` ยังไม่ได้เปิด (ต้องมี slug/uuid)

### ปัญหาเพิ่มจากรอบนี้ → เข้าแผน
- **P1** แก้ `/build`: โหลด Material Symbols แบบ self-host หรือเปลี่ยนเป็น Font Awesome ที่มีอยู่แล้ว (แนะนำ FA เพื่อไม่ให้มีไอคอนสองชุด) แล้วเขียน style ของ `.component-nav`, `.draft-pill`, filter select ใหม่ตาม token CI → เพิ่มใน Phase C
- **P2** หน้า `/shop`, `/market-report`, `.table` ทั้งหมด: สร้างสไตล์ตารางมืดกลาง (หัว `--ci-highlight`, แถว hover `--ci-elevated`, ตัวอักษร `#f1f5f9`) → Phase C
- **P2** หน้า `/login`: ทำเป็นการ์ดมืด + โลโก้ modinpect + ปุ่มไลม์ (ไม่แตะ logic) → Phase C
- **P3** กล่องเตือน "prototype/mock" ใช้โทน danger สีแดงเต็มกล่อง → ใช้ tertiary gold + ไอคอน ลดความตกใจ

## 2.2 Admin (ตรวจจากโค้ด + design mock — ยังไม่ได้ login ดูหน้าจริง)

หลักฐาน: `layouts/admin_header.php`, `public/assets/admin/css/admin.css` (1,102 บรรทัด), `design/admin_*/screen.png`

- **ธีมคนละระบบกับ CI**: admin เป็นธีมสว่าง Bootstrap 4 (template "Concept") — พื้น `#f4f6f9`, การ์ดขาว, sidebar `#101828`, active/primary น้ำเงิน `#1f6feb`/`#2563eb`, navbar `bg-white`
- **Design mock ของ admin (`design/admin_*`) ก็เป็นชุดเก่า "Price Radar"** — ธีมสว่าง, indigo, IBM Plex Sans (Thai) + mono ไม่ใช่ Cybernetic Trust/modinpect. สรุป: admin ตรงกับ mock ของตัวเอง แต่ **ไม่ตรง CI ปัจจุบัน** และแบรนด์ยังเป็น "ModInspect Admin" ไม่มีโลโก้ modinpect
- **ไม่มีไฟล์ CSS ร่วม**: admin ไม่ใช้ `mi-theme.css` จึงแยก token กัน 2 ชุด (public เขียวดำ / admin น้ำเงินขาว) — ต้นเหตุที่หน้า `/login` ดูเป็นคนละเว็บ
- **เมนู/ภาษา**: label ภาษาอังกฤษล้วน (Dashboard, Review Queue…) ขัดกับ public ที่ Thai-first; ไอคอน Font Awesome 5 (สอดคล้องกับ public)
- **Mobile**: sidebar ยุบเป็น navbar-toggler ได้ แต่ topbar `fixed-top` + `mi-topbar-context` ยังไม่ได้ทดสอบความกว้างแคบ

ข้อเสนอ (ต้องให้เจ้าของเลือกก่อนแตะ admin):
- **ตัวเลือก 1 (แนะนำ)** — admin คงธีมสว่างเพื่ออ่านตารางหนาแน่น แต่รับ **แบรนด์ CI**: โลโก้ modinpect, sidebar `#0a0d14`, active/primary เป็นไลม์ `#bbf246` (ตัวอักษร `#0a0d14`), สถานะใช้ `#22c55e / #facc15 / #ef4444`, ฟอนต์ Plus Jakarta Sans + Prompt, radius 8px — ใช้ token ชุดเดียวกับ public (แยกไฟล์ `ci-tokens.css` ให้ทั้งสองฝั่งโหลด)
- **ตัวเลือก 2** — admin เป็นธีมมืดเต็มตาม CI (ต้องปรับ DataTables, form, modal, chart ทั้งหมด งานมาก ความเสี่ยงถดถอยสูง)
- ไม่ว่าแบบไหน: ห้ามแตะ logic/สิทธิ์ (RBAC) — แก้เฉพาะ CSS + layout header/login; แปล label เมนูเป็นไทยเฉพาะเมื่อเจ้าของอนุมัติ

เพิ่ม **Phase G — Admin** ต่อท้ายแผน (หลัง Phase A–E): tokens ร่วม → login/brand/sidebar → ตาราง/ฟอร์ม/badge → ตรวจหน้า `/admin/*` จริงเมื่อ login แล้ว (dashboard, products, review-queue, sources, imports, audit)

## 3. ประเด็นที่ต้องตัดสินใจก่อนแก้ (Product vs Reference)

Reference เป็น **marketplace ซื้อ-ขาย** (เมนู Buy/Sell/Inspect, ราคา ฿29,900, ★ rating, ผู้ขาย, ตะกร้า, 100,000+ users)
แต่ ModInspect ปัจจุบันเป็น **ตัวเช็กราคา/เปรียบเทียบ POC** และ `AGENTS.md` ห้ามแสดงราคา/ข้อมูลที่ไม่มีหลักฐาน ("ไม่สร้างราคาตลาดปลอม")

ข้อเสนอ (แนะนำ): **ยึด Reference เฉพาะภาษาภาพ (token/component/layout)** แต่ **ไม่เพิ่ม** ตะกร้า, rating, ราคาผู้ขายจำลอง, ตัวเลขผู้ใช้ปลอม จนกว่าจะมีข้อมูลจริง — ใช้ "Verified / ยังไม่มีราคาตลาด / ช่วงราคา+ความมั่นใจ" แทน

## 4. แผนแก้ไข (เรียงลำดับ, แต่ละเฟสแยก commit/ตรวจได้)

**Phase A — Design tokens (ความเสี่ยงต่ำ, ผลมากที่สุด)** · `mi-theme.css` :root
- map token CI → CSS vars: `--ci-canvas #0a0d14`, `--ci-surface #111622`, `--ci-elevated #171f2e`, `--ci-highlight #1f2b3e`, `--ci-border #222d3d`, `--ci-border-active #354761`, `--ci-primary #bbf246`, `--ci-on-primary #0a0d14`, `--ci-verified #22c55e`, `--ci-gold #facc15`, `--ci-danger #ef4444`, text `#f1f5f9`/`#94a3b8`
- ชี้ `--mb-*` / `--mi-lime` ไปที่ token ใหม่ (alias) เพื่อไม่ให้ component เดิมพัง; ลบ gradient hard-code (`#1a211e`, `#121715`, ฯลฯ) → `--ci-surface`
- radius scale: 8 / 999 / 16 เท่านั้น

**Phase B — Typography**
- self-host Plus Jakarta Sans (400/600/700/800) + Prompt (ไทย) ใน `public/assets/fonts/` พร้อม `font-display: swap`; ตั้ง stack `Plus Jakarta Sans, Prompt, Noto Sans Thai, system-ui`
- ใช้ scale ตาม CI (display 48/56, headline 32, title 18, body 14–16, label 10–12 + tracking); ตัวเลขราคา weight 700–800, เลิกใช้ IBM Plex Mono ใน hero/metrics (หรือขอ Lead อนุมัติคงไว้เฉพาะตาราง)

**Phase C — Components**
- Button: primary lime + glow hover; secondary elevated + border → lime on hover; ghost
- Card: flat `--ci-surface` + 1px border, hover `translateY(-2px)` + border active + glow
- Chip: inactive `#171f2e/#94a3b8`, active solid lime; condition chip emerald ตาม CI §2
- Search: bg `#131722`, ring `#354761`, focus `border-glow`; (ถ้า PM อนุมัติ) segmented "ค้นหารุ่น / ตรวจรายงาน"
- Focus-visible ring ทุกองค์ประกอบ

**Phase D — Content/asset**
- รูปการ์ดสินค้า/หมวดหมู่: ชุดเดียวกัน (render ตัดพื้น, พื้นโปร่ง + radial glow) แทนไอคอน "+" และรูปถ่ายพื้นขาว; CPU/Storage ต้องมีภาพระดับเดียวกับ GPU/RAM
- empty state ของการ์ด/หน้า Price: ใช้โครง skeleton (price band, confidence, source) + ข้อความเดียวต่อหน้า ไม่ซ้ำทุกการ์ด
- Footer ตาม Reference: โซเชียล, ช่วยเหลือ/FAQ, นโยบาย, ติดต่อ (ใช้ข้อมูลจริงที่เจ้าของให้เท่านั้น — ห้ามใช้เบอร์ placeholder `02-xxx-xxxx`)
- Hero: เพิ่ม halo + สไลซ์เฉียงตามภาพ, chip ยอดนิยมเพิ่มเป็น 5–6

**Phase E — Motion / Mobile / A11y**
- reveal: เริ่มแสดงเมื่ออยู่ใน viewport ภายใน ≤ 400ms, fallback `<noscript>` และ `prefers-reduced-motion`, rootMargin ล่วงหน้า, ห้าม hero ซ่อนตอนโหลด
- tap target ≥ 44px (chip, footer link, nav icon), nav mobile ตรวจ focus/escape
- ตรวจ contrast WCAG AA ทุก token ใหม่ (muted/label 12px)
- เพิ่ม `lang`, aria-label ไอคอนล้วน (ตรวจซ้ำ)

**Phase F — QA (M3)**
- เทียบ screenshot 3 breakpoint (1440 / 768 / 375) กับ `screen.png` ของ CI ทั้งหน้า Home, Price, Deal Checker
- Lighthouse a11y/perf, ตรวจ CLS จากฟอนต์/รูป, regression หน้า admin (ห้ามแตะ `app.css` ส่วน admin)

## 5. ข้อจำกัด / ความเสี่ยง
- `app.css` (74 KB) + `mi-theme.css` ซ้อนกัน และ Bootstrap 4 + FontAwesome 5 — เปลี่ยน token ต้องทำผ่าน alias ก่อน แล้วค่อยลบของเก่า
- เฟสแรกควรแตะเฉพาะ `mi-theme.css` + ไฟล์ฟอนต์ ไม่แตะ PHP view จนถึงเฟส D
- ยังไม่ได้ตรวจหน้า Deal Checker, Admin (มี design ใน `design/admin_*`) และ `request_inspection_service` ของ CI — ควรเป็นรอบถัดไป
- ประเมินจาก browser pane ที่ 1440px และ 375px เท่านั้น ยังไม่ได้รัน Lighthouse
