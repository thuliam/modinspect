# Checklist วันขึ้นระบบจริง (ภาษาไทย ฉบับสั้น)

ทำตามลำดับ ติ๊กทีละข้อ ถ้าข้อไหนไม่ผ่านให้หยุดแล้วดู "ถอยกลับ" ท้ายไฟล์
รายละเอียดเต็มอยู่ใน `docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md` (เลขหัวข้อในวงเล็บอ้างถึงไฟล์นั้น)
ไฟล์นี้ไม่มีรหัสผ่านหรือค่าลับใดๆ และต้องไม่ใส่เพิ่ม

## ก่อนวันขึ้น (ทำได้ล่วงหน้า)

- [ ] **เลือกโฟลเดอร์ปลายทาง:** document root ชี้ไปที่ `.../modinspect/public` (5, 5b)
- [ ] **เปลี่ยนรหัสที่เคยหลุด:** รหัสใน LINE export (G-5)
- [ ] **ตัดสินเรื่องลิขสิทธิ์:** Font Awesome (G-7), รูปสินค้า, โลโก้แบรนด์ (7)
- [ ] **CI ของ PR ล่าสุดต้องเขียวทั้งหมด** รวมงาน "Fresh install rehearsal" ซึ่งพิสูจน์ว่า schema + migration ทุกตัวสร้างฐานข้อมูลเปล่าบน MariaDB 10.6 ได้
- [ ] **merge PR เข้า `main`:** พี่สั่งเท่านั้น

## ตั้งค่าบน host (พี่ทำเอง หรือสั่งให้ทำทีละขั้น)

- [ ] **สร้าง `.env` บน server** (3) ตั้งชื่อตัวแปรตามนี้ ส่วนค่าพี่ใส่เองบน server
  - **พื้นฐาน:** `APP_ENV=production`, `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (user ฐานข้อมูลห้ามเป็น root)
  - **ตั้งเป็นปิดไว้ก่อน:** `APP_PILOT` (true ถ้ายังเป็นช่วงทดลองปิด), `MODINSPECT_LIVE_COLLECTION_ENABLED=false`, `MODINSPECT_PAID_PROVIDER_CALLS_ENABLED=false`, `GEMINI_PROVIDER_ENABLED=false`
  - **ปล่อยว่างจนกว่าจะพร้อม:** `MEMBER_GOOGLE_*`, `MEMBER_MICROSOFT_*`, `MEMBER_FACEBOOK_*` (HG-13)
  - **seller:** `SELLER_CHECK_ENABLED=false` (HG-14), `SELLER_LISTINGS_PUBLIC=false` (HG-16)
  - **รายงานจากชุมชน (R-11):** `COMMUNITY_REPORTS_ENABLED=false` และ `COMMUNITY_REPORTS_HMAC_KEY` ว่างไว้ก่อน จะเปิดได้เมื่อพี่อนุมัติข้อความความเป็นส่วนตัว ตั้งคนตรวจรายงานประจำ และสร้างคีย์ยาว 32 ตัวอักษรขึ้นไป (`openssl rand -hex 32`) ดู ADR-006
- [ ] **HTTPS ใช้ได้ทุกหน้า** ถ้า host ถอด TLS ก่อนถึง PHP ต้องตั้งให้ PHP เห็น `HTTPS=on` ไม่อย่างนั้น cookie จะไม่มี `Secure` (ดู `SECURITY_REVIEW_2026-11-19.md`)
- [ ] **สร้างโฟลเดอร์ `storage/`** ตาม 5b และตั้งให้เขียนได้
- [ ] **PHP บน host ต้องมี GD แบบรองรับ FreeType** (ดูใน phpinfo ที่หัวข้อ gd ต้องเขียนว่า FreeType Support: enabled) ใช้วาดการ์ดภาพผลเช็กดีล ถ้าไม่มี ระบบจะซ่อนปุ่ม "บันทึกเป็นภาพ" เอง ไม่เกิด error

## ฐานข้อมูล (4)

- [ ] **Backup ก่อนทุกครั้ง** และเก็บสำเนาไว้นอก server
- [ ] **ถ้าเป็นฐานใหม่:** ห้ามไล่ migration ทั้งไฟล์ทับ `schema.sql` (จะชนกันเพราะ `schema.sql` มีโครงสร้างครบแล้ว) ให้ทำตามลำดับใน runbook ข้อ 4.3 ซึ่งตรงกับที่ `cli/rehearse_fresh_install.php --production` ทดสอบไว้
  - import `schema.sql`
  - จาก `seed.sql` เอาเฉพาะแคตตาล็อก (หมวด ยี่ห้อ รุ่น ชื่อเรียก) ห้ามเอาราคาทดสอบ
  - คำสั่งข้อมูล (INSERT/UPDATE) ของ migration ทุกตัวตามลำดับ
- [ ] **ถ้าเป็นฐานเดิม:** ลง migration 022, 023, 024, 025, 026 ตามลำดับ แล้วจดว่าลงตัวไหนไปแล้ว
- [ ] **สร้างผู้ดูแลคนแรก:** ใช้ `cli/create_admin_user.php` ให้พี่พิมพ์รหัสเอง ห้ามวางในแชต

## ขึ้นระบบ (5, 5b)

- [ ] อัปโหลด package จาก workflow ที่เขียว และตรวจ checksum
- [ ] ชี้ document root ไปที่ release ใหม่ ต้องเก็บ release เก่าไว้ด้วย

## ตรวจหลังขึ้น (ต้องผ่านทุกข้อ)

- [ ] `php cli/smoke_check.php https://<โดเมน> --links` ต้องได้ `SMOKE PASS` แล้วแปะผลไว้ใน release note
  - หน้าหลักตอบ 200
  - `/admin` ส่งไปหน้า login
  - `.env` และ `.git` เปิดไม่ได้
  - ลิงก์ภายในทุกลิงก์ใช้ได้
- [ ] เปิดหน้าแรก, ดัชนีราคา, หน้าสินค้า, Deal Checker และประกอบคอม ทั้งบนคอมและมือถือ console ต้องไม่มี error
- [ ] รุ่นที่ยังไม่มีราคาที่ตรวจแล้ว ต้องขึ้นว่า "ข้อมูลยังไม่เพียงพอ" ห้ามมีราคาโผล่มาเอง

## ถอยกลับ (9)

1. ชี้ document root กลับไปที่ release เก่า ใช้เวลาไม่กี่วินาที
2. ถ้าลง migration ไปแล้ว ให้กู้จาก backup ได้เฉพาะเมื่อพี่อนุมัติ เพราะข้อมูลที่เขียนหลัง backup จะหายไป
3. รัน smoke check อีกรอบ แล้วจดเหตุการณ์ไว้ใน `docs/DECISION_LOG.md`
