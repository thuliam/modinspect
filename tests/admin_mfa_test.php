<?php
declare(strict_types=1);

/** LS-89 BE-3: two-step sign-in. Off by default; when on, a password alone gives no access to an enrolled account. */
require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Auth\MfaService;
use App\Services\Auth\Totp;

function assert_mfa(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

// ---- RFC 6238 test vectors (SHA-1), last six digits of the published eight ----
$rfc = '12345678901234567890';
assert_mfa(Totp::base32Encode($rfc) === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'base32');
foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130'] as $time => $expected) {
    assert_mfa(Totp::code($rfc, intdiv($time, 30)) === $expected, "vector at $time: " . Totp::code($rfc, intdiv($time, 30)));
}
assert_mfa(Totp::match($rfc, '287 082', 59) === 1 && Totp::match($rfc, '287082', 59, 1) === null && Totp::match($rfc, '287082', 600) === null && Totp::match($rfc, 'abcdef', 59) === null, 'matching rules');

global $config;
assert_mfa(($config['auth_mfa']['enabled'] ?? null) === false, 'the feature is off unless configured');
$db = Database::connection($config['db']);
$db->beginTransaction();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$on = ['enabled' => true, 'key' => bin2hex(random_bytes(32))];
try {
    $auth = new AuthService();
    $email = 'mfa-' . bin2hex(random_bytes(4)) . '@example.test';
    $password = bin2hex(random_bytes(12));
    $uid = (int)$auth->createUser($email, 'MFA Admin', $password, 'admin')['user_id'];
    $decode = static function (string $base32): string {
        $bits = ''; foreach (str_split($base32) as $c) { $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $c)), 5, '0', STR_PAD_LEFT); }
        $out = ''; foreach (str_split($bits, 8) as $b) { if (strlen($b) === 8) { $out .= chr(bindec($b)); } } return $out;
    };

    // off: enrolment refused
    assert_mfa((new MfaService($db))->begin($uid, $email)['error'] === 'MFA_UNAVAILABLE', 'no enrolment while off');
    assert_mfa((new MfaService($db, ['enabled' => true, 'key' => 'short']))->begin($uid, $email)['error'] === 'MFA_UNAVAILABLE', 'no enrolment with a short key');

    $config['auth_mfa'] = $on;
    $mfa = new MfaService($db);
    $started = $mfa->begin($uid, $email);
    assert_mfa($started['ok'] && str_starts_with((string)$started['uri'], 'otpauth://totp/ModInspect%3A'), 'enrolment starts');
    $secret = $decode((string)$started['secret']);
    $row = $db->query("SELECT * FROM user_mfa WHERE user_id=$uid")->fetch();
    assert_mfa(!str_contains((string)$row['secret_encrypted'], (string)$started['secret']) && !str_contains((string)base64_decode((string)$row['secret_encrypted']), $secret), 'the secret is stored encrypted');
    assert_mfa(!$mfa->requiredFor($uid), 'an unconfirmed enrolment does not lock anyone out');
    $step = intdiv(time(), 30);
    $wrong = Totp::code($secret, $step + 100);
    assert_mfa($mfa->confirm($uid, $wrong)['error'] === 'MFA_CODE_WRONG', 'a wrong code does not confirm');
    $confirmed = $mfa->confirm($uid, Totp::code($secret, $step - 1));
    assert_mfa($confirmed['ok'] && count($confirmed['recovery_codes']) === MfaService::RECOVERY_CODES, 'confirmed with recovery codes: ' . json_encode($confirmed['error']));
    $stored = (string)$db->query("SELECT recovery_codes FROM user_mfa WHERE user_id=$uid")->fetchColumn();
    assert_mfa(!str_contains($stored, $confirmed['recovery_codes'][0]), 'recovery codes are stored hashed');

    // password alone: no session
    $_SESSION = [];
    $login = $auth->login($email, $password, '127.0.0.1');
    assert_mfa($login['ok'] === false && $login['error'] === 'MFA_REQUIRED', 'password asks for the second step');
    assert_mfa((new AuthService())->currentUser() === null && !isset($_SESSION[AuthService::SESSION_USER_ID]) && $auth->mfaPending(), 'no access before the code');
    assert_mfa($auth->completeMfa(Totp::code($secret, $step - 1), '127.0.0.1')['error'] === 'MFA_CODE_WRONG', 'a used code cannot be replayed');
    $done = $auth->completeMfa(Totp::code($secret, intdiv(time(), 30)), '127.0.0.1');
    assert_mfa($done['ok'] && (int)(new AuthService())->currentUser()['id'] === $uid, 'the right code signs in');

    // recovery code works once
    $_SESSION = [];
    $auth->login($email, $password, '127.0.0.1');
    assert_mfa($auth->completeMfa(strtolower($confirmed['recovery_codes'][0]), '127.0.0.1')['ok'] === true, 'recovery code accepted');
    $_SESSION = [];
    $auth->login($email, $password, '127.0.0.1');
    assert_mfa($auth->completeMfa($confirmed['recovery_codes'][0], '127.0.0.1')['error'] === 'MFA_CODE_WRONG', 'a recovery code is spent after one use');

    // expiry, throttle, and no secrets in the audit log
    $_SESSION[AuthService::SESSION_MFA_PENDING]['at'] = time() - 301;
    assert_mfa($auth->completeMfa($confirmed['recovery_codes'][1], '127.0.0.1')['error'] === 'MFA_EXPIRED', 'the second step expires');
    $auth->login($email, $password, '10.9.9.9');
    for ($i = 0; $i < 5; $i++) { $auth->completeMfa('000000', '10.9.9.9'); }
    assert_mfa($auth->completeMfa($confirmed['recovery_codes'][1], '10.9.9.9')['error'] === 'TOO_MANY_ATTEMPTS', 'guessing is limited');
    $log = json_encode($db->query("SELECT action,before_data,after_data FROM audit_logs WHERE user_id=$uid OR entity_id=$uid")->fetchAll());
    foreach (array_merge([(string)$started['secret']], $confirmed['recovery_codes']) as $private) { assert_mfa(!str_contains($log, $private), 'no secret or code in the audit log'); }

    // fail closed with a lost key; the recovery command path removes it
    $config['auth_mfa'] = ['enabled' => true, 'key' => ''];
    $_SESSION = [];
    assert_mfa($auth->login($email, $password, '127.0.0.2')['error'] === 'MFA_REQUIRED', 'still asked without a key');
    assert_mfa($auth->completeMfa(Totp::code($secret, intdiv(time(), 30) + 1), '127.0.0.2')['ok'] === false, 'a code cannot pass without the key');

    // switched off again: password is enough, enrolment kept
    $config['auth_mfa'] = ['enabled' => false, 'key' => $on['key']];
    $_SESSION = [];
    assert_mfa($auth->login($email, $password, '127.0.0.3')['ok'] === true, 'feature off: password signs in');

    // the pages
    $config['auth_mfa'] = $on;
    $call = static function (callable $action, string $method, array $post = []): array {
        $_SERVER['REQUEST_METHOD'] = $method; $_SERVER['REQUEST_URI'] = '/admin/account-security';
        $_POST = $post === [] ? [] : $post + ['_token' => Csrf::token()];
        http_response_code(200); $path = null; ob_start();
        try { $action(); } catch (RedirectException $e) { $path = $e->path; }
        return ['path' => $path, 'page' => (string)ob_get_clean(), 'code' => http_response_code()];
    };
    $page = $call(fn() => (new AdminController())->accountSecurity(), 'GET');
    assert_mfa(str_contains($page['page'], 'value="disable"'), 'an enrolled account is offered to turn it off');
    $call(fn() => (new AdminController())->accountSecurityAction(), 'POST', ['action' => 'disable', 'code' => '000000']);
    assert_mfa($mfa->isEnrolled($uid), 'a wrong code does not turn it off');
    // turning it off is limited like signing in: after five wrong codes from one address even a right one waits
    $_SERVER['REMOTE_ADDR'] = '10.8.8.8';
    for ($i = 0; $i < 5; $i++) { $call(fn() => (new AdminController())->accountSecurityAction(), 'POST', ['action' => 'disable', 'code' => '000000']); }
    $call(fn() => (new AdminController())->accountSecurityAction(), 'POST', ['action' => 'disable', 'code' => $confirmed['recovery_codes'][2]]);
    assert_mfa((new MfaService($db))->isEnrolled($uid), 'guessing the turn-off code is limited');
    $_SERVER['REMOTE_ADDR'] = '127.0.0.5';
    $call(fn() => (new AdminController())->accountSecurityAction(), 'POST', ['action' => 'disable', 'code' => $confirmed['recovery_codes'][2]]);
    assert_mfa(!(new MfaService($db))->isEnrolled($uid), 'a valid code turns it off');
    $call(fn() => (new AdminController())->accountSecurityAction(), 'POST', ['action' => 'begin']);
    $page = $call(fn() => (new AdminController())->accountSecurity(), 'GET');
    $pending = (new MfaService($db))->pending($uid, $email);
    assert_mfa($pending !== null && str_contains($page['page'], trim(chunk_split($pending['secret'], 4, ' '))), 'the page shows the secret to add');
    $shown = $call(fn() => (new AdminController())->accountSecurityAction(), 'POST', ['action' => 'confirm', 'code' => Totp::code($decode($pending['secret']), intdiv(time(), 30))]);
    assert_mfa($shown['path'] === null && substr_count($shown['page'], '<li><code>') === MfaService::RECOVERY_CODES && !str_contains(json_encode($_SESSION), 'recovery'), 'recovery codes are shown once and not kept in the session');

    // sign-in pages
    $_SESSION = [];
    $r = $call(fn() => (new AuthController())->mfaForm(), 'GET');
    assert_mfa($r['path'] === '/login', 'the second step needs a password first');
    $r = $call(fn() => (new AuthController())->login(), 'POST', ['email' => $email, 'password' => $password]);
    assert_mfa($r['path'] === '/login/mfa', 'login goes to the second step');
    $r = $call(fn() => (new AuthController())->mfaForm(), 'GET');
    assert_mfa(str_contains($r['page'], 'name="code"'), 'the code form');
    $r = $call(fn() => (new AuthController())->mfaVerify(), 'POST', ['code' => '000000']);
    assert_mfa($r['code'] === 401 && (new AuthService())->currentUser() === null, 'wrong code stays out');
    $_POST = ['_token' => 'bad', 'code' => '000000']; $_SERVER['REQUEST_METHOD'] = 'POST'; ob_start(); (new AuthController())->mfaVerify(); ob_end_clean();
    assert_mfa(http_response_code() === 419, 'CSRF');
    assert_mfa((new MfaService($db))->remove($uid, null, 'cli') === true && $auth->login($email, $password, '127.0.0.4')['ok'] === true, 'after a reset the password signs in');

    echo "admin_mfa_test: PASS\n";
} finally {
    $config['auth_mfa'] = ['enabled' => false, 'key' => ''];
    $_SESSION = [];
    $db->rollBack();
}
