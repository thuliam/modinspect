<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Search\DidYouMean as D;

/**
 * F-7 typo tolerance (owner scope 2026-11-18). It only runs when the normal search found nothing, and it only SUGGESTS ("did you mean"): it never changes
 * a result list and never guesses a model number, because a model number that is one digit off is another product (3060 is not 3070).
 * Letters-only words may be one or two characters off; any word with a digit must match exactly. Pure (no database).
 */
function ok_dm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$names = [
    ['slug' => 'amd-ryzen-5-5600', 'full_name' => 'AMD Ryzen 5 5600'],
    ['slug' => 'amd-ryzen-7-5700x3d', 'full_name' => 'AMD Ryzen 7 5700X3D'],
    ['slug' => 'nvidia-rtx-3060', 'full_name' => 'NVIDIA GeForce RTX 3060'],
    ['slug' => 'nvidia-rtx-3070', 'full_name' => 'NVIDIA GeForce RTX 3070'],
    ['slug' => 'intel-i5-12400f', 'full_name' => 'Intel Core i5-12400F'],
    ['slug' => 'kingston-fury-16', 'full_name' => 'Kingston Fury Beast DDR4-3200 16GB'],
];
$slugs = static fn(array $r): array => array_column($r, 'slug');

ok_dm(D::distance('ryzen', 'ryzen') === 0 && D::distance('ryzn', 'ryzen') === 1 && D::distance('rzyen', 'ryzen') === 1 && D::distance('geforse', 'geforce') === 1, 'Edit distance counts one insertion, swap or substitution as 1');
ok_dm(D::distance('abc', '') === 3, 'Distance to an empty word is its length');

ok_dm($slugs(D::suggest('ryzn 5 5600', $names)) === ['amd-ryzen-5-5600'], 'A typo in a letters-only word is suggested: ' . json_encode($slugs(D::suggest('ryzn 5 5600', $names))));
ok_dm($slugs(D::suggest('geforse rtx 3060', $names)) === ['nvidia-rtx-3060'], 'A typo in the brand word still finds only the right model number');
ok_dm(in_array('kingston-fury-16', $slugs(D::suggest('kingstn', $names)), true), 'A single misspelled word finds the product');

// model numbers are never guessed
ok_dm(D::suggest('rtx 3061', $names) === [], 'A number one digit off is not suggested (it would be another product)');
ok_dm(D::suggest('ryzen 5 5601', $names) === [], 'Same for a CPU number');

// short words get no tolerance, nothing matches nothing
ok_dm(D::suggest('xyz', $names) === [], 'Unrelated text gives no suggestion');
ok_dm(D::suggest('rtz 3060', $names) === [], 'A three-letter word gets no tolerance (rtz is not rtx)');
ok_dm(D::suggest('', $names) === [] && D::suggest('   ', $names) === [], 'Empty query gives no suggestion');
ok_dm(D::suggest('ปกติ', $names) === [], 'Thai-only text gives no suggestion');
ok_dm(D::suggest('rtx 3O60', $names) === [], 'A letter O in place of a zero is not guessed');

// limit and ordering: closest first
$many = [];
foreach (range(1, 9) as $i) { $many[] = ['slug' => "x$i", 'full_name' => "Ryzen Chip $i"]; }
ok_dm(count(D::suggest('ryzn chip', $many, 5)) === 5, 'At most the requested number of suggestions');
$o = D::suggest('ryzn', [['slug' => 'far', 'full_name' => 'Ryzen Extra Long Name'], ['slug' => 'near', 'full_name' => 'Ryzen']]);
ok_dm($slugs($o) === ['near', 'far'], 'Closer and shorter names come first: ' . json_encode($slugs($o)));

// wiring: only when nothing was found, suggestion list and native datalist (no script)
$root = dirname(__DIR__);
$ctl = (string)file_get_contents($root . '/app/Controllers/PriceController.php');
ok_dm(str_contains($ctl, 'DidYouMean::suggest') && preg_match('/!\$products\s*&&\s*\$q\s*!==\s*\'\'/', $ctl) === 1, 'The controller suggests only when the search is empty');
$view = (string)file_get_contents($root . '/app/Views/price/index.php');
ok_dm(str_contains($view, '<datalist') && str_contains($view, 'list="price-names"') && str_contains($view, 'htmlspecialchars'), 'Autocomplete is a native datalist and values are escaped');
ok_dm(!str_contains($view, '<script'), 'No script was added for autocomplete');
echo "did_you_mean_test: PASS\n";
