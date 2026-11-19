<?php /** @var array $listing  @var list<array> $photos — shown only when the owner turned public listings on (HG-16) */ ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">ประกาศจากผู้ขาย · ผ่านการตรวจของทีมงาน</span>
        <h1><?= htmlspecialchars((string) $listing['full_name']) ?></h1>
        <p class="lead">ราคาที่ผู้ขายตั้ง ฿<?= number_format((float) $listing['asking_price']) ?> · <a href="<?= $base ?>/price/<?= rawurlencode((string) $listing['slug']) ?>">ดูช่วงราคาตลาดของรุ่นนี้</a></p>
    </div>
    <div class="row mb-3"><?php foreach ($photos as $ph): ?><div class="col-6 col-md-3 mb-3"><img src="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $listing['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" alt="รูปสินค้า" width="<?= (int) $ph['width'] ?>" height="<?= (int) $ph['height'] ?>" loading="lazy" class="img-fluid"></div><?php endforeach; ?></div>
    <div class="card mb-3"><h2>รายละเอียดจากผู้ขาย</h2><dl class="mb-0"><?php foreach (\App\Services\Listing\ListingAttributes::describe($listing) as $d): ?><div><dt><?= htmlspecialchars($d['label']) ?></dt><dd><?= htmlspecialchars($d['value']) ?></dd></div><?php endforeach; ?></dl></div>
    <p class="mi-trend-note">ข้อมูลนี้ผู้ขายกรอกเอง ทีมงานตรวจรูปแบบประกาศ ไม่ได้ตรวจสภาพสินค้าจริง <a href="<?= $base ?>/condition-guide">ดูคู่มือเช็กสภาพก่อนซื้อ</a></p>
</section>
