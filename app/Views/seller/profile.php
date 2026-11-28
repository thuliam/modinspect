<?php
/** LS-90 D1: @var ?array $profile  @var array<string,string> $errors  @var array $old  @var ?string $notice */
use App\Services\Listing\ListingAttributes as LA;

$e = static fn($v): string => htmlspecialchars((string) $v);
$token = $e(\App\Core\Csrf::token());
$state = (string) ($profile['verification'] ?? 'unverified');
$stateText = ['unverified' => 'ยังไม่ยืนยัน', 'pending' => 'รอผู้ดูแลตรวจ', 'verified' => 'ยืนยันแล้ว', 'revoked' => 'ถูกถอนการยืนยัน'];
$err = static fn(string $k): string => isset($errors[$k]) ? '<small class="text-danger d-block">' . htmlspecialchars($errors[$k]) . '</small>' : '';
?>
<section class="container page">
    <div class="mi-page-heading"><span class="eyebrow">ผู้ขาย</span><h1>โปรไฟล์ผู้ขาย</h1><p class="lead">ชื่อที่แสดงและจังหวัดเท่านั้น ห้ามใส่เบอร์ ไลน์ ลิงก์ ชื่อจริง หรือที่อยู่ <a href="<?= $base ?>/seller">กลับไปประกาศของฉัน</a></p></div>
    <?php if ($notice): ?><div class="alert alert-info" role="status"><?= $e($notice) ?></div><?php endif; ?>
    <form class="card mb-3" method="post" action="<?= $base ?>/seller/profile">
        <input type="hidden" name="_token" value="<?= $token ?>">
        <div class="form-group"><label for="sp-name">ชื่อที่แสดง</label><input id="sp-name" class="form-control" name="display_name" maxlength="60" required value="<?= $e($old['display_name'] ?? '') ?>"><?= $err('display_name') ?></div>
        <div class="form-group"><label for="sp-prov">จังหวัด (ไม่บังคับ)</label><select id="sp-prov" class="form-control" name="province"><option value="">ไม่ระบุ</option><?php foreach (LA::PROVINCES as $pv): ?><option<?= ($old['province'] ?? '') === $pv ? ' selected' : '' ?>><?= $e($pv) ?></option><?php endforeach; ?></select><?= $err('province') ?></div>
        <div class="form-group"><label for="sp-about">เกี่ยวกับร้าน (ไม่บังคับ)</label><textarea id="sp-about" class="form-control" name="about" maxlength="300" rows="3"><?= $e($old['about'] ?? '') ?></textarea><?= $err('about') ?></div>
        <div><button class="button" type="submit">บันทึกโปรไฟล์</button></div>
        <?php if ($state === 'verified' || $state === 'pending'): ?><small class="text-muted mt-2 d-block">แก้โปรไฟล์แล้วต้องขอยืนยันใหม่</small><?php endif; ?>
    </form>
    <?php if ($profile): ?>
        <div class="card">
            <h2 class="h5">การยืนยัน: <span class="badge <?= $state === 'verified' ? 'badge-success' : ($state === 'pending' ? 'badge-warning' : 'badge-secondary') ?>"><?= $e($stateText[$state] ?? $state) ?></span></h2>
            <p>ผู้ดูแลตรวจด้วยตนเอง การยืนยันไม่ได้รับประกันสินค้าหรือการซื้อขาย</p>
            <?php if ($state === 'unverified' || $state === 'revoked'): ?><form method="post" action="<?= $base ?>/seller/profile/verify"><input type="hidden" name="_token" value="<?= $token ?>"><button class="button ghost" type="submit">ขอให้ผู้ดูแลยืนยัน</button></form><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
