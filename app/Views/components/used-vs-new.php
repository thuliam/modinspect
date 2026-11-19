<?php
/** LS-24 A5: used against new. @var ?array $uvn from UsedVsNew::compare */
if (!$uvn) { return; }
$safeUrl = preg_match('#^https?://#i', $uvn['source_url']) === 1 ? $uvn['source_url'] : '';
?>
<section class="card mi-result-panel mt-4" id="used-vs-new" data-nav="เทียบกับของใหม่">
    <span class="eyebrow">มือสองเทียบของใหม่</span>
    <h2 class="h5">
        <?php if ($uvn['direction'] === 'cheaper'): ?>ราคากลางมือสอง ฿<?= number_format($uvn['used']) ?> ต่ำกว่าของใหม่ ฿<?= number_format($uvn['new']) ?> ประมาณ <?= (int) $uvn['diff_percent'] ?>%
        <?php elseif ($uvn['direction'] === 'dearer'): ?>ราคากลางมือสอง ฿<?= number_format($uvn['used']) ?> สูงกว่าราคาของใหม่ที่บันทึกไว้ ฿<?= number_format($uvn['new']) ?> ประมาณ <?= (int) $uvn['diff_percent'] ?>%
        <?php else: ?>ราคากลางมือสองเท่ากับราคาของใหม่ที่บันทึกไว้ (฿<?= number_format($uvn['new']) ?>)<?php endif; ?>
    </h2>
    <p class="mb-2"><?= $uvn['direction'] === 'cheaper'
        ? 'ส่วนต่างนี้คือสิ่งที่คุณแลกกับของมือสอง เช่น ประกันที่เหลือน้อยหรือไม่มี อายุการใช้งานที่ผ่านมา และสภาพ ถ้าส่วนต่างน้อย ลองเทียบกับของใหม่ที่มีประกันเต็มก่อนตัดสินใจ'
        : 'ราคามือสองไม่ต่ำกว่าของใหม่ ตรวจราคาของใหม่ล่าสุดที่ร้านก่อน เพราะราคาของใหม่อาจเปลี่ยนไปแล้ว' ?></p>
    <p class="mi-trend-note mb-0">ราคาของใหม่เป็นราคาอ้างอิงที่ทีมงานบันทึกวันที่ <?= htmlspecialchars($uvn['checked_on']) ?><?= $uvn['stale'] ? ' (นานกว่า ' . \App\Services\Catalog\UsedVsNew::STALE_DAYS . ' วันแล้ว ร้านอาจปรับราคาไปแล้ว)' : '' ?><?= $safeUrl !== '' ? ' · <a href="' . htmlspecialchars($safeUrl) . '" rel="noopener nofollow">ดูหน้าร้าน</a>' : '' ?> ใช้เทียบเท่านั้น ไม่ได้เป็นส่วนหนึ่งของช่วงราคามือสอง</p>
</section>
