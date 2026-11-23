<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\RamFormat;

/**
 * LS-71: fills the RAM facts (memory_type, memory_speed_mhz, capacity_gb, kit_modules, form_factor) of the RAM products that are still empty, from what their own
 * name and summary state. Never overwrites a value that is already there and never guesses a number the text does not give; form_factor defaults to DIMM
 * (desktop), the owner's decision of 2026-11-23 for this PC catalog. Dry run by default; --apply writes. Prints the products whose facts are still incomplete.
 * Usage: php cli/ram_backfill.php [--apply]
 */
global $config;
$apply = getopt('', ['apply']) !== [];
$db = Database::connection($config['db']);
try { $db->query('SELECT memory_speed_mhz, kit_modules FROM products LIMIT 1'); } catch (PDOException) { fwrite(STDERR, "The RAM columns are missing on this database (apply the RAM migration first).\n"); exit(3); }
$rows = $db->query("SELECT p.id, p.slug, p.full_name, p.model_name, p.spec_summary, p.memory_type, p.form_factor, p.capacity_gb, p.memory_speed_mhz, p.kit_modules FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug = 'ram' ORDER BY p.id")->fetchAll();
$cols = ['memory_type', 'memory_speed_mhz', 'capacity_gb', 'kit_modules', 'form_factor'];
$plan = []; $incomplete = [];
foreach ($rows as $r) {
    $f = RamFormat::parse($r['full_name'] . ' ' . $r['model_name'] . ' ' . (string) $r['spec_summary']);
    if ($f['form_factor'] === null) { $f['form_factor'] = 'DIMM'; }
    $set = [];
    foreach ($cols as $c) { if (($r[$c] === null || $r[$c] === '') && $f[$c] !== null) { $set[$c] = $f[$c]; } }
    $after = array_merge(array_intersect_key($r, array_flip($cols)), $set);
    $miss = array_values(array_filter(['memory_type', 'memory_speed_mhz', 'capacity_gb'], static fn ($c) => ($after[$c] ?? null) === null || $after[$c] === ''));
    if ($miss !== []) { $incomplete[] = $r['slug'] . ' lacks ' . implode(', ', $miss); }
    if ($set !== []) { $plan[] = ['id' => (int) $r['id'], 'slug' => $r['slug'], 'set' => $set]; }
}
$done = 0;
if ($apply) {
    $db->beginTransaction();
    try {
        foreach ($plan as $p) {
            $sql = 'UPDATE products SET ' . implode(', ', array_map(static fn ($c) => "$c = :$c", array_keys($p['set']))) . ' WHERE id = :id';
            $db->prepare($sql)->execute($p['set'] + ['id' => $p['id']]); $done++;
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); fwrite(STDERR, 'failed, nothing was saved: ' . $e->getMessage() . "\n"); exit(1); }
}
echo json_encode(['apply' => $apply, 'ram_products' => count($rows), 'to_fill' => count($plan), 'updated' => $done, 'incomplete' => $incomplete, 'plan' => array_map(static fn ($p) => $p['slug'] . ': ' . json_encode($p['set']), $plan)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
