<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Research\LocationHint;

function assert_tl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// Location hint: whitelist provinces only, coarse output, nothing else leaks out
$h = LocationHint::detect('การ์ดจอ RTX 3070 รับเองที่เชียงใหม่ หรือส่ง kerry');
assert_tl($h === ['province' => 'เชียงใหม่', 'region' => 'north'], 'Chiang Mai hint wrong');
assert_tl(LocationHint::detect('นัดรับ กทม. ย่านลาดพร้าว')['province'] === 'กรุงเทพมหานคร', 'BKK alias not normalised');
assert_tl(LocationHint::detect('โคราช')['province'] === 'นครราชสีมา', 'Korat alias not normalised');
assert_tl(LocationHint::detect('ขายการ์ดจอ ราคา 5000') === null, 'No location must give null');
assert_tl(LocationHint::detect('ซื้อได้เลย ราคาเท่านี้เลย') === null, 'Everyday word เลย must not count as Loei');
assert_tl(LocationHint::detect('จ.เลย')['province'] === 'เลย', 'Explicit จ.เลย must count');
assert_tl(array_keys(LocationHint::detect('ภูเก็ต')) === ['province', 'region'], 'Hint must expose province and region only');

$base = '';
// Trend chart renders the median series and the quartile band from preserved buckets
$trend = [
    ['period_start' => '2026-09-01', 'q1' => '2325', 'median' => '2400', 'q3' => '2550', 'sample_size' => 7],
    ['period_start' => '2026-10-01', 'q1' => '2400', 'median' => '2545', 'q3' => '2865', 'sample_size' => 42],
    ['period_start' => '2026-11-01', 'q1' => '2565', 'median' => '2990', 'q3' => '3140', 'sample_size' => 10],
];
ob_start();
require dirname(__DIR__) . '/app/Views/components/trend-chart.php';
$html = (string)ob_get_clean();
// LS-19 D1: the chart is drawn by public/assets/mi-charts.js from a JSON data block (no inline script)
assert_tl(str_contains($html, '<canvas') && str_contains($html, 'data-chart="candles"') && preg_match('#<script type="application/json" id="(trend[0-9a-f]+)-data">\[\{#', $html) === 1 && str_contains((string)file_get_contents(dirname(__DIR__) . '/public/assets/mi-charts.js'), 'new Chart('), 'Chart.js canvas, data block and init missing');
assert_tl(substr_count($html, '"med":') === 3, 'Chart data must carry one point per month');
assert_tl(str_contains($html, '+24.6%'), 'Chart change versus first month must be computed from the buckets');

$trend = [$trend[0]];
ob_start();
require dirname(__DIR__) . '/app/Views/components/trend-chart.php';
$single = (string)ob_get_clean();
assert_tl(str_contains($single, 'ยังบอกแนวโน้มไม่ได้') && str_contains($single, '<canvas') && !str_contains($single, 'ราคากลางเปลี่ยน'), 'One month draws a single candle but states no trend');

// Schema: the two additive tables exist and the location log has no personal-data columns
global $config;
$db = Database::connection($config['db']);
foreach (['price_trend_points', 'listing_location_log'] as $t) {
    assert_tl((bool)$db->query("SHOW TABLES LIKE '" . $t . "'")->fetchColumn(), "Table $t missing (run migrations 015/016)");
}
$cols = array_column($db->query('SHOW COLUMNS FROM listing_location_log')->fetchAll(), 'Field');
foreach (['seller', 'name', 'phone', 'address', 'url', 'text'] as $bad) {
    foreach ($cols as $c) { assert_tl(!str_contains($c, $bad), "listing_location_log must not hold '$bad' ($c)"); }
}

echo "trend_location_test: PASS\n";
