<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;

/**
 * LS-23 C1: the recalculation plan. A dry run stores nothing; apply stores a snapshot only for new or changed products; a second apply stores nothing (history is
 * not flooded); no formula, observation or approval is touched. Test database inside a transaction, rolled back (the app shares one connection).
 */
function ok_sr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_sr($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$svc = new PriceSnapshotService();
$job = new SnapshotRecompute($db, $svc);
$count = static fn(): int => (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn();
$approved = static fn(): int => (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn();

$db->beginTransaction();
try {
    // a fixture of its own: a clean database (CI) has no model with three approved rows
    $suffix = bin2hex(random_bytes(4));
    $db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused) VALUES (?,?,?,'research','manual','manual','low',60,'low',14,1,0)")->execute(['offline_owner_text_sr_' . $suffix, 'Recompute ' . $suffix, 'sr-' . $suffix . '.test']);
    $source = (int)$db->lastInsertId();
    $category = (int)$db->query("SELECT id FROM product_categories WHERE slug='cpu' LIMIT 1")->fetchColumn(); $brand = (int)$db->query("SELECT id FROM brands WHERE slug='amd' LIMIT 1")->fetchColumn();
    $db->prepare('INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active) VALUES (?,?,?,?,?,1)')->execute([$category, $brand, 'SR Fixture', 'sr-fixture-' . $suffix, 'AMD SR Fixture ' . $suffix]);
    $fixturePid = (int)$db->lastInsertId();
    foreach ([3000, 3100, 3200] as $i => $price) {
        $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")->execute([$source, hash('sha256', "sr$i$suffix"), 'SR ' . $i, (string)$price]);
        $raw = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at) VALUES (?,?,'asking',?,?,'THB','good','single_item',2,90,'approved',?)")->execute([$raw, $fixturePid, $price, $price, date('Y-m-d 12:00:00')]);
    }
    $n0 = $count(); $a0 = $approved();
    $plan = $job->plan();
    $c = SnapshotRecompute::counts($plan);
    ok_sr(array_sum($c) === (int)$db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(), 'Every active product has a status');
    ok_sr($count() === $n0, 'Planning stores nothing');
    $eligible = array_values(array_filter($plan, static fn(array $r): bool => in_array($r['status'], ['new', 'changed'], true)));
    ok_sr($eligible !== [], 'The fixture data has at least one product whose price the plan would store');
    $byId0 = array_column($plan, null, 'product_id');
    ok_sr(($byId0[$fixturePid]['status'] ?? '') === 'new', 'The fixture model has three approved rows, so the plan says its price is new');
    $name = $byId0[$fixturePid]['name']; $pid = $fixturePid;

    $stored = $job->apply($plan);
    ok_sr($stored === count($eligible) && $count() === $n0 + $stored, 'Apply stores one snapshot per new or changed product and no other');
    $after = $job->plan();
    $byId = array_column($after, null, 'product_id');
    ok_sr($byId[$pid]['status'] === 'unchanged' && $byId[$pid]['name'] === $name, 'After applying, the same product is unchanged');
    $n1 = $count();
    ok_sr($job->apply($after) === 0 && $count() === $n1, 'Applying again stores nothing');
    ok_sr($approved() === $a0, 'No observation approval changed');

    // a change in the data shows up as "changed" with the numbers; below three samples a price is no longer eligible
    $rawNew = (static function () use ($db, $source, $suffix): int { $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")->execute([$source, hash('sha256', "sr-extra$suffix"), 'SR extra', '3300']); return (int)$db->lastInsertId(); })();
    $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at) VALUES (?,?,'asking',3300,3300,'THB','good','single_item',2,90,'approved',?)")->execute([$rawNew, $pid, date('Y-m-d 12:00:00')]);
    $row = array_column($job->plan(), null, 'product_id')[$pid];
    ok_sr($row['status'] === 'changed' && $row['changes']['valid_sample_size'] === ['from' => 3.0, 'to' => 4.0] && isset($row['changes']['median']), 'A fourth approved row is reported as a change with the old and new numbers: ' . json_encode($row['changes']));
    $db->prepare("UPDATE price_observations SET verified_status = 'rejected' WHERE product_id = :p")->execute(['p' => $pid]);
    ok_sr(array_column($job->plan(), null, 'product_id')[$pid]['status'] === 'not_eligible', 'Below three usable rows the model is not eligible (the old snapshot is not deleted)');
    ok_sr((int)$db->query('SELECT COUNT(*) FROM price_indices WHERE product_id = ' . (int)$pid)->fetchColumn() === 1, 'The earlier snapshot is still there');
} finally {
    $db->rollBack();
}
$src = (string)file_get_contents(ROOT_PATH . '/cli/snapshot_recompute.php');
ok_sr(str_contains($src, "in_array('--apply', \$argv, true)") && str_contains($src, 'DRY RUN'), 'The CLI is a dry run unless --apply is given');
echo "snapshot_recompute_test: PASS\n";
