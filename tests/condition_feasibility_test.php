<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\ConditionFeasibility as F;

/** LS-23 C3: the feasibility analysis needs enough rows on BOTH sides, is read-only, and never produces a baht adjustment. */
function ok_cf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$r = static fn(int $p, float $price, ?int $w, ?int $box, ?int $rc): array => ['product_id' => $p, 'price' => $price, 'warranty' => $w, 'box' => $box, 'receipt' => $rc];
$rows = [
    // product 1: 3 with box, 3 without: splittable on box; warranty known on 2 only
    $r(1, 9000, 6, 1, null), $r(1, 9200, 3, 1, null), $r(1, 9100, null, 1, null), $r(1, 8000, null, 0, null), $r(1, 8200, null, 0, null), $r(1, 8100, null, 0, null),
    // product 2: 4 with receipt, 2 without: NOT splittable
    $r(2, 5000, null, null, 1), $r(2, 5100, null, null, 1), $r(2, 5200, null, null, 1), $r(2, 5300, null, null, 1), $r(2, 4500, null, null, 0), $r(2, 4400, null, null, 0),
    // product 3: nothing recorded
    $r(3, 3000, null, null, null),
];
$s = F::summarize($rows);
ok_cf($s['box']['products_with_data'] === 1 && $s['box']['products_splittable'] === 1, 'Product 1 can be split by box, nobody else has the fact');
ok_cf($s['box']['examples'][0]['median_with'] === 9100.0 && $s['box']['examples'][0]['median_without'] === 8100.0 && $s['box']['examples'][0]['gap_percent'] === 12.3, 'Medians and the gap are computed from the two groups: ' . json_encode($s['box']['examples'][0]));
ok_cf($s['receipt']['products_with_data'] === 1 && $s['receipt']['products_splittable'] === 0, 'Four with and two without is not enough on the small side');
ok_cf($s['warranty']['products_with_data'] === 1 && $s['warranty']['products_splittable'] === 0, 'A fact recorded on a few rows is counted as data but not as splittable');
ok_cf(F::summarize([]) === ['warranty' => ['products_with_data' => 0, 'products_splittable' => 0, 'examples' => []], 'box' => ['products_with_data' => 0, 'products_splittable' => 0, 'examples' => []], 'receipt' => ['products_with_data' => 0, 'products_splittable' => 0, 'examples' => []]], 'No rows, no claim');

global $config;
$db = \App\Core\Database::connection($config['db']);
$real = F::rows($db);
ok_cf(is_array($real) && array_reduce($real, static fn(bool $c, array $x): bool => $c && $x['price'] > 0, true), 'The query returns priced rows only');
$src = (string)file_get_contents(ROOT_PATH . '/app/Services/Admin/ConditionFeasibility.php') . (string)file_get_contents(ROOT_PATH . '/cli/condition_feasibility.php');
ok_cf(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', $src), 'The analysis is SELECT-only');
echo "condition_feasibility_test: PASS\n";
