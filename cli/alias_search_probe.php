<?php
declare(strict_types=1);

/**
 * LS-31 C1: read-only. For every active alias, search for the alias text and report where its own product lands in the results.
 * A product missing from its own alias's results is a defect; a product that is present but not first is listed for a person to judge
 * (a bare "3070" may fairly show the Ti next to the base card). SELECTs only.
 *   php cli/alias_search_probe.php [--all]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Product;

global $config;
$db = Database::connection($config['db']);
$rows = $db->query('SELECT a.id, a.alias_text, a.product_id, p.slug, p.full_name FROM product_aliases a JOIN products p ON p.id = a.product_id WHERE p.is_active = 1 ORDER BY a.id')->fetchAll(PDO::FETCH_ASSOC);
$model = new Product();
$missing = $notFirst = $first = 0;
foreach ($rows as $r) {
    $slugs = array_column($model->search((string)$r['alias_text']), 'slug');
    $pos = array_search($r['slug'], $slugs, true);
    if ($pos === false) { $missing++; printf("MISSING  #%d %-28s -> %s (results: %d)\n", $r['id'], $r['alias_text'], $r['full_name'], count($slugs)); }
    elseif ($pos > 0) { $notFirst++; printf("NOT-FIRST #%d %-27s -> %s is #%d of %d, first is %s\n", $r['id'], $r['alias_text'], $r['full_name'], $pos + 1, count($slugs), $slugs[0]); }
    else { $first++; if (in_array('--all', $argv, true)) { printf("first    #%d %s\n", $r['id'], $r['alias_text']); } }
}
printf("aliases %d: own product first %d, present but not first %d, missing %d\n", count($rows), $first, $notFirst, $missing);
exit($missing > 0 ? 1 : 0);
