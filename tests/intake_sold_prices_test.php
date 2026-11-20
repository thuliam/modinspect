<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeSoldPrices;
use App\Services\ProductResolver\ProductResolver;

/**
 * LS-23 B7: a collected SOLD price is only a pending candidate for the review queue. A dry run writes nothing, the second run adds nothing, an unmatched title is
 * reported, nothing is approved, and no price, snapshot or median changes. Test database inside a transaction, rolled back.
 */
function ok_sp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_sp($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$resolver = new ProductResolver((new \App\Models\CollectionPipeline())->catalog());
$row = static fn(string $id, string $title = 'NVIDIA GeForce RTX 3070 8GB ขายแล้ว', string $price = '8100'): array => ['source_listing_url' => "https://pantip.com/topic/9$id", 'source_item_id' => $id, 'listing_title' => $title, 'sold_price' => $price,
    'sold_confirmed_by' => 'seller_post', 'observed_at' => '2026-11-14 12:00:00', 'asking_price_before' => '8500', 'condition_hint' => 'used'];
$state = static fn(): string => md5(json_encode($db->query('SELECT product_id, median, valid_sample_size, confidence_score FROM price_indices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));

$db->beginTransaction();
try {
    $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('sold test', :e, 'x', 'admin')")->execute(['e' => 'sp-' . bin2hex(random_bytes(4)) . '@example.test']);
    $admin = (int)$db->lastInsertId();
    $before = $state();
    $obs = (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE price_type = 'sold'")->fetchColumn();
    $approvedAsking = (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn();

    $dry = IntakeSoldPrices::import($db, $admin, [$row('101'), $row('102', 'zzz unknown thing')], $resolver, false);
    ok_sp($dry['to_add'] === 1 && $dry['unresolved'] === 1 && $dry['added'] === 0, 'A dry run counts the match and the unmatched title: ' . json_encode($dry));
    ok_sp((int)$db->query("SELECT COUNT(*) FROM price_observations WHERE price_type = 'sold'")->fetchColumn() === $obs && (int)$db->query("SELECT COUNT(*) FROM data_sources WHERE source_key = 'collected_sold_listing'")->fetchColumn() === 0, 'A dry run writes nothing, not even the source row');

    $real = IntakeSoldPrices::import($db, $admin, [$row('101'), $row('102', 'zzz unknown thing')], $resolver, true);
    ok_sp($real['added'] === 1 && $real['unresolved'] === 1, 'The matched row is added');
    $o = $db->query("SELECT * FROM price_observations WHERE price_type = 'sold' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ok_sp($o['verified_status'] === 'pending' && $o['asking_price'] === null && (float)$o['price_value'] === 8100.0 && (int)$o['evidence_level'] === 1, 'It is a PENDING sold observation at the lowest evidence level, with no asking price');
    $d = $db->prepare('SELECT lane, decision FROM observation_review_decisions WHERE price_observation_id = :o'); $d->execute(['o' => $o['id']]); $dec = $d->fetch(PDO::FETCH_ASSOC);
    ok_sp($dec && $dec['decision'] === 'review_required', 'A review decision says a person must decide');
    ok_sp((int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn() === $approvedAsking, 'Nothing was approved');
    ok_sp($state() === $before, 'No snapshot, median, sample size or confidence changed');

    $again = IntakeSoldPrices::import($db, $admin, [$row('101')], $resolver, true);
    ok_sp($again['already_stored'] === 1 && $again['added'] === 0, 'The same listing again adds nothing');
} finally {
    $db->rollBack();
}
$src = (string)file_get_contents(ROOT_PATH . '/app/Services/Intake/IntakeSoldPrices.php') . (string)file_get_contents(ROOT_PATH . '/cli/intake_import_sold_prices.php');
ok_sp(!str_contains($src, "'approved'") && !str_contains($src, 'price_indices'), 'The importer never approves and never touches snapshots');
echo "intake_sold_prices_test: PASS\n";
