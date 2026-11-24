# แผน PWA (frontend + backend)

สถานะ: **แผนเท่านั้น ยังไม่ได้เขียนโค้ด** ต้องให้พี่อนุมัติตามจุดที่ระบุ (ติดกฎ "ห้ามเขียน JS เองเมื่อมีไลบรารี" และกฎค่าใช้จ่าย 0 บาท)

## 1. ของที่มีแล้ว (ตรวจจากโค้ด)
- `/manifest.webmanifest` (SeoController::manifest, SeoService::manifestJson): ชื่อ, start_url, scope, standalone, ไอคอน 192/512, สีตาม CI
- ทุกหน้าลิงก์ manifest และมี `theme-color` (header.php) — ทดสอบใน `tests/ls20_speed_pwa_test.php`
- ยังไม่มี service worker, ไม่มีหน้า offline, ไม่มี push, ไม่มีไอคอน maskable

## 2. เป้าหมาย (เรียงตามคุณค่า)
1. ติดตั้งลงหน้าจอหลักได้จริง (installable) บนมือถือ/เดสก์ท็อป
2. เปิดเร็วและใช้ได้เมื่อเน็ตหลุด: หน้าที่เคยเปิด + หน้า offline ที่อ่านง่าย
3. แจ้งเตือนราคา (ต่อยอด N-1 ใน LS24) แบบไม่เสียเงิน
4. ไม่ทำให้ราคาที่เห็นเก่าโดยไม่รู้ตัว (ข้อมูลราคาต้องบอกเวลาที่ดึงเสมอ)

## 3. Frontend
| งาน | วิธี | หมายเหตุ |
|---|---|---|
| F1 ไอคอน maskable 512 + apple-touch-icon + screenshots ใน manifest | ไฟล์ภาพ + แก้ SeoService::manifestJson | ไม่มี JS ใหม่; ต้องมีไฟล์ภาพจากพี่/ทำจากโลโก้เดิม |
| F2 Service worker | **ใช้ Workbox (ไลบรารี) self-host** สร้างไฟล์ `public/sw.js` จาก config สั้นๆ | ต้องขออนุมัติดาวน์โหลด Workbox (ชื่อ, แหล่ง npm/Google, ขนาด ~ไม่กี่สิบ KB) ตามกฎ UI; ห้ามเขียนเองยาว |
| F3 กลยุทธ์แคช | assets (css/js/ภาพ): stale-while-revalidate; หน้า HTML: network-first fallback หน้า offline; **ไม่แคช** `/admin`, `/account`, `/api`, ฟอร์ม POST | ป้องกันข้อมูลส่วนตัวค้างในเครื่อง |
| F4 หน้า `/offline` | view PHP + CSS เดิม | แสดง "ไม่มีอินเทอร์เน็ต" + รายการหน้าที่เคยเปิด |
| F5 ปุ่ม/แบนเนอร์ติดตั้ง | ใช้ Bootstrap component + glue ไม่กี่บรรทัด (`beforeinstallprompt`) | ต้องขออนุมัติ glue นี้ |
| F6 แจ้งเมื่อมีเวอร์ชันใหม่ | Workbox `workbox-window` | ตัวเลือก |
| F7 ป้ายเวลา "ราคา ณ วันที่…" บนหน้าที่ออฟไลน์ | PHP view | ใช้ข้อมูล snapshot เดิม |

## 4. Backend
| งาน | วิธี | หมายเหตุ |
|---|---|---|
| B1 `GET /sw.js` ต้องส่งจาก root scope ด้วย `Service-Worker-Allowed` และ `Cache-Control: no-cache` | route ใน `public/index.php` หรือไฟล์ static ที่ root | ตอนนี้ root .htaccess เขียนทุกอย่างเข้า `public/` จึงวาง `public/sw.js` ได้ scope = `/` |
| B2 เวอร์ชันแคช | ใช้ hash ของไฟล์/ค่า build ใน sw | deploy แล้วเครื่องผู้ใช้ต้องอัปเดตเอง |
| B3 Header ความปลอดภัย | HTTPS บังคับ (host มีแล้ว), CSP ต้องอนุญาต `worker-src 'self'` | ตรวจ CSP ปัจจุบัน |
| B4 Push (ภายหลัง) | Web Push + VAPID, ไลบรารี PHP `minishlab/web-push-php` หรือเทียบเท่า (**ต้องขออนุมัติ**) | ไม่มีค่าใช้จ่าย (ใช้บริการ push ของเบราว์เซอร์); ต้องมีตาราง `push_subscriptions` (endpoint, keys, member_id ว่างได้, created_at) ด้วย migration แบบ additive |
| B5 งานส่งแจ้งเตือน | CLI `cli/push_price_alerts.php` รันด้วย cron ของ host | ส่งเมื่อราคา median ของสินค้าที่ติดตามเปลี่ยนเกินเกณฑ์; ไม่ส่งข้อมูลส่วนตัว; ผูกกับระบบสมาชิก (ปิดอยู่รอ HG-13) |
| B6 API สมัคร/ยกเลิก push | `POST /api/push/subscribe`, `/unsubscribe` + CSRF + rate limit | ข้อมูลเก็บน้อยที่สุด ไม่มี PII |
| B7 ทดสอบ | `tests/pwa_test.php`: manifest ครบ, sw.js ส่ง header ถูก, หน้า private ไม่อยู่ใน precache, สมัคร/ยกเลิก push | รันใน CI |

## 5. ลำดับทำ (แนะนำ)
1. **P1 Installable + offline เบื้องต้น**: F1, F2, F3, F4, B1, B2, B3, B7 (ต้องอนุมัติ Workbox)
2. **P2 ปุ่มติดตั้ง + แจ้งอัปเดต**: F5, F6
3. **P3 Push แจ้งเตือนราคา**: B4–B6 (ต้องรอระบบสมาชิกเปิด และอนุมัติไลบรารี)

## 6. สิ่งที่ต้องให้พี่ตัดสิน
1. อนุมัติดาวน์โหลด Workbox (ฟรี, MIT) เพื่อทำ service worker หรือไม่ (ถ้าไม่ จะเขียน sw เองสั้นๆ ซึ่งขัดกฎ UI)
2. ไฟล์ไอคอน maskable/ภาพหน้าจอ: ให้ทำจากโลโก้ปัจจุบันหรือพี่มีไฟล์ให้
3. Push: เปิดหลังระบบสมาชิก (HG-13) ใช่หรือไม่

## 7. ความเสี่ยง
- แคชหน้าราคาเก่า: ใช้ network-first สำหรับ HTML และแสดงเวลา snapshot
- Service worker ที่ผิดทำให้ผู้ใช้ค้างเวอร์ชันเก่า: ต้องมีเส้นทาง kill-switch (ส่ง sw ว่างที่ unregister ตัวเอง)
- iOS รองรับ push เฉพาะเมื่อติดตั้งลงหน้าจอหลักก่อน
