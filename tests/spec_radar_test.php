<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\SpecRadar;

/**
 * LS-11 C: the radar on a saved build. Every axis is measured from what the saved build holds, never from a performance score (none exists yet):
 *  coverage   = distinct main categories chosen / the 8 categories of a PC
 *  confidence = mean confidence score of the chosen parts
 *  budget     = expected total / the budget, capped at 100
 *  stability  = 100 minus the mean width of the Q1-Q3 price range as a percent of the median (a narrow range means the market agrees on the price)
 * Alternatives (is_alternative = 1) are not part of the build and never count. Pure (no database).
 */
function ok_rd(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$item = static fn(string $role, float $low, float $exp, float $high, float $conf, int $alt = 0): array => ['component_role' => $role, 'price_low' => $low, 'price_expected' => $exp, 'price_high' => $high, 'confidence_score' => $conf, 'is_alternative' => $alt];
$val = static fn(array $axes, string $key): float => (float)array_column($axes, 'value', 'key')[$key];

$items = [$item('cpu', 4000, 4500, 5000, 70), $item('gpu', 8000, 9000, 10000, 50), $item('ram', 650, 700, 750, 60)];
$axes = SpecRadar::axes($items, 30000.0);
ok_rd(array_column($axes, 'key') === ['coverage', 'confidence', 'budget', 'stability'], 'Four axes in a fixed order: ' . json_encode(array_column($axes, 'key')));
foreach ($axes as $a) { ok_rd(isset($a['label'], $a['note']) && $a['label'] !== '' && $a['note'] !== '', 'Each axis has a Thai label and a note saying how it is measured: ' . json_encode($a, JSON_UNESCAPED_UNICODE)); }
ok_rd($val($axes, 'coverage') === 37.5, 'Three of the eight categories: ' . $val($axes, 'coverage'));
ok_rd($val($axes, 'confidence') === 60.0, 'Mean of 70, 50, 60: ' . $val($axes, 'confidence'));
ok_rd($val($axes, 'budget') === 47.3, 'Expected 14,200 of 30,000: ' . $val($axes, 'budget'));
ok_rd($val($axes, 'stability') === 80.4, 'Mean range width 19.6% of the median: ' . $val($axes, 'stability'));

// over budget caps at 100, and a missing budget reads as 0 rather than dividing by zero
ok_rd($val(SpecRadar::axes($items, 10000.0), 'budget') === 100.0, 'Over budget is capped at 100');
ok_rd($val(SpecRadar::axes($items, 0.0), 'budget') === 0.0, 'No budget gives 0');

// two parts of one category count once; alternatives never count
$dup = array_merge($items, [$item('cpu', 3000, 3500, 4000, 90), $item('motherboard', 100, 100, 100, 100, 1)]);
$d = SpecRadar::axes($dup, 30000.0);
ok_rd($val($d, 'coverage') === 37.5, 'A second cpu adds no coverage and an alternative motherboard is ignored: ' . $val($d, 'coverage'));
ok_rd($val($d, 'confidence') === 67.5, 'The extra cpu counts in the mean (70, 50, 60, 90) but the alternative does not: ' . $val($d, 'confidence'));

// a part with no median cannot give a range width: it is left out of the stability mean
$z = SpecRadar::axes([$item('cpu', 4000, 4500, 5000, 70), $item('gpu', 0, 0, 0, 50)], 30000.0);
ok_rd($val($z, 'stability') === 77.8, 'A zero-median part is skipped for stability: ' . $val($z, 'stability'));

// LS-14 (review finding): a part with no price, or only a new-price reference (confidence 0), is not used-market data. It must not raise coverage,
// pull the mean confidence down as if measured, or count as a perfectly stable price; a new price still counts in what the build would cost
$m = SpecRadar::axes([$item('cpu', 4000, 4500, 5000, 70), $item('psu', 0, 0, 0, 0), $item('cooling', 2000, 2000, 2000, 0)], 30000.0);
ok_rd($val($m, 'coverage') === 12.5, 'Only the used-market part covers a category (1 of 8): ' . $val($m, 'coverage'));
ok_rd($val($m, 'confidence') === 70.0, 'The mean confidence is over used-market parts only: ' . $val($m, 'confidence'));
ok_rd($val($m, 'stability') === 77.8, 'A new-price reference is not "perfectly stable": ' . $val($m, 'stability'));
ok_rd($val($m, 'budget') === 21.7, 'The new price still counts toward what the build would cost (4,500 + 2,000 of 30,000): ' . $val($m, 'budget'));

// an empty build gives zeros, not an error
$e = SpecRadar::axes([], 30000.0);
ok_rd(array_sum(array_column($e, 'value')) === 0.0, 'An empty build reads 0 on every axis');

// the page: the radar sits on the saved-build view, uses the Chart.js already shipped, and says there is no speed axis
$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/app/Views/modules/build.php');
$comp = (string)file_get_contents($root . '/app/Views/components/build-radar.php');
ok_rd(str_contains($view, "components/build-radar"), 'The saved build page includes the radar component');
ok_rd(str_contains($comp, "type: 'radar'") && str_contains($comp, 'assets/vendor/chartjs/chart.umd.min.js'), 'The component draws a Chart.js radar from the shipped file, no CDN');
ok_rd(preg_match('/pointLabels:\s*\{\s*font:\s*\{\s*size:\s*(\d+)/', $comp, $pl) === 1 && (int)$pl[1] >= 13, 'The axis labels are set to at least 13px through Chart.js pointLabels (the default is small): ' . ($pl[1] ?? 'not set'));
ok_rd(str_contains($comp, 'ไม่ใช่ความแรงของเครื่อง') && str_contains($comp, 'ไม่มีผลทดสอบจริง'), 'The data-quality radar says it is not a speed measure; the spec-based stats (LS-14 OD-1) say they are not a benchmark');
ok_rd(str_contains($comp, 'SpecRadar::axes'), 'The axes come from SpecRadar, so the formula lives in one tested place');
echo "spec_radar_test: PASS\n";
