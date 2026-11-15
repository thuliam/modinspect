<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Research\OwnerIntakeDeduper;

function assert_oif(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// same listing, different spacing/punctuation/case and a different row id -> same key
$a = OwnerIntakeDeduper::keyFor('การ์ดจอ RTX 3070  MSI Ventus 8GB  (ประกัน 2 ปี)', 7500);
$b = OwnerIntakeDeduper::keyFor('การ์ดจอ rtx 3070 msi ventus 8gb ประกัน 2 ปี', 7500);
assert_oif($a === $b, 'Spacing, punctuation and case must not change the key');
assert_oif(OwnerIntakeDeduper::keyFor('RTX 3070 MSI Ventus', 7500) !== OwnerIntakeDeduper::keyFor('RTX 3070 MSI Ventus', 7600), 'A different price must be a different listing');
assert_oif(OwnerIntakeDeduper::keyFor('RTX 3070 MSI Ventus', 7500) !== OwnerIntakeDeduper::keyFor('RTX 3060 Ti MSI Ventus', 7500), 'A different title must be a different listing');

// the importer stores "title text" with the same text twice
assert_oif(OwnerIntakeDeduper::undouble('RTX 3070 RTX 3070') === 'RTX 3070', 'A doubled stored title must be halved');
assert_oif(OwnerIntakeDeduper::undouble('RTX 3070 Ti') === 'RTX 3070 Ti', 'A normal title must be left alone');

// filter: existing listing removed, new kept, same title with another price kept
$existing = [OwnerIntakeDeduper::keyFor('Ryzen 5 5600 มือสอง', 3300) => true];
$res = OwnerIntakeDeduper::filter([
    ['title' => 'ryzen 5 5600  มือสอง', 'asking_price' => '3300'],
    ['title' => 'Ryzen 5 5600 มือสอง', 'asking_price' => '3400'],
    ['title' => 'Ryzen 7 5700X3D', 'asking_price' => '7900'],
], $existing);
assert_oif($res['already_present'] === 1 && count($res['kept']) === 2, 'Filter must drop exactly the already-imported listing');

echo "owner_intake_filter_test: PASS\n";
