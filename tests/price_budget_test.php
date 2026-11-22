<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\PriceList;

/** LS-51: a budget in baht keeps only models with a public price whose median is inside it; 0 means no limit on that side. Pure. */
function ok_pb(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$row = static fn(string $n, ?float $median): array => ['full_name' => $n, 'median' => $median, 'valid_sample_size' => $median ? 5 : 0, 'accepted_observation_count' => $median ? 5 : 0];
$all = [$row('A', 2500.0), $row('B', 4000.0), $row('C', 6000.0), $row('D', 12000.0), $row('E', null)];

ok_pb(count(PriceList::withinBudget($all, 0, 0)) === 5, 'No budget leaves the list as it is, unpriced models included');
ok_pb(array_column(PriceList::withinBudget($all, 3000, 6000), 'full_name') === ['B', 'C'], 'A band keeps the models inside it, the edges included');
ok_pb(array_column(PriceList::withinBudget($all, 0, 3000), 'full_name') === ['A'], 'A maximum only means "up to"');
ok_pb(array_column(PriceList::withinBudget($all, 10000, 0), 'full_name') === ['D'], 'A minimum only means "from"');
ok_pb(!in_array('E', array_column(PriceList::withinBudget($all, 1, 0), 'full_name'), true), 'A model without a public price can never be inside a budget');
echo "price_budget_test: PASS\n";
