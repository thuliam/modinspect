<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\DataHealthReport;

function ok_dh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$r = (new DataHealthReport())->build();
foreach (['categories', 'confidence', 'totals', 'freshness', 'grades', 'ladder'] as $k) { ok_dh(array_key_exists($k, $r), "Report has $k"); }
foreach ($r['categories'] as $c) {
    ok_dh($c['priced'] <= $c['products'], 'A category cannot have more priced products than products: ' . $c['category']);
    ok_dh($c['priced_pct'] >= 0.0 && $c['priced_pct'] <= 100.0, 'Coverage is a percentage');
}
$priced = array_sum(array_column($r['categories'], 'priced'));
ok_dh(array_sum($r['confidence']) === $priced, 'Every public price has exactly one confidence label (labels ' . array_sum($r['confidence']) . ' vs priced ' . $priced . ')');
ok_dh($r['freshness']['snapshots'] === $priced, 'Freshness counts the same public snapshots');
ok_dh($r['freshness']['fresh_majority'] <= $r['freshness']['snapshots'] && $r['freshness']['avg_fresh_pct'] >= 0.0 && $r['freshness']['avg_fresh_pct'] <= 100.0, 'Freshness values stay in range (the ratio is stored as a percentage)');
ok_dh($r['ladder']['flags'] === count($r['ladder']['items']) && $r['ladder']['checked'] >= $r['ladder']['flags'], 'Ladder counts are consistent');
// privacy: nothing that looks like a source key, URL or listing text may be in the report
$flat = json_encode($r, JSON_UNESCAPED_UNICODE);
ok_dh(!str_contains($flat, 'offline_owner_text') && !str_contains($flat, 'http'), 'The report carries no source keys or URLs');

// the view renders without PHP notices on the real shape
if (!function_exists('mi_page_header')) { function mi_page_header($b, $c, $t, $d = '', $a = '') { return '<h1>' . $t . '</h1>'; } }
$base = ''; $report = $r;
ob_start(); include __DIR__ . '/../app/Views/admin/data-health.php'; $html = ob_get_clean();
ok_dh(str_contains($html, 'Coverage by category') && str_contains($html, 'Evidence mix'), 'The data health view renders its sections');

echo "data_health_report_test: PASS\n";
