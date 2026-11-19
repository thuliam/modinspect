<?php
/**
 * LS-24 M9: the "bought it, how is it" block of a product page. Shown only while the forum is enabled.
 * @var array $product @var array $cm_summary @var list<array> $cm_reviews @var ?array $cm_member @var ?string $cm_flash
 * Review figures appear from five reviews and always with their count; the price a reviewer paid is what they say, not a market price, and never feeds the price range.
 */
use App\Services\Community\Forum;
$token = htmlspecialchars(\App\Core\Csrf::token()); $back = '/price/' . rawurlencode((string) $product['slug']);
?>
<section class="card mi-result-panel mt-4" id="reviews" data-nav="รีวิวผู้ซื้อ">
    <span class="eyebrow">ซื้อแล้วเป็นไง</span>
    <h2 class="h4">ประสบการณ์ของคนที่ซื้อรุ่นนี้มือสอง</h2>
    <?php if ($cm_flash): ?><div class="alert alert-info" role="status"><?= htmlspecialchars($cm_flash) ?></div><?php endif; ?>
    <?php if ($cm_summary['shown']): ?>
        <div class="mi-compare">
            <div><span>ไม่มีปัญหาใน 30 วันแรก</span><b><?= (int) $cm_summary['no_issue_pct'] ?>%</b></div>
            <div><span>จะซื้อซ้ำ</span><b><?= (int) $cm_summary['again_pct'] ?>%</b></div>
            <div><span>จำนวนรีวิว</span><b><?= (int) $cm_summary['count'] ?> คน</b></div>
        </div>
        <p class="mi-trend-note">สรุปจากรีวิวของสมาชิก ไม่ได้ผ่านการตรวจสอบของ ModInspect และไม่ใช่ราคาตลาด</p>
    <?php else: ?>
        <p class="mi-trend-note"><?= (int) $cm_summary['count'] ?> รีวิว ยังน้อยกว่า <?= Forum::MIN_REVIEWS_SHOWN ?> คน จึงยังไม่สรุปเป็นเปอร์เซ็นต์</p>
    <?php endif; ?>
    <?php foreach ($cm_reviews as $r): ?>
        <div class="border-top pt-3 mt-3">
            <small class="text-muted"><?= htmlspecialchars($r['handle']) ?> · ซื้อจาก <?= htmlspecialchars(Forum::SOURCES[$r['source_kind']] ?? '') ?> · สภาพตอนได้รับ <?= htmlspecialchars(Forum::CONDITIONS[$r['condition_received']] ?? '') ?> · <?= htmlspecialchars(Forum::ISSUES[$r['issue_30d']] ?? '') ?> · <?= (int) $r['would_buy_again'] === 1 ? 'จะซื้อซ้ำ' : 'ไม่ซื้อซ้ำ' ?></small>
            <p class="mb-0">ผู้รีวิวบอกว่าจ่าย ฿<?= number_format((int) $r['price_paid']) ?><?= $r['note'] ? ' · ' . htmlspecialchars((string) $r['note']) : '' ?></p>
        </div>
    <?php endforeach; ?>
    <div class="mt-4">
        <?php if (!$cm_member): ?>
            <p class="mb-2">ต้อง<a href="<?= $base ?>/account/login">เข้าสู่ระบบ</a>ก่อนรีวิวหรือถาม ชื่อที่แสดงจะเป็นนามแฝงสั้น ๆ</p>
        <?php else: ?>
        <h3 class="h6">เล่าประสบการณ์ของคุณ</h3>
        <form method="post" action="<?= $base ?>/community/review" class="form-row">
            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <div class="col-md-3 mb-2"><label for="rv-price">ราคาที่จ่าย (บาท)</label><input id="rv-price" name="price_paid" type="number" min="1" max="10000000" class="form-control" required></div>
            <div class="col-md-3 mb-2"><label for="rv-src">ซื้อจาก</label><select id="rv-src" name="source_kind" class="form-control" required><?php foreach (Forum::SOURCES as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 mb-2"><label for="rv-cond">สภาพตอนได้รับ</label><select id="rv-cond" name="condition_received" class="form-control" required><?php foreach (Forum::CONDITIONS as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 mb-2"><label for="rv-issue">ปัญหาใน 30 วัน</label><select id="rv-issue" name="issue_30d" class="form-control" required><?php foreach (Forum::ISSUES as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 mb-2"><label for="rv-again">จะซื้อซ้ำไหม</label><select id="rv-again" name="would_buy_again" class="form-control" required><option value="1">ซื้อซ้ำ</option><option value="0">ไม่ซื้อซ้ำ</option></select></div>
            <div class="col-md-9 mb-2"><label for="rv-note">เล่าเพิ่ม (ไม่บังคับ ไม่ใส่เบอร์ ลิงก์ หรือชื่อผู้ขาย)</label><input id="rv-note" name="note" class="form-control" maxlength="500"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">ส่งรีวิว</button></div>
        </form>
        <?php endif; ?>
        <p class="mt-3 mb-0"><a href="<?= $base ?>/community?product=<?= rawurlencode((string) $product['slug']) ?>">ถามตอบเรื่องรุ่นนี้</a></p>
    </div>
</section>
