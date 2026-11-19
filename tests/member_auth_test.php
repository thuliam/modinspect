<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Member\FacebookProvider;
use App\Services\Member\Jwt;
use App\Services\Member\MemberAuth;
use App\Services\Member\OAuthHttp;
use App\Services\Member\OidcProvider;
use App\Services\Member\ProviderRegistry;

/**
 * LS-14 Module 7 (OD-4): visitor sign-in with Google, Microsoft and Facebook. A real RSA key signs the test id_tokens and a fake HTTP client
 * plays the provider, so the whole flow runs with no network: JWT checks (signature, alg, kid, issuer, audience, expiry, nonce), state and
 * PKCE, one-time pending state, find-or-create by (provider, subject), separate session from the admin, account deletion. DB rolled back.
 */
function ok_m(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$keyOpts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$key = openssl_pkey_new($keyOpts);
if ($key === false && is_file($cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf')) { $key = openssl_pkey_new($keyOpts + ['config' => $cnf]); }   // Windows PHP needs its openssl.cnf
ok_m($key !== false, 'Could not create a test RSA key');
$d = openssl_pkey_get_details($key);
$jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'alg' => 'RS256', 'n' => Jwt::b64url($d['rsa']['n']), 'e' => Jwt::b64url($d['rsa']['e'])]]];
$sign = static function (array $claims, array $header = ['alg' => 'RS256', 'kid' => 'k1']) use ($key): string {
    $h = Jwt::b64url(json_encode($header)); $p = Jwt::b64url(json_encode($claims));
    openssl_sign("$h.$p", $sig, $key, OPENSSL_ALGO_SHA256);
    return "$h.$p." . Jwt::b64url($sig);
};
$now = time();
$base = ['iss' => 'https://accounts.google.com', 'aud' => 'cid', 'sub' => '1234', 'exp' => $now + 600, 'iat' => $now, 'nonce' => 'n1', 'email' => 'A@Example.com', 'email_verified' => true, 'name' => 'Somchai'];
$iss = static fn(string $i): bool => $i === 'https://accounts.google.com';

// ---- JWT ----
ok_m(Jwt::verify($sign($base), $jwks, $iss, 'cid', 'n1', $now)['sub'] === '1234', 'A valid token verifies (the RSA key rebuilt from n and e works)');
$refuse = static function (string $token, string $code, string $aud = 'cid', ?string $nonce = 'n1') use ($jwks, $iss, $now): void {
    try { Jwt::verify($token, $jwks, $iss, $aud, $nonce, $now); } catch (RuntimeException $e) { ok_m($e->getMessage() === $code, "Expected $code, got " . $e->getMessage()); return; }
    throw new RuntimeException("Token accepted, expected $code");
};
$refuse($sign($base, ['alg' => 'none', 'kid' => 'k1']), 'JWT_ALG');
$refuse($sign($base, ['alg' => 'HS256', 'kid' => 'k1']), 'JWT_ALG');
$refuse($sign($base, ['alg' => 'RS256', 'kid' => 'other']), 'JWT_KEY');
$t = $sign($base); $parts = explode('.', $t); $parts[1] = Jwt::b64url(json_encode(['sub' => 'evil'] + $base)); $refuse(implode('.', $parts), 'JWT_SIGNATURE');
$refuse($sign(['iss' => 'https://evil.example'] + $base), 'JWT_ISSUER');
$refuse($sign($base), 'JWT_AUDIENCE', 'other-client');
$refuse($sign(['exp' => $now - 3600] + $base), 'JWT_EXPIRED');
$refuse($sign(['iat' => $now + 3600] + $base), 'JWT_FUTURE');
$refuse($sign($base), 'JWT_NONCE', 'cid', 'different');
$refuse('a.b', 'JWT_MALFORMED');

// ---- fake provider over HTTP ----
final class FakeHttp implements OAuthHttp
{
    public array $calls = []; public array $routes = [];
    public function get(string $url, array $headers = []): array { $this->calls[] = ['GET', $url, $headers]; return $this->route($url); }
    public function postForm(string $url, array $fields, array $headers = []): array { $this->calls[] = ['POST', $url, $fields]; return $this->route($url, $fields); }
    private function route(string $url, array $fields = []): array { foreach ($this->routes as $prefix => $fn) { if (str_starts_with($url, $prefix)) { return $fn($url, $fields); } } return ['status' => 404, 'body' => '{}']; }
}
$http = new FakeHttp();
$tokenFor = static fn(array $claims) => static fn() => ['status' => 200, 'body' => json_encode(['id_token' => $sign($claims), 'access_token' => 'at'])];
$http->routes['https://www.googleapis.com/oauth2/v3/certs'] = static fn() => ['status' => 200, 'body' => json_encode($jwks)];

