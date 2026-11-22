# ใบสั่งงานวัน deploy (หน้าเดียว) ร่างจากการซ้อมในเครื่อง 2026-11-21

สถานะ: ยังไม่เคย deploy ไปโฮสต์ ผมซ้อมเฉพาะส่วนที่ทำในเครื่องได้ รายละเอียดเต็มอยู่ใน `docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md` ห้าม deploy จนกว่าพี่สั่งเป็นลายลักษณ์อักษร

## ผลการซ้อมในเครื่อง
- แพ็กเกจจาก `git archive` ของ `app/ config/ public/` ไม่รวม `products-mfr/`: 368 ไฟล์ ประมาณ 5.3 MB
- ไม่มีไฟล์ `.env`, `.sql`, `.log`, `.bak`, `.zip`, `.map`; ไม่พบ key หรือ private key ในไฟล์ php/js/json
- ตรวจ syntax PHP ทุกไฟล์ในแพ็กเกจ ผ่านไม่มี error
- `php cli/secret_scan.php`: ผ่าน (767 ไฟล์)
- ที่ซ้อมเองไม่ได้: การอัปโหลด FTPS จริง, รากของบัญชี FTP, phpMyAdmin import, ใบรับรอง HTTPS (ต้องมีโฮสต์จริง)

## ลำดับในวัน deploy
| # | ใคร | ทำอะไร | ย้อนกลับ |
|---|---|---|---|
| 1 | พี่ | ยืนยันว่า checklist ข้อ 10 ใน runbook ติ๊กครบ (G-5, G-7, สิทธิ์ภาพ, `.env` บนเซิร์ฟเวอร์) | หยุดได้ทุกเมื่อ |
| 2 | ผม | รัน `php cli/predeploy_report.php` ต้องได้ PASS หรือ PASS_WITH_WARNINGS แล้วส่งคำเตือนให้พี่อ่าน | ไม่แก้อะไร |
| 3 | พี่ | สำรองฐานข้อมูลใน DirectAdmin หรือ phpMyAdmin แล้วเก็บไฟล์ไว้นอกเซิร์ฟเวอร์ | - |
| 4 | พี่ | สร้างโฟลเดอร์ `storage/cache/ratelimit`, `storage/cache/oauth`, `storage/uploads/listings`, `storage/uploads/reports`, `storage/logs`, `storage/import` นอก document root และให้เขียนได้ | ลบโฟลเดอร์ใหม่ |
| 5 | พี่ | Import migration 022–029 ตามลำดับเลขใน phpMyAdmin หลังสำรองแล้ว (ทุกไฟล์เป็นแบบเพิ่ม ไม่ลบข้อมูล) | ไม่ต้อง restore ปกติ เพราะโค้ดเดิมใช้กับ schema ใหม่ได้ (runbook ข้อ 9) |
| 6 | พี่ | รัน workflow `ModInspect FTPS Deploy (manual)` ด้วย mode = plan อ่านรายการที่จะอัปโหลด | ไม่เขียนอะไรลงโฮสต์ |
| 7 | พี่ | รันอีกครั้ง mode = deploy, confirm = DEPLOY | รันซ้ำด้วย ref เป็น tag ก่อนหน้า |
| 8 | ผม/พี่ | `php cli/smoke_check.php https://modinspect.thuliamdev.com` ต้อง exit 0 แล้วเปิดหน้าแรก ราคา สินค้า Deal Checker ในเบราว์เซอร์ทั้งจอใหญ่และมือถือ | ย้อนด้วยข้อ 7 |
| 9 | พี่ | ตรวจว่า `/.env` และ `/app/bootstrap.php` ตอบ 403 หรือ 404 | แก้ document root ทันที |

## ต้องเป็นปิดไว้ในวันแรก (ใน `.env` บนเซิร์ฟเวอร์)
`SELLER_CHECK_ENABLED=false`, `COMMUNITY_REPORTS_ENABLED=false`, `COMMUNITY_FORUM_ENABLED=false`, `SELLER_LISTINGS_PUBLIC=false`, ช่อง OAuth ว่างทั้งหมด, `GEMINI_PROVIDER_ENABLED=false`, `MODINSPECT_LIVE_COLLECTION_ENABLED=false`

## สิ่งที่ผมพบตอนซ้อมและแก้ไว้ใน runbook
- รายการ migration ในเอกสารหยุดที่ 026 แต่จริงมีถึง 028
- ตัวแปร `COMMUNITY_FORUM_ENABLED` ไม่อยู่ในบล็อก `.env` ของ runbook
- ยังไม่มีขั้นตอน `predeploy_report` (เพิ่มใน checklist แล้ว)
