<?php
/** @var ?array $listing  @var list<array> $products  @var array<string,string> $errors  @var array $old  @var list<array> $photos  @var ?string $notice */
use App\Services\Listing\ListingAttributes as LA;
$token = \App\Core\Csrf::token();
$val = static fn(string $k): string => htmlspecialchars((string) ($old[$k] ?? ''));
$yes = static fn(string $k): string => (string) ($old[$k] ?? '') === '1' ? ' checked' : '';
$err = static fn(string $k): string => isset($errors[$k]) ? '<small class="text-danger d-block">' . htmlspecialchars($errors[$k]) . '</small>' : '';
$byCat = []; $byId = []; foreach ($products as $p) { $byCat[(string) $p['category_name']][] = $p; $byId[(int) $p['id']] = $p; }
$chosen = $byId[(int) ($old['product_id'] ?? 0)] ?? null;
$codes = is_array($old['defect_codes'] ?? null) ? $old['defect_codes'] : array_filter(explode(',', (string) ($old['defect_codes'] ?? '')));
$statusText = ['draft' => 'ฉบับร่าง', 'submitted' => 'รอทีมงานตรวจ', 'approved' => 'ผ่านการตรวจ', 'rejected' => 'ไม่ผ่านการตรวจ', 'withdrawn' => 'ถอนแล้ว'];
?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">ผู้ขาย<?= $listing ? ' · ' . ($statusText[$listing['status']] ?? '') : '' ?></span>
        <h1><?= $listing ? 'แก้ไขประกาศ' : 'ลงประกาศขาย' ?></h1>
        <p class="lead">กรอกเท่าที่รู้ ช่องที่ไม่แน่ใจเว้นไว้ได้ ห้ามใส่เบอร์โทร ลิงก์ หรือไอดีติดต่อในช่องข้อความ</p>
    </div>
    <?php if ($notice): ?><div class="notice mb-3"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert mb-3">กรุณาแก้ช่องที่มีข้อความสีแดง</div><?php endif; ?>
    <?php if ($chosen && \App\Services\SpecBuilderService::isPriced($chosen)): ?>
        <div class="notice mb-3">ราคาประกาศมือสองของ <?= htmlspecialchars((string) $chosen['full_name']) ?> ที่ผ่านการตรวจ: ฿<?= number_format((float) $chosen['q1']) ?>–<?= number_format((float) $chosen['q3']) ?> (ราคากลาง ฿<?= number_format((float) $chosen['median']) ?>) เป็นราคาประกาศ ไม่ใช่ราคาขายจริง</div>
    <?php endif; ?>

    <form class="card mb-4" method="post" action="<?= $base ?>/seller/listings/save">
        <input type="hidden" name="_token" value="<?= $token ?>">
        <?php if ($listing): ?><input type="hidden" name="uuid" value="<?= htmlspecialchars((string) $listing['uuid']) ?>"><?php endif; ?>
        <div class="row">
            <div class="col-md-8 mb-3"><label for="sl-product">รุ่นสินค้า</label><select id="sl-product" name="product_id" required><option value="">เลือกรุ่น</option><?php foreach ($byCat as $cat => $list): ?><optgroup label="<?= htmlspecialchars($cat) ?>"><?php foreach ($list as $p): ?><option value="<?= (int) $p['id'] ?>"<?= (int) ($old['product_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $p['full_name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><?= $err('product_id') ?></div>
            <div class="col-md-4 mb-3"><label for="sl-price">ราคาที่ตั้งขาย (บาท)</label><input id="sl-price" name="asking_price" type="number" min="1" max="10000000" step="1" required value="<?= $val('asking_price') ?>"><?= $err('asking_price') ?></div>
            <?php if (!empty($marketOpen)): ?><div class="col-md-4 mb-3"><label for="sl-qty">จำนวน (ชิ้น)</label><input id="sl-qty" name="quantity" type="number" min="1" max="999" step="1" value="<?= htmlspecialchars((string) ($old['quantity'] ?? 1)) ?>"><?= $err('quantity') ?></div><?php endif; ?>
            <div class="col-md-4 mb-3"><label for="sl-cond">สภาพสินค้า (%) ที่คุณประเมินเอง</label><input id="sl-cond" name="condition_percent" type="number" min="0" max="100" step="1" value="<?= $val('condition_percent') ?>" placeholder="เช่น 90"><?= $err('condition_percent') ?></div>
            <div class="col-md-4 mb-3"><label for="sl-age">อายุสินค้า (เดือน, ไม่บังคับ)</label><input id="sl-age" name="product_age_months" type="number" min="0" max="360" step="1" value="<?= $val('product_age_months') ?>"><?= $err('product_age_months') ?></div>
            <div class="col-md-4 mb-3"><label for="sl-prov">จังหวัด (ไม่บังคับ)</label><select id="sl-prov" name="province"><option value="">ไม่ระบุ</option><?php foreach (LA::PROVINCES as $pv): ?><option<?= ($old['province'] ?? '') === $pv ? ' selected' : '' ?>><?= $pv ?></option><?php endforeach; ?></select><?= $err('province') ?></div>
            <div class="col-md-4 mb-3"><label class="d-flex align-items-center"><input type="checkbox" name="under_warranty" value="1" class="mr-2"<?= $yes('under_warranty') ?>> ยังอยู่ในประกันศูนย์</label><label for="sl-wm">เหลือประกัน (เดือน)</label><input id="sl-wm" name="warranty_months" type="number" min="0" max="120" step="1" value="<?= $val('warranty_months') ?>"><?= $err('warranty_months') ?></div>
            <div class="col-md-8 mb-3"><label for="sl-terms">เงื่อนไขการรับประกันจากผู้ขาย</label><input id="sl-terms" name="seller_warranty_terms" maxlength="255" value="<?= $val('seller_warranty_terms') ?>" placeholder="เช่น รับคืนภายใน 3 วันถ้าเปิดไม่ติด"><?= $err('seller_warranty_terms') ?></div>
            <div class="col-md-4 mb-3"><label class="d-flex align-items-center"><input type="checkbox" name="is_defective" value="1" class="mr-2"<?= $yes('is_defective') ?>> ขายแบบเสีย/ซ่อม</label><label class="d-flex align-items-center"><input type="checkbox" name="has_repair_history" value="1" class="mr-2"<?= $yes('has_repair_history') ?>> เคยผ่านงานช่าง</label></div>
            <div class="col-md-4 mb-3"><label for="sl-ship">การส่ง</label><select id="sl-ship" name="shipping_option"><option value="">ไม่ระบุ</option><?php foreach (LA::SHIPPING as $k => $l): ?><option value="<?= $k ?>"<?= ($old['shipping_option'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?= $err('shipping_option') ?></div>
            <div class="col-md-4 mb-3"><label for="sl-shipcost">ค่าส่ง (บาท)</label><input id="sl-shipcost" name="shipping_cost" type="number" min="0" max="5000" step="1" value="<?= $val('shipping_cost') ?>"><?= $err('shipping_cost') ?></div>
            <div class="col-md-4 mb-3"><label class="d-flex align-items-center"><input type="checkbox" name="has_test_result" value="1" class="mr-2"<?= $yes('has_test_result') ?>> มีผลเทส</label><input name="test_result_note" maxlength="255" value="<?= $val('test_result_note') ?>" placeholder="เช่น รันเกม 2 ชม. อุณหภูมิ 72 องศา" aria-label="ผลเทส"><?= $err('test_result_note') ?></div>
            <div class="col-12 mb-3"><span class="d-block mb-1">ตำหนิ</span><?php foreach (LA::DEFECTS as $k => $l): ?><label class="d-inline-flex align-items-center mr-3"><input type="checkbox" name="defect_codes[]" value="<?= $k ?>" class="mr-1"<?= in_array($k, $codes, true) ? ' checked' : '' ?>> <?= $l ?></label><?php endforeach; ?><?= $err('defect_codes') ?></div>
            <div class="col-12 mb-3"><label for="sl-defect">รายละเอียดตำหนิ</label><input id="sl-defect" name="defect_note" maxlength="255" value="<?= $val('defect_note') ?>"><?= $err('defect_note') ?></div>
        </div>
        <button type="submit"><?= $listing ? 'บันทึกการแก้ไข' : 'สร้างประกาศ' ?></button>
    </form>

    <?php if ($listing): ?>
    <div class="card mb-4">
        <h2>รูปสินค้า (<?= count($photos) ?>/<?= \App\Services\Listing\ListingPhotoStore::MAX_PHOTOS ?>)</h2>
        <p class="mi-hint">JPG, PNG หรือ WebP รูปละไม่เกิน 5 MB ระบบลบข้อมูลตำแหน่ง (GPS) ในไฟล์ออกให้ และย่อรูปให้ไม่เกิน 1600 px</p>
        <div class="row">
            <?php foreach ($photos as $ph): ?>
                <div class="col-6 col-md-3 mb-3"><img src="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $listing['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" alt="รูปสินค้า" width="<?= (int) $ph['width'] ?>" height="<?= (int) $ph['height'] ?>" loading="lazy" class="img-fluid mb-1">
                    <form method="post" action="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="remove_photo"><input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>"><button type="submit" class="button ghost">ลบรูปนี้</button></form></div>
            <?php endforeach; ?>
        </div>
        <?php if (count($photos) < \App\Services\Listing\ListingPhotoStore::MAX_PHOTOS): ?>
        <form method="post" enctype="multipart/form-data" action="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/photos">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="เลือกรูป"> <button type="submit">อัปโหลด</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (!empty($marketOpen) && $listing['status'] === 'approved'): $sale = (string) ($listing['sale_state'] ?? 'available'); $until = (string) ($listing['expires_at'] ?? ''); ?>
    <div class="card mb-3">
        <h2 class="h5">สถานะการขาย: <?= ['available' => 'พร้อมขาย', 'reserved' => 'จองแล้ว', 'sold' => 'ขายแล้ว'][$sale] ?? htmlspecialchars($sale) ?></h2>
        <?php if ($until !== ''): ?><p class="mb-2"><?= \App\Services\Market\ListingLifecycle::expired($listing) ? 'หมดอายุแล้วเมื่อ' : 'แสดงถึง' ?> <?= htmlspecialchars(substr($until, 0, 10)) ?></p><?php endif; ?>
        <?php if ($sale !== 'sold'): ?><div class="mi-actions">
            <?php foreach (($sale === 'available' ? ['reserved' => 'ทำเครื่องหมายว่าจองแล้ว'] : ['available' => 'กลับเป็นพร้อมขาย']) + ['sold' => 'ขายแล้ว', 'renew' => 'ต่ออายุประกาศ'] as $to => $label): ?>
                <form method="post" action="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/sale"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="to" value="<?= $to ?>"><button type="submit" class="button ghost"><?= $label ?></button></form>
            <?php endforeach; ?>
        </div><small class="text-muted">"ขายแล้ว" ย้อนกลับไม่ได้ และประกาศจะไม่แสดงอีก</small><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="mi-actions">
        <?php foreach (in_array($listing['status'], ['draft', 'rejected'], true) ? ['submit' => 'ส่งให้ทีมงานตรวจ'] : [] as $a => $l): ?>
            <form method="post" action="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="<?= $a ?>"><button type="submit"><?= $l ?></button></form>
        <?php endforeach; ?>
        <?php if ($listing['status'] !== 'withdrawn'): ?><form method="post" action="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="withdraw"><button type="submit" class="button ghost">ถอนประกาศ</button></form><?php endif; ?>
        <form method="post" action="<?= $base ?>/seller/listings/<?= htmlspecialchars((string) $listing['uuid']) ?>/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="danger">ลบประกาศ</button></form>
    </div>
    <?php endif; ?>
    <p class="mt-4"><a href="<?= $base ?>/seller">กลับไปประกาศของฉัน</a></p>
</section>
