<section class="container page auth-page">
  <div class="section-heading">
    <p>Admin</p>
    <h1>เข้าสู่ระบบ</h1>
  </div>
  <form class="card form-card" method="post" action="<?= $base ?>/login">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(App\Core\Csrf::token()) ?>">
    <?php if(!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>อีเมล
      <input type="email" name="email" autocomplete="username" required>
    </label>
    <label>รหัสผ่าน
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="primary-button" type="submit">เข้าสู่ระบบ</button>
  </form>
</section>
