# MASTER PROMPT — ปรับ UI ModInspect ให้ตรง CI (Cybernetic Trust) ≥ 90%

> วิธีใช้: เปิด session ใหม่ที่โฟลเดอร์ `N:\modinspect` แล้ววางทั้งไฟล์นี้ (หรือสั่ง "อ่าน docs/MASTER_PROMPT_UI_CI_ALIGNMENT.md แล้วทำ Phase X")
> แต่ละ session ทำ **1 Phase เท่านั้น** แล้วหยุดรอตรวจ ห้ามข้าม Phase

```
/anthropic-skills:devteam-plus
Role: UX/UI (ออกแบบ/กำหนด spec) → Dev (แก้ CSS/view) → QA (ตรวจเทียบ CI)
Profile: PHP 8.1 custom MVC (ไม่มี framework) + Bootstrap 4.x + jQuery 3.3.1 + Font Awesome 5 — ธีมสาธารณะอยู่ใต้ `body.mi`
```

---

## 0. บริบทที่ต้องรู้ก่อนแตะไฟล์ (อ่านจริง ห้ามเดา)

**ผลิตภัณฑ์:** ModInspect/modinpect = ตัวเช็กราคา+เช็กสภาพอุปกรณ์ IT มือสอง (Thai-first) ยังเป็น POC ข้อมูลจำกัด **ไม่ใช่ marketplace ซื้อขายจริง**
**กฎเหล็กจาก `AGENTS.md` (ละเมิดไม่ได้):**
- ห้ามสร้าง/แสดงราคา, rating, จำนวนผู้ใช้, รีวิว, ชื่อผู้ขาย, เบอร์โทร ที่ไม่มีในฐานข้อมูลจริง — ถ้าไม่มีข้อมูล ให้แสดง empty state ที่ซื่อสัตย์ ("ข้อมูลยังไม่เพียงพอ")
- ห้ามเปิด API/บริการที่เสียเงิน, ห้ามเรียก network ภายนอก (CDN/Google Fonts) — ไซต์รันใน LAN และ `site.js` ระบุ "no network calls" → **ฟอนต์/ไอคอนต้อง self-host**
- ห้ามแตะ logic ฝั่ง PHP (controller/service/PriceEngine/สิทธิ์ RBAC/CSRF/DB) — งานนี้คือ **CSS + markup ของ view เท่านั้น**
- ห้ามแก้ `database/`, `app/Services/`, `app/Controllers/` (เว้นแต่ส่งตัวแปรให้ view แล้วจำเป็นจริง → ต้องหยุดถามก่อน)
- งานเสร็จต้องอัปเดต `docs/PROJECT_STATUS.md` ก่อนรายงาน PASS

**สิ่งที่ "ตรงแล้ว" — ห้ามรื้อทิ้ง:** โครงหน้า Home (hero + ภาพ `assets/brand/hero-hardware.png`, การ์ดหมวดหมู่ 6 ใบ, ทำไมต้อง modinpect 4 การ์ด, รุ่นแนะนำ, How it works 4 ขั้น, metrics, CTA), โลโก้ `assets/brand/modinpect-lockup-dark.png`, เมนูหลัก, reveal/count-up ใน `site.js` (มีโหมด `motion-full`/`motion-calm` อยู่แล้ว → แก้แค่ค่า delay ถ้าจำเป็น อย่าเขียนใหม่)

---

## 1. แหล่งความจริง (Source of Truth) — เรียงลำดับความสำคัญ

