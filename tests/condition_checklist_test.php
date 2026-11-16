<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\ConditionChecklist as C;
use App\Services\PriceEngine;

/** Condition checklist (LS-3 J-6): category-specific reminders, prompts only for evidence the visitor said is missing, neutral wording, and no influence on the verdict. */
function ok_cc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$full = ['has_box' => 1, 'has_receipt' => 1, 'has_benchmark' => 1, 'warranty_months' => 6];
foreach (['gpu', 'cpu', 'ram', 'storage', 'psu', 'motherboard', 'cooling', null, ''] as $cat) {
    $r = C::for($cat, $full);
    ok_cc(count($r['items']) >= 3, 'Every category (and an unknown one) gets at least 3 items: ' . var_export($cat, true));
    ok_cc($r['prompts'] === [], 'Complete evidence gives no prompts for ' . var_export($cat, true));
    ok_cc($r['note'] === C::NOTE && str_contains($r['note'], 'ไม่ใช่การรับรอง'), 'The note says it is not a certification');
}
ok_cc(count(array_unique(array_column(C::for('gpu', $full)['items'], 'text'))) === count(C::for('gpu', $full)['items']), 'No duplicated item');
ok_cc(C::for('gpu', $full)['items'] !== C::for('storage', $full)['items'] && in_array('smart', array_column(C::for('storage', $full)['items'], 'key'), true), 'Storage has its own SMART item, GPU a different list');
ok_cc(C::for('GPU', $full)['items'] === C::for('gpu', $full)['items'], 'The category is case-insensitive');

// prompts follow the visitor's own answers
$none = ['has_box' => 0, 'has_receipt' => 0, 'has_benchmark' => 0, 'warranty_months' => null];
ok_cc(array_column(C::for('gpu', $none)['prompts'], 'key') === ['no_box', 'no_receipt', 'no_test', 'no_warranty'], 'A GPU with no evidence at all gets all four prompts');
ok_cc(array_column(C::for('ram', $none)['prompts'], 'key') === ['no_box', 'no_receipt', 'no_warranty'], 'The benchmark prompt is only for GPU and CPU');
ok_cc(array_column(C::for('gpu', ['has_box' => 1, 'has_receipt' => 0, 'has_benchmark' => 1, 'warranty_months' => 12])['prompts'], 'key') === ['no_receipt'], 'Only the missing evidence is prompted');
ok_cc(array_column(C::for('gpu', array_merge($full, ['warranty_months' => '0']))['prompts'], 'key') === ['no_warranty'] && array_column(C::for('gpu', array_merge($full, ['warranty_months' => '']))['prompts'], 'key') === ['no_warranty'], 'A zero or empty warranty is prompted, a positive one is not');
ok_cc(C::for('gpu', [])['prompts'] !== [] && array_column(C::for('gpu', [])['prompts'], 'key') === ['no_warranty'], 'A missing key is treated as not given only for the warranty (flags default to no prompt)');

// neutral wording: nothing that certifies, accuses or promises
$all = [];
foreach (['gpu', 'cpu', 'ram', 'storage', 'psu', 'motherboard', 'x'] as $cat) { foreach (C::for($cat, $none) as $part) { if (is_array($part)) { foreach ($part as $it) { $all[] = $it['text']; } } } }
$text = implode(' ', $all);
ok_cc(!preg_match('/รับประกันว่า|ปลอดภัย 100|มั่นใจได้|โกง|หลอก|มิจฉาชีพ|ผู้ขายไม่น่าเชื่อถือ|ซื้อได้เลย/u', $text), 'No certification, accusation or promise in the wording');
ok_cc(!preg_match('/\d+\s*%/', $text), 'No statistic or percentage is claimed');

// the checklist never decides the verdict: PriceEngine is independent of it (same input, same label)
$snap = ['q1' => 7300, 'q3' => 8200, 'accepted_observation_count' => 13];
ok_cc((new PriceEngine())->evaluate($snap, 7800.0)['label'] === 'in_range', 'The verdict is computed from the price range only');
ok_cc(preg_match('/new\s+PriceEngine|PriceEngine::/', (string)file_get_contents(__DIR__ . '/../app/Services/ConditionChecklist.php')) === 0, 'The checklist never calls the price engine');

echo "condition_checklist_test: PASS\n";
