<?php /** LS-24 M9: thread list. @var array $list @var ?array $product @var int $page @var ?array $member @var ?string $flash @var array $products */
$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thDate = static function (?string $d) use ($months): string { $t = $d ? strtotime($d) : false; return $t ? (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . ((int) date('Y', $t) + 543) : ''; };
$token = htmlspecialchars(\App\Core\Csrf::token());
?>
<section class="container page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">ชุมชน</span>
        <h1><?= $product ? 'ถามตอบเรื่อง ' . htmlspecialchars((string) $product['full_name']) : 'ถามตอบและแลกเปลี่ยนเรื่องคอมมือสอง' ?></h1>
        <p class="lead">เล่าประสบการณ์ซื้อจริง ถามก่อนซื้อ ตอบจากคนที่เคยใช้ ห้ามใส่เบอร์โทร ไลน์ ลิงก์ หรือเรียกใครว่าโกง ให้เล่าสิ่งที่เกิดขึ้นกับคุณแทน</p>
    </div>
    <?php if ($flash): ?><div class="alert alert-info" role="status"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <?php if ($list['rows']): ?>
        <div class="card mi-table-card"><ul class="list-group list-group-flush">
        <?php foreach ($list['rows'] as $t): ?>
            <li class="list-group-item bg-transparent border-secondary">
                <a href="<?= $base ?>/community/thread/<?= (int) $t['id'] ?>"><strong><?= htmlspecialchars((string) $t['title']) ?></strong></a>
                <?php if ($t['status'] === 'locked'): ?><span class="badge">ปิดการตอบ</span><?php endif; ?><br>
                <small class="text-muted"><?= htmlspecialchars($t['handle']) ?> · <?= $thDate($t['last_post_at'] ?? $t['created_at']) ?> · ตอบ <?= (int) $t['post_count'] ?> · มีประโยชน์ <?= (int) $t['helpful_count'] ?>
                    <?php if ($t['product_name']): ?> · <a href="<?= $base ?>/price/<?= rawurlencode((string) $t['product_slug']) ?>"><?= htmlspecialchars((string) $t['product_name']) ?></a><?php endif; ?></small>
            </li>
        <?php endforeach; ?>
        </ul></div>
    <?php else: ?>
        <div class="mi-empty-state"><h2>ยังไม่มีกระทู้</h2><p>เป็นคนแรกที่ตั้งคำถามหรือเล่าประสบการณ์</p></div>
    <?php endif; ?>

    <div class="card form mt-4" id="new-thread">
        <h2 class="h5">ตั้งกระทู้ใหม่</h2>
        <?php if (!$member): ?>
            <p class="mb-0">ต้อง<a href="<?= $base ?>/account/login">เข้าสู่ระบบ</a>ก่อนตั้งกระทู้หรือตอบ ชื่อที่แสดงจะเป็นนามแฝงสั้น ๆ ไม่ใช่ชื่อบัญชีของคุณ</p>
        <?php else: ?>
        <form method="post" action="<?= $base ?>/community/thread">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <div class="mi-field"><label for="th-product">เกี่ยวกับรุ่น (ไม่บังคับ)</label>
                <select id="th-product" name="product_id" class="form-control"><option value="">ไม่ระบุรุ่น</option>
                    <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $product && (int) $product['id'] === (int) $p['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $p['full_name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="mi-field"><label for="th-title">หัวข้อ</label><input id="th-title" name="title" class="form-control" maxlength="160" required></div>
            <div class="mi-field"><label for="th-body">รายละเอียด</label><textarea id="th-body" name="body" rows="5" maxlength="2000" class="form-control" required></textarea></div>
            <button class="btn btn-primary" type="submit">ตั้งกระทู้</button>
        </form>
        <?php endif; ?>
    </div>
</section>
