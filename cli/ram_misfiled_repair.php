<?php
declare(strict_types=1);

/**
 * LS-77: find APPROVED RAM price rows whose own title contradicts the product they sit under (a 16GB kit approved under a 32GB class) and EXCLUDE them
 * through Dashboard::decideObservation (audit and decision record, recorded under --actor), then rebuild the snapshot of each affected product.
 * Excluding is reversible and keeps the evidence; it does not guess a new product. Dry run unless --apply; --apply refuses unless --expect equals the row count.
 * Usage: php cli/ram_misfiled_repair.php --actor=<admin id> [--apply --expect=<rows>]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Catalog\SpecConsistency;
use App\Services\Pricing\PriceSnapshotService;

$o = getopt('', ['actor:', 'apply', 'expect::']);
$actor = (int)($o['actor'] ?? 0);
if ($actor <= 0) { fwrite(STDERR, "--actor=<admin user id> is required\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
$chk = $db->prepare('SELECT role FROM users WHERE id=:id AND is_active=1');
$chk->execute(['id' => $actor]);
if (!in_array((string)$chk->fetchColumn(), ['admin', 'reviewer'], true)) { fwrite(STDERR, "Actor must be an active admin or reviewer\n"); exit(2); }

$rows = $db->query("SELECT o.id, o.product_id, p.full_name, p.memory_type, p.memory_speed_mhz, p.capacity_gb, COALESCE(o.price_value,o.asking_price) price, r.raw_title
    FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id
    WHERE c.name='RAM' AND o.price_type='asking' AND o.verified_status='approved' ORDER BY o.product_id, o.id")->fetchAll();
$bad = [];
foreach ($rows as $r) {
    $c = SpecConsistency::ramConflicts((string)$r['raw_title'], $r);
    if ($c) { $bad[] = $r + ['why' => implode('; ', $c)]; }
}
foreach ($bad as $r) { printf("#%d %-30s %8.0f  %s | %s\n", $r['id'], $r['full_name'], $r['price'], $r['why'], mb_strimwidth((string)$r['raw_title'], 0, 60)); }
$n = count($bad);
$products = array_values(array_unique(array_map(static fn($r) => (int)$r['product_id'], $bad)));
echo "\napproved RAM rows contradicting their product: $n, products affected: " . count($products) . "\n";
if (!isset($o['apply'])) { echo "Dry run: nothing written. Add --apply --expect=$n to exclude them.\n"; exit(0); }
if (!isset($o['expect']) || (int)$o['expect'] !== $n) { fwrite(STDERR, "Refused: --expect must equal $n.\n"); exit(2); }

$dash = new Dashboard();
$ok = 0; $failed = 0;
foreach ($bad as $r) {
    $note = 'Excluded as misfiled: the title states ' . $r['why'] . ' relative to the product it was filed under. Evidence kept; recorded by the agent under the owner\'s account.';
    $dash->decideObservation((int)$r['id'], 'excluded', $note, $actor) ? $ok++ : $failed++;
}
echo "excluded=$ok failed=$failed\n";
$snap = new PriceSnapshotService();
foreach ($products as $pid) { $s = $snap->recalculateProduct($pid, 'asking'); echo "product #$pid " . ($s ? 'snapshot #' . $s['snapshot_id'] : 'no snapshot (fewer than 3 approved rows)') . "\n"; }
