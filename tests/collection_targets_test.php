<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\CollectionTargets as T;

/**
 * LS-8 O-1: the daily collection target list in Thai. Pure ordering and wording from the gap rows [status, need, have, name]; products closest to a
 * price come first, bimodal ones (two price groups, a review problem and not a collection problem) come last and say so. Search words are derived
 * deterministically from the catalog name only (leading brand words dropped), never invented.
 */
function ok_ct(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

foreach ([
    'NVIDIA GeForce RTX 3070' => 'RTX 3070', 'AMD Radeon RX 6600' => 'RX 6600', 'Intel Core i5-12400F' => 'i5-12400F', 'AMD Ryzen 5 5600' => 'Ryzen 5 5600',
    'GeForce GTX 1660 Super' => 'GTX 1660 Super', 'Kingston Fury 16GB DDR4' => 'Kingston Fury 16GB DDR4', 'NVIDIA' => 'NVIDIA', '' => '',
] as $name => $want) { ok_ct(T::searchWords($name) === $want, "search words for [$name]: got [" . T::searchWords($name) . "], want [$want]"); }

$rows = [['too_few', 2, 1, 'AMD Ryzen 5 5600'], ['bimodal', 2, 5, 'NVIDIA GeForce RTX 5090'], ['too_few', 1, 2, 'NVIDIA GeForce RTX 3070'], ['too_few', 3, 0, 'Intel Core i5-12400F'], ['too_few', 1, 2, 'AMD Radeon RX 6600']];
$b = T::build($rows);
$names = array_column($b['targets'], 'name');
ok_ct($names === ['AMD Radeon RX 6600', 'NVIDIA GeForce RTX 3070', 'AMD Ryzen 5 5600', 'Intel Core i5-12400F', 'NVIDIA GeForce RTX 5090'], 'Order: need 1 first (name order inside a tie), then 2, then 3, bimodal last: ' . json_encode($names));
ok_ct($b['total_needed'] === 7 && $b['one_short'] === 2 && $b['bimodal'] === 1, 'Totals: ' . json_encode([$b['total_needed'], $b['one_short'], $b['bimodal']]));
ok_ct(T::build([])['targets'] === [] && T::build([])['total_needed'] === 0, 'Nothing pending: an empty list');

$text = T::renderText($b);
ok_ct(str_contains($text, 'RTX 3070') && str_contains($text, 'ขาดอีก 1 แถว') && str_contains($text, 'มีแล้ว 2'), 'The text names the model, rows missing and rows held');
ok_ct(str_contains($text, 'ตรวจประกาศเดิมก่อน') && strpos($text, 'RTX 5090') > strpos($text, 'i5-12400F'), 'The bimodal product comes last and says to review the old rows first, not to collect more');
ok_ct(!preg_match('/https?:|@|\b0\d{9}\b/', $text), 'The text carries no link, address or phone-like number');
$csv = T::renderCsv($b);
ok_ct(str_starts_with($csv, "status,need,have,product,search_words\n") && substr_count($csv, "\n") === 6, 'The CSV has a header and one line per product');

// the database glue returns the same row shape (test database, read-only)
global $config;
$g = T::gapRows(Database::connection($config['db']));
ok_ct(is_array($g), 'gapRows returns a list');
foreach ($g as $r) { ok_ct(count($r) === 4 && in_array($r[0], ['too_few', 'bimodal'], true) && is_int($r[1]) && is_int($r[2]) && is_string($r[3]), 'Each gap row is [status, need, have, name]'); }
echo "collection_targets_test: PASS\n";