| ลำดับ | ไฟล์ | ใช้เพื่อ |
|---|---|---|
| 1 | `design/ci/cybernetic_trust/DESIGN.md` | **token, typography, component rule** (ใช้ค่าจากหัวข้อ prose "Colors/Typography/Components" เป็นหลัก ส่วน YAML frontmatter เป็นชุด Material auto-gen ห้ามใช้ทับ prose) |
| 2 | `design/ci/reference-home.webp` | ภาพ Reference หน้า Home (เป้าหมายเชิงภาพ: layout, ความหนาแน่น, glow) |
| 3 | `design/ci/homepage_modinspect/{code.html,screen.png}` | โค้ด/ภาพ Stitch ของ Home |
| 4 | `design/ci/asus_rog_strix_g15_modinspect/` | โครงหน้ารายละเอียดสินค้า → อ้างอิงหน้า `/price/{slug}` |
| 5 | `design/ci/request_inspection_service_modinspect/` | โครงหน้าฟอร์ม/บริการ → อ้างอิง Deal Checker, Seller Advisor |
| 6 | `docs/UX_UI_AUDIT_CI_ALIGNMENT.md` | ผลตรวจและรายการ gap (ตรวจ 2026-11-15) |

ข้อควรระวัง: `code.html` ของ Stitch ใช้ Tailwind CDN + Google Fonts — **ใช้เป็นแค่ภาพอ้างอิงโครง/ค่าสี ห้ามคัดลอก `<script src=cdn.tailwindcss.com>` หรือ `<link fonts.googleapis>` เข้าโปรเจกต์**
ถ้าค่าใน `code.html` ขัดกับ DESIGN.md ให้ยึด DESIGN.md และแจ้งความขัดแย้งในรายงาน
ถ้าอ่านไฟล์ไม่ได้/ไม่พบ → หยุดและถาม ห้ามเดาค่า

---

## 2. Design Tokens ที่ต้องใช้ (ค่าตายตัว — ห้ามประดิษฐ์สี/ขนาดใหม่)

ประกาศเป็น CSS variables ชื่อ `--ci-*` ใน `:root` ของไฟล์ใหม่ `public/assets/ci-tokens.css` (โหลดก่อน `mi-theme.css`)

```css
:root{
  /* canvas & surface */
  --ci-canvas:#0a0d14; --ci-surface:#111622; --ci-elevated:#171f2e; --ci-highlight:#1f2b3e;
  --ci-border:#222d3d; --ci-border-active:#354761; --ci-border-glow:rgba(187,242,70,.4);
  /* brand */
  --ci-primary:#bbf246; --ci-on-primary:#0a0d14; --ci-primary-glow:rgba(187,242,70,.25);
  --ci-verified:#22c55e; --ci-gold:#facc15; --ci-danger:#ef4444;
  /* text */
  --ci-text:#f1f5f9; --ci-text-2:#94a3b8; --ci-text-3:#64748b;
  /* shape */
  --ci-r-md:8px; --ci-r-lg:16px; --ci-r-pill:9999px;
  /* spacing 8pt */
  --ci-gutter:1.25rem; --ci-margin:2rem; --ci-gutter-m:.75rem; --ci-margin-m:1rem;
  /* effects */
  --ci-bloom:0 0 24px -4px rgba(187,242,70,.22); --ci-btn-glow:0 0 16px rgba(187,242,70,.4);
  /* type */
  --ci-font:"Plus Jakarta Sans","Prompt","Noto Sans Thai","Leelawadee UI",Tahoma,system-ui,sans-serif;
}
```

**ตารางแปลงจากค่าเดิม (ใช้ alias ใน Phase A — อย่า find/replace ทั่วไฟล์):**

