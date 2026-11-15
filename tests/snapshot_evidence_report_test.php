<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\SnapshotEvidenceReport;
use App\Services\Pricing\PriceSnapshotService;

function assert_ser(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $mkSource = static function (string $key, string $domain) use ($db): int {
        $db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
            VALUES (?,?,?,'research','manual','manual','low',60,'low',14,1,0)")->execute([$key, 'Evidence report ' . $key, $domain]);
        return (int)$db->lastInsertId();
    };
    $owner = $mkSource('offline_owner_text_ser_' . $suffix, 'ser-owner.test');
    $other = $mkSource('ser_other_' . $suffix, 'ser-other.test');

    $category = (int)$db->query("SELECT id FROM product_categories WHERE slug='cpu' LIMIT 1")->fetchColumn();
    $brand = (int)$db->query("SELECT id FROM brands WHERE slug='amd' LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active) VALUES (?,?,?,?,?,1)")->execute([$category, $brand, 'SER Fixture', 'ser-fixture-' . $suffix, 'AMD SER Fixture']);
    $pid = (int)$db->lastInsertId();

    $obs = static function (int $sourceId, string $seed, int $price, string $status = 'approved') use ($db, $pid): void {
        $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")
            ->execute([$sourceId, hash('sha256', $seed), 'SER fixture ' . $seed, (string)$price]);
        $raw = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at)
            VALUES (?,?, 'asking',?,?,'THB','good','single_item',2,90,?,?)")->execute([$raw, $pid, $price, $price, $status, date('Y-m-d 12:00:00')]);
    };
    foreach ([3000, 3100, 3200, 3300] as $i => $p) { $obs($owner, "o$i$suffix", $p); }
    $obs($other, "x$suffix", 3250);
    $obs($owner, "pending$suffix", 3150, 'pending');   // not accepted -> excluded

    $snap = (new PriceSnapshotService())->recalculateProduct($pid, 'asking');
    assert_ser($snap !== null, 'Fixture snapshot must exist');
    $report = (new SnapshotEvidenceReport())->forSnapshot((int)$snap['snapshot_id']);

    assert_ser($report !== null && $report['product'] === 'AMD SER Fixture', 'Header must name the product');
    assert_ser($report['counts']['included'] === 5 && ($report['counts']['excluded_by_reason']['NOT_ACCEPTED'] ?? 0) === 1, 'Counts must show 5 included and 1 NOT_ACCEPTED');
    assert_ser($report['grades']['owner_text'] === 4 && $report['grades']['other'] === 1, 'Grades must split owner text 4 / other 1');
    assert_ser($report['sources']['distinct'] === 2 && abs($report['sources']['dominant_share'] - 0.8) < 0.001, 'Source concentration must be 2 sources, dominant 0.8');
    assert_ser($report['replay'] === 'MATCH', 'Replay must match');
    assert_ser(count($report['members']) === 6, 'Members must list included and excluded rows');
    assert_ser(isset($report['confidence_method_version']) && is_array($report['policy_caps']), 'Method version and caps must be present');
    assert_ser((new SnapshotEvidenceReport())->forSnapshot(999999999) === null, 'Unknown snapshot returns null');

    $flat = json_encode($report, JSON_UNESCAPED_UNICODE);
    assert_ser(!preg_match('~https?://|ser-owner|ser-other|SER fixture (o|x|pending)|offline_owner_text|external_reference~i', (string)$flat), 'Report must not contain source names, titles, URLs or references');
    echo "snapshot_evidence_report_test: PASS\n";
} finally {
    $db->rollBack();
}
