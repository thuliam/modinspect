<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Product;
use App\Services\Search\SearchQuery as S;

/**
 * Public search (LS-3 J-5): pure helpers, then the golden queries against the real search on whatever catalog this database holds,
 * and the invariant that matters most for a change to a working feature: the new search NEVER drops a result the old one gave.
 */
function ok_sq(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- pure helpers
ok_sq(S::compact('RTX 3070-Ti_x.y/z') === 'rtx3070tixyz', 'compact drops separators and lower-cases');
ok_sq(S::compact('การ์ดจอ 3070') === 'การ์ดจอ3070', 'compact keeps Thai letters');
ok_sq(S::words('  NVMe   1TB ') === ['nvme', '1tb'], 'words splits on spaces');
ok_sq(S::words('การ์ดจอ 3070') === ['3070'], 'a Thai-only word is dropped when another word carries the model');
ok_sq(S::words('การ์ดจอ') === ['การ์ดจอ'], 'a query made only of Thai words keeps them');
ok_sq(S::words('!!! ---') === [], 'a query of symbols has no words');
ok_sq(S::joinedPattern('3070ti') === '(^|[^a-z0-9])3[^a-z0-9]*0[^a-z0-9]*7[^a-z0-9]*0[^a-z0-9]*t[^a-z0-9]*i', 'the joined pattern starts at a word and allows separators between characters');
ok_sq(S::joinedPattern('a') === null && S::joinedPattern(str_repeat('a', 41)) === null && S::joinedPattern('การ์ด') === null, 'too short, too long or non-ASCII text gets no pattern');
ok_sq(preg_match('/' . str_replace('/', '\/', S::joinedPattern('3070ti')) . '/', 'nvidia geforce rtx 3070 ti') === 1, 'the pattern matches "3070 ti"');
ok_sq(preg_match('/' . S::joinedPattern('r55600') . '/', 'ram ddr5-5600 16gb') === 0, 'the pattern does not match the tail of DDR5-5600 for "r5 5600"');
ok_sq(preg_match('/' . S::joinedPattern('i512400f') . '/', 'intel core i5-12400f') === 1, 'the pattern bridges a hyphen');

$x = S::extraConditions('nvme 1tb');
preg_match_all('/:(qc_\d+)/', $x['sql'], $m);
ok_sq($x['sql'] !== '' && count($m[1]) === count(array_unique($m[1])) && array_keys($x['params']) === array_values(array_unique($m[1])), 'every placeholder is used once and every parameter is bound (native prepared statements)');
ok_sq(S::extraConditions('!!!') === ['sql' => '', 'params' => []], 'a query of symbols adds nothing');
ok_sq(!str_contains(json_encode(S::extraConditions("x' OR 1=1 --")), "1=1 --'"), 'user text never lands in the SQL text, only in bound parameters');

// ---- the golden queries on this database
global $config;
$db = Database::connection($config['db']);
$have = array_flip($db->query('SELECT slug FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN));
$golden = require __DIR__ . '/fixtures/search_golden.php';
$model = new Product();
$oldSearch = static function (string $q) use ($db): array {
    $st = $db->prepare("SELECT p.slug FROM products p WHERE p.is_active=1 AND (p.full_name LIKE :a OR p.model_name LIKE :b OR EXISTS(SELECT 1 FROM product_aliases pa WHERE pa.product_id=p.id AND pa.alias_text LIKE :c))");
    $st->execute(['a' => "%$q%", 'b' => "%$q%", 'c' => "%$q%"]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
};
$run = 0; $skipped = 0;
foreach ($golden as $g) {
    $must = $g['must'] ?? [];
    if ($must && array_diff($must, array_keys($have)) !== []) { $skipped++; continue; }
    $run++;
    $new = array_column($model->search($g['q']), 'slug');
    if (!empty($g['none'])) { ok_sq($new === [], "Query '{$g['q']}' must return nothing, got " . count($new)); }
    else {
        ok_sq(array_diff($must, $new) === [], "Query '{$g['q']}' must find " . implode(',', $must) . ', got ' . implode(',', $new));
        if (!empty($g['top'])) { ok_sq(($new[0] ?? null) === $must[0], "Query '{$g['q']}' must put {$must[0]} first"); }
    }
    ok_sq(array_diff($oldSearch($g['q']), $new) === [], "Query '{$g['q']}': the new search must never drop a result of the old search");
}
ok_sq($run >= 8, "The golden set must exercise at least 8 queries on this catalog (ran $run, skipped $skipped for missing products)");
// the invariant on free text too: single characters, brand words, a LIKE wildcard
foreach (['a', 'rtx', 'amd', 'nvidia', 'gb', '%', 'ti', 'i5'] as $q) {
    ok_sq(array_diff($oldSearch($q), array_column($model->search($q), 'slug')) === [], "Query '$q': no result of the old search may be lost");
}

echo "search_query_test: PASS (golden queries run: $run, skipped for missing products: $skipped)\n";
