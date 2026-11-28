<?php
declare(strict_types=1);

/** LS-90 T1: a listing that says the item is brand new is not a used price; wording that only compares a used item with a new one is. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/support/PriceFixture.php';

use App\Services\Pricing\BrandNewClaim;
use App\Services\Pricing\PriceRefresh;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;

function assert_new(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

// made-up listing texts in the shapes sellers use
foreach ([
    'การ์ดจอ มือ 1 ประกันศูนย์', 'CPU มือ1 ยังไม่แกะ', 'RAM 16GB มือหนึ่ง', 'ของใหม่ประกันร้าน', 'ใหม่แกะกล่อง', 'กล่องยังไม่แกะ', 'ยังไม่ได้แกะ',
    'Brand new in box', 'brand-new card', 'CPU new sealed', 'still sealed', 'ครบกล่อง (มือ 1) ประกันยาว', 'สภาพดีมาก ของใหม่', 'ขายมือ 1.5 ครับ แถมพัดลมของใหม่',
] as $text) {
    assert_new(BrandNewClaim::detect($text) !== null, "new item expected: $text");
}
foreach ([
    'การ์ดจอมือสอง สภาพดี', 'ขายมือ 1.5 สวยมาก', 'สภาพเหมือนมือ 1', 'สภาพน้องๆมือ1 เลย', 'น้อง ๆ มือหนึ่ง', 'การ์ดสภาพมือ 1', 'อุปกรณ์ครบตามมือ1',
    'ถูกกว่ามือ 1 เยอะ', 'ราคามือ 1 อยู่ที่ 9000', 'ซื้อมือ1มา ใช้ 3 เดือน', 'ซื้อมาจากมือ 1', 'ไม่ใช่ของใหม่', 'like brand new', 'not sealed', 'มือ 10 ปี', 'ใช้มา 1 ปี', '',
] as $text) {
    assert_new(BrandNewClaim::detect($text) === null, "used item expected: $text (got " . BrandNewClaim::detect($text) . ')');
}

$fx = new PriceFixture('brandnew');
$other = null;
try {
    $service = new PriceSnapshotService();
    $db = $fx->db;
    $publicId = static function (int $product) use ($db): ?int {
        $st = $db->prepare('SELECT ' . App\Services\Pricing\PublicSnapshot::idSql('p.id') . ' FROM products p WHERE p.id=?');
        $st->execute([$product]);
        $id = $st->fetchColumn();
        return $id === null || $id === false ? null : (int)$id;
    };
    $retitle = static function (int $observation, string $title) use ($db): void {
        $db->prepare('UPDATE raw_price_observations r JOIN price_observations o ON o.raw_observation_id=r.id SET r.raw_title=? WHERE o.id=?')->execute([$title, $observation]);
    };

    // four used listings give a price
    $product = $fx->productId;
    $ids = [];
    foreach ([1000, 1100, 1200, 1300] as $price) { $ids[] = $fx->observation($price); }
    assert_new($service->recalculateProduct($product) !== null && $publicId($product) !== null, 'a public price exists');
    $history = static fn() => (int)$db->query("SELECT COUNT(*) FROM price_histories WHERE product_id=$product")->fetchColumn();

    // a collector's note in the evidence excerpt is not the listing's own text
    $db->prepare("INSERT INTO market_evidence (raw_observation_id,source_id,evidence_level,evidence_type,excerpt,captured_at) SELECT r.id,r.source_id,'C','snippet',?,NOW() FROM raw_price_observations r JOIN price_observations o ON o.raw_observation_id=r.id WHERE o.id=?")
        ->execute(['Collector note: marked sealed where the seller states it is brand new', $ids[2]]);
    assert_new(count($service->cohort($product)['included']) === 4, 'an excerpt note does not exclude a listing');

    // one turns out to be new: it is left out with its own reason, three used ones remain
    $retitle($ids[0], 'ของ ทดสอบ มือหนึ่ง');
    $reasons = array_column($service->cohort($product)['excluded'], 'exclusion_reason', 'observation_id');
    assert_new(($reasons[$ids[0]] ?? null) === 'BRAND_NEW_CLAIM', 'excluded with the reason: ' . json_encode($reasons));
    $snapshot = $service->recalculateProduct($product);
    assert_new($snapshot !== null && $snapshot['valid_sample_size'] === 3 && (float)$snapshot['median'] === 1200.0, 'recalculated from the used listings');
    $kept = $publicId($product);
    $before = $history();

    // a second one: too few remain, so the public price must disappear instead of staying
    $retitle($ids[1], 'ของ ทดสอบ sealed');
    assert_new($publicId($product) === $kept, 'a text change alone does not rewrite history');
    $job = new SnapshotRecompute($db, $service);
    $plan = $job->planProduct($product);
    assert_new($plan['status'] === 'withdraw' && $plan['changes']['median']['to'] === null, 'plan says withdraw: ' . json_encode($plan));
    $rows = (int)$db->query("SELECT COUNT(*) FROM price_indices WHERE product_id=$product")->fetchColumn();
    $result = (new PriceRefresh($db))->refresh([$product])[0];
    assert_new($result['status'] === 'below_minimum' && $publicId($product) === null, 'the price is hidden: ' . json_encode($result));
    assert_new((int)$db->query("SELECT COUNT(*) FROM price_indices WHERE product_id=$product")->fetchColumn() === $rows + 1, 'older snapshots are kept');
    $withheld = (int)$db->query("SELECT MAX(id) FROM price_indices WHERE product_id=$product")->fetchColumn();
    assert_new($service->reproduceSnapshot($withheld)['result'] === 'MATCH' && $service->reproduceSnapshot($kept)['result'] === 'MATCH', 'both snapshots replay');
    assert_new($history() === $before, 'a withheld snapshot adds nothing to the price history');
    assert_new($job->planProduct($product)['status'] === 'not_eligible', 'asking again changes nothing');
    $service->recalculateProduct($product);
    assert_new((int)$db->query("SELECT COUNT(*) FROM price_indices WHERE product_id=$product")->fetchColumn() === $rows + 1, 'no repeated withheld rows');

    // nothing left at all
    $other = new PriceFixture('brandnew-empty');
    $empty = $other->productId;
    $all = [];
    foreach ([500, 600, 700] as $price) { $all[] = $other->observation($price); }
    $service->recalculateProduct($empty);
    foreach ($all as $id) { $retitle($id, 'brand new'); }
    assert_new($job->planProduct($empty)['status'] === 'withdraw' && $job->apply([$job->planProduct($empty)]) === 1 && $publicId($empty) === null, 'withdrawn with an empty cohort');
    assert_new($service->reproduceSnapshot((int)$db->query("SELECT MAX(id) FROM price_indices WHERE product_id=$empty")->fetchColumn())['result'] === 'MATCH', 'the empty snapshot replays');

    echo "brand_new_claim_test: PASS\n";
} finally {
    $other?->cleanup();
    $fx->db->exec("DELETE FROM market_evidence WHERE source_id={$fx->sourceId}");
    $fx->cleanup();
}
