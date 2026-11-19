<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Services/Ops/EnvPreflight.php';

use App\Services\Ops\EnvPreflight as E;

/** LS-24 M5/M6: the preflight knows the community switches and what the host must be able to do. Pure; only key names and folder names are ever printed. */
function ok_e24(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$good = ['APP_ENV' => 'production', 'APP_URL' => 'https://modinspect.example.com', 'DB_USERNAME' => 'mi_app', 'DB_PASSWORD' => 'S3CRET-VALUE-9f2', 'MODINSPECT_LIVE_COLLECTION_ENABLED' => 'false', 'MODINSPECT_PAID_PROVIDER_CALLS_ENABLED' => 'false', 'GEMINI_PROVIDER_ENABLED' => 'false'];
$ctxOk = ['file_mode' => 0640, 'public_env_exists' => false, 'extensions' => ['mbstring' => true, 'pdo_mysql' => true, 'json' => true, 'zip' => true, 'gd_freetype' => true], 'writable' => ['storage/cache' => true, 'storage/logs' => true]];
$run = static fn(array $env, array $ctx = []): array => E::evaluate(array_merge($good, $env), $ctx + $ctxOk);
$by = static fn(array $r, string $n): ?array => array_values(array_filter($r['checks'], static fn($c) => $c['name'] === $n))[0] ?? null;

ok_e24($run([])['verdict'] === 'PASS', 'A clean host and env pass: ' . json_encode($run([])['checks']));
ok_e24($by($run(['COMMUNITY_REPORTS_ENABLED' => 'true']), 'COMMUNITY_REPORTS')['status'] === 'fail' && $by($run(['COMMUNITY_REPORTS_ENABLED' => 'true', 'COMMUNITY_REPORTS_HMAC_KEY' => 'short']), 'COMMUNITY_REPORTS')['status'] === 'fail', 'Community reports on without a real key is a failure');
ok_e24($by($run(['COMMUNITY_REPORTS_ENABLED' => 'true', 'COMMUNITY_REPORTS_HMAC_KEY' => str_repeat('k', 32)]), 'COMMUNITY_REPORTS')['status'] === 'warn', 'Community reports on with a key is a reminder to confirm the privacy wording');
ok_e24($by($run(['COMMUNITY_FORUM_ENABLED' => 'true']), 'COMMUNITY_FORUM')['status'] === 'warn' && $by($run([]), 'COMMUNITY_FORUM')['status'] === 'pass', 'The forum switch on is a reminder, off is a pass');
ok_e24($by($run([], ['extensions' => ['mbstring' => true, 'pdo_mysql' => false, 'json' => true, 'zip' => true, 'gd_freetype' => true]]), 'PHP_EXTENSIONS')['status'] === 'fail' && str_contains($by($run([], ['extensions' => ['mbstring' => true, 'pdo_mysql' => false, 'json' => true]]), 'PHP_EXTENSIONS')['reason'], 'pdo_mysql'), 'A missing required extension fails and is named');
$opt = $by($run([], ['extensions' => ['mbstring' => true, 'pdo_mysql' => true, 'json' => true, 'zip' => false, 'gd_freetype' => false]]), 'PHP_OPTIONAL');
ok_e24($opt['status'] === 'warn' && str_contains($opt['reason'], 'GD with FreeType') && str_contains($opt['reason'], 'zip'), 'Missing GD/FreeType or zip is a warning that names the feature');
$w = $by($run([], ['writable' => ['storage/cache' => true, 'storage/logs' => false, 'storage/uploads' => false]]), 'STORAGE_WRITABLE');
ok_e24($w['status'] === 'fail' && str_contains($w['reason'], 'storage/logs') && str_contains($w['reason'], 'storage/uploads') && !str_contains($w['reason'], 'storage/cache,'), 'Folders that are not writable fail and are named');
ok_e24(!str_contains(json_encode($run(['DB_PASSWORD' => 'TOPSECRETVALUE', 'COMMUNITY_REPORTS_HMAC_KEY' => 'ANOTHERSECRETKEY' . str_repeat('x', 20)])), 'SECRET'), 'No value is ever printed');
ok_e24($by(E::evaluate($good, ['file_mode' => 0640, 'public_env_exists' => false]), 'PHP_EXTENSIONS') === null, 'Without host facts the extension and folder checks are simply absent (older callers keep working)');
echo "env_preflight_ls24_test: PASS\n";
