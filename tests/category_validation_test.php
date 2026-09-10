<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Data\SearchCandidate;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Review\ReviewCorrectionService;
use App\Services\Validation\CpuValidationRules;
use App\Services\Validation\GpuValidationRules;
use App\Services\Validation\ModelMarker;
use App\Services\Validation\ObservationValidator;

function assert_category_validation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function category_catalog(PDO $db): array
{
    $rows = $db->query(
        "SELECT p.id,p.full_name,GROUP_CONCAT(pa.alias_text SEPARATOR '||') aliases
         FROM products p
         JOIN product_categories c ON c.id=p.category_id
         LEFT JOIN product_aliases pa ON pa.product_id=p.id
         WHERE p.is_active=1 AND c.slug IN ('cpu','gpu')
         GROUP BY p.id"
    )->fetchAll();
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'full_name' => (string)$row['full_name'],
        'aliases' => $row['aliases'] ? explode('||', (string)$row['aliases']) : [],
    ], $rows);
}

function validate_title(ProductResolver $resolver, string $title, ?string $priceText = '7500'): array
{
    $extractor = new RuleBasedListingExtractor();
    $validator = new ObservationValidator(false);
    $candidate = new SearchCandidate('category-validation-test', $title, $priceText, 'https://example.test/category/' . hash('sha1', $title), '2026-09-09 12:00:00', 'C');
    $extraction = $extractor->extract($candidate);
    $resolution = $resolver->resolve($title);
    $validation = $validator->validate($extraction, $resolution);
    return [$extraction, $resolution, $validation];
}

