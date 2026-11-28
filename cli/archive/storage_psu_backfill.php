<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\PsuFormat;
use App\Services\Catalog\StorageFormat;

/**
 * LS-74: fills the storage and PSU facts that are still empty (storage: interface, media, form factor, capacity; PSU: wattage, 80 PLUS rating, cable type, form factor)
 * from what each product's own name and summary state. Empty columns only, never overwrites, never guesses (a PSU form factor stays empty unless the text says it).
 * Dry run by default; --apply writes. Prints what is still incomplete. Usage: php cli/archive/storage_psu_backfill.php [--apply]
 */
global $config;
$apply = getopt('', ['apply']) !== [];
$db = Database::connection($config['db']);
try { $db->query('SELECT interface_type, media_type, power_watt, efficiency_rating, modular_type FROM products LIMIT 1'); } catch (PDOException) { fwrite(STDERR, "The storage/PSU columns are missing on this database (apply the storage and PSU migration first).\n"); exit(3); }
$plan = []; $incomplete = []; $total = 0;
foreach (['storage' => ['interface_type', 'media_type', 'form_factor', 'capacity_gb'], 'psu' => ['power_watt', 'efficiency_rating', 'modular_type', 'form_factor']] as $cat => $cols) {
    $st = $db->prepare('SELECT p.id, p.slug, p.full_name, p.model_name, p.spec_summary, p.interface_type, p.media_type, p.form_factor, p.capacity_gb, p.power_watt, p.efficiency_rating, p.modular_type FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug = :c ORDER BY p.id');
    $st->execute(['c' => $cat]);
    foreach ($st->fetchAll() as $r) {
        $total++;
        $f = $cat === 'storage' ? StorageFormat::parse($r['full_name'] . ' ' . $r['model_name'] . ' ' . (string) $r['spec_summary']) : PsuFormat::parse($r['full_name'] . ' ' . $r['model_name'] . ' ' . (string) $r['spec_summary']);
        $set = [];
        foreach ($cols as $c) { if (($r[$c] === null || $r[$c] === '') && ($f[$c] ?? null) !== null) { $set[$c] = $f[$c]; } }
        $after = array_merge($r, $set);
        $miss = array_values(array_filter($cols, static fn ($c) => ($after[$c] ?? null) === null || $after[$c] === ''));
        if ($miss !== []) { $incomplete[] = $r['slug'] . ' lacks ' . implode(', ', $miss); }
        if ($set !== []) { $plan[] = ['id' => (int) $r['id'], 'slug' => $r['slug'], 'set' => $set]; }
    }
}
$done = 0;
if ($apply) {
    $db->beginTransaction();
    try {
        foreach ($plan as $p) { $db->prepare('UPDATE products SET ' . implode(', ', array_map(static fn ($c) => "$c = :$c", array_keys($p['set']))) . ' WHERE id = :id')->execute($p['set'] + ['id' => $p['id']]); $done++; }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); fwrite(STDERR, 'failed, nothing was saved: ' . $e->getMessage() . "\n"); exit(1); }
}
echo json_encode(['apply' => $apply, 'products' => $total, 'to_fill' => count($plan), 'updated' => $done, 'incomplete' => $incomplete, 'plan' => array_map(static fn ($p) => $p['slug'] . ': ' . json_encode($p['set'], JSON_UNESCAPED_UNICODE), $plan)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
