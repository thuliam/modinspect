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
    'excluded_rows' => [['observed_at' => '2026-09-01 12:00:00', 'calculation_price' => '9900', 'reason' => 'OUTSIDE_COHORT', 'grade' => 'owner_text', 'source_id' => 5, 'inclusion_status' => 'excluded']],
];
$product = ['median' => 3900];
ob_start();
require dirname(__DIR__) . '/app/Views/components/evidence-explorer.php';
$html = (string)ob_get_clean();
assert_pt(str_contains($html, '3,750') && str_contains($html, '<b>2</b> ตัวอย่าง'), 'Evidence must list included prices and the sample count');
assert_pt(str_contains($html, 'แหล่งเดียว'), 'A single source must be stated, not hidden');
assert_pt(str_contains($html, 'ยังไม่ผ่านการตรวจ 3') && str_contains($html, 'ราคาอยู่นอกช่วงเปรียบเทียบ 1'), 'Excluded counts must be explained');
assert_pt(!preg_match('~https?://|@|\bLINEFB|offline-ref~i', $html), 'Evidence must not expose URLs, handles or row references');
assert_pt(str_contains($html, '2569'), 'Dates are shown in the Buddhist era used by the rest of the site');
// LS-61: listings table sorted by price, middle price marked, excluded rows greyed with the reason
assert_pt(strpos($html, '3,750') < strpos($html, '3,990') && str_contains($html, 'mi-ev-mid') && str_contains($html, 'ราคากลาง ฿3,900'), 'Listings are sorted by price and the middle price is marked');
assert_pt(str_contains($html, 'mi-ev-out') && str_contains($html, '9,900') && str_contains($html, 'ไม่นับ: ราคาอยู่นอกช่วงเปรียบเทียบ'), 'Excluded listings are shown greyed with the reason');
assert_pt(str_contains($html, 'ประกาศมือสองที่ใช้คำนวณราคานี้'), 'The card carries the used-listings title');

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

assert_pt(array_key_exists('sources', $report) && is_array($report['sources']), 'Audit JSON must carry per-source-key counts');
assert_pt(array_key_exists('duplicate_rate', $report), 'Audit JSON must carry the repost rate key (null when the log is empty)');

// Policy confidence-v2 (DL-033): evidence that is entirely owner-collected text can never exceed the 'low' band
$svc = new \App\Services\Pricing\SnapshotConfidenceService();
$today = date('Y-m-d');
$mk = static function (int $i, string $key) use ($today): array {
    return ['observed_at' => $today . ' 12:00:00', 'source_key' => $key, 'source_name' => 'src-' . $key . $i, 'source_domain' => $key . $i . '.example', 'source_id' => $i, 'evidence_level' => 5, 'source_evidence_quality' => 'high'];
};
$ownerRows = [];
for ($i = 1; $i <= 12; $i++) { $ownerRows[] = $mk($i, 'offline_owner_text_batch' . $i); }
$ownerResult = $svc->calculate($ownerRows, 'asking', $today);
assert_pt(in_array('OWNER_TEXT_ONLY_CAP', $ownerResult['policy_caps'], true) && $ownerResult['label'] === 'low' && $ownerResult['overall_score'] <= 59.0, 'Owner-text-only evidence must be capped to low even with many fresh diverse rows');
$mixed = $ownerRows;
$mixed[0] = $mk(99, 'offline_real_priceza');
$mixedResult = $svc->calculate($mixed, 'asking', $today);
assert_pt(!in_array('OWNER_TEXT_ONLY_CAP', $mixedResult['policy_caps'], true), 'A cohort with any non-owner-text source must not get the owner-text cap');
assert_pt(\App\Services\Pricing\SnapshotConfidenceService::METHOD_VERSION === 'confidence-v3', 'Method version must be bumped when the policy changes');
echo "price_trust_test: PASS\n";
