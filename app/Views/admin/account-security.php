<?php
/** LS-89 BE-3: @var bool $featureOn  @var bool $canEnrol  @var bool $enrolled  @var ?array{secret:string,uri:string} $pending  @var list<string> $recoveryCodes */
$token = htmlspecialchars(\App\Core\Csrf::token());
?>
<?= mi_page_header($base, ['Account security'], htmlspecialchars(t('mfa.title')), htmlspecialchars(t('mfa.intro'))) ?>

<?php if ($recoveryCodes): ?>
<div class="alert alert-warning">
    <strong><?= htmlspecialchars(t('mfa.recovery_once')) ?></strong>
    <ul class="mb-0 mt-2"><?php foreach ($recoveryCodes as $code): ?><li><code><?= htmlspecialchars($code) ?></code></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card mi-admin-card">
    <div class="card-body">
        <p><span class="badge <?= $enrolled ? 'badge-success' : 'badge-secondary' ?>"><?= htmlspecialchars(t($enrolled ? 'mfa.on' : 'mfa.off')) ?></span></p>
        <?php if (!$featureOn): ?>
            <p class="text-muted mb-0"><?= htmlspecialchars(t('mfa.feature_off')) ?></p>
        <?php elseif ($enrolled): ?>
            <form method="post" action="<?= $base ?>/admin/account-security" autocomplete="off">
                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="disable">
                <div class="form-group"><label><?= htmlspecialchars(t('mfa.code')) ?><input class="form-control" name="code" maxlength="20" required></label></div>
                <button class="btn btn-outline-danger" type="submit"><?= htmlspecialchars(t('mfa.disable')) ?></button>
            </form>
        <?php elseif (!$canEnrol): ?>
            <p class="text-muted mb-0"><?= htmlspecialchars(t('mfa.not_configured')) ?></p>
        <?php elseif ($pending !== null): ?>
            <p><?= htmlspecialchars(t('mfa.add_to_app')) ?></p>
            <p><code><?= htmlspecialchars(trim(chunk_split($pending['secret'], 4, ' '))) ?></code></p>
            <p class="text-break"><small><a href="<?= htmlspecialchars($pending['uri']) ?>"><?= htmlspecialchars($pending['uri']) ?></a></small></p>
            <form method="post" action="<?= $base ?>/admin/account-security" autocomplete="off">
                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="confirm">
                <div class="form-group"><label><?= htmlspecialchars(t('mfa.code')) ?><input class="form-control" name="code" inputmode="numeric" maxlength="10" required></label></div>
                <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('mfa.confirm')) ?></button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= $base ?>/admin/account-security">
                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="begin">
                <button class="btn btn-primary" type="submit"><i class="fas fa-shield-alt mr-2"></i><?= htmlspecialchars(t('mfa.enable')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
