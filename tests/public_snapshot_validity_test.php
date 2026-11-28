<?php
declare(strict_types=1);

/**
 * LS-88 T1 regression: a public price disappears as soon as any observation behind its snapshot stops being valid.
 * Found on the live site: a CPU page kept a median made of two SSD listings that had been excluded, because fewer than three rows were
 * left, no new snapshot was written and the old one stayed public.
 */
require __DIR__ . '/bootstrap.php';

use App\Controllers\SeoController;
use App\Core\Database;
use App\Models\DealCheck;
use App\Models\Product;
use App\Services\Admin\CoverageReport;
use App\Services\Pricing\PriceSnapshotService;

function assert_psv(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    [$productId, $otherId] = array_map('intval', $db->query('SELECT id FROM products WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN));
    $slug = (string)$db->query("SELECT slug FROM products WHERE id=$productId")->fetchColumn();
    $db->exec("UPDATE price_observations SET verified_status='excluded' WHERE product_id=$productId AND verified_status='approved'");
    $db->exec("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,risk_level,is_active,last_success_at) VALUES ('validity_sample_ls88','Validity sample','validity.example','research','manual','low',1,NOW())");
    $sourceId = (int)$db->lastInsertId();

    $add = static function (int $price) use ($db, $productId, $sourceId): int {
        static $n = 0; $n++;
        $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES ($sourceId,:h,:t,:p,NOW(),'processed')")
            ->execute(['h' => hash('sha256', 'validity-' . $n), 't' => 'Validity sample ' . $n, 'p' => (string)$price]);
        $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:r,:pid,'asking',:p,'THB','good','single_item',0,0,0,3,95,'approved',NOW())")
            ->execute(['r' => (int)$db->lastInsertId(), 'pid' => $productId, 'p' => $price]);
        return (int)$db->lastInsertId();
    };
    $model = new Product();
    $service = new PriceSnapshotService();
    $sitemap = static function (): string {
        $m = new ReflectionMethod(SeoController::class, 'buildSitemap'); $m->setAccessible(true);
        return (string)$m->invoke(new SeoController());
    };
    $row = static function () use ($model, $slug): array { return (array)$model->findBySlug($slug); };
    $deal = new DealCheck();
    $uuid = '00000000-0000-4000-8000-000000000088';
    $db->prepare("INSERT INTO deal_checks (uuid,product_id,user_price,condition_level,result_label,recommendation) VALUES (:u,:p,1300,'good','below_range','x')")->execute(['u' => $uuid, 'p' => $productId]);

    // 1) an older snapshot, then the newer one that is later invalidated
    $a = $add(1000); $b = $add(1100); $c = $add(1200);
    assert_psv($service->recalculateProduct($productId, 'asking') !== null, 'first snapshot expected');
    $d = $add(2000); $e = $add(2100);
    assert_psv($service->recalculateProduct($productId, 'asking') !== null, 'second snapshot expected');
    assert_psv((float)$row()['median'] === 1200.0, 'a valid latest snapshot is public');
    assert_psv($model->evidence($productId) !== null, 'evidence follows the public snapshot');
    assert_psv(str_contains($sitemap(), '/price/' . $slug . '<'), 'a priced model is in the sitemap');
    assert_psv((float)$deal->findByUuid($uuid)['median'] === 1200.0, 'the deal result reads the public snapshot');

    // 2) a newly approved row that is not part of the snapshot does not hide it
    $extra = $add(1500);
    assert_psv((float)$row()['median'] === 1200.0, 'an unrelated new approval must not hide the price');

    // 3) excluding one member hides the price at once, everywhere, with no fallback to the older (still intact) snapshot
    $db->exec("UPDATE price_observations SET verified_status='excluded' WHERE id=$d");
    $hidden = $row();
    assert_psv($hidden['median'] === null && $hidden['q1'] === null && $hidden['valid_sample_size'] === null, 'a stale snapshot must not be shown');
    foreach ($model->search() as $p) { if ((int)$p['id'] === $productId) { assert_psv($p['median'] === null, 'the price list must not show a stale price'); } }
    assert_psv($model->evidence($productId) === null, 'no evidence table for a hidden price');
    assert_psv($model->evidenceGrades($productId)['total'] === 0, 'no evidence grades for a hidden price');
    assert_psv($model->trend($productId) === [], 'no trend next to a hidden price');
    assert_psv(!str_contains($sitemap(), '/price/' . $slug . '<'), 'a hidden price leaves the sitemap');
    assert_psv($deal->findByUuid($uuid)['median'] === null, 'the deal result must not read a stale snapshot');
    $priced = new ReflectionClass(CoverageReport::class);
    assert_psv($priced->hasMethod('build'), 'coverage report is available');

    // 4) a recalculation with at least three valid rows brings a price back
    assert_psv($service->recalculateProduct($productId, 'asking') !== null, 'recalculation expected');
    assert_psv((float)$row()['median'] === 1200.0, 'the new snapshot is public');

    // 5) fewer than three valid rows: no new snapshot, and the old one stays hidden (the live-site case)
    $db->exec("UPDATE price_observations SET verified_status='excluded' WHERE id IN ($a,$b,$c,$extra)");
    assert_psv($service->recalculateProduct($productId, 'asking') === null, 'one row is below the minimum sample');
    assert_psv($row()['median'] === null, 'below the minimum sample nothing is shown');

    // 6) a member moved to another model, or with a changed price, also hides the snapshot
    $f = $add(3000); $g = $add(3100); $h = $add(3200);
    $service->recalculateProduct($productId, 'asking');
    assert_psv($row()['median'] !== null, 'price expected again');
    $db->exec("UPDATE price_observations SET product_id=$otherId WHERE id=$f");
    assert_psv($row()['median'] === null, 'a moved member hides the snapshot');
    $db->exec("UPDATE price_observations SET product_id=$productId WHERE id=$f");
    assert_psv($row()['median'] !== null, 'restored member restores the snapshot');
    $db->exec("UPDATE price_observations SET asking_price=9999 WHERE id=$g");
    assert_psv($row()['median'] === null, 'a changed member price hides the snapshot');

    echo "public_snapshot_validity_test: PASS\n";
} finally {
    $db->rollBack();
}
