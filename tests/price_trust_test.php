<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function assert_pt(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// Evidence Explorer: public-safe rendering from snapshot membership
$base = '';
$evidence = [
    'snapshot_id' => 7, 'sources' => 1,
    'included' => [
        ['observed_at' => '2026-11-10 12:00:00', 'calculation_price' => '3750', 'grade' => 'owner_text', 'source_id' => 5, 'inclusion_status' => 'included'],
        ['observed_at' => '2026-10-02 12:00:00', 'calculation_price' => '3990', 'grade' => 'owner_text', 'source_id' => 5, 'inclusion_status' => 'included'],
    ],
    'excluded' => ['NOT_ACCEPTED' => 3, 'OUTSIDE_COHORT' => 1],
];
ob_start();
require dirname(__DIR__) . '/app/Views/components/evidence-explorer.php';
$html = (string)ob_get_clean();
assert_pt(str_contains($html, '3,750') && str_contains($html, '<b>2</b> ตัวอย่าง'), 'Evidence must list included prices and the sample count');
assert_pt(str_contains($html, 'แหล่งเดียว'), 'A single source must be stated, not hidden');
assert_pt(str_contains($html, 'ยังไม่ผ่านการตรวจ 3') && str_contains($html, 'ราคาอยู่นอกช่วงเปรียบเทียบ 1'), 'Excluded counts must be explained');
assert_pt(!preg_match('~https?://|@|\bLINEFB|offline-ref~i', $html), 'Evidence must not expose URLs, handles or row references');
assert_pt(str_contains($html, '2569'), 'Dates are shown in the Buddhist era used by the rest of the site');

ob_start();
$evidence = null;
require dirname(__DIR__) . '/app/Views/components/evidence-explorer.php';
assert_pt(trim((string)ob_get_clean()) === '', 'No snapshot means no evidence block');

// The audit must find no P0 on the test database (replay MATCH, no mock members, ordered quartiles)
$out = []; $code = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/cli/price_trust_audit.php') . ' --json 2>&1', $out, $code);
$report = json_decode(implode("\n", $out), true);
assert_pt(is_array($report) && isset($report['summary']), 'price_trust_audit --json must return a summary');
assert_pt($report['summary']['p0'] === 0 && $code === 0, 'price_trust_audit found a P0 on the test database');

echo "price_trust_test: PASS\n";
