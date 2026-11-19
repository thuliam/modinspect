<?php /** @var ?string $error  @var bool $done  @var array $old */
use App\Services\Listing\SellerCheck as SC; ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">เช็กผู้ขาย · สำหรับสมาชิก</span>
        <h1>แจ้งปัญหาผู้ขาย</h1>
        <p class="lead">ระบบเก็บเบอร์หรือเลขบัญชีในรูปรหัสที่ย้อนกลับไม่ได้ ไม่เก็บชื่อผู้ขาย ทีมงานตรวจก่อนแสดงผล</p>
    </div>
    <?php if ($done): ?><div class="notice mb-3">ส่งรายงานแล้ว ทีมงานจะตรวจก่อนแสดงผลให้ผู้อื่นเห็น</div><?php endif; ?>
    <?php if ($error): ?><div class="alert mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="card" method="post" action="<?= $base ?>/seller-check/report" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
        <div class="row">
            <div class="col-md-4 mb-3"><label for="rp-type">ข้อมูลที่ใช้โอนเงิน</label><select id="rp-type" name="type"><?php foreach (SC::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= ($old['type'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div class="col-md-8 mb-3"><label for="rp-q">เบอร์โทรหรือเลขบัญชี (ตัวเลขเท่านั้น)</label><input id="rp-q" name="q" inputmode="numeric" autocomplete="off" maxlength="30" required></div>
            <div class="col-md-6 mb-3"><label for="rp-problem">ประเภทปัญหา</label><select id="rp-problem" name="problem" required><option value="">เลือก</option><?php foreach (SC::PROBLEMS as $k => $l): ?><option value="<?= $k ?>"<?= ($old['problem'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div class="col-12 mb-3"><label for="rp-note">เล่าเหตุการณ์สั้น ๆ (ไม่บังคับ ห้ามใส่เบอร์ ลิงก์ หรือไอดีติดต่อ)</label><textarea id="rp-note" name="note" maxlength="500" rows="3"><?= htmlspecialchars((string) ($old['note'] ?? '')) ?></textarea></div>
            <div class="col-12 mb-3"><label for="rp-ev">หลักฐาน เช่น สลิปโอน (รูปได้สูงสุด 5 รูป เฉพาะทีมงานเห็น)</label><input id="rp-ev" type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple></div>
        </div>
        <button type="submit">ส่งรายงาน</button>
    </form>
    <p class="mt-3"><a href="<?= $base ?>/seller-check">กลับไปเช็กเบอร์หรือบัญชี</a></p>
</section>
