<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\DealExplanation as D;
use App\Services\PriceEngine as E;

/**
 * LS-20: the Deal Checker no longer calls every price over the range "slightly above". The stored label stays one of four (a database ENUM, no migration);
 * a severity tier (slight / clear / far: how far outside the edge it crossed, as a share of that edge) drives the wording, and an explanation built from the
 * saved row gives the numbers and the factors the visitor entered. It never turns those factors into money and never moves the verdict. Pure.
 */
function ok_de(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

// ---- tiers (edges count as inside; boundaries at 15 % and 50 % of the crossed edge) ----
ok_de(E::tier(2240.0, 1970.0, 2240.0) === null && E::tier(1970.0, 1970.0, 2240.0) === null && E::tier(2100.0, 1970.0, 2240.0) === null, 'The edges and anything between them are inside: no tier');
ok_de(E::tier(2576.0, 1970.0, 2240.0) === 'slight', 'Exactly 15 % over the top is still slight');
ok_de(E::tier(2576.01, 1970.0, 2240.0) === 'clear' && E::tier(3360.0, 1970.0, 2240.0) === 'clear', 'Over 15 % up to exactly 50 % is clear');
ok_de(E::tier(3360.01, 1970.0, 2240.0) === 'far' && E::tier(9200.0, 1970.0, 2240.0) === 'far', 'Over 50 % is far (the owner example: 9,200 against a 2,240 top)');
ok_de(E::tier(1674.5, 1970.0, 2240.0) === 'slight' && E::tier(1674.4, 1970.0, 2240.0) === 'clear' && E::tier(985.0, 1970.0, 2240.0) === 'clear' && E::tier(984.9, 1970.0, 2240.0) === 'far', 'Below the range the same 15 % / 50 % apply to the bottom edge');
ok_de(E::tier(5.0, 0.0, 0.0) === null, 'A missing range gives no tier');

// ---- the engine keeps the four stored labels and adds the tier ----
$snap = ['q1' => 1970, 'q3' => 2240, 'median' => 2190, 'accepted_observation_count' => 10];
$e = new E();
$far = $e->evaluate($snap, 9200.0); $slight = $e->evaluate($snap, 2500.0); $in = $e->evaluate($snap, 2100.0); $low = $e->evaluate($snap, 900.0);
ok_de($far['label'] === 'above_range' && $far['tier'] === 'far' && $slight['label'] === 'above_range' && $slight['tier'] === 'slight' && $in['label'] === 'in_range' && $in['tier'] === null && $low['label'] === 'below_range' && $low['tier'] === 'far', 'Labels unchanged, tiers added');
ok_de(str_contains($far['recommendation'], 'มาก') && !str_contains($far['recommendation'], 'เล็กน้อย') && !str_contains($far['recommendation'], 'อาจรองรับส่วนต่างได้'), 'A far-above price is not described as small and is not said to be supportable by the extras');
ok_de(str_contains($slight['recommendation'], 'เล็กน้อย'), 'A slightly-above price says so');
ok_de($e->evaluate(['q1' => 0, 'q3' => 0, 'accepted_observation_count' => 0], 9200.0)['label'] === 'insufficient', 'No evidence still gives insufficient');
$schema = (string)file_get_contents($root . '/database/schema.sql');
ok_de(str_contains($schema, "result_label ENUM('below_range','in_range','above_range','insufficient') NOT NULL"), 'The stored label set is unchanged: no migration is needed');
foreach (array_merge(array_column(E::TEXT['above'], 'label'), array_column(E::TEXT['below'], 'label'), array_column(E::TEXT['above'], 'recommendation'), array_column(E::TEXT['below'], 'recommendation')) as $t) {
    ok_de(!preg_match('/ซื้อเลย|คุ้มมาก|ดีลดี|รับประกัน(ว่า)?กำไร|guarantee|หลอก|โกง/iu', $t), "Neutral wording: $t");
}

// ---- the explanation (the owner example: a saved row) ----
$row = ['result_label' => 'above_range', 'user_price' => 9200, 'suggested_price_min' => 1970, 'suggested_price_max' => 2240, 'median' => 2190, 'condition_level' => 'good', 'warranty_months' => 6, 'has_receipt' => 1, 'has_box' => 0, 'has_benchmark' => 0];
$x = D::build($row);
ok_de($x['tier'] === 'far' && $x['label'] === 'สูงกว่าช่วงที่พบมาก' && $x['tone'] === 'bad' && !str_contains($x['label'], 'เล็กน้อย'), 'Far above: wording and tone follow the tier');
ok_de($x['numbers'][0] === ['label' => 'ต่างจากราคากลาง', 'value' => '+฿7,010 (+320%)'] && $x['numbers'][1] === ['label' => 'สูงกว่าปลายบนของช่วง', 'value' => '+฿6,960 (+311%)'], 'The gap in baht and percent, from the median and from the top edge: ' . json_encode($x['numbers'], JSON_UNESCAPED_UNICODE));
ok_de($x['have'] === ['มีใบเสร็จ', 'ประกันเหลือ 6 เดือน', 'สภาพดี'] && $x['lack'] === ['ไม่มีกล่อง', 'ยังไม่มีผลทดสอบ'], 'The factors the visitor entered, split into what exists and what is missing: ' . json_encode([$x['have'], $x['lack']], JSON_UNESCAPED_UNICODE));
ok_de(str_contains($x['have_heading'], 'อาจรองรับ') && str_contains($x['notes'][0], 'ไม่ได้คำนวณเป็นราคา'), 'The factors are offered as possible support and never as a price');
$all = D::build(array_merge($row, ['user_price' => 2500, 'has_box' => 1, 'has_benchmark' => 1, 'condition_level' => 'like_new', 'warranty_months' => 12]));
ok_de($all['tier'] === 'slight' && $all['tone'] === 'warn' && $all['lack'] === [] && count($all['have']) === 5 && $all['numbers'][1]['value'] === '+฿260 (+12%)', 'Slightly above with everything present: nothing missing');
$none = D::build(array_merge($row, ['has_receipt' => 0, 'warranty_months' => null, 'condition_level' => 'unknown']));
ok_de(in_array('ไม่มีใบเสร็จ', $none['lack'], true) && in_array('ไม่มีประกันคงเหลือหรือไม่ได้ระบุ', $none['lack'], true) && in_array('ยังไม่ได้ระบุสภาพ', $none['lack'], true) && !in_array('สภาพดี', $none['have'], true), 'Missing receipt, warranty and condition are listed as missing');
ok_de(in_array('สภาพพอใช้', D::build(array_merge($row, ['condition_level' => 'fair']))['lack'], true), 'A fair condition is not offered as support');
$below = D::build(['result_label' => 'below_range', 'user_price' => 900, 'suggested_price_min' => 1970, 'suggested_price_max' => 2240, 'median' => 2190, 'condition_level' => 'fair', 'warranty_months' => 0, 'has_receipt' => 0, 'has_box' => 0, 'has_benchmark' => 0]);
ok_de($below['direction'] === 'below' && $below['tier'] === 'far' && $below['numbers'][1]['value'] === '−฿1,070 (−54%)' && str_contains($below['lack_heading'], 'อาจเป็นเหตุผลที่ราคาต่ำ'), 'Below the range: the gap, and the missing items as possible reasons for a low price');
$in2 = D::build(array_merge($row, ['result_label' => 'in_range', 'user_price' => 2150]));
ok_de($in2['tone'] === 'ok' && $in2['tier'] === null && $in2['have'] === [] && $in2['lack'] === [] && count($in2['numbers']) === 1, 'Inside the range: just the distance from the median, no factor lists');
ok_de(D::build(array_merge($row, ['result_label' => 'insufficient'])) === null && D::build(array_merge($row, ['suggested_price_min' => 0, 'suggested_price_max' => 0, 'q1' => 0, 'q3' => 0])) === null, 'No explanation without a range to compare with');
ok_de(D::build(array_merge($row, ['suggested_price_min' => null, 'suggested_price_max' => null, 'q1' => 1970, 'q3' => 2240]))['tier'] === 'far', 'Falls back to the joined snapshot range when the row has none');
ok_de(D::build(array_merge($row, ['median' => 0]))['numbers'][0]['label'] === 'สูงกว่าปลายบนของช่วง', 'Without a median only the edge distance is shown');

// ---- the pages ----
$view = (string)file_get_contents($root . '/app/Views/deal/result.php');
ok_de(str_contains($view, '\App\Services\DealExplanation::build($deal)') && str_contains($view, 'id="deal-why"') && str_contains($view, 'htmlspecialchars($headline)') && !str_contains($view, "'above_range' => 'สูงกว่าช่วงที่พบเล็กน้อย'") && !str_contains($view, 'premium'), 'The result page uses the explanation and no longer maps every above-range result to "slightly above"');
foreach (['htmlspecialchars($row[', 'htmlspecialchars($t)', 'htmlspecialchars($explain[\'have_heading\'])'] as $esc) { ok_de(str_contains($view, $esc), "The explanation is escaped ($esc)"); }
$form = (string)file_get_contents($root . '/app/Views/deal/form.php');
ok_de(!str_contains($form, '<li>สูงกว่าช่วงที่พบเล็กน้อย</li>') && str_contains($form, 'สูงกว่าช่วงที่พบ (เล็กน้อย พอสมควร หรือมาก)'), 'The form lists the real outcomes');
echo "deal_explanation_test: PASS\n";
