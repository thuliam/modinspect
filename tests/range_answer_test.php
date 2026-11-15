<?php
declare(strict_types=1);

/** Range-bar answer (DL-015): geometry, rendering, empty-state safety and the 3:1 contrast guardrail. */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Pricing\PriceRangeLayout;

function expect(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

// Geometry
$g = PriceRangeLayout::compute(6000, 7000, 7500, 8500, 10000);
expect($g['valid'] && $g['band_left'] === 25.0 && $g['band_width'] === 37.5 && $g['median'] === 37.5, 'Normal geometry: ' . json_encode($g));
$same = PriceRangeLayout::compute(5000, 5000, 5000, 5000, 5000);
expect($same['valid'] && $same['band_width'] === 100.0 && $same['median'] === 50.0, 'Single-price span must not divide by zero');
$narrow = PriceRangeLayout::compute(0, 1000, 1000, 1001, 100000);
expect($narrow['band_width'] >= 2.0, 'Band must stay visible when Q1 and Q3 are close');
$edge = PriceRangeLayout::compute(0, 99000, 99500, 100000, 100000);
expect($edge['band_left'] + $edge['band_width'] <= 100.0, 'Band must not overflow the track');
foreach ([[10, 5, 7, 9, 12], [1, 2, 3, 4, 3], [-5, 1, 2, 3, 4]] as $bad) {
    expect(PriceRangeLayout::compute(...array_map('floatval', $bad))['valid'] === false, 'Disordered values must be rejected: ' . json_encode($bad));
}

// Rendering
function render_range(array $product, string $size): string
{
    $rangeProduct = $product;
    $rangeSize = $size;
    ob_start();
    require ROOT_PATH . '/app/Views/components/range-answer.php';
    return (string)ob_get_clean();
}
$html = render_range(['price_low' => 6000, 'q1' => 7000, 'median' => 7500, 'q3' => 8500, 'price_high' => 10000], 'lg');
expect(str_contains($html, '฿7,000 – ฿8,500') && str_contains($html, 'role="img"') && str_contains($html, 'ราคากลาง ฿7,500'), 'Rendered range lacks figures or accessible label');
expect(str_contains(render_range(['q1' => 7000, 'median' => 7500, 'q3' => 8500], 'sm'), 'mb-range-sm'), 'Compact variant class missing');
expect(trim(render_range(['q1' => 9000, 'median' => 7500, 'q3' => 8500], 'lg')) === '', 'Disordered snapshot must render nothing');
expect(trim(render_range([], 'lg')) === '', 'No data must render nothing (the caller shows the honest no-data state)');

// Contrast guardrail: the band (the one accent) must reach 3:1 against its track and against the card surface.
$css = (string)file_get_contents(ROOT_PATH . '/public/assets/ci-tokens.css'); // CI tokens (docs/MASTER_PROMPT_UI_CI_ALIGNMENT.md)
preg_match('/--ci-primary:\s*(#[0-9a-f]{6})/i', $css, $a);
preg_match('/--ci-border-active:\s*(#[0-9a-f]{6})/i', $css, $t);
preg_match('/--ci-surface:\s*(#[0-9a-f]{6})/i', $css, $s);
expect(isset($a[1], $t[1], $s[1]), 'Market Board tokens missing from ci-tokens.css');
$lum = static function (string $hex): float {
    $c = array_map(static fn(string $h): float => (hexdec($h) / 255) <= 0.03928 ? (hexdec($h) / 255) / 12.92 : (((hexdec($h) / 255) + 0.055) / 1.055) ** 2.4, str_split(ltrim($hex, '#'), 2));
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};
$ratio = static fn(string $x, string $y): float => (max($lum($x), $lum($y)) + 0.05) / (min($lum($x), $lum($y)) + 0.05);
expect($ratio($a[1], $t[1]) >= 3.0, 'Band vs track contrast below 3:1: ' . round($ratio($a[1], $t[1]), 2));
expect($ratio($a[1], $s[1]) >= 3.0, 'Band vs surface contrast below 3:1');

echo "range_answer_test: ok\n";
