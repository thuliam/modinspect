<?php
/** @var array|null $evidence from Product::evidence(): snapshot_id, included[], excluded[reason=>count], sources */
if (empty($evidence) || empty($evidence['included'])) { return; }
$n = count($evidence['included']);
$skipped = array_sum($evidence['excluded']);
$reasonLabels = [
    'NOT_ACCEPTED' => 'ยังไม่ผ่านการตรวจ', 'OUTSIDE_COHORT' => 'ราคาอยู่นอกช่วงเปรียบเทียบ', 'DEFECTIVE' => 'สินค้าชำรุด/อะไหล่', 'DEPOSIT' => 'ราคามัดจำ',
    'WRONG_PRICE_TYPE' => 'ไม่ใช่ราคาเสนอขาย', 'INVALID_PRICE' => 'ราคาไม่ถูกต้อง', 'OTHER_DETERMINISTIC_RULE' => 'ตามกฎการคัดกรอง', 'NEW_PRICE_REFERENCE_SOURCE' => 'ราคาอ้างอิงของใหม่ (ไม่นับรวมราคามือสอง)',
];
$gradeLabels = ['owner_text' => 'ข้อความที่ทีมงานรวบรวม (ไม่มีลิงก์ต้นทาง)', 'other' => 'แหล่งข้อมูลอื่น'];
$collapseId = 'evidence' . (int) $evidence['snapshot_id'];
?>
<section class="card mi-history-card mi-evidence" id="sec-evidence" data-nav="หลักฐาน" aria-label="หลักฐานที่ใช้คำนวณราคา">
    <div class="mi-section-head compact">
        <div><span class="eyebrow">หลักฐาน</span><h2>ราคานี้มาจากอะไร</h2></div>
    </div>
    <p class="mi-trend-note">คำนวณจาก <b><?= $n ?></b> ตัวอย่าง จาก <b><?= (int) $evidence['sources'] ?></b> แหล่งข้อมูล<?= $skipped > 0 ? ' และไม่นับอีก ' . (int) $skipped . ' รายการ' : '' ?>
        <?php if ((int) $evidence['sources'] <= 1): ?>ตัวอย่างทั้งหมดมาจากแหล่งเดียว จึงไม่ใช่การยืนยันจากผู้ขายอิสระหลายราย<?php endif; ?></p>
    <?php if ($skipped > 0): ?>
        <p class="mi-trend-note">ที่ไม่นับ:
            <?php $parts = []; foreach ($evidence['excluded'] as $reason => $count) { $parts[] = ($reasonLabels[$reason] ?? htmlspecialchars((string) $reason)) . ' ' . (int) $count; } echo implode(' · ', $parts); ?></p>
    <?php endif; ?>
    <button class="btn btn-sm btn-ghost align-self-start" type="button" data-toggle="collapse" data-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">ดูตัวอย่างที่ใช้คำนวณ</button>
    <div class="collapse mt-3" id="<?= $collapseId ?>">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>วันที่พบ</th><th class="text-right">ราคาเสนอขาย</th><th>ประเภทหลักฐาน</th></tr></thead>
                <tbody>
                <?php foreach ($evidence['included'] as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/', strtotime((string) $r['observed_at'])) . ((int) date('Y', strtotime((string) $r['observed_at'])) + 543)) ?></td>
                        <td class="text-right">฿<?= number_format((float) $r['calculation_price']) ?></td>
                        <td><?= htmlspecialchars($gradeLabels[$r['grade']] ?? $gradeLabels['other']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mi-trend-note">แสดงเฉพาะวันที่ ราคา และประเภทหลักฐาน ไม่แสดงชื่อผู้ขาย ข้อความประกาศ หรือข้อมูลติดต่อ</p>
    </div>
</section>
