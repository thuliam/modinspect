<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeDailyRun;
use App\Services\Intake\IntakeValidator;

/**
 * LS-23 E1: one run over a batch folder. A dry run writes nothing; a dataset with a problem is skipped whole; with --apply (inside a transaction here) clean
 * datasets are imported as PENDING rows, nothing is approved, nothing is migrated, and the report never repeats a title, price or link.
 */
function ok_dr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_dr($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$c = IntakeValidator::contract();
$tag = bin2hex(random_bytes(3)); $secret = 'ZZSECRET' . strtoupper($tag);
$product = (string)$db->query("SELECT full_name FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$mk = static function (string $dir, array $files): void { @mkdir($dir, 0700, true); foreach ($files as $name => [$header, $rows]) { $h = fopen($dir . '/' . $name, 'w'); fputcsv($h, $header, ',', '"', ''); foreach ($rows as $r) { fputcsv($h, $r, ',', '"', ''); } fclose($h); } };
$cols = static fn(string $k): array => array_column($c['datasets'][$k]['columns'], 'name');
$today = date('Y-m-d');
$fixture = static fn(string $title): array => [
    'A_used_listings.csv' => [$cols('A'), [['owner_transcription', "https://www.facebook.com/marketplace/item/94{$tag}01/", "DR-$tag-1", $title, '8200', '2026-11-14 12:00:00', 'used']]],
    'A_used_listings_extra.csv' => [$cols('A_extra'), [["DR-$tag-1", '', '6', '1', '0', '14', 'นนทบุรี', '', '']]],
    'B_new_price_references.csv' => [$cols('B'), [[$product, '3290', 'https://shop.example.co.th/p/' . $tag, $today, '', '']]],
    'F_sold_prices.csv' => [$cols('F'), [["https://pantip.com/topic/94{$tag}", "DR-F-$tag", 'NVIDIA GeForce RTX 3070 ขายแล้ว', '8100', 'seller_post', '2026-11-14 12:00:00', '8500', 'used']]],
    'C_reference_specs.csv' => [$cols('C'), [[$product, 'speed_mhz', '3200', 'MHz', 'https://www.example-maker.com/p/1', '2026-11-10', 'official_checked', '']]],
];
$dir = sys_get_temp_dir() . '/mi_dr_' . bin2hex(random_bytes(4));
$work = $dir . '/work';
$mk($dir . '/ok', $fixture('NVIDIA GeForce RTX 3070 8GB used'));
$mk($dir . '/bad', $fixture("NVIDIA GeForce RTX 3070 $secret โทร 081-234-5678"));

$tables = ['raw_price_observations', 'price_observations', 'new_price_references', 'product_reference_specs', 'products', 'price_indices', 'data_sources'];
$snap = static fn(): array => array_map(static fn(string $t): int => (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn(), array_combine($tables, $tables));
$approved = static fn(): int => (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn();

$db->beginTransaction();
try {
    $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('dr test', :e, 'x', 'admin')")->execute(['e' => 'dr-' . bin2hex(random_bytes(4)) . '@example.test']);
    $admin = (int)$db->lastInsertId();
    $before = $snap(); $ap = $approved();

    $r = IntakeDailyRun::run($db, $dir . '/ok', false, 0, $work);
    ok_dr($r['mode'] === 'dry run' && $r['skipped'] === [], 'A clean batch is not skipped: ' . json_encode($r['skipped'] + $r['files']));
    ok_dr($snap() === $before, 'A dry run writes nothing');
    foreach (['A_used_listings', 'B_new_prices', 'F_sold_prices', 'C_specs'] as $step) { ok_dr(isset($r['steps'][$step]), "Step $step is reported"); }
    ok_dr($r['steps']['B_new_prices']['to_add'] === 1 && $r['steps']['F_sold_prices']['to_add'] === 1 && $r['steps']['C_specs']['would_insert_specs'] === 1, 'Each step says what it would add');
    ok_dr(isset($r['snapshot_plan']['counts']), 'The report ends with what a recalculation would change');

    $bad = IntakeDailyRun::run($db, $dir . '/bad', false, 0, $work);
    ok_dr(isset($bad['skipped']['A']) && !isset($bad['steps']['A_used_listings']) && isset($bad['steps']['B_new_prices']), 'A dataset with a problem is skipped whole; the clean ones still run');
    ok_dr(!str_contains(json_encode($bad, JSON_UNESCAPED_UNICODE), $secret) && !str_contains(json_encode($bad), '0812345678') && !str_contains(json_encode($bad), 'facebook.com'), 'The report never repeats a title, number or link');

    $none = IntakeDailyRun::run($db, $dir . '/ok', true, 0, $work);
    ok_dr(($none['steps']['B_new_prices']['error'] ?? '') === 'ACTOR_REQUIRED' && ($none['steps']['F_sold_prices']['error'] ?? '') === 'ACTOR_REQUIRED', 'Apply without an actor writes no reviewed data');

    $a = IntakeDailyRun::run($db, $dir . '/ok', true, $admin, $work);
    $after = $snap();
    ok_dr($none['steps']['A_used_listings']['candidates_created'] === 1, 'Used listings need no actor: the first apply created the candidate');
    ok_dr($a['steps']['B_new_prices']['added'] === 1 && $a['steps']['F_sold_prices']['added'] === 1 && $a['steps']['A_used_listings']['candidates_created'] === 0, 'Apply with an actor imports B and F, and the listing already imported is not added twice: ' . json_encode($a['steps']));
    ok_dr($after['new_price_references'] === $before['new_price_references'] + 1 && $after['raw_price_observations'] >= $before['raw_price_observations'] + 2, 'Rows landed in their own tables');
    ok_dr($after['product_reference_specs'] === $before['product_reference_specs'] && $after['products'] === $before['products'], 'Specs and models are only reported: no migration was applied');
    ok_dr($after['price_indices'] === $before['price_indices'] && $approved() === $ap, 'No snapshot was created and nothing was approved');
} finally {
    $db->rollBack();
    foreach (['ok', 'bad', 'work'] as $d) { foreach (glob($dir . '/' . $d . '/*') ?: [] as $f) { @unlink($f); } @rmdir($dir . '/' . $d); }
    @rmdir($dir);
}
$src = (string)file_get_contents(ROOT_PATH . '/app/Services/Intake/IntakeDailyRun.php');
ok_dr(!str_contains($src, "'approved'") && !preg_match('/\bcli\/migrate|exec\(|shell_exec|\bproc_open/', $src), 'The daily run never approves and never applies a migration');
echo "intake_daily_run_test: PASS\n";
