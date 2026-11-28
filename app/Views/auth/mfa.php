<?php /** LS-89 BE-3: second step of the sign-in. @var ?string $error */ $token = htmlspecialchars(App\Core\Csrf::token()); ?>
<main class="splash-container mi-auth-container">
    <section class="card mi-auth-card">
        <div class="card-header text-center">
            <span class="dx-mark mx-auto"><i class="fas fa-shield-alt" aria-hidden="true"></i></span>
            <h1><?= htmlspecialchars(t('mfa.title')) ?></h1>
            <p><?= htmlspecialchars(t('mfa.ask')) ?></p>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string)$error) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $base ?>/login/mfa">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <div class="form-group">
                    <label for="mfa-code"><?= htmlspecialchars(t('mfa.code')) ?></label>
                    <input class="form-control form-control-lg" id="mfa-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fas fa-sign-in-alt mr-2"></i><?= htmlspecialchars(t('login.sign_in')) ?></button>
            </form>
        </div>
        <div class="card-footer text-center">
            <a href="<?= $base ?>/login"><?= htmlspecialchars(t('mfa.start_over')) ?></a>
        </div>
    </section>
</main>
