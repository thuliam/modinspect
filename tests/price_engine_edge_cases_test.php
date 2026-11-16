<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\PriceEngine;

/**
 * Edge cases of the deterministic Deal Checker verdict (LS-2 H-10). The verdict is arithmetic on the stored snapshot range:
 * it must say "not enough data" whenever there is no accepted evidence, treat the range boundaries as inside, never produce
 * a label for a price the controller would reject, and always carry the asking-price caveat.
 */
function ok_pe(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$engine = new PriceEngine();
$snap = ['q1' => 7300, 'q3' => 8200, 'accepted_observation_count' => 13];

ok_pe($engine->evaluate($snap, 7299.99)['label'] === 'below_range', 'Just under Q1 is below the range');
ok_pe($engine->evaluate($snap, 7300.0)['label'] === 'in_range', 'Exactly Q1 is inside the range');
ok_pe($engine->evaluate($snap, 8200.0)['label'] === 'in_range', 'Exactly Q3 is inside the range');
ok_pe($engine->evaluate($snap, 8200.01)['label'] === 'above_range', 'Just over Q3 is above the range');
ok_pe($engine->evaluate($snap, 0.01)['label'] === 'below_range' && $engine->evaluate($snap, 9999999.0)['label'] === 'above_range', 'Extreme prices that the controller still accepts get a defined label');

// no usable evidence: never compare against nothing
foreach ([
    'no quartiles'            => ['q1' => null, 'q3' => null, 'accepted_observation_count' => 5],
    'zero quartiles'          => ['q1' => 0, 'q3' => 0, 'accepted_observation_count' => 5],
    'no accepted rows'        => ['q1' => 7300, 'q3' => 8200, 'accepted_observation_count' => 0],
    'accepted count missing'  => ['q1' => 7300, 'q3' => 8200],
] as $name => $p) {
    $r = $engine->evaluate($p, 8000.0);
    ok_pe($r['label'] === 'insufficient', "$name must be reported as insufficient, got " . $r['label']);
}

// a degenerate range (Q1 = Q3) is still a range: only the exact value is inside
$flat = ['q1' => 5000, 'q3' => 5000, 'accepted_observation_count' => 4];
ok_pe($engine->evaluate($flat, 5000.0)['label'] === 'in_range' && $engine->evaluate($flat, 5001.0)['label'] === 'above_range' && $engine->evaluate($flat, 4999.0)['label'] === 'below_range', 'A flat range is judged exactly');

// every verdict explains itself and keeps the asking-price caveat; none promises a "good deal" or advises to buy
foreach ([7000.0, 7800.0, 9000.0] as $price) {
    $r = $engine->evaluate($snap, $price);
    ok_pe(trim((string)$r['recommendation']) !== '' && trim((string)$r['warning']) !== '', 'Verdict has a recommendation and a warning at ' . $price);
    ok_pe(!preg_match('/ซื้อเลย|คุ้มมาก|ดีลดี|รับประกัน(ว่า)?กำไร|guarantee/iu', (string)$r['recommendation'] . ' ' . (string)$r['warning']), 'Verdict never promises a good deal or a guarantee at ' . $price);
}
ok_pe(str_contains((string)$engine->evaluate($snap, 7800.0)['warning'], 'ราคา'), 'The in-range warning talks about the nature of the price data');

// the controller's own input rule, restated: positive, finite and at most 10,000,000 is the accepted range of prices
foreach (['0' => false, '-5' => false, 'abc' => false, 'NAN' => false, 'INF' => false, '10000001' => false, '1e3' => true, '7800' => true, '7800.5' => true] as $raw => $accepted) {
    $f = filter_var($raw, FILTER_VALIDATE_FLOAT);
    $valid = $f !== false && $f > 0 && $f <= 10000000;
    ok_pe($valid === $accepted, "Price input '$raw' should be " . ($accepted ? 'accepted' : 'rejected'));
}

echo "price_engine_edge_cases_test: PASS\n";
