<?php /** @var array $member  @var list<array> $builds */ $token = \App\Core\Csrf::token(); ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">สมาชิก</span>
        <h1>บัญชีของฉัน</h1>
        <p class="lead"><?= htmlspecialchars((string) ($member['display_name'] ?: 'สมาชิก')) ?><?= $member['email'] ? ' · ' . htmlspecialchars((string) $member['email']) : '' ?></p>
    </div>
    <div class="card mb-4">
        <h2>ชุดประกอบที่บันทึกไว้</h2>
        <?php if (!$builds): ?>
            <p>ยังไม่มีชุดประกอบ <a href="<?= $base ?>/build">ลองจัดสเปก</a> ระหว่างที่เข้าสู่ระบบอยู่ ชุดที่บันทึกจะเก็บไว้ในบัญชีนี้</p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($builds as $b): ?>
                    <li class="mb-2"><a href="<?= $base ?>/build/<?= rawurlencode((string) $b['uuid']) ?>">สเปก <?= htmlspecialchars(substr((string) $b['uuid'], 0, 8)) ?></a> · ฿<?= number_format((float) $b['estimated_expected']) ?> · <?= htmlspecialchars(substr((string) $b['created_at'], 0, 10)) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="mi-actions mb-4">
        <a class="button" href="<?= $base ?>/seller">ประกาศขายของฉัน</a>
        <form method="post" action="<?= $base ?>/account/logout"><input type="hidden" name="_token" value="<?= $token ?>"><button type="submit" class="button ghost">ออกจากระบบ</button></form>
    </div>
    <form class="card" method="post" action="<?= $base ?>/account/delete">
        <input type="hidden" name="_token" value="<?= $token ?>">
        <h2>ลบบัญชี</h2>
        <p>ลบบัญชีและการเชื่อมกับผู้ให้บริการเข้าสู่ระบบทันที ชุดประกอบที่บันทึกไว้จะไม่ผูกกับคุณอีก ลบแล้วกู้คืนไม่ได้</p>
        <label class="d-flex align-items-start mb-3"><input type="checkbox" name="confirm" value="1" required class="mr-2 mt-1"> <span>ฉันเข้าใจและต้องการลบบัญชี</span></label>
        <button type="submit" class="danger">ลบบัญชีของฉัน</button>
    </form>
</section>