function insert_snapshot_observation(PDO $db, int $productId, int $sourceId, string $suffix, int $price, array $overrides = []): int
{
    $raw = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,source_url_encrypted,captured_at,processing_status)
         VALUES (:source_id,:hash,:title,:price,:url,'2026-09-09 12:00:00','review')"
    );
    $raw->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', 'category-validation-' . $suffix),
        'title' => 'Category validation ' . $suffix,
        'price' => (string)$price,
        'url' => 'https://example.test/category/' . $suffix,
    ]);
    $rawId = (int)$db->lastInsertId();
    $data = array_merge([
        'listing_type' => 'single_item',
        'is_deposit' => 0,
        'is_defective' => 0,
        'quality_flags' => [],
    ], $overrides);
    $stmt = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,quality_flags,verified_status,observed_at)
         VALUES (:raw_id,:product_id,'asking',:asking_price,:price_value,'THB','good',:listing_type,:is_deposit,:is_defective,0,4,99,:quality_flags,'approved','2026-09-09 12:00:00')"
    );
    $stmt->execute([
        'raw_id' => $rawId,
        'product_id' => $productId,
        'asking_price' => $price,
        'price_value' => $price,
        'listing_type' => $data['listing_type'],
        'is_deposit' => $data['is_deposit'],
        'is_defective' => $data['is_defective'],
        'quality_flags' => json_encode($data['quality_flags'], JSON_UNESCAPED_UNICODE),
    ]);
    return (int)$db->lastInsertId();
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $resolver = new ProductResolver(category_catalog($db));

    foreach ([
        'ขาย R7 5700X3D ประกันเหลือ' => '5700x3d',
        'Ryzen 7 5700X ไม่ใช่ X3D' => null,
        'AMD 5800X3D มือสอง' => null,
        'Ryzen 5 5600G มี iGPU' => null,
        'Ryzen 5 5600 AM4' => '5600',
    ] as $title => $expectedMarker) {
        [, $resolution, $validation] = validate_title($resolver, $title);
        $marker = $resolution->matchedName === null ? null : ModelMarker::primary($resolution->matchedName);
        assert_category_validation($marker === $expectedMarker || in_array('WRONG_PRODUCT', $validation->reasonCodes, true), 'CPU suffix discrimination failed for ' . $title);
    }

    foreach ([
        'RTX3060Ti 8G สวย' => 'rtx3060ti',
        'RTX 3070 Ti 8GB มือสอง' => 'WRONG_PRODUCT',
        'RTX4070Ti ประกันร้าน' => 'rtx4070ti',
        'RTX 4070 SUPER 12GB' => 'UNRESOLVED_PRODUCT',
        'RX 6700 XT 12GB' => 'rx6700xt',
    ] as $title => $expected) {
        [, $resolution, $validation] = validate_title($resolver, $title);
        $marker = $resolution->matchedName === null ? null : ModelMarker::primary($resolution->matchedName);
        if (str_starts_with($expected, 'WRONG') || str_starts_with($expected, 'UNRESOLVED')) {
            assert_category_validation(in_array($expected, $validation->reasonCodes, true), 'GPU unsafe nearby model should be flagged for ' . $title);
        } else {
            assert_category_validation($marker === $expected, 'GPU model discrimination failed for ' . $title);
        }
    }

    $ruleCases = [
        'RTX 3070 Laptop GPU ถอดจาก notebook' => ['red', 'MOBILE_GPU'],
        'Ryzen5700X3D + B550 bundle พร้อมบอร์ด' => ['amber', 'BUNDLE'],
        'ขายทั้งเครื่อง Ryzen 5700X3D RTX 3070 RAM 32GB' => ['red', 'WHOLE_PC'],
        'รับซื้อ RX 6600 XT งบ 4200' => ['red', 'WANTED'],
        'จอง RTX 3070 มัดจำก่อน 500' => ['red', 'DEPOSIT'],
        'RX6700XT เสีย เปิดไม่ติด ขายซ่อม' => ['red', 'DEFECTIVE'],
        'RTX 4070 ราคา 16000 หรือแลก RTX 3070 เพิ่มเงิน 5000' => ['amber', 'MULTIPLE_PRICES'],
        'ASUS ROG STRIX RTX 4070 Ti ประกันยาว' => ['green', 'BOARD_PARTNER_CONTEXT'],
    ];
    foreach ($ruleCases as $title => [$lane, $flag]) {
        [, , $validation] = validate_title($resolver, $title, $title);
        assert_category_validation($validation->lane === $lane, 'Expected lane ' . $lane . ' for ' . $title . ', got ' . $validation->lane);
        assert_category_validation(in_array($flag, $validation->reasonCodes, true), 'Expected flag ' . $flag . ' for ' . $title);
    }

    [, , $valid] = validate_title($resolver, 'ขาย RTX3070 8GB ใช้งานได้ดี');
    assert_category_validation(in_array('category_validation_version:' . GpuValidationRules::VERSION, $valid->reasonCodes, true), 'GPU validation version missing');
    [, , $cpuValid] = validate_title($resolver, 'ขาย R7 5700X3D ประกันเหลือ');
    assert_category_validation(in_array('category_validation_version:' . CpuValidationRules::VERSION, $cpuValid->reasonCodes, true), 'CPU validation version missing');

    $source = $db->prepare(
        "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
         VALUES (:source_key,'Category Validation Source','fixture.category-validation','research','manual','manual','low',90,'high',14,1,0)"
    );
    $source->execute(['source_key' => 'category_validation_' . bin2hex(random_bytes(4))]);
    $sourceId = (int)$db->lastInsertId();
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='nvidia-geforce-rtx-3070' LIMIT 1")->fetchColumn();
    assert_category_validation($productId > 0, 'Missing RTX 3070 fixture product');

    foreach ([7000, 7600, 8200] as $i => $price) {
        insert_snapshot_observation($db, $productId, $sourceId, 'included-' . $i, $price);
    }
    $mobileId = insert_snapshot_observation($db, $productId, $sourceId, 'mobile', 5000, ['quality_flags' => ['MOBILE_GPU']]);
    $wrongId = insert_snapshot_observation($db, $productId, $sourceId, 'wrong', 5100, ['quality_flags' => ['WRONG_PRODUCT']]);
    $bundleId = insert_snapshot_observation($db, $productId, $sourceId, 'bundle', 9000, ['listing_type' => 'bundle', 'quality_flags' => ['BUNDLE']]);
    $defectiveId = insert_snapshot_observation($db, $productId, $sourceId, 'defective', 3000, ['is_defective' => 1, 'quality_flags' => ['DEFECTIVE']]);
    $depositId = insert_snapshot_observation($db, $productId, $sourceId, 'deposit', 500, ['is_deposit' => 1, 'quality_flags' => ['DEPOSIT']]);

    $snapshot = (new PriceSnapshotService())->recalculateProduct($productId, 'asking');
    assert_category_validation($snapshot !== null, 'Expected category snapshot fixture');
    $snapshotId = (int)$snapshot['snapshot_id'];
    $reasons = [];
    foreach ($db->query("SELECT observation_id,exclusion_reason FROM price_snapshot_observations WHERE snapshot_id={$snapshotId} AND inclusion_status='excluded'") as $row) {
        $reasons[(int)$row['observation_id']] = (string)$row['exclusion_reason'];
    }
    assert_category_validation($reasons[$mobileId] === 'MOBILE_GPU', 'Mobile GPU must be excluded from asking snapshot');
    assert_category_validation($reasons[$wrongId] === 'WRONG_PRODUCT', 'Wrong product must be excluded from asking snapshot');
    assert_category_validation($reasons[$bundleId] === 'OUTSIDE_COHORT', 'Bundle must be excluded from asking snapshot');
    assert_category_validation($reasons[$defectiveId] === 'DEFECTIVE', 'Defective must be excluded from asking snapshot');
    assert_category_validation($reasons[$depositId] === 'DEPOSIT', 'Deposit must be excluded from asking snapshot');

    $correctionService = new ReviewCorrectionService();
    $raw = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,source_url_encrypted,captured_at,processing_status)
         VALUES (:source_id,:hash,'Correction revalidation RTX 3070 bundle','9000',:url,'2026-09-09 12:00:00','review')"
    );
    $raw->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', 'category-correction-' . random_int(1, 1000000)),
        'url' => 'https://example.test/category/correction',
    ]);
    $rawId = (int)$db->lastInsertId();
    $obs = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,quality_flags,verified_status,observed_at)
         VALUES (:raw_id,:product_id,'asking',9000,9000,'THB','good','bundle',4,88,'[\"BUNDLE\"]','pending','2026-09-09 12:00:00')"
    );
    $obs->execute(['raw_id' => $rawId, 'product_id' => $productId]);
    $observationId = (int)$db->lastInsertId();
    assert_category_validation($correctionService->correct($observationId, 'listing_type', 'single_item', 'Reviewer verified standalone component')['ok'] === true, 'Correction should save listing_type');
    $result = $correctionService->applyApprovedValues($observationId);
    assert_category_validation($result['ok'] === true, 'Correction revalidation should allow approved values');
    assert_category_validation(!in_array('BUNDLE', $result['quality_flags'], true), 'Correction revalidation should remove BUNDLE flag');

    $quality = json_decode((string)shell_exec('C:\\xampp8.1\\php\\php.exe ' . escapeshellarg(dirname(__DIR__) . '\\cli\\evaluate_validation_quality.php')), true);
    assert_category_validation(($quality['passes_targets'] ?? false) === true, 'Quality evaluator should pass Phase 3C targets');

    echo "Category validation test passed\n";
    echo "CPU/GPU deterministic discrimination: ok\n";
    echo "Snapshot contamination regression: ok\n";
    echo "Correction revalidation: ok\n";
    echo "Golden dataset fixtures: " . ($quality['fixture_count'] ?? 0) . "\n";
} finally {
    $db->rollBack();
}