| เดิมใน `mi-theme.css` / `app.css` | เปลี่ยนเป็น |
|---|---|
| `--mb-bg #0d1110` | `var(--ci-canvas)` |
| `--mb-surface #151a18`, `#1a211e`, `#121715`, `#171c1a`, `#141917` | `var(--ci-surface)` (card), `var(--ci-elevated)` (hover/dropdown) |
| `--mb-raised #1c2320` | `var(--ci-elevated)` |
| `--mb-line rgba(255,255,255,.11)` | `var(--ci-border)` |
| `--mb-accent`, `--mi-lime #d4f139` | `var(--ci-primary)` (#bbf246) |
| `--mb-on-accent #382f32` | `var(--ci-on-primary)` |
| `--mb-ink #fff`, `--mb-soft #d3d9d5` | `var(--ci-text)` |
| `--mb-muted #9aa39e` | `var(--ci-text-2)` |
| radius 10/12/14/18/20/22/24px | เหลือ 8px (input/card/button), 16px (hero/CTA/banner), 9999px (chip/badge) |

**Typography (จาก DESIGN.md):** display 48/56 w800 (mobile 32/40) · headline-lg 32/40 w700 (mobile 24/32) · headline-md 22/28 w700 · title 18/24 w600 · body 16/24 และ 14/20 (ไทย line-height ≥ 1.5) · label 12/16 w600 · label-sm 10/14 w700 uppercase tracking .025–.05em เฉพาะ badge/pill ภาษาอังกฤษ
ตัวเลขราคา/คะแนน/metrics: w700–800, `font-variant-numeric: tabular-nums` (เลิกใช้ IBM Plex Mono ใน hero/metrics)

**Component rule (ย่อจาก DESIGN.md — อ่านฉบับเต็มก่อนทำ):**
- Primary button: พื้น `--ci-primary`, ตัวอักษร `--ci-on-primary` w700, radius 8, hover = `--ci-btn-glow`
- Secondary: พื้น `--ci-elevated`, border 1px `#273142`, hover border → `--ci-primary`
- Ghost: โปร่ง, `--ci-text-2` → hover `--ci-primary` + เส้นใต้ไลม์
- Card: พื้น `--ci-surface` **สีเรียบ ไม่ใช้ gradient**, border 1px `--ci-border`, radius 8; hover: `translateY(-2px)` + border `--ci-border-active` + `--ci-bloom`
- Filter chip: inactive `--ci-elevated`/`--ci-text-2`; active พื้นไลม์ตัวอักษรเข้ม; radius pill
- Condition/verified chip: พื้น `rgba(34,197,94,.15)`, border 1px `#22c55e`, ตัวอักษรเขียว, pill
- Search: พื้น `#131722`, ring 1px `--ci-border-active`, focus ring `--ci-border-glow`
- สีเขียว `#22c55e` ใช้กับ "ผ่านตรวจ/verified" เท่านั้น, ทอง `#facc15` ใช้กับ rating/คำเตือน, แดงใช้กับ error/สต็อกหมด
- หัวข้อ section มีสไลซ์ไลม์เฉียง (`.mi-slash` มีอยู่แล้ว — คงไว้)

---

## 3. กฎการทำงาน (กัน "มั่ว")

1. **Gate G1:** ก่อนแก้ ให้เขียนแผนไฟล์ที่จะแตะ + จำนวนบรรทัดโดยประมาณ + คะแนนความมั่นใจ (ต้อง ≥ 95% ตามสกิล ไม่ถึงให้ถาม)
2. **อ่านก่อนแก้:** `Read` ไฟล์เป้าหมายจริง (`mi-theme.css`, view ที่เกี่ยวข้อง) — ห้ามแก้จากความจำ; หา selector ด้วย Grep แล้วแก้ทีละจุดด้วย Edit
3. **ขอบเขตไฟล์ที่แก้ได้:** `public/assets/ci-tokens.css` (ใหม่), `public/assets/mi-theme.css`, `public/assets/fonts/*` (ใหม่), `app/Views/**` (markup/class เท่านั้น), `app/Views/layouts/header.php` (เพิ่ม `<link>`)
   **ห้ามแก้:** `public/assets/admin/**`, `app.css` ส่วน admin/ที่ไม่ใช่ `.mi`/`.mb` ของหน้า public (ถ้าจำเป็นให้ override ใน `mi-theme.css` ภายใต้ `body.mi`), `vendor/*`
4. **Scope ทุก selector ด้วย `body.mi`** เพื่อไม่ให้กระทบ admin/login
5. **Bootstrap เวอร์ชัน 4** (`.input-group-prepend`, `data-toggle`, `.ml-auto`, `.sr-only`) — ห้ามใช้คลาส/แอตทริบิวต์ของ Bootstrap 5 (`data-bs-*`, `.ms-*`, `.visually-hidden`)
6. **ไอคอนใช้ Font Awesome 5 ที่มีอยู่** (`fas fa-*`) — ห้ามเพิ่ม Material Symbols/ชุดไอคอนใหม่ (แก้ `/build` โดยเปลี่ยน `material-symbols-outlined` เป็น FA — ต้องทำ mapping ตารางชื่อเก่า→FA ใน PR นั้น)
7. **ฟอนต์:** self-host เท่านั้น. ไฟล์ woff2 ต้องดาวน์โหลดจากแหล่งทางการ (Google Fonts/fontsource, ใบอนุญาต OFL ฟรี) → **ต้องขออนุญาตผู้ใช้ก่อนดาวน์โหลด** ระบุชื่อไฟล์/แหล่ง/ขนาด; ถ้าไม่ได้รับอนุญาต ให้ตั้ง stack fallback และเว้น `@font-face` ไว้พร้อม TODO ที่รายงาน
8. **ห้าม refactor นอกงาน**, ห้ามลบ rule เดิมที่ไม่แน่ใจว่าใช้ที่ไหน — ใช้ Grep ทั้งโปรเจกต์ยืนยันก่อนลบ; ลบเฉพาะที่ถูก override ครบแล้ว
9. **ห้ามใส่ข้อมูลปลอมเพื่อให้เหมือน Reference** (ราคา ฿29,900, ★4.9, "100,000+ ผู้ใช้", ชื่อผู้ขาย, เบอร์ `02-xxx-xxxx`) — ที่ว่างให้ใช้ข้อมูลจริงจาก DB หรือ empty state
10. **หนึ่ง Phase = หนึ่ง commit** (ถ้าผู้ใช้สั่ง commit): ข้อความ `ui(ci): phase X — …` ห้าม push เอง
11. รายงานทุกครั้งตามรูปแบบ: ไฟล์ที่แก้ / สิ่งที่เปลี่ยน / สิ่งที่ **ไม่ได้ทำ** / ผลตรวจ / ความเสี่ยง

---

## 4. แผนงานเป็น Phase (ทำทีละ session)

### Phase A — Tokens (ความเสี่ยงต่ำสุด, เห็นผลมากสุด)
งาน: สร้าง `ci-tokens.css`; เพิ่ม `<link>` ใน `layouts/header.php` ก่อน `mi-theme.css` (ใช้ `filemtime` cache-bust เหมือนเดิม); ใน `mi-theme.css` เปลี่ยนค่า `--mb-*`/`--mi-lime` ให้ชี้ `var(--ci-*)` (alias) แล้วแทน hard-code ตามตาราง §2 ทีละ block; ตั้ง radius ตามสเกล
เสร็จเมื่อ: ไม่มี hex ของพาเลตต์เก่า (`#0d1110 #151a18 #1c2320 #d4f139 #382f32 #1a211e #121715`) เหลือใน `mi-theme.css` (`grep` = 0 บรรทัด); หน้าเว็บไม่แตก (เทียบก่อน/หลัง)
ห้าม: แตะฟอนต์, แตะ PHP view

### Phase B — Typography
งาน: self-host Plus Jakarta Sans (400/600/700/800) + Prompt (400/500/600 สำหรับไทย) → `public/assets/fonts/` + `@font-face` (`font-display:swap`, `unicode-range` แยกไทย/ละติน) ใน `ci-tokens.css`; ตั้ง `--ci-font` ที่ `body.mi`; ใช้สเกลตัวอักษรตาม §2; ตัวเลขใช้ `tabular-nums`
เสร็จเมื่อ: `document.fonts` โหลดได้จริง (ไม่ 404 — ตรวจ Network), h1 desktop 48px/mobile 32px, ราคา/metrics w700+, ไม่มี CLS ชัดเจนตอนโหลดฟอนต์ (ใช้ `size-adjust` หรือ fallback ใกล้เคียง)

### Phase C — Components
ทำทีละกลุ่ม ตรวจทุกกลุ่มก่อนไปต่อ: (1) buttons (2) cards + hover (3) chips/badges/condition chip (4) search + input + select + focus ring (5) ตารางมืดกลาง `body.mi .table` (หัว `--ci-highlight`, แถว hover `--ci-elevated`) ใช้กับ `/shop`, `/market-report`, `/shop/inventory` (6) กล่องแจ้งเตือน "prototype/mock" เปลี่ยนจากแดงเต็มกล่องเป็นโทน gold + ไอคอน (7) หน้า `/login` เป็นการ์ดมืด+โลโก้ (แก้ `layouts/auth_header.php` + CSS เท่านั้น ห้ามแตะ form action/CSRF/field name) (8) แก้ `/build` ให้ไม่พัง (ไอคอนเป็นข้อความ, แถบ component, ปุ่ม verified พื้นขาว)
เสร็จเมื่อ: ทุก state ครบ (default/hover/focus-visible/active/disabled/loading/empty/error) ต่อ component

### Phase D — Content & asset
งาน: การ์ดรุ่นแนะนำและหมวดหมู่ใช้ภาพชุดเดียวกัน (พื้นโปร่ง/ตัดพื้น + radial glow) — **ถ้าไม่มีรูปจริง ให้ใช้ placeholder แบบเดียวกันทุกใบที่ดูตั้งใจ (ไอคอนหมวด+glow) ไม่ใช่เครื่องหมาย "+"**; รูปพื้นขาวต้องครอบด้วยกรอบพื้นมืด (`object-fit:contain` + padding) แทนปล่อยชนธีม; empty state หน้า `/price`, `/compare` ไม่ซ้ำข้อความทุกการ์ด (รวมเป็นแถบเดียวหรือย่อ); footer เพิ่มคอลัมน์ช่วยเหลือ/นโยบายตาม Reference **เฉพาะลิงก์ที่มีหน้าจริง**, ช่องติดต่อ/โซเชียลใส่ก็ต่อเมื่อเจ้าของให้ข้อมูลจริง; hero: halo/สไลซ์เฉียงเบาๆ, chip ยอดนิยมใช้รุ่นจริงจาก DB
ห้าม: ตะกร้า, rating, ราคาจำลอง, ตัวเลขผู้ใช้

### Phase E — Motion / Mobile / A11y
งาน: reveal เริ่มภายใน ≤ 400ms (ตอนนี้ delay สะสมถึง ~560ms+ และหัว hero บน mobile ยังจางเกิน 2 วินาทีที่ทดสอบ) — แก้ค่า `--d`/transition ใน CSS, **ห้าม** ซ่อน hero ก่อนโหลด; คง `motion-calm`; tap target ≥ 44px (chip, footer link, nav icon); contrast WCAG AA ทุก token (ตรวจ `--ci-text-2` บน `--ci-surface` และตัวอักษร 12px); `:focus-visible` ring ไลม์; `aria-label` ปุ่มไอคอนล้วน; ไม่มี horizontal scroll ที่ 375/768/1440

### Phase F — QA เทียบ CI (เกณฑ์ผ่าน 90%)
ดู §5

### Phase G — Admin (ทำภายหลังเมื่อผู้ใช้สั่ง; **ห้ามเริ่มใน session ของ Phase A–F**)
ต้องให้ผู้ใช้เลือกแบบ (สว่าง+แบรนด์ CI หรือมืดเต็ม) และ login ค้างใน browser pane ก่อน; ห้ามกรอกรหัสผ่านแทนผู้ใช้

---

## 5. เกณฑ์ตรวจรับ "ตรง CI ≥ 90%" (วัดได้ — ทุกข้อต้องมีหลักฐาน)

ตรวจที่ 1440 / 768 / 375px กับหน้า: `/`, `/price`, `/price/nvidia-geforce-rtx-3070`, `/deal-checker`, `/compare`, `/methodology`, `/build`, `/seller-price-advisor`, `/market-report`, `/shop`, `/login`

**ตรวจเชิงตัวเลข (JS ใน browser, ผลต้องแนบในรายงาน):**
```js
const cs=getComputedStyle(document.body);
({bg:cs.backgroundColor, font:cs.fontFamily,
  h1:getComputedStyle(document.querySelector('h1')).fontSize,
  primaryBtn:getComputedStyle(document.querySelector('.btn-lime,.btn-primary,button[type=submit]')).backgroundColor,
  fontsLoaded:[...document.fonts].filter(f=>f.status==='loaded').map(f=>f.family),
  overflowX:document.documentElement.scrollWidth>innerWidth,
  smallTargets:[...document.querySelectorAll('a,button')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&(r.height<44||r.width<44)}).length})
```
คาดหวัง: bg `rgb(10, 13, 20)`; ปุ่มหลัก `rgb(187, 242, 70)`; ฟอนต์ Plus Jakarta Sans โหลดแล้ว; h1 48px (desktop); overflowX = false

**ตรวจเชิงภาพ (checklist 20 ข้อ — ผ่านข้อละ 5%):**
1–4 พื้นหลัง/การ์ด/เส้นขอบ/สีไลม์ ตรง token · 5–6 ฟอนต์+สเกลตัวอักษรตรง · 7 ปุ่ม primary/secondary/ghost ตรง · 8 chip active/inactive ตรง · 9 การ์ด hover ยก 2px+glow · 10 search bar ring/focus · 11 radius ใช้เฉพาะ 8/16/pill · 12 เงา/glow เฉพาะ bloom ตามกำหนด ไม่มี gradient การ์ด · 13 ไอคอนเป็นชุด FA เดียว ไม่มีข้อความไอคอนหลุด · 14 ตารางมืด ไม่มีพื้นขาวหลุด · 15 empty state ไม่ซ้ำ ไม่ว่างจอ · 16 รูปสินค้าไม่ชนธีม · 17 mobile ไม่ overflow, tap ≥ 44px · 18 contrast AA · 19 reveal ≤ 400ms, ไม่ซ่อนเนื้อหาหลัก · 20 ไม่มีข้อมูลปลอม/ไม่ละเมิด AGENTS.md
คะแนน = จำนวนข้อที่ผ่าน × 5% → ต้อง ≥ 90% (ผ่าน ≥ 18/20) และ **ข้อ 20 ต้องผ่านเสมอ**

**Regression:** `php -l` ทุกไฟล์ PHP ที่แก้; `php tests/phase0_pipeline_test.php`; เปิดหน้า `/admin` (redirect login) และ `/login` ยังส่งฟอร์มได้ (ไม่ต้องล็อกอินจริง แค่ยืนยัน field/CSRF ยังอยู่ใน DOM); `public/assets/admin/**` ไม่มี diff

---

## 6. สิ่งที่ห้ามทำ (สรุป)

- ห้ามคัดลอก Tailwind/Google Fonts CDN จากไฟล์ Stitch
- ห้ามใส่ข้อมูลตัวอย่างจาก Reference (฿, ★, ผู้ขาย, ผู้ใช้ 100,000+, เบอร์โทร)
- ห้ามแตะ admin, RBAC, CSRF, PHP logic, DB, `vendor/`
- ห้ามเปลี่ยนไลบรารี (Bootstrap 4/jQuery/FA5) หรือเพิ่ม Tailwind
- ห้ามรื้อโครง/ข้อความหน้าที่ตรงอยู่แล้ว เพื่อ "ทำใหม่ให้สวย"
- ห้ามรายงานว่า "เสร็จ/ตรง 90%" โดยไม่มีผลตรวจ §5 แนบ
- ถ้าไม่แน่ใจ: หยุดและถาม (ความมั่นใจ < 95%)

## 7. รูปแบบรายงานท้าย session

```
Profile/Gate/Role: …
Phase: X
ไฟล์ที่แก้: path — สรุปสั้น
ไม่ได้ทำ (และเหตุผล): …
ผลตรวจ §5: ตัวเลข JS + checklist ข้อที่ผ่าน/ไม่ผ่าน (+ ภาพก่อน/หลัง ที่ 1440/375)
ความเสี่ยง/ข้อสงสัยที่ต้องให้ผู้ใช้ตัดสินใจ: …
Next: Phase ถัดไป
```
