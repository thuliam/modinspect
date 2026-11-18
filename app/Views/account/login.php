<?php /** @var array<string,\App\Services\Member\OAuthProvider> $providers  @var ?string $error */ ?>
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">สมาชิก</span>
        <h1>เข้าสู่ระบบสมาชิก</h1>
        <p class="lead">ใช้บัญชี Google, Microsoft (Hotmail / Outlook) หรือ Facebook ไม่ต้องตั้งรหัสผ่านใหม่ เช็กราคาและเช็กสภาพได้โดยไม่ต้องเข้าสู่ระบบ</p>
    </div>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!$providers): ?>
        <div class="mi-empty-state"><h2>ยังไม่เปิดรับสมาชิก</h2><p>ระบบสมาชิกพร้อมแล้ว แต่ยังไม่ได้เชื่อมกับผู้ให้บริการเข้าสู่ระบบ</p><div class="mi-actions"><a class="button" href="<?= $base ?>/price">ไปเช็กราคา</a></div></div>
    <?php else: ?>
        <form class="card mi-filter-card" method="post" action="<?= $base ?>/account/login">
            <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
            <h2 class="h5">สิ่งที่เราเก็บเมื่อคุณเป็นสมาชิก</h2>
            <ul>
                <li>ชื่อที่แสดงและอีเมลที่ผู้ให้บริการยืนยันแล้ว เพื่อระบุบัญชีของคุณ</li>
                <li>ชุดประกอบคอมที่คุณบันทึก และประกาศหรือรายงานที่คุณส่งเอง</li>
                <li>เราไม่เก็บรหัสผ่าน ไม่เก็บรูปโปรไฟล์ ไม่โพสต์อะไรแทนคุณ และคุณลบบัญชีได้เองทุกเมื่อ</li>
            </ul>
            <label class="d-flex align-items-start mb-3"><input type="checkbox" name="consent" value="1" required class="mr-2 mt-1"> <span>ฉันยอมรับการเก็บและใช้ข้อมูลตามที่ระบุด้านบน</span></label>
            <div class="mi-actions">
                <?php foreach ($providers as $key => $p): ?>
                    <button type="submit" name="provider" value="<?= htmlspecialchars($key) ?>">เข้าสู่ระบบด้วย <?= htmlspecialchars($p->label()) ?></button>
                <?php endforeach; ?>
            </div>
        </form>
    <?php endif; ?>
</section>
