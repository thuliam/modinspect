<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\SnapshotHistory as H;
use App\Services\Pricing\PriceSnapshotService;

function ok_sh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_sh(H::diffSets([1, 2, 3], [2, 3, 4, 5]) === ['added' => 2, 'removed' => 1, 'kept' => 2], 'Added, removed and kept observation ids');
ok_sh(H::diffSets([], [1, 2]) === ['added' => 2, 'removed' => 0, 'kept' => 0], 'A first snapshot has everything added');
ok_sh(H::diffSets([1, 2], [1, 2]) === ['added' => 0, 'removed' => 0, 'kept' => 2], 'An identical set changes nothing');
ok_sh(H::medianChange(8000.0, 8800.0) === ['delta' => 800.0, 'change_pct' => 10.0], 'Median rise in baht and percent');
ok_sh(H::medianChange(10000.0, 9000.0)['change_pct'] === -10.0, 'Median fall is negative');
ok_sh(H::medianChange(0.0, 9000.0)['change_pct'] === null, 'No percentage against a zero median');
ok_sh((new H())->forSnapshot(2147483000) === null, 'An unknown snapshot returns null');

// fixture (rolled back): two recalculations of one product, the second adds two observations
global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
        VALUES (?,?,?,'research','manual','manual','low',60,'low',14,1,0)")->execute(['offline_owner_text_sh_' . $suffix, 'History ' . $suffix, 'sh-' . $suffix . '.test']);
    $source = (int)$db->lastInsertId();
    $category = (int)$db->query("SELECT id FROM product_categories WHERE slug='cpu' LIMIT 1")->fetchColumn();
    $brand = (int)$db->query("SELECT id FROM brands WHERE slug='amd' LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active) VALUES (?,?,?,?,?,1)")->execute([$category, $brand, 'SH Fixture', 'sh-fixture-' . $suffix, 'AMD SH Fixture ' . $suffix]);
    $pid = (int)$db->lastInsertId();
    $obs = static function (string $seed, int $price) use ($db, $pid, $source): void {
        $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")
            ->execute([$source, hash('sha256', $seed), 'SH ' . $seed, (string)$price]);
        $raw = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at)
            VALUES (?,?, 'asking',?,?,'THB','good','single_item',2,90,'approved',?)")->execute([$raw, $pid, $price, $price, date('Y-m-d 12:00:00')]);
    };
    foreach ([3000, 3100, 3200] as $i => $p) { $obs("a$i$suffix", $p); }
    $svc = new PriceSnapshotService();
    $first = $svc->recalculateProduct($pid, 'asking');
    ok_sh($first !== null, 'First fixture snapshot exists');
    foreach ([3300, 3400] as $i => $p) { $obs("b$i$suffix", $p); }
    $second = $svc->recalculateProduct($pid, 'asking');
    ok_sh($second !== null && (int)$second['snapshot_id'] > (int)$first['snapshot_id'], 'Second fixture snapshot is newer');

    $r = (new H())->forSnapshot((int)$second['snapshot_id']);
    ok_sh($r !== null && $r['previous']['id'] === (int)$first['snapshot_id'], 'The previous snapshot is the first one');
    ok_sh($r['diff'] === ['added' => 2, 'removed' => 0, 'kept' => 3], 'Two observations were added, none removed, three kept');
    ok_sh($r['median']['delta'] > 0 && $r['median']['change_pct'] > 0, 'The median rose');
    ok_sh(count($r['history']) === 2 && $r['history'][0]['id'] === (int)$second['snapshot_id'], 'History lists both, newest first');
    $rf = (new H())->forSnapshot((int)$first['snapshot_id']);
    ok_sh($rf !== null && $rf['previous'] === null && $rf['diff'] === null, 'The first snapshot has no previous');
    ok_sh(!preg_match('~https?://|offline_owner_text|sh-' . $suffix . '~i', (string)json_encode($r)), 'No source names or URLs in the result');
} finally {
    $db->rollBack();
}

echo "snapshot_history_test: PASS\n";
