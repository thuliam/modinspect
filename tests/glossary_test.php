<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\Glossary;
use App\Services\Listing\RedFlags;

/** LS-24 B2: the used-market glossary is complete, its ids are unique anchors, it covers the phrases the listing reader warns about, and it judges nobody. */
function ok_gl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$terms = Glossary::terms();
ok_gl(count($terms) >= 14, 'At least fourteen terms');
$ids = array_column($terms, 'id');
ok_gl(count($ids) === count(array_unique($ids)) && array_reduce($ids, static fn(bool $c, string $i): bool => $c && preg_match('/^[a-z0-9-]+$/', $i) === 1, true), 'Ids are unique and safe as anchors');
foreach ($terms as $t) {
    foreach (['term', 'meaning', 'example', 'ask'] as $k) { ok_gl(trim($t[$k]) !== '', "{$t['id']} has $k"); }
    ok_gl(!preg_match('/โกง|มิจฉาชีพ|หลอก/u', json_encode($t, JSON_UNESCAPED_UNICODE)), "{$t['id']} accuses nobody");
}
$all = json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
foreach (['ตัวโชว์', 'ตัวขุด', 'ประกันศูนย์', 'ถอดเครื่อง', 'Reball', 'ES / QS', 'แฟลช BIOS', 'ราคาประกาศ'] as $word) { ok_gl(str_contains($all, $word), "Covers $word"); }
ok_gl(count(RedFlags::codes()) >= 10, 'The reader keeps its watch-list');
ok_gl(str_contains((string)file_get_contents(ROOT_PATH . '/public/index.php'), "'/glossary'") && str_contains((string)file_get_contents(ROOT_PATH . '/app/Views/listing/reader.php'), '/glossary'), 'The route exists and the reader links to it');
echo "glossary_test: PASS\n";
