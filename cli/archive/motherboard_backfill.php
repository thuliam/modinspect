<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\MotherboardFormat;

/**
 * LS-73: fills the chipset of motherboards that lack it from their own model name (empty columns only, never overwrites) and reports boards whose facts are
 * missing or impossible (an AM5 board that is DDR4, a form factor spelled in a non-standard way). Dry run by default; --apply writes.
 * Usage: php cli/archive/motherboard_backfill.php [--apply]
 */
global $config;
$apply = getopt('', ['apply']) !== [];
$db = Database::connection($config['db']);
$rows = $db->query("SELECT p.id, p.slug, p.model_name, p.full_name, p.socket, p.chipset, p.memory_type, p.form_factor FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug = 'motherboard' ORDER BY p.id")->fetchAll();
$plan = []; $issues = [];
foreach ($rows as $r) {
    $set = [];
    if (trim((string) $r['chipset']) === '') { $c = MotherboardFormat::chipsetFromName($r['model_name'] . ' ' . $r['full_name']); if ($c !== null) { $set['chipset'] = $c; } }
    $ff = MotherboardFormat::formFactor((string) $r['form_factor']);
    if ($ff !== null && $ff !== $r['form_factor']) { $set['form_factor'] = $ff; }
    $after = array_merge($r, $set);
    $err = MotherboardFormat::check(['socket' => $after['socket'], 'chipset' => $after['chipset'], 'memory_type' => $after['memory_type'], 'form_factor' => $after['form_factor']]);
    if ($err !== null) { $issues[] = $r['slug'] . ': ' . $err; }
    if ($set !== []) { $plan[] = ['id' => (int) $r['id'], 'slug' => $r['slug'], 'set' => $set]; }
}
$done = 0;
if ($apply) {
    $db->beginTransaction();
    try {
        foreach ($plan as $p) { $db->prepare('UPDATE products SET ' . implode(', ', array_map(static fn ($c) => "$c = :$c", array_keys($p['set']))) . ' WHERE id = :id')->execute($p['set'] + ['id' => $p['id']]); $done++; }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); fwrite(STDERR, 'failed, nothing was saved: ' . $e->getMessage() . "\n"); exit(1); }
}
echo json_encode(['apply' => $apply, 'boards' => count($rows), 'to_fill' => count($plan), 'updated' => $done, 'issues' => $issues, 'plan' => array_map(static fn ($p) => $p['slug'] . ': ' . json_encode($p['set']), $plan)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
