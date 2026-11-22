<?php $lang = \App\Core\Lang::current(); $langToken = htmlspecialchars(App\Core\Csrf::token()); ?>
<main class="splash-container mi-auth-container">
    <section class="card mi-auth-card">
        <div class="card-header text-center">
            <span class="dx-mark mx-auto"><i class="fas fa-search" aria-hidden="true"></i></span>
            <h1>ModInspect Admin</h1>
            <p><?= htmlspecialchars(t('login.subtitle')) ?></p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string)$error) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $base ?>/login" novalidate>
                <input type="hidden" name="_token" value="<?= $langToken ?>">
                <div class="form-group">
                    <label for="admin-email"><?= htmlspecialchars(t('login.email')) ?></label>
                    <input class="form-control form-control-lg" id="admin-email" type="email" name="email" autocomplete="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="admin-password"><?= htmlspecialchars(t('login.password')) ?></label>
                    <input class="form-control form-control-lg" id="admin-password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fas fa-sign-in-alt mr-2"></i><?= htmlspecialchars(t('login.sign_in')) ?></button>
            </form>
        </div>
        <div class="card-footer text-center">
            <form method="post" action="<?= $base ?>/lang" class="mb-2" aria-label="<?= htmlspecialchars(t('lang.label')) ?>">
                <input type="hidden" name="_token" value="<?= $langToken ?>"><input type="hidden" name="back" value="/login">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="submit" name="lang" value="th" class="btn <?= $lang === 'th' ? 'btn-primary' : 'btn-light border' ?>" aria-pressed="<?= $lang === 'th' ? 'true' : 'false' ?>"><?= htmlspecialchars(t('lang.th')) ?></button>
                    <button type="submit" name="lang" value="en" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-light border' ?>" aria-pressed="<?= $lang === 'en' ? 'true' : 'false' ?>"><?= htmlspecialchars(t('lang.en')) ?></button>
                </div>
            </form>
            <a href="<?= $base ?>/" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(t('login.public_site')) ?></a>
        </div>
    </section>
</main>
