<?php
/** LS-24 M8: the price card inside an article. @var array $p product row @var bool $priced @var string $base */
$url = $base . '/price/' . rawurlencode((string) $p['slug']);
?>
<aside class="card mi-result-panel my-4" aria-label="ราคา <?= htmlspecialchars((string) $p['full_name']) ?>">
    <span class="eyebrow">ราคาตลาดมือสอง</span>
    <h3 class="h5 mb-2"><a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars((string) $p['full_name']) ?></a></h3>
    <?php if ($priced): ?>
        <div class="mi-compare">
            <div><span>ช่วงราคาที่พบ</span><b>฿<?= number_format((float) $p['q1']) ?> – ฿<?= number_format((float) $p['q3']) ?></b></div>
            <div><span>ราคากลาง</span><b>฿<?= number_format((float) $p['median']) ?></b></div>
            <div><span>ตัวอย่าง</span><b><?= (int) $p['valid_sample_size'] ?> ประกาศ</b></div>
        </div>
        <p class="mi-trend-note mb-0">ราคาประกาศขาย ไม่ใช่ราคาปิดดีล · <a href="<?= htmlspecialchars($url) ?>">ดูที่มาของตัวเลข</a></p>
    <?php else: ?>
        <p class="mb-0 text-muted">ยังไม่มีช่วงราคาที่ผ่านการตรวจสอบของรุ่นนี้ · <a href="<?= htmlspecialchars($url) ?>">ดูหน้ารุ่น</a></p>
    <?php endif; ?>
</aside>
