<?php
declare(strict_types=1);

/**
 * LS-28 A1: read-only audit of product aliases that could send a visitor to the wrong product. SELECTs only.
 *   php cli/alias_audit.php [--json]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\AliasAudit;

global $config;
$db = Database::connection($config['db']);
$aliases = array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'product_id' => (int)$r['product_id'], 'alias_text' => (string)$r['alias_text']],
    $db->query('SELECT a.id, a.product_id, a.alias_text FROM product_aliases a JOIN products p ON p.id = a.product_id WHERE p.is_active = 1 ORDER BY a.id')->fetchAll(PDO::FETCH_ASSOC));
$names = [];
foreach ($db->query('SELECT id, full_name FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $r) { $names[(int)$r['id']] = (string)$r['full_name']; }
$r = AliasAudit::audit($aliases, $names);
if (in_array('--json', $argv, true)) { echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL; exit(0); }
echo "aliases checked: {$r['aliases']}\n";
foreach ($r['counts'] as $k => $n) { printf("  %-18s %d\n", $k, $n); }
foreach ($r['findings'] as $f) {
    printf("%-18s alias #%d %-30s product %d%s\n", $f['kind'], $f['alias_id'], mb_substr($f['alias'], 0, 30), $f['product_id'], $f['other_product_id'] ? ' vs ' . $f['other_product_id'] : '');
}
