<?php
/** LS-90 D3: @var array{rows:list<array>,total:int,page:int,pages:int} $result  @var array $filters  @var list<array> $categories  @var ?array $member  @var ?string $notice */
use App\Services\Listing\ListingAttributes as LA;

$e = static fn($v): string => htmlspecialchars((string) $v);
$f = static fn(string $k): string => htmlspecialchars((string) ($filters[$k] ?? ''));
$pageUrl = static fn(int $n): string => $base . '/listings?' . htmlspecialchars(http_build_query($filters + ['page' => $n]));
?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">ประกาศจากผู้ขาย</span>
        <h1>ประกาศขายคอมมือสอง</h1>
        <p class="lead">ราคาในประกาศเป็นราคาที่ผู้ขายตั้งเอง ไม่ใช่ราคาตลาด เปิดหน้ารุ่นเพื่อดูช่วงราคาก่อนตัดสินใจ</p>
    </div>
    <?php if ($notice): ?><div class="alert alert-info" role="status"><?= $e($notice) ?></div><?php endif; ?>
    <form class="card mb-3" method="get" action="<?= $base ?>/listings">
        <div class="form-row">
            <div class="form-group col-md-4"><label for="mk-q">ชื่อรุ่น</label><input id="mk-q" class="form-control" name="q" maxlength="80" value="<?= $f('q') ?>"></div>
            <div class="form-group col-md-2"><label for="mk-cat">หมวด</label><select id="mk-cat" class="form-control" name="category"><option value="">ทุกหมวด</option><?php foreach ($categories as $c): ?><option value="<?= $e($c['slug']) ?>"<?= ($filters['category'] ?? '') === $c['slug'] ? ' selected' : '' ?>><?= $e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group col-md-2"><label for="mk-prov">จังหวัด</label><select id="mk-prov" class="form-control" name="province"><option value="">ทุกจังหวัด</option><?php foreach (LA::PROVINCES as $pv): ?><option<?= ($filters['province'] ?? '') === $pv ? ' selected' : '' ?>><?= $e($pv) ?></option><?php endforeach; ?></select></div>
            <div class="form-group col-md-2"><label for="mk-max">ราคาไม่เกิน</label><input id="mk-max" class="form-control" type="number" min="1" name="max_price" value="<?= $f('max_price') ?>"></div>
            <div class="form-group col-md-2"><label for="mk-sort">เรียง</label><select id="mk-sort" class="form-control" name="sort"><?php foreach (['new' => 'ใหม่ก่อน', 'price_asc' => 'ราคาต่ำก่อน', 'price_desc' => 'ราคาสูงก่อน'] as $k => $label): ?><option value="<?= $k ?>"<?= ($filters['sort'] ?? 'new') === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2"><label class="d-flex align-items-center mb-0"><input type="checkbox" name="verified" value="1" class="mr-2"<?= !empty($filters['verified']) ? ' checked' : '' ?>> เฉพาะผู้ขายที่ผู้ดูแลยืนยันแล้ว</label></div>
        <div><button class="button" type="submit">ค้นหา</button> <a class="button ghost" href="<?= $base ?>/listings">ล้างตัวกรอง</a></div>
    </form>
    <?php if ($member): ?>
        <form class="mb-3" method="post" action="<?= $base ?>/listings/searches/save">
            <input type="hidden" name="_token" value="<?= $e(\App\Core\Csrf::token()) ?>">
            <?php foreach ($filters as $k => $v): ?><input type="hidden" name="<?= $e($k) ?>" value="<?= $e($v) ?>"><?php endforeach; ?>
            <button class="button ghost" type="submit">บันทึกการค้นหานี้</button> <a href="<?= $base ?>/listings/saved">รายการที่บันทึกไว้</a>
        </form>
    <?php endif; ?>
    <p class="text-muted">พบ <?= (int) $result['total'] ?> ประกาศ</p>
    <?php if (!$result['rows']): ?>
        <div class="mi-empty-state"><h2>ยังไม่มีประกาศที่ตรงเงื่อนไข</h2><p>ลองลดตัวกรอง หรือดูช่วงราคาของรุ่นในหน้าเช็กราคา</p></div>
    <?php else: ?>
        <div class="row"><?php foreach ($result['rows'] as $r): ?>
            <div class="col-md-6 col-lg-4 mb-3"><div class="card h-100">
                <?php if ($r['photo']): ?><img class="img-fluid mb-2" src="<?= $base ?>/seller/photos/<?= $e($r['uuid']) ?>/<?= $e($r['photo']) ?>" alt="รูปสินค้าจากผู้ขาย" loading="lazy"><?php endif; ?>
                <h2 class="h5"><a href="<?= $base ?>/listings/<?= $e($r['uuid']) ?>"><?= $e($r['full_name']) ?></a></h2>
                <p class="mb-1"><b>฿<?= number_format((float) $r['asking_price']) ?></b><?= (int) $r['quantity'] > 1 ? ' · มี ' . (int) $r['quantity'] . ' ชิ้น' : '' ?></p>
                <p class="mb-1 small text-muted"><?= $e($r['category_name']) ?><?= $r['province'] ? ' · ' . $e($r['province']) : '' ?></p>
                <p class="mb-0"><?php if ($r['verification'] === 'verified'): ?><span class="badge badge-success">ผู้ขายยืนยันแล้ว</span> <?php endif; ?><?php if ($r['sale_state'] === 'reserved'): ?><span class="badge badge-warning">มีคนจองแล้ว</span><?php endif; ?></p>
            </div></div>
        <?php endforeach; ?></div>
        <?php if ($result['pages'] > 1): ?><nav aria-label="หน้าผลการค้นหา"><ul class="pagination">
            <?php if ($result['page'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $pageUrl($result['page'] - 1) ?>">ก่อนหน้า</a></li><?php endif; ?>
            <li class="page-item disabled"><span class="page-link"><?= (int) $result['page'] ?> / <?= (int) $result['pages'] ?></span></li>
            <?php if ($result['page'] < $result['pages']): ?><li class="page-item"><a class="page-link" href="<?= $pageUrl($result['page'] + 1) ?>">ถัดไป</a></li><?php endif; ?>
        </ul></nav><?php endif; ?>
    <?php endif; ?>
</section>
