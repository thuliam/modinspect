<?php /** @var ?string $error  @var bool $done  @var array $old */
use App\Services\Listing\SellerCheck as SC; ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">เช็กผู้ขาย</span>
        <h1>โต้แย้งรายงาน</h1>
        <p class="lead">ถ้าคุณคือเจ้าของเบอร์หรือบัญชีที่ถูกรายงานและเห็นว่าไม่ถูกต้อง แจ้งเราได้ รายงานของเบอร์หรือบัญชีนั้นจะไม่ถูกนับระหว่างที่ทีมงานพิจารณา</p>
    </div>
    <?php if ($done): ?><div class="notice mb-3">รับคำโต้แย้งแล้ว ทีมงานจะพิจารณาและติดต่อกลับทางอีเมลที่ให้ไว้</div><?php endif; ?>
    <?php if ($error): ?><div class="alert mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="card" method="post" action="<?= $base ?>/seller-check/dispute">
        <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
        <div class="row">
            <div class="col-md-4 mb-3"><label for="ds-type">ชนิดข้อมูล</label><select id="ds-type" name="type"><?php foreach (SC::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= ($old['type'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div class="col-md-8 mb-3"><label for="ds-q">เบอร์หรือเลขบัญชีของคุณ (ตัวเลขเท่านั้น)</label><input id="ds-q" name="q" inputmode="numeric" autocomplete="off" maxlength="30" required></div>
            <div class="col-12 mb-3"><label for="ds-ex">เหตุผลที่เห็นว่าไม่ถูกต้อง</label><textarea id="ds-ex" name="explanation" rows="4" minlength="10" maxlength="1000" required><?= htmlspecialchars((string) ($old['explanation'] ?? '')) ?></textarea></div>
            <div class="col-md-6 mb-3"><label for="ds-mail">อีเมลสำหรับติดต่อกลับ (ทีมงานเท่านั้นที่เห็น)</label><input id="ds-mail" type="email" name="email" maxlength="190" required value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>"></div>
        </div>
        <button type="submit">ส่งคำโต้แย้ง</button>
    </form>
    <p class="mt-3"><a href="<?= $base ?>/seller-check">กลับไปเช็กเบอร์หรือบัญชี</a></p>
</section>
