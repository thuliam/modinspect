<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Services/Ops/EnvPreflight.php';

use App\Services\Ops\EnvPreflight as E;

/**
 * LS-7 N-4: pre-deploy check of a production .env (beta checklist C-4). Pure: takes the parsed values, returns named checks with pass/warn/fail and a
 * plain reason. It never returns, and the test proves it never leaks, a value: only key names appear in the output.
 */
function ok_ep(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$good = ['APP_ENV' => 'production', 'APP_URL' => 'https://modinspect.example.com', 'DB_HOST' => 'db.internal', 'DB_USERNAME' => 'mi_app', 'DB_PASSWORD' => 'S3CRET-VALUE-9f2',
    'MODINSPECT_LIVE_COLLECTION_ENABLED' => 'false', 'MODINSPECT_PAID_PROVIDER_CALLS_ENABLED' => 'false', 'GEMINI_PROVIDER_ENABLED' => 'false', 'GEMINI_API_KEY' => ''];
$r = E::evaluate($good, ['file_mode' => 0640, 'public_env_exists' => false]);
ok_ep($r['verdict'] === 'PASS' && $r['fail'] === 0, 'A clean production env passes: ' . json_encode($r));

$bad = static function (array $over, array $ctx = []) use ($good): array { return E::evaluate(array_merge($good, $over), $ctx + ['file_mode' => 0640, 'public_env_exists' => false]); };
$failed = static fn(array $r): array => array_values(array_map(static fn($c) => $c['name'], array_filter($r['checks'], static fn($c) => $c['status'] === 'fail')));
$warned = static fn(array $r): array => array_values(array_map(static fn($c) => $c['name'], array_filter($r['checks'], static fn($c) => $c['status'] === 'warn')));

// LS-14: the optional features' settings
$k32 = str_repeat('k', 32);
ok_ep(in_array('MEMBER_PROVIDERS', $failed($bad(['MEMBER_GOOGLE_CLIENT_ID' => 'id-only'])), true) && in_array('MEMBER_PROVIDERS', $failed($bad(['MEMBER_FACEBOOK_APP_SECRET' => 'secret-only'])), true), 'A provider with only an id or only a secret fails');
ok_ep($bad(['MEMBER_GOOGLE_CLIENT_ID' => 'i', 'MEMBER_GOOGLE_CLIENT_SECRET' => 's'])['verdict'] === 'PASS', 'A provider with both values, or none, passes');
ok_ep(in_array('SELLER_CHECK', $failed($bad(['SELLER_CHECK_ENABLED' => 'true'])), true) && in_array('SELLER_CHECK', $failed($bad(['SELLER_CHECK_ENABLED' => 'true', 'SELLER_CHECK_HMAC_KEY' => 'short'])), true), 'The seller check switched on without a 32-character key fails');
ok_ep(in_array('SELLER_CHECK', $warned($bad(['SELLER_CHECK_ENABLED' => 'true', 'SELLER_CHECK_HMAC_KEY' => $k32])), true) && !in_array('SELLER_CHECK', $failed($bad(['SELLER_CHECK_ENABLED' => 'true', 'SELLER_CHECK_HMAC_KEY' => $k32])), true), 'The seller check switched on with a key is a warning to confirm the lawyer review');
ok_ep(in_array('SELLER_LISTINGS_PUBLIC', $warned($bad(['SELLER_LISTINGS_PUBLIC' => 'true'])), true), 'Public seller listings are a warning to confirm HG-16');
ok_ep(!str_contains(json_encode($bad(['SELLER_CHECK_ENABLED' => 'true', 'SELLER_CHECK_HMAC_KEY' => 'SECRET-KEY-VALUE-ABCDEFGHIJKLMNOPQRSTUVWXYZ'])), 'SECRET-KEY-VALUE'), 'The key value never appears in the report');

foreach (['local', 'testing', 'staging', ''] as $env) { ok_ep(in_array('APP_ENV', $failed($bad(['APP_ENV' => $env])), true), "APP_ENV=[$env] fails"); }
$noEnv = $good; unset($noEnv['APP_ENV']);
ok_ep(in_array('APP_ENV', $failed(E::evaluate($noEnv, ['file_mode' => 0640, 'public_env_exists' => false])), true), 'A missing APP_ENV fails (it must be explicit)');
ok_ep(in_array('AUTH_OVERRIDES', $failed($bad(['AUTH_IDLE_TIMEOUT_SECONDS' => '99999'])), true), 'An AUTH_ override fails');
ok_ep(in_array('AUTH_OVERRIDES', $failed($bad(['AUTH_ABSOLUTE_LIFETIME_SECONDS' => '99999'])), true), 'The other AUTH_ override fails too');
foreach (['http://modinspect.example.com', 'https://localhost', 'https://127.0.0.1/x', 'https://', 'https://site.test', ''] as $u) { ok_ep(in_array('APP_URL', $failed($bad(['APP_URL' => $u])), true), "APP_URL [$u] fails"); }
foreach (['MODINSPECT_LIVE_COLLECTION_ENABLED', 'MODINSPECT_PAID_PROVIDER_CALLS_ENABLED', 'GEMINI_PROVIDER_ENABLED'] as $k) {
    ok_ep(in_array('SPEND_SWITCHES', $failed($bad([$k => 'true'])), true), "$k=true fails the zero-cost policy");
    ok_ep(in_array('SPEND_SWITCHES', $failed($bad([$k => '1'])), true), "$k=1 fails too");
}
ok_ep(in_array('DB_PASSWORD', $failed($bad(['DB_PASSWORD' => ''])), true), 'An empty database password fails');
ok_ep(in_array('DB_USERNAME', $warned($bad(['DB_USERNAME' => 'root'])), true), 'The root database user is a warning');
ok_ep(in_array('GEMINI_API_KEY', $warned($bad(['GEMINI_API_KEY' => 'abc'])), true), 'A provider key on the host is a warning');
ok_ep(in_array('ENV_FILE_LOCATION', $failed($bad([], ['public_env_exists' => true])), true), 'An .env inside the web root fails');
ok_ep(in_array('ENV_FILE_MODE', $warned($bad([], ['file_mode' => 0644])), true), 'A world-readable .env is a warning');
ok_ep(!in_array('ENV_FILE_MODE', array_merge($failed($bad([], ['file_mode' => null])), $warned($bad([], ['file_mode' => null]))), true), 'Unknown file mode (Windows) is skipped, not guessed');
ok_ep($bad(['APP_ENV' => 'local'])['verdict'] === 'FAIL' && $bad(['DB_USERNAME' => 'root'])['verdict'] === 'PASS_WITH_WARNINGS', 'Verdicts: any fail is FAIL, only warnings is PASS_WITH_WARNINGS');

// no value ever appears in the output
$all = json_encode(E::evaluate(array_merge($good, ['GEMINI_API_KEY' => 'KEY-VALUE-ZZZ', 'AUTH_IDLE_TIMEOUT_SECONDS' => '77777', 'APP_URL' => 'http://secret-host.example']), ['file_mode' => 0644, 'public_env_exists' => true]));
foreach (['S3CRET-VALUE-9f2', 'KEY-VALUE-ZZZ', '77777', 'secret-host.example', 'db.internal', 'mi_app'] as $v) { ok_ep(!str_contains((string)$all, $v), 'The output never contains a configured value: ' . $v); }
echo "env_preflight_test: PASS\n";
