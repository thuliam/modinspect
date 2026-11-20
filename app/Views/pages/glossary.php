<?php /** LS-24 B2: used-market words. @var list<array> $terms */ ?>
<section class="container page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">ศัพท์ตลาดมือสอง</span>
        <h1>คำที่เจอในประกาศขายคอมมือสอง แปลว่าอะไร</h1>
        <p class="lead">ตัวโชว์ ตัวขุด ประกันศูนย์ ถอดเครื่อง และคำอื่น ๆ พร้อมประโยคที่เจอจริงในโพสต์ และสิ่งที่ควรถามก่อนจ่ายเงิน</p>
    </div>
    <nav class="mi-category-chips mb-4" aria-label="ไปยังคำศัพท์">
        <?php foreach ($terms as $t): ?><a href="#<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['term']) ?></a><?php endforeach; ?>
    </nav>
    <div class="narrow-copy">
        <?php foreach ($terms as $t): ?>
            <article class="card mb-3" id="<?= htmlspecialchars($t['id']) ?>"><div class="card-body">
                <h2 class="h5 mb-1"><?= htmlspecialchars($t['term']) ?></h2>
                <?php if ($t['also'] !== ''): ?><small class="text-muted">มักเขียนว่า: <?= htmlspecialchars($t['also']) ?></small><?php endif; ?>
                <p class="mt-2"><?= htmlspecialchars($t['meaning']) ?></p>
                <p class="mb-1"><strong>ที่เจอในโพสต์:</strong> “<?= htmlspecialchars($t['example']) ?>”</p>
                <p class="mb-0"><strong>ควรถาม:</strong> <?= htmlspecialchars($t['ask']) ?></p>
            </div></article>
        <?php endforeach; ?>
        <p class="mi-trend-note">คำอธิบายเป็นความรู้ทั่วไปของตลาด ไม่ได้ตัดสินผู้ขายรายใด</p>
        <a class="btn btn-ghost" href="<?= $base ?>/read-listing">วางประกาศให้ระบบอ่าน</a>
    </div>
</section>
