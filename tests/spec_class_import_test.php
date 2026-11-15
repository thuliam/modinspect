<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Research\SpecClassifier;

function assert_spec(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// classifier: every field of a class must be stated, otherwise null
$ram = SpecClassifier::classify(['category' => 'ram', 'ddr_gen' => 'ddr4', 'capacity_gb' => '16', 'speed_mhz' => '3200']);
assert_spec($ram !== null && $ram['slug'] === 'ram-ddr4-3200-16gb' && $ram['full_name'] === 'RAM DDR4-3200 16GB', 'RAM class slug/name wrong');
assert_spec(SpecClassifier::classify(['category' => 'ram', 'ddr_gen' => 'ddr4', 'capacity_gb' => '16', 'speed_mhz' => '']) === null, 'RAM without speed must not be classified');
assert_spec(SpecClassifier::classify(['category' => 'ram', 'ddr_gen' => 'ddr4', 'capacity_gb' => '16', 'speed_mhz' => '3333']) === null, 'RAM with an unusual speed must not be classified');
assert_spec((SpecClassifier::classify(['category' => 'storage', 'storage_type' => 'm2_nvme', 'capacity_gb' => '1000']))['slug'] === 'storage-m2-nvme-1000gb', 'Storage class wrong');
assert_spec(SpecClassifier::classify(['category' => 'storage', 'storage_type' => 'm2_unspecified', 'capacity_gb' => '1000']) === null, 'Unspecified M.2 must not be classified');
assert_spec((SpecClassifier::classify(['category' => 'psu', 'watt' => '750', 'efficiency' => 'Gold']))['slug'] === 'psu-750w-gold', 'PSU class wrong');
assert_spec(SpecClassifier::classify(['category' => 'psu', 'watt' => '750', 'efficiency' => '']) === null, 'PSU without efficiency must not be classified');
assert_spec(SpecClassifier::classify(['category' => 'gpu', 'model_key' => 'rtx 3070']) === null, 'GPU is not a spec class');

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    // a spec-class product for this test (the isolated test DB has no migration 014)
    $brandId = (int)$db->query("SELECT id FROM brands ORDER BY id LIMIT 1")->fetchColumn();
    $catId = (int)$db->query("SELECT id FROM product_categories WHERE slug='ram' LIMIT 1")->fetchColumn();
    assert_spec($brandId > 0 && $catId > 0, 'Test DB needs a brand and the ram category');
    $slug = 'ram-ddr4-3200-16gb';
    if (!(int)$db->query("SELECT COUNT(*) FROM products WHERE slug='$slug'")->fetchColumn()) {
        $db->prepare("INSERT INTO products (category_id,brand_id,model_name,slug,full_name,generation,spec_summary,capacity_gb,memory_type,is_active,created_at,updated_at) VALUES (:c,:b,'RAM DDR4-3200 16GB',:s,'RAM DDR4-3200 16GB','DDR4','spec class test',16,'DDR4',1,NOW(),NOW())")->execute(['c' => $catId, 'b' => $brandId, 's' => $slug]);
    }
    $pid = (int)$db->query("SELECT id FROM products WHERE slug='$slug'")->fetchColumn();
    $suffix = bin2hex(random_bytes(4));
    $rec = static fn(string $id, string $price, ?string $classSlug): array => [
        'source_label' => 'owner_collected_facebook_text_spec', 'provenance_grade' => 'OWNER_TEXT_NO_URL', 'source' => 'owner_collected_facebook_text',
        'source_item_id' => "SC-$suffix-$id", 'external_ref' => "SC-$suffix-$id", 'catalog_slug' => $classSlug,
        'listing_title' => "แรม DDR4 3200 16GB (8x2) $price", 'title' => "แรม DDR4 3200 16GB (8x2) $price", 'listing_text' => "ขายแรม DDR4 บัส 3200 รวม 16GB ราคา $price บาท",
        'asking_price' => $price, 'currency' => 'THB', 'observed_at' => date('Y-m-d', strtotime('-2 days')) . ' 12:00:00',
    ];
    $dir = ROOT_PATH . '/storage/import/test'; if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    $file = $dir . "/spec-class-$suffix.json";
    file_put_contents($file, json_encode(['records' => [$rec('1', '1000', $slug), $rec('2', '1100', $slug), $rec('3', '1200', $slug), $rec('4', '900', 'ram-ddr9-0-1gb')]], JSON_UNESCAPED_UNICODE));
    $service = new OfflineEvidenceImportService();
    $run = $service->import($file, 'owner_text', false, 10);
    assert_spec($run['candidates_created'] === 4, 'Expected four candidates: ' . json_encode($run));
    $sourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='offline_owner_text_owner_collected_facebook_text_spec'")->fetchColumn();
    $matched = (int)$db->query("SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id=$sourceId AND o.product_id=$pid")->fetchColumn();
    assert_spec($matched === 3, "Three rows must resolve to the spec-class product, got $matched");
    $stray = (int)$db->query("SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id=$sourceId AND o.product_id<>$pid")->fetchColumn();
    assert_spec($stray === 0, 'An unknown spec-class slug must not attach to another product by accident');
    // a catalog_slug is ignored outside the owner_text dataset
    $other = write_spec_other($dir, $suffix, $rec('9', '1300', $slug));
    $real = $service->import($other, 'test', true, 10);
    assert_spec(($real['dataset'] ?? '') === 'TEST', 'Other datasets keep their own rules');

    echo "Spec class import test passed\n";
    echo "Resolved by spec class: $matched of 4 (unknown slug stayed unmatched)\n";
} finally {
    $db->rollBack();
}

function write_spec_other(string $dir, string $suffix, array $record): string
{
    $p = $dir . "/spec-class-other-$suffix.json";
    file_put_contents($p, json_encode(['records' => [$record + ['source_domain' => 'market.local']]], JSON_UNESCAPED_UNICODE));
    return $p;
}
