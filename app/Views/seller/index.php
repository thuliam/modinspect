<?php /** @var list<array> $listings  @var ?string $notice */
$statusText = ['draft' => 'ฉบับร่าง', 'submitted' => 'รอทีมงานตรวจ', 'approved' => 'ผ่านการตรวจ', 'rejected' => 'ไม่ผ่านการตรวจ', 'withdrawn' => 'ถอนแล้ว']; ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">ผู้ขาย</span>
        <h1>ประกาศขายของฉัน</h1>
        <p class="lead">ลงรายละเอียดของที่จะขาย ดูช่วงราคาตลาดของรุ่นนั้น แล้วส่งให้ทีมงานตรวจ ข้อมูลติดต่อของคุณไม่ถูกเก็บในประกาศ</p>
    </div>
    <?php if ($notice): ?><div class="notice mb-3"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <div class="mi-actions mb-4"><a class="button" href="<?= $base ?>/seller/listings/new">ลงประกาศใหม่</a><?php if (!empty($marketOpen)): ?><a class="button ghost" href="<?= $base ?>/seller/profile">โปรไฟล์ผู้ขาย</a><a class="button ghost" href="<?= $base ?>/listings">ดูประกาศทั้งหมด</a><?php endif; ?><?php if (!empty($config['seller_advisor']['enabled'])): ?><a class="button ghost" href="<?= $base ?>/seller-price-advisor">ดูราคาแนะนำก่อนตั้งราคา</a><?php endif; ?></div>
    <?php if (!$listings): ?>
        <div class="mi-empty-state"><h2>ยังไม่มีประกาศ</h2><p>เริ่มจากเลือกรุ่นและใส่ราคาที่ตั้งใจขาย</p></div>
    <?php else: ?>
        <div class="card">
            <ul class="list-unstyled mb-0">
                <?php foreach ($listings as $l): ?>
                    <li class="mb-3"><a href="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $l['uuid']) ?>"><b><?= htmlspecialchars((string) $l['full_name']) ?></b></a>
                        · ฿<?= number_format((float) $l['asking_price']) ?> · <?= $statusText[$l['status']] ?? htmlspecialchars((string) $l['status']) ?> · รูป <?= (int) $l['photo_count'] ?>/10
                        <?php if (!empty($marketOpen) && $l['status'] === 'approved'): ?> · <?= ['available' => 'พร้อมขาย', 'reserved' => 'จองแล้ว', 'sold' => 'ขายแล้ว'][$l['sale_state'] ?? 'available'] ?? '' ?><?php if (\App\Services\Market\ListingLifecycle::expired($l)): ?> · <span class="badge badge-secondary">หมดอายุ</span><?php endif; ?><?php endif; ?>
                        <?php if ($l['status'] === 'rejected' && $l['moderation_note']): ?><br><small>เหตุผล: <?= htmlspecialchars((string) $l['moderation_note']) ?></small><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
