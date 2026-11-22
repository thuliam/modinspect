<?php /** @var array $member  @var list<array> $builds  @var array $prefs  @var list<array> $requests  @var list<array> $pendingLegal  @var list<array> $acceptances  @var ?string $notice */ $token = \App\Core\Csrf::token(); ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">สมาชิก</span>
        <h1>บัญชีของฉัน</h1>
        <p class="lead"><?= htmlspecialchars((string) ($member['display_name'] ?: 'สมาชิก')) ?><?= $member['email'] ? ' · ' . htmlspecialchars((string) $member['email']) : '' ?></p>
    </div>
    <?php if (!empty($notice)): ?><div class="alert alert-info" role="status"><?= htmlspecialchars((string) $notice) ?></div><?php endif; ?>
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
    <form class="card mb-4" method="post" action="<?= $base ?>/account/preferences">
        <input type="hidden" name="_token" value="<?= $token ?>">
        <h2>ข้อมูลโปรไฟล์และการแจ้งเตือน</h2>
        <div class="form-group"><label for="display_name">ชื่อที่แสดง</label>
            <input class="form-control" id="display_name" name="display_name" maxlength="60" value="<?= htmlspecialchars((string) ($member['display_name'] ?? '')) ?>"></div>
        <div class="form-group"><label for="locale">ภาษา</label>
            <select class="form-control" id="locale" name="locale"><option value="th"<?= $prefs['locale'] === 'th' ? ' selected' : '' ?>>ไทย</option><option value="en"<?= $prefs['locale'] === 'en' ? ' selected' : '' ?>>English</option></select></div>
        <label class="d-flex align-items-start mb-2"><input type="checkbox" name="notify_transactional" value="1" class="mr-2 mt-1"<?= $prefs['notify_transactional'] ? ' checked' : '' ?>> <span>การแจ้งเตือนเกี่ยวกับบัญชีและรายการของฉัน</span></label>
        <label class="d-flex align-items-start mb-3"><input type="checkbox" name="notify_marketing" value="1" class="mr-2 mt-1"<?= $prefs['notify_marketing'] ? ' checked' : '' ?>> <span>ข่าวสารและโปรโมชัน (ไม่เลือก = ไม่ส่ง)</span></label>
        <button type="submit" class="button">บันทึก</button>
    </form>
    <?php if ($pendingLegal): ?>
    <div class="card mb-4">
        <h2>เอกสารที่รอการยอมรับ</h2>
        <?php foreach ($pendingLegal as $d): ?>
            <form method="post" action="<?= $base ?>/account/legal/accept" class="mb-2">
                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                <?= htmlspecialchars((string) $d['title']) ?> (เวอร์ชัน <?= htmlspecialchars((string) $d['version']) ?>) <button type="submit" class="button ghost">ยอมรับ</button>
            </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($acceptances): ?>
    <div class="card mb-4">
        <h2>เอกสารที่ฉันยอมรับแล้ว</h2>
        <ul class="list-unstyled mb-0"><?php foreach ($acceptances as $a): ?><li><?= htmlspecialchars((string) $a['title']) ?> · เวอร์ชัน <?= htmlspecialchars((string) $a['version']) ?> · <?= htmlspecialchars(substr((string) $a['accepted_at'], 0, 10)) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <form class="card mb-4" method="post" action="<?= $base ?>/account/privacy-request">
        <input type="hidden" name="_token" value="<?= $token ?>">
        <h2>คำขอเกี่ยวกับข้อมูลส่วนบุคคล</h2>
        <div class="form-group"><label for="request_type">ประเภทคำขอ</label>
            <select class="form-control" id="request_type" name="request_type"><option value="access">ขอเข้าถึงข้อมูล</option><option value="export">ขอสำเนาข้อมูล</option><option value="correction">ขอแก้ไขข้อมูล</option><option value="objection">คัดค้านการใช้ข้อมูล</option><option value="deletion">ขอให้ลบข้อมูล (ให้ทีมงานตรวจสอบ)</option></select></div>
        <div class="form-group"><label for="detail">รายละเอียด (ไม่บังคับ ไม่ต้องใส่เลขบัตรหรือรหัสผ่าน)</label>
            <textarea class="form-control" id="detail" name="detail" rows="2" maxlength="500"></textarea></div>
        <button type="submit" class="button">ส่งคำขอ</button>
        <?php if ($requests): ?>
            <ul class="list-unstyled mt-3 mb-0"><?php foreach ($requests as $r): ?><li><?= htmlspecialchars((string) $r['request_type']) ?> · <?= htmlspecialchars((string) $r['status']) ?> · <?= htmlspecialchars(substr((string) $r['received_at'], 0, 10)) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </form>
    <form class="card" method="post" action="<?= $base ?>/account/delete">
        <input type="hidden" name="_token" value="<?= $token ?>">
        <h2>ลบบัญชี</h2>
        <p>ลบบัญชีและการเชื่อมกับผู้ให้บริการเข้าสู่ระบบทันที ชุดประกอบที่บันทึกไว้จะไม่ผูกกับคุณอีก ลบแล้วกู้คืนไม่ได้</p>
        <label class="d-flex align-items-start mb-3"><input type="checkbox" name="confirm" value="1" required class="mr-2 mt-1"> <span>ฉันเข้าใจและต้องการลบบัญชี</span></label>
        <button type="submit" class="danger">ลบบัญชีของฉัน</button>
    </form>
</section>
