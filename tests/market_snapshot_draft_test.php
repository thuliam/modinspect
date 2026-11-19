<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Blog\BlogRenderer;
use App\Services\Blog\MarketSnapshotDraft as D;

/** LS-24 C3: the market-today draft uses only stored public prices, names the sample size, claims no change over time, and renders safely through the blog. */
function ok_ms(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$p = static fn(string $slug, string $name, string $cat, ?float $med, int $n, int $acc = 5): array => ['slug' => $slug, 'full_name' => $name, 'category_slug' => $cat, 'median' => $med, 'q1' => $med ? $med - 400 : null, 'q3' => $med ? $med + 300 : null, 'valid_sample_size' => $n, 'accepted_observation_count' => $acc];
$rows = [$p('rtx-3070', 'NVIDIA GeForce RTX 3070', 'gpu', 7890, 13), $p('rtx-3060', 'NVIDIA GeForce RTX 3060', 'gpu', 5200, 8), $p('rtx-3080', 'NVIDIA GeForce RTX 3080', 'gpu', 10200, 10), $p('rtx-4090', 'RTX 4090 <script>', 'gpu', null, 0), $p('i5-12400f', 'Intel Core i5-12400F', 'cpu', 3820, 10), $p('no-evidence', 'No Evidence', 'cpu', 3000, 4, 0)];
$d = D::build($rows, new DateTimeImmutable('2026-11-20'));
ok_ms($d['priced'] === 4 && $d['models'] === 6, 'Only models whose page shows a price are counted (not a null median, not a model with no accepted evidence)');
ok_ms(str_contains($d['body'], '20 พฤศจิกายน 2569') && str_contains($d['title'], '20 พฤศจิกายน 2569') && $d['slug'] === 'market-today-2026-11-20', 'The date is in the Thai Buddhist year');
ok_ms(str_contains($d['body'], 'NVIDIA GeForce RTX 3070 เป็นรุ่นที่มีตัวอย่างมากที่สุดในหมวดนี้ (13 ประกาศ) ช่วงราคาที่พบ ฿7,490 ถึง ฿8,190 ราคากลาง ฿7,890'), 'The lead model of a category is the one with the most samples, with the page figures');
ok_ms(substr_count($d['body'], '{{price:') === 4 && !str_contains($d['body'], 'rtx-4090') && !str_contains($d['body'], 'no-evidence'), 'One live card per priced model, none for the others');
ok_ms(str_contains($d['body'], 'ไม่ใช่ราคาปิดดีล') && !preg_match('/ขึ้น|ลดลง|สัปดาห์นี้ราคา|เทียบกับสัปดาห์/u', $d['body']), 'It says asking prices, and claims no change over time');
ok_ms(!str_contains($d['body'], '<script>'), 'No model name that was not priced reaches the text');
$html = BlogRenderer::render($d['body'], static fn(string $s): string => "[card:$s]");
ok_ms(str_contains($html, '[card:rtx-3070]') && str_contains($html, '<h2>') && str_contains($html, '<h3>การ์ดจอ</h3>') && str_contains($html, '<a href="/read-listing" rel="noopener">วางประกาศ</a>'), 'The blog renders the draft: headings, live cards and the link to the listing reader');
$empty = D::build([], new DateTimeImmutable('2026-11-20'));
ok_ms($empty['priced'] === 0 && !str_contains($empty['body'], '{{price:'), 'With no priced model the draft has no cards');
ok_ms(!preg_match('/\b(INSERT|UPDATE|DELETE|file_put_contents)\b/', (string)file_get_contents(ROOT_PATH . '/app/Services/Blog/MarketSnapshotDraft.php')), 'The generator is pure');
echo "market_snapshot_draft_test: PASS\n";
