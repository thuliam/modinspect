<?php
/** LS-24 A3: bargaining help. @var ?array $neg from Negotiation::plan */
if (!$neg) { return; }
$modes = ['below_band' => 'ราคาต่ำกว่าช่วงที่พบ', 'above_band' => 'ราคาสูงกว่าช่วงที่พบ', 'upper_band' => 'ราคาอยู่ครึ่งบนของช่วง', 'lower_band' => 'ราคาอยู่ครึ่งล่างของช่วง'];
?>
<section class="card mi-result-panel mt-4" id="negotiation">
    <span class="eyebrow">ช่วยต่อรอง</span>
    <h2 class="h5"><?= htmlspecialchars($modes[$neg['mode']] ?? '') ?></h2>
    <?php if ($neg['offer'] !== null): ?>
        <div class="mi-compare">
            <div><span>ราคาเริ่มต่อที่แนะนำ</span><b>฿<?= number_format($neg['offer']) ?></b></div>
            <div><span>ไม่ควรเกิน (ถ้าของและประกันตรงประกาศ)</span><b>฿<?= number_format($neg['ceiling']) ?></b></div>
        </div>
        <p class="mi-trend-note"><?= htmlspecialchars($neg['basis']) ?> ตัวเลขนี้วางราคาไว้ในช่วงตลาดเท่านั้น ไม่ได้บวกลบตามสภาพหรืออุปกรณ์ที่ได้</p>
        <h3 class="h6 mt-3">ข้อความพร้อมส่ง</h3>
        <?php foreach ($neg['messages'] as $m): ?>
            <div class="mb-3">
                <small class="text-muted"><?= htmlspecialchars($m['tone']) ?></small>
                <p class="mb-1"><?= htmlspecialchars($m['text']) ?></p>
                <button type="button" class="btn btn-ghost btn-sm" data-copy-text="<?= htmlspecialchars($m['text']) ?>"><i class="fas fa-copy" aria-hidden="true"></i> <span>คัดลอก</span></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <ul class="mb-0"><?php foreach ($neg['tips'] as $t): ?><li><?= htmlspecialchars($t) ?></li><?php endforeach; ?></ul>
</section>
