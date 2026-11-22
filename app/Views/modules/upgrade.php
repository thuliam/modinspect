<?php
/** LS-25 A6: upgrade finder. @var ?array $have @var ?array $result @var string $typed @var list<array{slug:string,name:string}> $options */
$labels = ['cores' => 'คอร์', 'threads' => 'เธรด', 'vram_gb' => 'VRAM (GB)', 'capacity_gb' => 'ความจุ (GB)'];
$baht = static fn(float $v): string => '฿' . number_format($v);
$n = static fn(float $v): string => rtrim(rtrim(number_format($v, 1), '0'), '.');
?>
<section class="container page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">อัปเกรด</span>
        <h1>ใช้รุ่นนี้อยู่ ขยับไปรุ่นไหนได้บ้าง</h1>
        <p class="lead">ดูรุ่นที่สเปกมากกว่าของเดิม พร้อมราคามือสองที่ตรวจแล้วและสิ่งที่ต้องเช็กก่อนซื้อ ระบบไม่บอกว่ารุ่นไหนเร็วกว่าหรือคุ้มกว่า เทียบจากตัวเลขสเปกที่ผู้ผลิตประกาศเท่านั้น</p>
    </div>
    <form method="get" action="<?= $base ?>/upgrade" class="mi-ask mb-4" role="search">
        <label for="up-m">รุ่นที่ใช้อยู่ (ซีพียู การ์ดจอ หรือแรม)</label>
        <div class="mi-ask-bar">
            <i class="fas fa-microchip mi-ask-ico" aria-hidden="true"></i>
            <input id="up-m" name="m" list="up-options" class="mi-ask-input" value="<?= htmlspecialchars($have ? (string) $have['full_name'] : $typed) ?>" maxlength="120" placeholder="เช่น Ryzen 5 3600, RTX 3060" autocomplete="off">
            <datalist id="up-options"><?php foreach ($options as $o): ?><option value="<?= htmlspecialchars($o['name']) ?>"><?php endforeach; ?></datalist>
            <button class="mi-ask-go" type="submit" aria-label="ดูตัวเลือก" title="ดูตัวเลือก"><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
        </div>
    </form>

    <?php if ($typed !== '' && !$have): ?>
        <div class="mi-empty-state"><h2>ไม่พบรุ่นนี้ในรายการ</h2><p>ลองพิมพ์ชื่อบางส่วน หรือเลือกจากรายการที่แสดงตอนพิมพ์</p></div>
    <?php elseif ($have && !$result['supported']): ?>
        <div class="mi-empty-state"><h2>ยังไม่รองรับหมวดนี้</h2><p><?= htmlspecialchars((string) $have['full_name']) ?> อยู่ในหมวด <?= htmlspecialchars((string) $have['category_name']) ?> ตอนนี้ตัวช่วยอัปเกรดทำได้กับซีพียู การ์ดจอ และแรม</p></div>
    <?php elseif ($have): ?>
        <p class="mb-3">รุ่นเดิม: <strong><?= htmlspecialchars((string) $have['full_name']) ?></strong>
            <?php if ($result['base_priced']): ?> · ราคามือสองกลาง <?= $baht((float) $have['median']) ?><?php else: ?> · <span class="text-muted">รุ่นเดิมยังไม่มีราคาที่ตรวจแล้ว จึงไม่แสดงส่วนต่าง</span><?php endif; ?></p>
        <?php if (!$result['items']): ?>
            <div class="mi-empty-state"><h2>ยังไม่มีตัวเลือกที่มีราคา</h2><p>ไม่พบรุ่นที่สเปกมากกว่าและมีราคาตรวจแล้ว<?= $result['unpriced_hidden'] > 0 ? ' (มี ' . (int) $result['unpriced_hidden'] . ' รุ่นที่สเปกมากกว่าแต่ยังไม่มีราคา)' : '' ?></p></div>
        <?php else: ?>
            <p class="mi-trend-note">เรียงตามราคามือสองจากน้อยไปมาก ไม่ใช่การจัดอันดับ</p>
            <div class="row">
            <?php foreach ($result['items'] as $it): $p = $it['product']; ?>
                <div class="col-md-6 mb-3"><article class="card h-100 mi-up-card">
                    <h2 class="h5 mb-1"><a href="<?= $base ?>/price/<?= rawurlencode((string) $p['slug']) ?>"><?= htmlspecialchars((string) $p['full_name']) ?></a></h2>
                    <p class="mb-2"><strong><?= $baht($it['median']) ?></strong>
                        <?php if ($it['delta'] !== null): ?> <span class="mi-delta mi-delta-<?= $it['delta'] >= 0 ? 'up' : 'down' ?>"><i class="fas <?= $it['delta'] >= 0 ? 'fa-caret-up' : 'fa-caret-down' ?>" aria-hidden="true"></i> <?= $it['delta'] >= 0 ? 'มากกว่ารุ่นเดิม ' : 'น้อยกว่ารุ่นเดิม ' ?><?= $baht(abs((float) $it['delta'])) ?></span><?php endif; ?></p>
                    <ul class="mb-2 pl-3">
                        <?php foreach ($it['steps'] as $s): ?><li><?= htmlspecialchars($labels[$s['key']] ?? $s['key']) ?>: <?= $n($s['from']) ?> เป็น <?= $n($s['to']) ?></li><?php endforeach; ?>
                    </ul>
                    <?php if ($it['notes']): ?><p class="mb-1"><small class="text-muted">ควรเช็ก:</small></p><ul class="mb-0 pl-3"><?php foreach ($it['notes'] as $note): ?><li><small><?= htmlspecialchars($note) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
                </article></div>
            <?php endforeach; ?>
            </div>
            <?php if ($result['unpriced_hidden'] > 0): ?><p class="mi-trend-note">ยังมีอีก <?= (int) $result['unpriced_hidden'] ?> รุ่นที่สเปกมากกว่าแต่ยังไม่มีราคาที่ตรวจแล้ว จึงไม่แสดง</p><?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require ROOT_PATH . '/app/Views/components/next-step.php'; // LS-56: every tool page ends with a next step ?>
