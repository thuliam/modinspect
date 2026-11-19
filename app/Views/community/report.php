<?php
/**
 * LS-21 (R-11, ADR-006 option A). @var array $product @var ?string $type @var array $old @var ?string $error @var bool $done @var list<array{id:int,name:string}> $others
 * @var array $types @var array $specs @var array $conditions @var array $months
 * Choices and numbers only: no text box, no name, no contact. The one text input is the honeypot (hidden from people, a bot fills it).
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$slug = rawurlencode((string) $product['slug']);
$o = static fn(string $k): string => htmlspecialchars((string) ($old[$k] ?? ''));
$sel = static fn(string $k, string $v): string => (string) ($old[$k] ?? '') === $v ? ' selected' : '';
?>
<section class="container page narrow">
    <div class="mi-page-heading">
        <span class="eyebrow">ช่วยกันตรวจข้อมูล</span>
        <h1>แจ้งข้อมูลที่ไม่ถูกต้อง</h1>
        <p class="lead"><?= htmlspecialchars((string) $product['full_name']) ?></p>
    </div>

    <?php if ($done): ?>
        <div class="card p-4">
            <h2 class="h4"><i class="fas fa-check-circle text-success mr-2" aria-hidden="true"></i>ได้รับรายงานแล้ว ขอบคุณครับ</h2>
            <p class="mb-3">มีคนตรวจรายงานนี้ก่อนเสมอ และรายงานไม่เปลี่ยนราคาหรือข้อมูลบนเว็บได้เอง ระบบไม่แสดงรายงานของคุณให้คนอื่นเห็น</p>
            <div class="mi-actions"><a class="button" href="<?= $base ?>/price/<?= $slug ?>">กลับหน้ารุ่นนี้</a><a class="button ghost" href="<?= $base ?>/price">ดูรุ่นอื่น</a></div>
        </div>
    <?php elseif ($type === null): ?>
        <div class="card p-4">
            <h2 class="h5 mb-3">อยากแจ้งเรื่องอะไร</h2>
            <?php foreach ($types as $key => $t): ?>
                <a class="card p-3 mb-2" href="<?= $base ?>/report/<?= $slug ?>?type=<?= rawurlencode($key) ?>"><strong><?= htmlspecialchars($t['label']) ?></strong><small class="text-muted"><?= htmlspecialchars($t['hint']) ?></small></a>
            <?php endforeach; ?>
            <p class="mi-trend-note mt-3 mb-0">ทุกข้อเป็นการเลือกหรือใส่ตัวเลข ไม่มีช่องพิมพ์ข้อความ ไม่ต้องใส่ชื่อหรือช่องทางติดต่อ</p>
        </div>
    <?php else: ?>
        <form class="card p-4" method="post" action="<?= $base ?>/report/<?= $slug ?>">
            <h2 class="h5"><?= htmlspecialchars($types[$type]['label']) ?></h2>
            <p class="text-muted"><?= htmlspecialchars($types[$type]['hint']) ?></p>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <input type="hidden" name="_token" value="<?= $token ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <div class="sr-only" aria-hidden="true"><label>เว้นว่างไว้ <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <?php if ($type === 'wrong_product'): ?>
                <div class="form-group"><label for="f-should-be">รุ่นที่ถูกต้องคือ</label>
                    <select id="f-should-be" name="should_be" class="form-control h-auto" required data-placeholder="พิมพ์หรือเลือกรุ่น">
                        <option value=""></option>
                        <option value="not_in_catalog"<?= $sel('should_be', 'not_in_catalog') ?>>ไม่มีในแคตตาล็อก</option>
                        <?php foreach ($others as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $sel('should_be', (string) $p['id']) ?>><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
                    </select></div>
            <?php elseif ($type === 'price_looks_off'): ?>
                <div class="form-group"><label for="f-direction">ช่วงราคาที่แสดง</label>
                    <select id="f-direction" name="direction" class="form-control h-auto" required data-placeholder="เลือก">
                        <option value=""></option><option value="higher"<?= $sel('direction', 'higher') ?>>ดูสูงเกินไป</option><option value="lower"<?= $sel('direction', 'lower') ?>>ดูต่ำเกินไป</option>
                    </select></div>
                <div class="form-group"><label for="f-price">ราคาที่คุณเห็น (บาท) ไม่บังคับ</label>
                    <input id="f-price" class="form-control h-auto" type="number" name="price" min="1" max="10000000" step="1" inputmode="numeric" value="<?= $o('price') ?>"></div>
            <?php elseif ($type === 'sold_price'): ?>
                <div class="form-group"><label for="f-price">ราคาที่ขายได้ (บาท)</label>
                    <input id="f-price" class="form-control h-auto" type="number" name="price" min="1" max="10000000" step="1" inputmode="numeric" required value="<?= $o('price') ?>"></div>
                <div class="form-group"><label for="f-month">ขายได้เมื่อเดือน</label>
                    <select id="f-month" name="month" class="form-control h-auto" required data-placeholder="เลือกเดือน">
                        <option value=""></option><?php foreach ($months as $v => $label): ?><option value="<?= htmlspecialchars($v) ?>"<?= $sel('month', $v) ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="f-condition">สภาพสินค้า</label>
                    <select id="f-condition" name="condition" class="form-control h-auto" required data-placeholder="เลือกสภาพ">
                        <option value=""></option><?php foreach ($conditions as $v => $label): ?><option value="<?= htmlspecialchars($v) ?>"<?= $sel('condition', $v) ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="f-warranty">ประกันที่เหลือ (เดือน) ไม่บังคับ</label>
                    <input id="f-warranty" class="form-control h-auto" type="number" name="warranty_months" min="0" max="120" step="1" inputmode="numeric" value="<?= $o('warranty_months') ?>"></div>
                <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="f-box" name="has_box" value="1"<?= !empty($old['has_box']) ? ' checked' : '' ?>><label class="custom-control-label" for="f-box">มีกล่อง</label></div>
                <div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="f-receipt" name="has_receipt" value="1"<?= !empty($old['has_receipt']) ? ' checked' : '' ?>><label class="custom-control-label" for="f-receipt">มีใบเสร็จ</label></div>
                <p class="mi-trend-note">ราคาที่ขายได้ใช้เป็นหลักฐานประกอบเท่านั้น แยกจากราคาประกาศขาย และต้องผ่านการตรวจจากคนก่อนเสมอ</p>
            <?php elseif ($type === 'spec_wrong'): ?>
                <div class="form-group"><label for="f-spec">สเปกที่ไม่ถูกต้อง</label>
                    <select id="f-spec" name="spec" class="form-control h-auto" required data-placeholder="เลือกสเปก">
                        <option value=""></option><?php foreach ($specs as $v => $label): ?><option value="<?= htmlspecialchars($v) ?>"<?= $sel('spec', $v) ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                    </select></div>
            <?php else: ?>
                <p>ไม่ต้องกรอกอะไรเพิ่ม กดส่งได้เลย</p>
            <?php endif; ?>

            <div class="mi-actions mt-2"><button class="btn btn-lime" type="submit">ส่งรายงาน</button><a class="button ghost" href="<?= $base ?>/report/<?= $slug ?>">เปลี่ยนเรื่องที่แจ้ง</a></div>
        </form>
    <?php endif; ?>

    <p class="mi-trend-note mt-3">เราเก็บเฉพาะตัวเลือกที่คุณเลือกกับรหัสนิรนามอายุสั้น (เปลี่ยนทุกวัน) เพื่อกันการส่งซ้ำรัวๆ ไม่เก็บชื่อ ช่องทางติดต่อ หรือที่อยู่ มีคนตรวจทุกรายงาน และรายงานไม่เปลี่ยนราคาได้เอง</p>
</section>
