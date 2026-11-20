<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeValidator;

/** LS-24 M2: the cron-safe daily command. Dry run by default and writes nothing to the database, --apply needs an admin actor, the log line holds counts only. */
function ok_dp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_dp($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$tables = ['raw_price_observations', 'price_observations', 'new_price_references', 'price_indices', 'products', 'users'];
$count = static fn(): array => array_map(static fn(string $t): int => (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn(), array_combine($tables, $tables));
$run = static function (string $args) use (&$out): int { $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/daily_pipeline.php') . ' ' . $args . ' 2>&1', $out, $code); return $code; };

$secret = 'ZZSECRET' . strtoupper(bin2hex(random_bytes(3)));
$dir = sys_get_temp_dir() . '/mi_dp_' . bin2hex(random_bytes(4)); mkdir($dir, 0700, true);
$cols = array_column(IntakeValidator::contract()['datasets']['A']['columns'], 'name');
$h = fopen($dir . '/A_used_listings.csv', 'w'); fputcsv($h, $cols, ',', '"', '');
fputcsv($h, ['owner_transcription', 'https://www.facebook.com/marketplace/item/95' . random_int(1000, 9999) . '/', 'DP-' . $secret, "NVIDIA GeForce RTX 3070 $secret 081-234-5678", '8200', '2026-11-14 12:00:00', 'used'], ',', '"', ''); fclose($h);

$before = $count();
$code = $run(escapeshellarg($dir) . ' --json');
$j = json_decode(implode("\n", $out), true);
ok_dp($code === 1 && is_array($j) && $j['mode'] === 'dry run' && $j['batch']['skipped'] === ['A'], 'A batch with a phone number in a title is skipped whole and the exit code says so: ' . implode(' ', array_slice($out, 0, 3)));
ok_dp($count() === $before, 'A dry run writes nothing to the database');
ok_dp(!str_contains(implode("\n", $out), $secret) && !str_contains(implode("\n", $out), '0812345678') && !str_contains(implode("\n", $out), 'facebook.com'), 'The summary never repeats a title, number or link');
$log = (string)file_get_contents(ROOT_PATH . '/storage/logs/daily_pipeline.log');
ok_dp(str_contains($log, '"mode":"dry run"') && !str_contains($log, $secret) && !str_contains($log, 'facebook'), 'The log line is counts only');
ok_dp($run('--apply') === 2 && $run('--apply --actor=999999999') === 2, '--apply without a real admin actor stops before doing anything');
ok_dp($run('') === 0 && str_contains(implode("\n", $out), 'dry run | snapshots'), 'With no batch it reports the snapshot plan and coverage and exits 0');
foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); } @rmdir($dir);
$src = (string)file_get_contents(ROOT_PATH . '/cli/daily_pipeline.php');
ok_dp(!str_contains($src, "'approved'") && !str_contains($src, 'cli/migrate'), 'The command never approves and never applies a migration');
echo "daily_pipeline_test: PASS\n";
