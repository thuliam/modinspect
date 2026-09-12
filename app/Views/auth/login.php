<main class="splash-container mi-auth-container">
    <section class="card mi-auth-card">
        <div class="card-header text-center bg-white">
            <div class="mi-auth-mark"><i class="fas fa-search-dollar"></i></div>
            <h1>ModInspect Admin</h1>
            <p>Operations console for market evidence, review, and catalog quality.</p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string)$error) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $base ?>/login" novalidate>
                <input type="hidden" name="_token" value="<?= htmlspecialchars(App\Core\Csrf::token()) ?>">
                <div class="form-group">
                    <label for="admin-email">Email</label>
                    <input class="form-control form-control-lg" id="admin-email" type="email" name="email" autocomplete="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="admin-password">Password</label>
                    <input class="form-control form-control-lg" id="admin-password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fas fa-sign-in-alt mr-2"></i>Sign in</button>
            </form>
        </div>
        <div class="card-footer bg-white text-center">
            <a href="<?= $base ?>/" target="_blank" rel="noopener noreferrer">Open public site</a>
        </div>
    </section>
</main>
