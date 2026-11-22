<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Services\Benchmark\CompareRadar;

/** LS-45: the Compare radar draws only published scores, only models of one category, only axes every drawn model has, at most four, and never guesses. Pure. */
function ok_cr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$gpu = static fn(string $name, array $v): array => ['product' => ['full_name' => $name, 'category_slug' => 'gpu'], 'scores' => array_map(static fn(string $k, float $x): array => ['metric_key' => $k, 'score_value' => (string)$x], array_keys($v), $v)];
$full = ['inno_score' => 50.0, 'score_rasterization' => 40.0, 'score_rayTracing' => 30.0, 'score_memory' => 60.0, 'score_efficiency' => 70.0, 'score_compute' => 55.0];

$r = CompareRadar::build([$gpu('A', $full), $gpu('B', array_map(static fn(float $x): float => $x + 5, $full))]);
ok_cr($r !== null && count($r['series']) === 2 && count($r['labels']) === 5, 'Two scored GPUs give five shared axes (the overall score is not an axis)');
ok_cr($r['series'][0]['color'] !== $r['series'][1]['color'] && $r['series'][0]['values'][0] === 40.0, 'Each model has its own colour and its own values in axis order');

$r = CompareRadar::build([$gpu('A', $full), $gpu('B', ['inno_score' => 10.0]), $gpu('C', $full)]);
ok_cr($r !== null && count($r['models']) === 3 && $r['models'][1]['scored'] === false && $r['models'][1]['name'] === 'B', 'Every chosen model is listed, the one without scores flagged so the page can say why it is not drawn');
ok_cr($r !== null && $r['missing'] === ['B'] && count($r['series']) === 2 && $r['series'][1]['color'] === CompareRadar::COLORS[2], 'A model without three scores is named as missing and the colour stays tied to its Compare position');

$partial = $full; unset($partial['score_memory'], $partial['score_efficiency']);
$r = CompareRadar::build([$gpu('A', $full), $gpu('B', $partial)]);
ok_cr($r !== null && count($r['labels']) === 3, 'Only axes that every drawn model has are used');

$cpu = ['product' => ['full_name' => 'Z', 'category_slug' => 'cpu'], 'scores' => [['metric_key' => 'score_singleCore', 'score_value' => '40'], ['metric_key' => 'score_multiCore', 'score_value' => '41'], ['metric_key' => 'score_gaming', 'score_value' => '42']]];
$r = CompareRadar::build([$gpu('A', $full), $gpu('B', $full), $cpu]);
ok_cr($r !== null && count($r['series']) === 2 && $r['missing'] === ['Z'], 'Models of another category are never drawn on the same radar');

ok_cr(CompareRadar::build([$gpu('A', $full), $gpu('B', ['inno_score' => 1.0])]) === null, 'One scored model is not a comparison: no radar');
ok_cr(CompareRadar::build([]) === null, 'No models: no radar');
echo "compare_radar_test: PASS\n";
