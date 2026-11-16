<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Research\OwnerIntakePlanner as P;

function ok_pl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$rows = static function (array $prices): array { $o = []; foreach ($prices as $i => $p) { $o[] = ['id' => $i + 1, 'price' => (float)$p]; } return $o; };

// legacy behaviour (no approved rows): 3 close rows approve, a 4th far row is excluded
$r = P::plan($rows([8000, 8200, 8500, 20000]));
ok_pl($r['status'] === 'approve' && count($r['keep']) === 3 && count($r['drop']) === 1, 'Three close rows approve and the far one is excluded');
// fewer than 3 usable rows stay pending
ok_pl(P::plan($rows([8000, 8200]))['status'] === 'too_few', 'Two rows alone stay pending');
ok_pl(P::plan($rows([2790, 2790, 6500]))['status'] === 'too_few', 'RX 6500 XT shape: two usable after the outlier, stays pending');
// established sample: 1-2 new rows join approved ones
$r = P::plan($rows([38590, 43990]), [33333, 36990, 37990, 39900, 39900, 40000, 40500, 44500]);
ok_pl($r['status'] === 'approve' && count($r['keep']) === 2, 'New rows join an established approved sample');
$r = P::plan($rows([80000]), [79999, 79999, 100000]);
ok_pl($r['status'] === 'approve' && count($r['keep']) === 1, 'One new row joins three approved rows');
// a new row far from the established median is excluded, not approved
$r = P::plan($rows([3500, 41000]), [38000, 39000, 40000]);
ok_pl($r['status'] === 'approve' && count($r['keep']) === 1 && count($r['drop']) === 1 && $r['drop'][0]['price'] === 3500.0, 'Outlier against the approved median is excluded');
// nothing new usable: do not "approve" an empty set even if approved rows exist
ok_pl(P::plan($rows([3500]), [38000, 39000, 40000])['status'] === 'too_few', 'No usable new row leaves the product pending');
// bimodal: the excluded rows form their own cluster of 3+, so a median must not decide
$r = P::plan($rows([3500, 13500, 21760, 24000, 170000, 218000, 239000]));
ok_pl($r['status'] === 'bimodal' && $r['keep'] === [] && $r['drop'] === [], 'RTX 5090 shape (two price worlds) is bimodal and untouched');

echo "owner_intake_planner_test: PASS\n";
