# เก็บประกาศมือสองประจำวัน (แหล่งข้อมูลที่สอง)

ใช้กับประกาศจาก Facebook fan page / Marketplace, Pantip, Kaidee ประมาณ 10 แถวขึ้นไปต่อวัน ไม่เสียเงิน ไม่ใช้ตัวเก็บอัตโนมัติ

## ทำไมต้องทำแบบนี้
แถวจากเทมเพลตเก่า (`second_source_minimal.csv`) **ไม่ถูกนับเข้าราคา** ให้ใช้ `examples/import/owner_listing_urls.csv` เท่านั้น

## 0) ทางลัด (ไม่บังคับ): ให้ระบบจัดไฟล์ให้
ถ้าคุณจดแค่ ลิงก์, หัวข้อ, ราคา, วันที่ (หัวคอลัมน์ `link,title,price,date` หรือชื่อในเทมเพลตก็ได้ บันทึกจาก Excel แบบ CSV UTF-8 ได้) ให้รัน
```bash
php cli/prepare_owner_rows.php --in=storage/import/raw_today.csv --out=storage/import/second_source.csv --default-date=2026-11-17
```
ระบบจะตัด tracking ออกจากลิงก์ ดึงรหัสประกาศจากลิงก์ ทำราคาเป็นตัวเลข และเติม `capture_method` ให้ แถวที่ใช้ไม่ได้จะถูกรายงานเป็นเลขแถวกับรหัส (ไม่พิมพ์หัวข้อ ราคา หรือลิงก์) คอลัมน์อื่น เช่น ชื่อผู้ขาย จะไม่ถูกส่งต่อ `--default-date` ใช้เติมเฉพาะแถวที่ไม่มีวันที่ ไม่ทับวันที่ที่คุณกรอก ไม่ได้เขียนฐานข้อมูล แล้วข้ามไปขั้นตอน 3

## 1) คัดลอกเทมเพลต
คัดลอก `examples/import/owner_listing_urls.csv` ไปไว้ที่ `storage/import/` (โฟลเดอร์นี้ไม่ถูกส่งขึ้น git) ตั้งชื่อเช่น `second_source.csv` ห้ามนำไฟล์จริงไปไว้ที่อื่น

## 2) กรอกหนึ่งประกาศต่อหนึ่งแถว
| คอลัมน์ | ใส่อะไร |
|---|---|
| capture_method | พิมพ์ `owner_transcription` ทุกแถว |
| source_listing_url | ลิงก์ของ **ประกาศ** (Marketplace item, โพสต์ของแฟนเพจ, กระทู้ Pantip, สินค้า Kaidee) ห้ามใช้ลิงก์โปรไฟล์ส่วนตัว (`profile.php`, `/people/`) |
| source_item_id | รหัสสั้นๆ จาก URL เช่นตัวเลขหลัง `/marketplace/item/` (ต้องมี) |
| listing_title | หัวข้อตามที่โพสต์ |
| asking_price | ราคาเสนอขาย เป็นตัวเลข บาท |
| observed_at | วันที่เห็นประกาศ `YYYY-MM-DD HH:MM:SS` |
| condition_hint | ไม่บังคับ: `used`, `like new`, `new` |

**ห้ามใส่** ชื่อผู้ขาย เบอร์โทร ที่อยู่ ข้อความแชท หรือรูปโปรไฟล์

## 3) ลอง dry run ก่อน (ยังไม่เขียนข้อมูล)
```bash
php cli/import_market_evidence.php --file=storage/import/second_source.csv --dataset=real --dry-run
```
ดู `valid_rows`, `invalid_rows`, `row_errors` รหัสที่พบบ่อย: `OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED` (โดเมนไม่อยู่ในรายการ), `OWNER_TRANSCRIPTION_REQUIRES_ITEM_ID` (ลืมรหัส), `OWNER_TRANSCRIPTION_PERSONAL_PROFILE_LINK` (เป็นลิงก์โปรไฟล์)

## 4) นำเข้า แล้วตรวจเอง
```bash
php cli/import_market_evidence.php --file=storage/import/second_source.csv --dataset=real
```
จากนั้นเข้าหน้า Review Queue ตรวจแต่ละแถวและอนุมัติเอง ระบบไม่อนุมัติแถวจริงอัตโนมัติ

## ข้อจำกัดที่ต้องรู้
- ระบบบันทึกว่า "คุณบันทึกอะไร เมื่อไร" ไม่ได้พิสูจน์ว่าหน้าประกาศเขียนอย่างนั้นจริง และประกาศ Facebook อาจหายภายหลัง
- ราคาที่มาจากข้อมูลที่คนพิมพ์ทั้งหมด (ข้อความที่คุณรวบรวม + แถวชุดนี้) จะไม่ถูกติดป้ายความมั่นใจ "สูง" (เพดาน 79 มีเหตุผลแสดงในหน้า snapshot)
- แถว Priceza เป็นราคาอ้างอิงของใหม่ ไม่เข้าราคามือสอง
- สินค้าที่ต้องการแถวเพิ่มมากที่สุดดูได้จาก `php cli/pending_gap_report.php`
- ระบบปฏิเสธแถวที่ **หัวข้อมีเบอร์โทร** (รหัส `OWNER_TRANSCRIPTION_TITLE_HAS_PHONE_NUMBER`) ให้ลบเบอร์ออกแล้วลองใหม่ การตรวจนี้จับได้เฉพาะรูปแบบเบอร์ ไม่จับชื่อหรือที่อยู่ที่พิมพ์ในหัวข้อ
