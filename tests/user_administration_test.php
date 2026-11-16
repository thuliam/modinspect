<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\UserAdministration;
use App\Services\Auth\AuthService;

/** User administration (LS-3 J-2): the rules that stop an admin from locking the system or leaking a credential. Everything runs in a rolled-back transaction. */
function ok_ua(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $auth = new AuthService();
    $svc = new UserAdministration($db, $auth);
    $pw = 'CorrectHorseBattery99';
    $mk = static function (string $tag, string $role) use ($auth, $pw, $suffix): int {
        $r = $auth->createUser("ua-$tag-$suffix@example.test", "UA $tag", $pw, $role);
        if (!($r['ok'] ?? false)) { throw new RuntimeException('fixture user failed: ' . json_encode($r)); }
        return (int)$r['user_id'];
    };
    $a = $mk('a', 'admin'); $b = $mk('b', 'admin'); $rev = $mk('r', 'reviewer');
    // make A and B the only active admins of this run
    $db->prepare("UPDATE users SET is_active = 0 WHERE role = 'admin' AND id NOT IN (?, ?)")->execute([$a, $b]);

    // list never carries the hash
    $list = $svc->list();
    ok_ua(count($list) >= 3 && !array_key_exists('password_hash', $list[0]), 'The list has no password hash');

    // create
    $c = $svc->create($a, "ua-new-$suffix@example.test", 'New Person', $pw, 'operator');
    ok_ua($c['ok'] && $c['user_id'] > 0, 'An admin can create a user');
    ok_ua($svc->create($a, "ua-new-$suffix@example.test", 'Dup', $pw, 'operator')['error'] === 'EMAIL_ALREADY_EXISTS', 'A duplicate email is refused');
    ok_ua($svc->create($a, "ua-x-$suffix@example.test", 'Short', 'short', 'operator')['error'] === 'PASSWORD_TOO_SHORT', 'A short password is refused');
    ok_ua($svc->create($a, "ua-y-$suffix@example.test", 'Bad role', $pw, 'root')['error'] === 'INVALID_ROLE', 'An unknown role is refused');

    // role changes
    ok_ua($svc->changeRole($a, $rev, 'operator')['ok'], 'A reviewer can be changed to operator');
    ok_ua($db->query("SELECT role FROM users WHERE id=$rev")->fetchColumn() === 'operator', 'The role was stored');
    ok_ua((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='auth_user_role_changed' AND entity_id=$rev AND user_id=$a")->fetchColumn() === 1, 'The role change is audited with the acting admin');
    ok_ua($svc->changeRole($a, $rev, 'superuser')['error'] === 'INVALID_ROLE', 'An unknown role is refused');
    ok_ua($svc->changeRole($a, $a, 'reviewer')['error'] === 'CANNOT_CHANGE_OWN_ROLE', 'Nobody changes their own role');
    ok_ua($svc->changeRole($a, 99999999, 'reviewer')['error'] === 'USER_NOT_FOUND', 'An unknown user is refused');

    // last-admin protection: with A and B active, B can be demoted; then A is the last admin
    ok_ua($svc->changeRole($a, $b, 'reviewer')['ok'], 'With two admins one can be demoted');
    // B is a reviewer now, so A is the only active admin; B (any other actor) cannot take A's admin role or account away
    ok_ua($svc->changeRole($b, $a, 'operator')['error'] === 'LAST_ADMIN', 'The last active admin cannot be demoted');
    ok_ua($svc->setActive($b, $a, false)['error'] === 'LAST_ADMIN', 'The last active admin cannot be disabled');
    ok_ua($db->query("SELECT role FROM users WHERE id=$a")->fetchColumn() === 'admin' && (int)$db->query("SELECT is_active FROM users WHERE id=$a")->fetchColumn() === 1, 'Refusals changed nothing');
    ok_ua($svc->setActive($a, $a, false)['error'] === 'CANNOT_DISABLE_SELF', 'Nobody disables themselves');

    // disable / enable
    ok_ua($svc->setActive($a, $rev, false)['ok'] && (int)$db->query("SELECT is_active FROM users WHERE id=$rev")->fetchColumn() === 0, 'A user can be disabled');
    ok_ua($auth->login("ua-r-$suffix@example.test", $pw, '127.0.0.1')['error'] === 'INVALID_CREDENTIALS', 'A disabled user cannot sign in');
    ok_ua($svc->setActive($a, $rev, true)['ok'] && (int)$db->query("SELECT is_active FROM users WHERE id=$rev")->fetchColumn() === 1, 'A user can be enabled again');
    ok_ua((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('auth_user_disabled','auth_user_enabled') AND entity_id=$rev")->fetchColumn() === 2, 'Disable and enable are audited');

    // password
    $new = 'AnotherLongPassphrase77';
    ok_ua($svc->setPassword($a, $rev, 'short')['error'] === 'PASSWORD_TOO_SHORT', 'A short new password is refused');
    $before = (string)$db->query("SELECT password_hash FROM users WHERE id=$rev")->fetchColumn();
    ok_ua($svc->setPassword($a, $rev, $new)['ok'], 'An admin can set a new password');
    $after = (string)$db->query("SELECT password_hash FROM users WHERE id=$rev")->fetchColumn();
    ok_ua($after !== $before && password_verify($new, $after) && !password_verify($pw, $after), 'The hash changed and verifies only the new password');
    $audit = (string)$db->query("SELECT after_data FROM audit_logs WHERE action='auth_password_reset' AND entity_id=$rev ORDER BY id DESC LIMIT 1")->fetchColumn();
    ok_ua($audit !== '' && !str_contains($audit, $new) && !str_contains($audit, $after), 'The audit entry carries neither the password nor the hash');
    ok_ua($auth->login("ua-r-$suffix@example.test", $new, '127.0.0.1')['ok'] ?? false, 'The user can sign in with the new password');
    $auth->logout(false);
} finally {
    $db->rollBack();
}

echo "user_administration_test: PASS\n";
