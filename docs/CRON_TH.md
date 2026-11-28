# ตัวอย่าง cron ทั้งหมดในไฟล์เดียว (พี่ติดตั้งบน host เอง ผมไม่ได้รันอะไรบน host)

วันที่ 2026-11-20 · แทน `/path/to/modinspect` ด้วยโฟลเดอร์จริง และ `php` ด้วย path ของ PHP บน host ทุกคำสั่งไม่พิมพ์ข้อมูลส่วนตัว (นับเลขอย่างเดียว)
ลองรันมือทีละคำสั่งก่อนตั้ง cron และอ่านผลจนมั่นใจ **คำสั่งที่ลบข้อมูล (retention) ให้รัน dry-run ก่อนเสมอ** (ไม่ใส่ `--apply`)

| เมื่อไร | คำสั่ง | ทำอะไร |
|---|---|---|
| ทุกวัน 02:30 | `php /path/to/modinspect/cli/db_backup.php` | สำรองฐานข้อมูลไปที่ `storage/backups` (ไฟล์มีข้อมูลส่วนตัว เก็บให้เป็นความลับ และต้องมีสำเนานอกเครื่องด้วย) |
| ทุกวัน 02:45 | `php /path/to/modinspect/cli/db_backup_prune.php --keep=14` | เก็บ 14 ไฟล์ล่าสุด |
| ทุกวัน 03:15 | `php /path/to/modinspect/cli/daily_pipeline.php` | รายงานแผนของ snapshot และความครอบคลุม เขียนบรรทัดตัวเลขลง `storage/logs/daily_pipeline.log` (ไม่ใส่ `--apply` จนกว่าจะพร้อม) |
| ทุกวัน 03:15 (เมื่อมีโฟลเดอร์ชุดข้อมูลใหม่) | `php /path/to/modinspect/cli/daily_pipeline.php /path/to/batch --apply --actor=<id แอดมิน>` | ตรวจและนำเข้าชุดข้อมูล แถวเข้าคิวตรวจ ไม่มีการอนุมัติอัตโนมัติ |
| สัปดาห์ละครั้ง | `php /path/to/modinspect/cli/retention_purge.php --deal-checks-days=90 --builds-days=180 --ratelimit-hours=24` | ลบผลเช็กดีล/ชุดประกอบเก่าและไฟล์ rate limit (dry-run ก่อน) |
| สัปดาห์ละครั้ง (เมื่อเปิดโมดูล) | `php /path/to/modinspect/cli/retention_purge.php --community-days=90 --forum-days=90 --seller-pending-days=90` | ลบตามระยะเก็บที่พี่เลือก (ดู `docs/MODULE_SWITCHES_TH.md`) |
| ทุก 5 นาที | `php /path/to/modinspect/cli/ops_probe.php --base=https://โดเมนของพี่` | ตรวจว่าเว็บตอบถูกต้อง (exit 1 ถ้าผิด ใช้ร่วมกับตัวแจ้งเตือนของ host) |

เพิ่ม `--apply` ในคำสั่ง retention เฉพาะเมื่อ dry-run แสดงตัวเลขที่ยอมรับได้ และสำรองข้อมูลแล้ว ขั้นต่ำของทุกช่วงเวลาคือ 7 วัน

ตรวจ host ก่อนเปิดใช้งานจริง: `php /path/to/modinspect/cli/env_preflight.php` (ไม่ควรมี FAIL) และ `php /path/to/modinspect/cli/smoke_check.php`


## งานประจำวันคำสั่งเดียว (LS-89)

ต้องรัน migration 038 ก่อนจึงจะมีประวัติงานในหน้าสุขภาพระบบ (คำสั่งรันได้แม้ยังไม่มีตาราง แต่ไม่บันทึกประวัติ)

| เมื่อไร | คำสั่ง | ทำอะไร |
|---|---|---|
| ทุกวัน 02:30 | `php /path/to/modinspect/cli/daily_jobs.php` | สำรองฐาน (เก็บ 14 ไฟล์) แล้ว**รายงานอย่างเดียว**ว่าราคาและกราฟจะเปลี่ยนอะไร และตรวจระบบ |
| เมื่อพร้อมให้อัปเดตเอง | `php /path/to/modinspect/cli/daily_jobs.php --apply --actor=<id แอดมิน>` | สำรองฐานสำเร็จก่อนจึงเขียนราคาและกราฟ ถ้าสำรองไม่สำเร็จจะไม่เขียนอะไร |

ถ้าใช้คำสั่งนี้ ไม่ต้องตั้ง `db_backup.php` และ `db_backup_prune.php` ในตารางด้านบนซ้ำ การนำเข้าชุดข้อมูลยังใช้ `daily_pipeline.php`

## งานสำรองฐานบนเครื่อง Studio (LS-90)

Windows Task Scheduler ชื่อ `ModInspect daily DB backup` ทุกวัน 12:00 (ถ้าเครื่องปิดอยู่ จะรันเมื่อเปิดและล็อกอิน) รัน `php cli/db_backup.php` จากโฟลเดอร์หลัก และเก็บผลลง `storage/logs/backup.log` (เมื่อโฟลเดอร์หลักอัปเดตตรง `main` แล้ว เปลี่ยนเป็น `cli/backup_job.php` ได้ ซึ่งบันทึกเวลาด้วย)
อ่านฐานอย่างเดียว เขียนไฟล์ใหม่ใน `storage/backups/` ไม่ลบไฟล์เก่า (ราว 10 MB ต่อวัน ลบเองเป็นครั้งคราว หรือใช้ `cli/db_backup_prune.php`)
ปิดงาน: `Unregister-ScheduledTask -TaskName "ModInspect daily DB backup"`

เมื่อเปิดตลาดประกาศ (LS-90): ตั้ง `php /path/to/modinspect/cli/notify_process.php --apply` ทุกชั่วโมง เขียนการแจ้งเตือนลง `storage/logs/notifications.log` เท่านั้น
