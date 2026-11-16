<?php $token = htmlspecialchars(\App\Core\Csrf::token()); ?>
<?= mi_page_header($base, ['System', 'Users'], 'Users', 'Create accounts, change roles, disable accounts, set a new password. Passwords are typed here by the admin, stored only as a hash and never shown or logged. Every change is in the audit log.', '') ?>

<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/users/action" autocomplete="off">
    <input type="hidden" name="_token" value="<?= $token ?>">
    <input type="hidden" name="action" value="create">
    <div class="card-header"><h2>Add user</h2></div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label>Name<input class="form-control" name="name" maxlength="120"></label></div>
            <div class="form-group col-md-3"><label>Email<input class="form-control" type="email" name="email" maxlength="190" required></label></div>
            <div class="form-group col-md-3"><label>Password (12+ characters)<input class="form-control" type="password" name="password" minlength="12" autocomplete="new-password" required></label></div>
            <div class="form-group col-md-2"><label>Role<select class="form-control" name="role"><?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-1"><button class="btn btn-primary btn-block" type="submit">Add</button></div>
        </div>
        <small class="text-muted">admin: everything · reviewer: review and corrections · operator: sources and jobs · user: no admin access.</small>
    </div>
</form>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2>Accounts</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Name / email</th><th>Role</th><th>Status</th><th>Last sign-in</th><th style="min-width:24rem">Actions</th></tr></thead>
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
                            <select class="form-control form-control-sm mr-1" name="role" aria-label="Role" <?= $self ? 'disabled' : '' ?>><?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
                            <button class="btn btn-sm btn-outline-secondary mr-2" type="submit" <?= $self ? 'disabled' : '' ?>>Set role</button>
                        </form>
                        <form class="form-inline mb-1" method="post" action="<?= $base ?>/admin/users/action" autocomplete="off">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="user_id" value="<?= $uid ?>"><input type="hidden" name="action" value="password">
                            <input class="form-control form-control-sm mr-1" type="password" name="password" minlength="12" placeholder="New password" aria-label="New password" autocomplete="new-password" required>
                            <button class="btn btn-sm btn-outline-secondary mr-2" type="submit">Set password</button>
                        </form>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/users/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="user_id" value="<?= $uid ?>">
                            <?php if ((int) $u['is_active'] === 1): ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="disable" <?= $self ? 'disabled' : '' ?>>Disable</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="enable">Enable</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
