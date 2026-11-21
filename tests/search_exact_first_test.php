<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Product;

/** LS-31 C2: a model whose full name, model name or alias equals the query is listed before models that only contain it. Property check over the whole test catalog; reads only. */
function ok_sx(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_sx($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$model = new Product();
$checked = 0;
$pairs = [];
foreach ($db->query('SELECT id, full_name, model_name FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $pairs[] = [(int)$p['id'], (string)$p['full_name']];
    if ((string)$p['model_name'] !== '') { $pairs[] = [(int)$p['id'], (string)$p['model_name']]; }
}
foreach ($db->query('SELECT a.product_id, a.alias_text FROM product_aliases a JOIN products p ON p.id = a.product_id WHERE p.is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $a) { $pairs[] = [(int)$a['product_id'], (string)$a['alias_text']]; }
ok_sx(count($pairs) >= 20, 'The test catalog is too small to mean anything: ' . count($pairs));
foreach ($pairs as [$pid, $text]) {
    $ids = array_map('intval', array_column($model->search($text), 'id'));
    ok_sx(in_array($pid, $ids, true), "Product $pid is found by its own name or alias '$text'");
    // everyone whose name or alias is exactly this text may come first; no product that merely contains it may come before such a product
    $exactStmt = $db->prepare('SELECT p.id FROM products p WHERE p.is_active = 1 AND (p.full_name = :a OR p.model_name = :b OR EXISTS (SELECT 1 FROM product_aliases x WHERE x.product_id = p.id AND x.alias_text = :c))');
    $exactStmt->execute(['a' => $text, 'b' => $text, 'c' => $text]);
    $exact = array_map('intval', $exactStmt->fetchAll(PDO::FETCH_COLUMN));
    $firstOther = null;
    foreach ($ids as $i => $id) { if (!in_array($id, $exact, true)) { $firstOther = $i; break; } }
    foreach ($exact as $id) {
        $pos = array_search($id, $ids, true);
        ok_sx($pos !== false && ($firstOther === null || $pos < $firstOther), "Exact match $id for '$text' comes before models that only contain it");
    }
    $checked++;
}
ok_sx($model->search('') !== [], 'An empty query still lists the catalog');

// the case that mattered: a popular model with a longer name ("... Ti") must not outrank the plain model when its exact name is typed
$db->beginTransaction();
try {
    $base = $db->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $db->prepare('UPDATE products SET is_popular = 0 WHERE id = :id')->execute(['id' => $base['id']]);
    $clone = $base;
    unset($clone['id']);
    $clone['full_name'] = $base['full_name'] . ' Ti'; $clone['model_name'] = $base['model_name'] . ' Ti'; $clone['slug'] = $base['slug'] . '-ti-probe'; $clone['is_popular'] = 1;
    foreach (array_keys($clone) as $col) { if (str_ends_with($col, '_at') && $clone[$col] === null) { unset($clone[$col]); } }
    $cols = array_keys($clone);
    $db->prepare('INSERT INTO products (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')')->execute($clone);
    $slugs = array_column($model->search($base['full_name']), 'slug');
    ok_sx(($slugs[0] ?? '') === $base['slug'] && in_array($base['slug'] . '-ti-probe', $slugs, true), 'The plain model is first and the longer-named popular one is still listed: ' . json_encode($slugs));
    $slugs2 = array_column($model->search($base['full_name'] . ' Ti'), 'slug');
    ok_sx(($slugs2[0] ?? '') === $base['slug'] . '-ti-probe', 'Typing the longer name puts the longer-named model first');
} finally {
    $db->rollBack();
}
echo "search_exact_first_test: PASS ($checked names and aliases)\n";
