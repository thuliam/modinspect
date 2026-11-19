<?php /** LS-24 M9: one thread. @var array $t @var ?array $member @var ?string $flash @var array $reasons */
$th = $t['thread']; $posts = $t['posts'];
$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thDate = static function (?string $d) use ($months): string { $x = $d ? strtotime($d) : false; return $x ? (int) date('j', $x) . ' ' . $months[(int) date('n', $x) - 1] . ' ' . ((int) date('Y', $x) + 543) . ' ' . date('H:i', $x) : ''; };
$token = htmlspecialchars(\App\Core\Csrf::token()); $back = '/community/thread/' . (int) $th['id'];
$byParent = []; foreach ($posts as $p) { $byParent[(int) ($p['parent_id'] ?? 0)][] = $p; }
$actions = static function (string $type, int $id) use ($base, $token, $back, $reasons, $member): void {
    if (!$member) { return; } ?>
    <div class="d-flex flex-wrap align-items-center mt-2">
        <form method="post" action="<?= $base ?>/community/vote" class="mr-2"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="back" value="<?= $back ?>"><button class="btn btn-ghost btn-sm" type="submit"><i class="fas fa-thumbs-up" aria-hidden="true"></i> มีประโยชน์</button></form>
        <form method="post" action="<?= $base ?>/community/flag" class="form-inline"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="back" value="<?= $back ?>">
            <label class="sr-only" for="fl-<?= $type . $id ?>">เหตุผลที่แจ้ง</label>
            <select id="fl-<?= $type . $id ?>" name="reason" class="form-control form-control-sm mr-1" data-width="150px"><?php foreach ($reasons as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
            <button class="btn btn-ghost btn-sm" type="submit">แจ้งผู้ดูแล</button></form>
    </div>
<?php };
?>
<section class="container page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= $base ?>/community">ชุมชน</a> / <span><?= htmlspecialchars((string) $th['title']) ?></span></nav>
    <?php if ($flash): ?><div class="alert alert-info" role="status"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <article class="card mi-result-panel">
        <h1 class="h3"><?= htmlspecialchars((string) $th['title']) ?></h1>
        <p class="mi-trend-note"><?= htmlspecialchars($th['handle']) ?> · <?= $thDate($th['created_at']) ?><?php if ($th['product_name']): ?> · <a href="<?= $base ?>/price/<?= rawurlencode((string) $th['product_slug']) ?>"><?= htmlspecialchars((string) $th['product_name']) ?></a><?php endif; ?> · มีประโยชน์ <?= (int) $th['helpful_count'] ?></p>
        <p style="white-space:pre-line"><?= htmlspecialchars((string) $th['body']) ?></p>
        <?php $actions('thread', (int) $th['id']); ?>
    </article>

    <h2 class="h5 mt-4">คำตอบ (<?= count($posts) ?>)</h2>
    <?php foreach ($byParent[0] ?? [] as $p): ?>
        <div class="card mb-3" id="post-<?= (int) $p['id'] ?>"><div class="card-body">
            <small class="text-muted"><?= htmlspecialchars($p['handle']) ?> · <?= $thDate($p['created_at']) ?> · มีประโยชน์ <?= (int) $p['helpful_count'] ?></small>
            <p class="mb-0" style="white-space:pre-line"><?= htmlspecialchars((string) $p['body']) ?></p>
            <?php $actions('post', (int) $p['id']); ?>
            <?php foreach ($byParent[(int) $p['id']] ?? [] as $c): ?>
                <div class="border-left pl-3 mt-3" id="post-<?= (int) $c['id'] ?>">
                    <small class="text-muted"><?= htmlspecialchars($c['handle']) ?> · <?= $thDate($c['created_at']) ?> · มีประโยชน์ <?= (int) $c['helpful_count'] ?></small>
                    <p class="mb-0" style="white-space:pre-line"><?= htmlspecialchars((string) $c['body']) ?></p>
                    <?php $actions('post', (int) $c['id']); ?>
                </div>
            <?php endforeach; ?>
            <?php if ($member && $th['status'] === 'visible'): ?>
                <form method="post" action="<?= $base ?>/community/thread/<?= (int) $th['id'] ?>/reply" class="mt-3"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="parent_id" value="<?= (int) $p['id'] ?>">
                    <label class="sr-only" for="rp-<?= (int) $p['id'] ?>">ตอบข้อความนี้</label>
                    <div class="input-group"><input id="rp-<?= (int) $p['id'] ?>" name="body" class="form-control" maxlength="1500" placeholder="ตอบข้อความนี้" required><div class="input-group-append"><button class="btn btn-ghost" type="submit">ตอบ</button></div></div></form>
            <?php endif; ?>
        </div></div>
    <?php endforeach; ?>
    <?php if (!$posts): ?><p class="text-muted">ยังไม่มีคำตอบ</p><?php endif; ?>

    <div class="card form mt-4">
        <?php if ($th['status'] === 'locked'): ?><p class="mb-0">กระทู้นี้ปิดการตอบแล้ว</p>
        <?php elseif (!$member): ?><p class="mb-0">ต้อง<a href="<?= $base ?>/account/login">เข้าสู่ระบบ</a>ก่อนตอบ</p>
        <?php else: ?>
            <form method="post" action="<?= $base ?>/community/thread/<?= (int) $th['id'] ?>/reply"><input type="hidden" name="_token" value="<?= $token ?>">
                <div class="mi-field"><label for="reply-body">ตอบกระทู้นี้</label><textarea id="reply-body" name="body" rows="4" maxlength="1500" class="form-control" required></textarea></div>
                <button class="btn btn-primary" type="submit">ส่งคำตอบ</button></form>
        <?php endif; ?>
    </div>
</section>
