<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\UnresolvedProposalReport as R;

/** LS-4 K-1: an alias proposal is only called safe when family AND the full model token match the product (found on real data: i7 9700F had been matched to Ryzen 7 9700X). */
function ok_up(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$same = [['i5 13500', 'Intel Core i5-13500'], ['I3-13100f', 'Intel Core i3-13100F'], ['RTX 3070Ti', 'NVIDIA GeForce RTX 3070 Ti'], ['ryzen5 7500F', 'AMD Ryzen 5 7500F'], ['Ryzen 7 5800X3D', 'AMD Ryzen 7 5800X3D']];
foreach ($same as [$a, $p]) { ok_up(R::sameModel($a, $p), "$a should equal $p"); }
$diff = [['i7 9700F', 'AMD Ryzen 7 9700X'], ['i3-8100', 'AMD Ryzen 9 9800X3D'], ['I5-14600K', 'Intel Core i7-14700KF'], ['i7 14700K', 'Intel Core i7-14700KF'],
    ['Ryzen 7 5800X3D', 'AMD Ryzen 7 5800X'], ['Ryzen 5 2600', 'AMD Ryzen 5 2600X'], ['RTX 5060', 'NVIDIA GeForce RTX 5060 Ti'], ['RTX 3070', 'NVIDIA GeForce RTX 3070 Ti'], ['hello', 'AMD Ryzen 5 5500']];
foreach ($diff as [$a, $p]) { ok_up(!R::sameModel($a, $p), "$a must NOT equal $p"); }
echo "unresolved_proposal_report_test: PASS (" . count($same) . " same, " . count($diff) . " different)\n";
