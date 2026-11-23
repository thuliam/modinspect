<?php
/** @var array|null $evidence from Product::evidence(): snapshot_id, included[], excluded[reason=>count], excluded_rows[], sources.  Optional $product['median'] marks the middle of the group. */
if (empty($evidence) || empty($evidence['included'])) { return; }
$n = count($evidence['included']);
$skipped = array_sum($evidence['excluded']);
$reasonLabels = [
    'NOT_ACCEPTED' => 'ยังไม่ผ่านการตรวจ', 'OUTSIDE_COHORT' => 'ราคาอยู่นอกช่วงเปรียบเทียบ', 'DEFECTIVE' => 'สินค้าชำรุด/อะไหล่', 'DEPOSIT' => 'ราคามัดจำ',
    'WRONG_PRICE_TYPE' => 'ไม่ใช่ราคาเสนอขาย', 'INVALID_PRICE' => 'ราคาไม่ถูกต้อง', 'OTHER_DETERMINISTIC_RULE' => 'ตามกฎการคัดกรอง', 'NEW_PRICE_REFERENCE_SOURCE' => 'ราคาอ้างอิงของใหม่ (ไม่นับรวมราคามือสอง)',
];
$gradeLabels = ['owner_text' => 'ทีมงานรวบรวม (ไม่มีลิงก์)', 'other' => 'แหล่งข้อมูลอื่น'];
$thDate = static fn (string $d): string => date('d/m/', strtotime($d)) . ((int) date('Y', strtotime($d)) + 543);
$rows = $evidence['included'];
usort($rows, static fn ($a, $b) => (float) $a['calculation_price'] <=> (float) $b['calculation_price']);
$median = (float) ($product['median'] ?? 0);
$markAt = null; // the first listing at or above the middle price carries the marker
if ($median > 0) { foreach ($rows as $i => $r) { if ((float) $r['calculation_price'] >= $median) { $markAt = $i; break; } } }
$gone = array_filter($evidence['excluded_rows'] ?? [], static fn ($r) => $r['calculation_price'] !== null);
?>
<section class="card mi-history-card mi-evidence mt-0 h-100" id="sec-evidence" data-nav="ประกาศมือสอง" data-icon="fa-clipboard-list" aria-label="ประกาศมือสองที่ใช้คำนวณราคา">
    <div class="mi-section-head compact">
        <div><span class="eyebrow">ประกาศที่เกี่ยวข้อง</span><h2>ประกาศมือสองที่ใช้คำนวณราคานี้</h2></div>
    </div>
    <p class="mi-trend-note">คำนวณจาก <b><?= $n ?></b> ตัวอย่าง จาก <b><?= (int) $evidence['sources'] ?></b> แหล่งข้อมูล<?= $skipped > 0 ? ' และไม่นับอีก ' . (int) $skipped . ' รายการ' : '' ?>
        <?php if ((int) $evidence['sources'] <= 1): ?>ตัวอย่างทั้งหมดมาจากแหล่งเดียว จึงไม่ใช่การยืนยันจากผู้ขายอิสระหลายราย<?php endif; ?></p>
    <?php if ($skipped > 0): ?>
        <p class="mi-trend-note">ที่ไม่นับ:
            <?php $parts = []; foreach ($evidence['excluded'] as $reason => $count) { $parts[] = ($reasonLabels[$reason] ?? htmlspecialchars((string) $reason)) . ' ' . (int) $count; } echo implode(' · ', $parts); ?></p>
    <?php endif; ?>
    <div class="table-responsive mi-evidence-scroll">
        <table class="table table-sm mb-0">
            <thead><tr><th>วันที่พบ</th><th class="text-right">ราคาเสนอขาย</th><th>แหล่ง</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr<?= $i === $markAt ? ' class="mi-ev-mid"' : '' ?>>
                    <td><?= htmlspecialchars($thDate((string) $r['observed_at'])) ?></td>
                    <td class="text-right">฿<?= number_format((float) $r['calculation_price']) ?><?= $i === $markAt ? ' <span class="badge badge-pill mi-ev-badge">ราคากลาง ฿' . number_format($median) . '</span>' : '' ?></td>
                    <td><?= htmlspecialchars($gradeLabels[$r['grade']] ?? $gradeLabels['other']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($gone as $r): ?>
                <tr class="mi-ev-out">
                    <td><?= htmlspecialchars($thDate((string) $r['observed_at'])) ?></td>
                    <td class="text-right">฿<?= number_format((float) $r['calculation_price']) ?></td>
                    <td>ไม่นับ: <?= htmlspecialchars($reasonLabels[$r['reason']] ?? 'ตามกฎการคัดกรอง') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="mi-trend-note mt-2">เรียงตามราคา แสดงเฉพาะวันที่ ราคา และประเภทแหล่ง ไม่แสดงชื่อผู้ขาย ข้อความประกาศ หรือข้อมูลติดต่อ</p>
</section>