ok_m(ProviderRegistry::fromConfig([], $http) === [], 'No keys in .env: no provider is offered');
$reg = ProviderRegistry::fromConfig(['google' => ['client_id' => 'cid', 'client_secret' => 's'], 'microsoft' => ['client_id' => 'm', 'client_secret' => ''], 'facebook' => ['client_id' => 'f', 'client_secret' => 'fs'], 'apple' => ['client_id' => 'a', 'client_secret' => 'b']], $http);
ok_m(array_keys($reg) === ['google', 'facebook'], 'Only providers with id and secret are offered, and never Apple (cost gate HG-15): ' . json_encode(array_keys($reg)));

global $config;
$db = Database::connection($config['db']);
ok_m($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db->beginTransaction();
try {
    $_SESSION = ['auth_user_id' => 777];   // an admin session that must stay untouched
    $auth = new MemberAuth($db);
    $google = $reg['google'];
    $redirect = 'https://site.example/account/callback/google';

    try { $auth->start($google, $redirect, false); ok_m(false, 'start without consent must fail'); } catch (RuntimeException $e) { ok_m($e->getMessage() === 'CONSENT_REQUIRED', 'Consent is required before sign-in'); }
    $url = $auth->start($google, $redirect, true, $now);
    $pending = $_SESSION[MemberAuth::SESSION_PENDING];
    parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
    ok_m($q['state'] === $pending['state'] && $q['nonce'] === $pending['nonce'] && $q['code_challenge_method'] === 'S256' && $q['code_challenge'] === Jwt::b64url(hash('sha256', $pending['verifier'], true)) && $q['redirect_uri'] === $redirect, 'The authorize URL carries state, nonce and the PKCE challenge');

    // wrong state: refused and the pending state is gone (one use)
    ok_m($auth->callback($google, $redirect, 'wrong', 'code', null, $now)['error'] === 'STATE_MISMATCH', 'A wrong state is refused');
    ok_m(!isset($_SESSION[MemberAuth::SESSION_PENDING]) && $auth->callback($google, $redirect, $pending['state'], 'code', null, $now)['error'] === 'STATE_EXPIRED', 'The pending state can be used once only');

    // expired pending state
    $auth->start($google, $redirect, true, $now - 3600); $p2 = $_SESSION[MemberAuth::SESSION_PENDING];
    ok_m($auth->callback($google, $redirect, $p2['state'], 'code', null, $now)['error'] === 'STATE_EXPIRED', 'A pending state older than 10 minutes is refused');

    // provider says no / bad token
    $auth->start($google, $redirect, true, $now); $p3 = $_SESSION[MemberAuth::SESSION_PENDING];
    ok_m($auth->callback($google, $redirect, $p3['state'], null, 'access_denied', $now)['error'] === 'PROVIDER_DENIED', 'A cancelled sign-in is reported');
    $auth->start($google, $redirect, true, $now); $p4 = $_SESSION[MemberAuth::SESSION_PENDING];
    $http->routes['https://oauth2.googleapis.com/token'] = $tokenFor(['nonce' => 'not-the-one'] + $base);
    ok_m($auth->callback($google, $redirect, $p4['state'], 'code', null, $now)['error'] === 'PROVIDER_REJECTED' && !isset($_SESSION[MemberAuth::SESSION_MEMBER_ID]), 'A token with another nonce is refused and nobody is signed in');

    // success: a member is created, the verifier went to the token endpoint, the admin session is untouched
    $auth->start($google, $redirect, true, $now); $p5 = $_SESSION[MemberAuth::SESSION_PENDING];
    $http->routes['https://oauth2.googleapis.com/token'] = static fn($u, $f) => ['status' => 200, 'body' => json_encode(['id_token' => $sign(['nonce' => $p5['nonce']] + $base)])];
    $r = $auth->callback($google, $redirect, $p5['state'], 'code', null, $now);
    ok_m($r['ok'] && $r['created'] === true && $_SESSION[MemberAuth::SESSION_MEMBER_ID] === $r['member_id'], 'A first sign-in creates a member and signs them in: ' . json_encode($r));
    $tokenPosts = array_values(array_filter($http->calls, static fn($c) => $c[0] === 'POST' && $c[1] === 'https://oauth2.googleapis.com/token'));
    ok_m(end($tokenPosts)[2]['code_verifier'] === $p5['verifier'] && end($tokenPosts)[2]['redirect_uri'] === $redirect, 'The PKCE verifier and the same redirect URI are sent to the token endpoint');
    ok_m(($_SESSION['auth_user_id'] ?? null) === 777, 'The admin session key is not touched by a member sign-in');
    $m = $auth->current();
    ok_m($m !== null && $m['email'] === 'a@example.com' && $m['display_name'] === 'Somchai', 'The member keeps a lower-cased verified email and the name');
    $row = $db->query('SELECT terms_accepted_at FROM members WHERE id = ' . (int)$r['member_id'])->fetch();
    ok_m(!empty($row['terms_accepted_at']), 'The policy acceptance time is stored');

    // second sign-in: same member
    $auth->start($google, $redirect, true, $now); $p6 = $_SESSION[MemberAuth::SESSION_PENDING];
    $http->routes['https://oauth2.googleapis.com/token'] = static fn() => ['status' => 200, 'body' => json_encode(['id_token' => $sign(['nonce' => $p6['nonce']] + $base)])];
    $r2 = $auth->callback($google, $redirect, $p6['state'], 'code', null, $now);
    ok_m($r2['ok'] && $r2['created'] === false && $r2['member_id'] === $r['member_id'], 'The same provider account signs in as the same member');

    // an unverified email is not stored
    $auth->start($google, $redirect, true, $now); $p7 = $_SESSION[MemberAuth::SESSION_PENDING];
    $http->routes['https://oauth2.googleapis.com/token'] = static fn() => ['status' => 200, 'body' => json_encode(['id_token' => $sign(['nonce' => $p7['nonce'], 'sub' => '999', 'email_verified' => false] + $base)])];
    $r3 = $auth->callback($google, $redirect, $p7['state'], 'code', null, $now);
    ok_m($r3['ok'] && $r3['member_id'] !== $r['member_id'] && $db->query('SELECT email FROM members WHERE id = ' . (int)$r3['member_id'])->fetchColumn() === null, 'Another Google account is another member, and an unverified email is not kept');

    // Microsoft issuer must match the account's tenant
    $ms = OidcProvider::microsoft('mid', 'ms', $http);
    $http->routes['https://login.microsoftonline.com/common/discovery/v2.0/keys'] = static fn() => ['status' => 200, 'body' => json_encode($jwks)];
    $tid = '9188040d-6c67-4c5b-b112-36a304b66dad';
    $msBase = ['iss' => "https://login.microsoftonline.com/$tid/v2.0", 'tid' => $tid, 'aud' => 'mid', 'sub' => 'msub', 'exp' => $now + 600, 'iat' => $now, 'email' => 'x@hotmail.com', 'name' => 'X'];
    $auth->start($ms, $redirect, true, $now); $pm = $_SESSION[MemberAuth::SESSION_PENDING];
    $http->routes['https://login.microsoftonline.com/common/oauth2/v2.0/token'] = static fn() => ['status' => 200, 'body' => json_encode(['id_token' => $sign(['nonce' => $pm['nonce'], 'iss' => 'https://login.microsoftonline.com/00000000-0000-0000-0000-000000000000/v2.0'] + $msBase)])];
    ok_m($auth->callback($ms, $redirect, $pm['state'], 'code', null, $now)['error'] === 'PROVIDER_REJECTED', 'A Microsoft token whose issuer is another tenant than its tid is refused');
    $auth->start($ms, $redirect, true, $now); $pm2 = $_SESSION[MemberAuth::SESSION_PENDING];
    $http->routes['https://login.microsoftonline.com/common/oauth2/v2.0/token'] = static fn() => ['status' => 200, 'body' => json_encode(['id_token' => $sign(['nonce' => $pm2['nonce']] + $msBase)])];
    ok_m($auth->callback($ms, $redirect, $pm2['state'], 'code', null, $now)['ok'], 'A personal Microsoft (Hotmail) account signs in');

    // Facebook: Graph profile with appsecret_proof
    $fb = $reg['facebook'];
    $http->routes['https://graph.facebook.com/v19.0/oauth/access_token'] = static fn() => ['status' => 200, 'body' => json_encode(['access_token' => 'fbtoken'])];
    $http->routes['https://graph.facebook.com/v19.0/me'] = static fn() => ['status' => 200, 'body' => json_encode(['id' => '10001', 'name' => 'F', 'email' => 'f@example.com'])];
    $auth->start($fb, $redirect, true, $now); $pf = $_SESSION[MemberAuth::SESSION_PENDING];
    $rf = $auth->callback($fb, $redirect, $pf['state'], 'code', null, $now);
    $me = array_values(array_filter($http->calls, static fn($c) => str_starts_with($c[1], 'https://graph.facebook.com/v19.0/me')))[0];
    ok_m($rf['ok'] && str_contains($me[1], 'appsecret_proof=' . hash_hmac('sha256', 'fbtoken', 'fs')), 'Facebook signs in and the Graph call carries appsecret_proof');
    $http->routes['https://graph.facebook.com/v19.0/me'] = static fn() => ['status' => 200, 'body' => json_encode(['id' => '../../x'])];
    $auth->start($fb, $redirect, true, $now); $pf2 = $_SESSION[MemberAuth::SESSION_PENDING];
    ok_m($auth->callback($fb, $redirect, $pf2['state'], 'code', null, $now)['error'] === 'PROVIDER_REJECTED', 'A Facebook id that is not a number is refused');

    // the pending state of one provider cannot finish another provider
    $auth->start($google, $redirect, true, $now); $px = $_SESSION[MemberAuth::SESSION_PENDING];
    ok_m($auth->callback($fb, $redirect, $px['state'], 'code', null, $now)['error'] === 'STATE_EXPIRED', 'A state started for Google cannot complete a Facebook sign-in');

    // disabled member, delete account
    $db->prepare("UPDATE members SET status = 'disabled' WHERE id = :id")->execute(['id' => $r['member_id']]);
    ok_m((new MemberAuth($db))->current() === null || $_SESSION[MemberAuth::SESSION_MEMBER_ID] !== $r['member_id'], 'A disabled member is not current');
    $db->prepare("UPDATE members SET status = 'active' WHERE id = :id")->execute(['id' => $r['member_id']]);
    $db->prepare("INSERT INTO spec_builds (uuid, budget_min, budget_max, intended_use, member_id) VALUES (:u, 1, 2, 'gaming', :m)")->execute(['u' => 'test-' . bin2hex(random_bytes(8)), 'm' => $r['member_id']]);
    ok_m(count($auth->builds((int)$r['member_id'])) === 1, 'Saved builds are listed for their member');
    // the member also has a listing with a photo on disk: deleting the account must remove the files too (the database rows go by cascade, files do not)
    $photoDir = sys_get_temp_dir() . '/mi-del-photos-' . bin2hex(random_bytes(4));
    $luuid = bin2hex(random_bytes(16)); $pname = bin2hex(random_bytes(12)) . '.jpg';
    mkdir($photoDir . '/' . $luuid, 0777, true); file_put_contents($photoDir . '/' . $luuid . '/' . $pname, 'x');
    $otherUuid = bin2hex(random_bytes(16)); mkdir($photoDir . '/' . $otherUuid, 0777, true); file_put_contents($photoDir . '/' . $otherUuid . '/' . $pname, 'x');
    $pid = (int)$db->query('SELECT id FROM products ORDER BY id LIMIT 1')->fetchColumn();
    $db->prepare("INSERT INTO seller_listings (uuid, member_id, product_id, asking_price) VALUES (:u, :m, :p, 1000)")->execute(['u' => $luuid, 'm' => $r['member_id'], 'p' => $pid]);
    (new MemberAuth($db, new \App\Services\Listing\ListingPhotoStore($photoDir)))->deleteAccount((int)$r['member_id']);
    ok_m(!is_dir($photoDir . '/' . $luuid) && is_file($photoDir . '/' . $otherUuid . '/' . $pname), "Deleting the account removes that member's listing photos from disk and leaves everyone else's alone");
    @unlink($photoDir . '/' . $otherUuid . '/' . $pname); @rmdir($photoDir . '/' . $otherUuid); @rmdir($photoDir);
    ok_m((int)$db->query('SELECT COUNT(*) FROM members WHERE id = ' . (int)$r['member_id'])->fetchColumn() === 0 && (int)$db->query('SELECT COUNT(*) FROM member_identities WHERE member_id = ' . (int)$r['member_id'])->fetchColumn() === 0, 'Deleting the account removes the member and the identities');
    ok_m((int)$db->query('SELECT COUNT(*) FROM spec_builds WHERE member_id = ' . (int)$r['member_id'])->fetchColumn() === 0 && !isset($_SESSION[MemberAuth::SESSION_MEMBER_ID]), 'Builds lose their owner and the member is signed out');
} finally {
    $db->rollBack();
}
// wiring: the real controller must give MemberAuth the photo store, otherwise "delete my account" would leave the photos behind
$acc = (string)file_get_contents(dirname(__DIR__) . '/app/Controllers/AccountController.php');
ok_m(str_contains($acc, 'new MemberAuth(Database::connection($config[\'db\']), new \App\Services\Listing\ListingPhotoStore('), 'AccountController builds MemberAuth with the listing photo store');
echo "member_auth_test: PASS\n";
