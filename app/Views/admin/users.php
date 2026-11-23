<?php $token = htmlspecialchars(\App\Core\Csrf::token()); ?>
<?= mi_page_header($base, ['System', 'Users'], htmlspecialchars(t('v.users')), htmlspecialchars(t('v.create_accounts_change_roles_disable')), '') ?>

<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/users/action" autocomplete="off">
    <input type="hidden" name="_token" value="<?= $token ?>">
    <input type="hidden" name="action" value="create">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.add_user')) ?></h2></div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.name')) ?><input class="form-control" name="name" maxlength="120"></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.email')) ?><input class="form-control" type="email" name="email" maxlength="190" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.password_12_characters')) ?><input class="form-control" type="password" name="password" minlength="12" autocomplete="new-password" required></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.role')) ?><select class="form-control" name="role"><?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-1"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.add')) ?></button></div>
        </div>
        <small class="text-muted"><?= htmlspecialchars(t('v.admin_everything_reviewer_review_and')) ?></small>
    </div>
</form>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.accounts')) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.name_email')) ?></th><th><?= htmlspecialchars(t('v.role')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th><?= htmlspecialchars(t('v.last_sign_in')) ?></th><th class="mi-th-widest"><?= htmlspecialchars(t('v.actions')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = (int) $u['id'] === $me; $uid = (int) $u['id']; ?>
                <tr>
                    <td><?= htmlspecialchars((string) $u['name']) ?><br><small class="text-muted"><?= htmlspecialchars((string) $u['email']) ?><?= $self ? ' · you' : '' ?></small></td>
                    <td><span class="badge badge-light"><?= htmlspecialchars((string) $u['role']) ?></span></td>
                    <td><?= (int) $u['is_active'] === 1 ? '<span class="badge badge-success">active</span>' : '<span class="badge badge-secondary">disabled</span>' ?></td>
                    <td><?= $u['last_login_at'] ? htmlspecialchars(substr((string) $u['last_login_at'], 0, 16)) : '<span class="text-muted">never</span>' ?></td>
                    <td>
                        <form class="form-inline mb-1" method="post" action="<?= $base ?>/admin/users/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="user_id" value="<?= $uid ?>"><input type="hidden" name="action" value="role">
                            <select class="form-control form-control-sm mr-1" name="role" aria-label="<?= htmlspecialchars(t('v.role')) ?>" <?= $self ? 'disabled' : '' ?>><?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
                            <button class="btn btn-sm btn-outline-secondary mr-2" type="submit" <?= $self ? 'disabled' : '' ?>><?= htmlspecialchars(t('v.set_role')) ?></button>
                        </form>
                        <form class="form-inline mb-1" method="post" action="<?= $base ?>/admin/users/action" autocomplete="off">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="user_id" value="<?= $uid ?>"><input type="hidden" name="action" value="password">
                            <input class="form-control form-control-sm mr-1" type="password" name="password" minlength="12" placeholder="<?= htmlspecialchars(t('v.new_password')) ?>" aria-label="<?= htmlspecialchars(t('v.new_password')) ?>" autocomplete="new-password" required>
                            <button class="btn btn-sm btn-outline-secondary mr-2" type="submit"><?= htmlspecialchars(t('v.set_password')) ?></button>
                        </form>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/users/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="user_id" value="<?= $uid ?>">
                            <?php if ((int) $u['is_active'] === 1): ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="disable" <?= $self ? 'disabled' : '' ?>><?= htmlspecialchars(t('v.disable')) ?></button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="enable"><?= htmlspecialchars(t('v.enable')) ?></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
