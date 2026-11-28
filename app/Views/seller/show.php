<?php /** @var array $listing  @var list<array> $photos — shown only when the owner turned public listings on (HG-16) */ ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">ประกาศจากผู้ขาย · ผ่านการตรวจของทีมงาน</span>
        <h1><?= htmlspecialchars((string) $listing['full_name']) ?></h1>
        <p class="lead">ราคาที่ผู้ขายตั้ง ฿<?= number_format((float) $listing['asking_price']) ?> · <a href="<?= $base ?>/price/<?= rawurlencode((string) $listing['slug']) ?>">ดูช่วงราคาตลาดของรุ่นนี้</a></p>
    </div>
    <?php if (!empty($notice)): ?><div class="alert alert-info" role="status"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if (!empty($market)): ?><p>
        <?php if ($market['seller_name']): ?>ผู้ขาย: <b><?= htmlspecialchars((string) $market['seller_name']) ?></b> <?php endif; ?>
        <?php if ($market['verification'] === 'verified'): ?><span class="badge badge-success">ผู้ดูแลยืนยันผู้ขายแล้ว</span> <?php endif; ?>
        <?php if ($market['sale_state'] === 'reserved'): ?><span class="badge badge-warning">มีคนจองแล้ว</span> <?php endif; ?>
        <?php if ((int) $market['quantity'] > 1): ?>มี <?= (int) $market['quantity'] ?> ชิ้น<?php endif; ?>
    </p><?php endif; ?>
    <?php if (!empty($market) && !empty($member)): $token = htmlspecialchars(\App\Core\Csrf::token()); ?>
        <form class="mb-3" method="post" action="<?= $base ?>/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/save"><input type="hidden" name="_token" value="<?= $token ?>"><button class="button ghost" type="submit"><?= !empty($saved) ? 'เอาออกจากรายการที่บันทึก' : 'บันทึกประกาศนี้' ?></button></form>
        <?php if ((int) $member['id'] !== (int) $listing['member_id']): ?>
        <details class="mb-3"><summary>รายงานประกาศนี้</summary>
            <form class="card mt-2" method="post" action="<?= $base ?>/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/report"><input type="hidden" name="_token" value="<?= $token ?>">
                <div class="form-group"><label for="rp-reason">เหตุผล</label><select id="rp-reason" class="form-control" name="reason" required><option value="">เลือกเหตุผล</option><?php foreach (['wrong_info' => 'ข้อมูลไม่ตรง', 'suspicious' => 'น่าสงสัย', 'already_sold' => 'ขายไปแล้ว', 'prohibited' => 'ของต้องห้าม', 'personal_data' => 'มีข้อมูลส่วนบุคคล'] as $k => $label): ?><option value="<?= $k ?>"><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="rp-note">รายละเอียด (ไม่บังคับ ห้ามใส่เบอร์หรือชื่อบุคคล)</label><input id="rp-note" class="form-control" name="note" maxlength="255"></div>
                <div><button class="button ghost" type="submit">ส่งรายงาน</button></div>
            </form>
        </details>
        <?php endif; ?>
    <?php endif; ?>
    <div class="row mb-3"><?php foreach ($photos as $ph): ?><div class="col-6 col-md-3 mb-3"><img src="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $listing['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" alt="รูปสินค้า" width="<?= (int) $ph['width'] ?>" height="<?= (int) $ph['height'] ?>" loading="lazy" class="img-fluid"></div><?php endforeach; ?></div>
    <div class="card mb-3"><h2>รายละเอียดจากผู้ขาย</h2><dl class="mb-0"><?php foreach (\App\Services\Listing\ListingAttributes::describe($listing) as $d): ?><div><dt><?= htmlspecialchars($d['label']) ?></dt><dd><?= htmlspecialchars($d['value']) ?></dd></div><?php endforeach; ?></dl></div>
    <p class="mi-trend-note">ข้อมูลนี้ผู้ขายกรอกเอง ทีมงานตรวจรูปแบบประกาศ ไม่ได้ตรวจสภาพสินค้าจริง <a href="<?= $base ?>/condition-guide">ดูคู่มือเช็กสภาพก่อนซื้อ</a></p>
</section>
