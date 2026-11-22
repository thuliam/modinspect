<?php /** @var list<array{label:string,value:string,source:?string,checked:?string,note:?string}> $facts rows from ProductFacts::rows */ ?>
<section class="card mi-facts-card h-100" id="sec-specs" data-icon="fa-microchip" data-nav="<?= empty($benchmarks) ? 'สเปก' : 'สเปกและผลทดสอบ' ?>" aria-labelledby="mi-facts-title">
    <div class="mi-section-head compact"><div><span class="eyebrow">ข้อมูลรุ่น</span><h2 id="mi-facts-title">สเปกและอายุรุ่น</h2></div></div>
    <?php if (!empty($facts)): ?>
        <dl class="mi-context-list">
            <?php foreach ($facts as $f): ?>
                <div>
                    <dt><?= htmlspecialchars($f['label']) ?></dt>
                    <dd>
                        <?= htmlspecialchars($f['value']) ?>
                        <?php if (!empty($f['source'])): ?>
                            <small class="muted"> · <a href="<?= htmlspecialchars($f['source']) ?>" target="_blank" rel="noopener noreferrer">แหล่งที่มา (ผู้ผลิต)</a><?= !empty($f['checked']) ? ' ตรวจเมื่อ ' . htmlspecialchars($f['checked']) : '' ?></small>
                        <?php endif; ?>
                        <?php if (!empty($f['note'])): ?><small class="muted d-block"><?= htmlspecialchars($f['note']) ?></small><?php endif; ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php else: ?>
        <p class="muted">ยังไม่มีข้อมูลสเปกที่ตรวจแล้วของรุ่นนี้</p>
    <?php endif; ?>
    <?php if (empty($benchmarks)): ?>    <p class="mi-trend-note">ยังไม่มีคะแนนเบนช์มาร์กหรืออันดับความแรง เพราะยังไม่มีแหล่งข้อมูลที่ได้รับอนุญาตให้นำมาแสดง และจะไม่แสดงตัวเลขที่ไม่มีที่มา</p><?php endif; ?>
</section>
