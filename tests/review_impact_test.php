<?php
declare(strict_types=1);

/** LS-90 T2: the queue says which pending rows would change a price, warns about the text, filters on it and exports it. Nothing is approved. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/support/PriceFixture.php';

use App\Services\Review\PriceImpact;
use App\Services\Review\ReviewExport;
use App\Services\Review\ReviewQueueQuery;

function assert_impact(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$fx = new PriceFixture('impact');
$few = null;
try {
    $db = $fx->db;
    $source = $fx->source('offline_owner_text_impact_' . bin2hex(random_bytes(3)), 'Owner text impact fixture', 'ls89.example');
    $retitle = static function (int $observation, string $title) use ($db): void {
        $db->prepare('UPDATE raw_price_observations r JOIN price_observations o ON o.raw_observation_id=r.id SET r.raw_title=? WHERE o.id=?')->execute([$title, $observation]);
    };
    // a model with no public price: one approved and three pending, one of which says it is new
    $a = $fx->observation(1000, 'approved', $source);
    $b = $fx->observation(1100, 'pending', $source);
    $c = $fx->observation(1200, 'pending', $source);
    $new = $fx->observation(1500, 'pending', $source);
    $retitle($new, 'ของ ทดสอบ มือหนึ่ง จองแล้ว');
    $retitle($b, '=cmd formula test');
    // a model that cannot reach three
    $few = new PriceFixture('impact-few');
    $fewSource = $few->source('offline_owner_text_few_' . bin2hex(random_bytes(3)), 'Owner text few fixture', 'ls89.example');
    $lonely = $few->observation(900, 'pending', $fewSource);

    $impact = (new PriceImpact($db))->pending();
    foreach ([$b, $c] as $id) { assert_impact(in_array($id, $impact['eligible'], true) && in_array($id, $impact['first_price'], true), "row $id would give a first price"); }
    assert_impact(!in_array($new, $impact['eligible'], true), 'a brand-new claim never counts');
    assert_impact(!in_array($a, $impact['eligible'], true), 'approved rows are not pending work');
    assert_impact(in_array($lonely, $impact['eligible'], true) && !in_array($lonely, $impact['first_price'], true), 'one row cannot give a price');

    $query = new ReviewQueueQuery($db);
    $base = ['dataset' => 'ALL', 'status' => 'pending', 'product_id' => $fx->productId, 'page' => 1, 'per_page' => 50];
    $all = $query->reviewQueue($base);
    assert_impact($all['total'] === 3, 'three pending rows without the filter: ' . $all['total']);
    $filtered = $query->reviewQueue($base + ['impact' => 'first_price']);
    $ids = array_map('intval', array_column($filtered['items'], 'id'));
    sort($ids);
    assert_impact($ids === [$b, $c], 'the filter keeps the two useful rows: ' . json_encode($ids));
    $warned = array_values(array_filter($all['items'], static fn(array $r): bool => (int)$r['id'] === $new))[0];
    $codes = array_column($warned['warning_items'], 'code');
    assert_impact(in_array('BRAND_NEW_CLAIM', $codes, true) && in_array('SOLD_OR_RESERVED', $codes, true), 'warnings: ' . json_encode($codes));
    assert_impact($query->reviewQueue(['product_id' => $few->productId, 'impact' => 'first_price'] + $base)['total'] === 0, 'an empty impact list shows nothing, not everything');

    $rows = array_column((new ReviewExport($db))->pendingRows(), null, 'observation_id');
    assert_impact($rows[$b]['impact'] === 'first_price' && $rows[$new]['impact'] === 'none' && $rows[$lonely]['impact'] === 'price', 'export impact');
    assert_impact(str_contains($rows[$new]['warnings'], 'BRAND_NEW_CLAIM') && !isset($rows[$a]), 'export warnings, pending only');
    $csv = (new ReviewExport($db))->pendingCsv();
    assert_impact(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, "'=cmd formula test"), 'a formula-looking title is neutralised');
    assert_impact($fx->count("SELECT COUNT(*) FROM price_observations WHERE product_id=? AND verified_status='pending'", [$fx->productId]) === 3, 'nothing was approved');

    echo "review_impact_test: PASS\n";
} finally {
    $few?->cleanup();
    $fx->cleanup();
}
