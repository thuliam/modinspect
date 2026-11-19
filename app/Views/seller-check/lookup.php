<?php /** @var string $type  @var ?array $result  @var ?string $error  @var bool $searched */
use App\Services\Listing\SellerCheck as SC; ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">เช็กผู้ขาย</span>
        <h1>เช็กเบอร์หรือบัญชีผู้ขาย</h1>
        <p class="lead">ดูว่ามีรายงานที่ทีมงานตรวจแล้วเกี่ยวกับเบอร์โทรหรือเลขบัญชีนี้หรือไม่ ก่อนโอนเงิน</p>
    </div>
    <form class="mi-filter-card" action="<?= $base ?>/seller-check" method="get">
        <div class="mi-filter-row">
            <select name="type" aria-label="ชนิดข้อมูล"><?php foreach (SC::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $type === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            <input name="q" inputmode="numeric" autocomplete="off" maxlength="30" placeholder="กรอกเฉพาะตัวเลข" aria-label="เบอร์หรือเลขบัญชี" required>
            <button type="submit">ค้นหา</button>
        </div>
    </form>
    <?php if ($error): ?><div class="alert mt-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($result): ?>
        <div class="card mt-3" role="status">
            <?php if ($result['count'] === 0): ?>
                <h2>ยังไม่พบรายงานที่ตรวจแล้ว</h2>
                <p>นี่ไม่ได้แปลว่าปลอดภัย ยังไม่พบรายงาน อาจเพราะยังไม่เคยมีใครแจ้ง ควรตรวจสภาพสินค้าและเลือกวิธีชำระเงินที่ปลอดภัยเสมอ <a href="<?= $base ?>/condition-guide">ดูคู่มือเช็กสภาพ</a></p>
            <?php else: ?>
                <h2>พบรายงานที่ตรวจแล้ว <?= (int) $result['count'] ?> รายการ</h2>
                <ul><?php foreach ($result['by_type'] as $p => $n): ?><li><?= htmlspecialchars(SC::PROBLEMS[$p] ?? $p) ?> · <?= (int) $n ?> รายการ</li><?php endforeach; ?></ul>
            <?php endif; ?>
            <p class="mi-trend-note"><?= htmlspecialchars(SC::NOTE) ?> <a href="<?= $base ?>/seller-check/dispute">โต้แย้งรายงาน</a></p>
        </div>
    <?php endif; ?>
    <p class="mt-4"><a class="button ghost" href="<?= $base ?>/seller-check/report">แจ้งปัญหาผู้ขาย (สำหรับสมาชิก)</a></p>
</section>
