<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\ProductResolver\ProductResolver;

function assert_catalog(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);

$counts = $db->query(
    "SELECT c.slug, COUNT(*) count
     FROM products p JOIN product_categories c ON c.id=p.category_id
     WHERE p.is_active=1 AND c.slug IN ('cpu','gpu')
     GROUP BY c.slug"
)->fetchAll();
$byCategory = [];
foreach ($counts as $row) {
    $byCategory[(string)$row['slug']] = (int)$row['count'];
}

assert_catalog(($byCategory['cpu'] ?? 0) >= 10, 'Expected at least 10 active CPU products');
assert_catalog(($byCategory['gpu'] ?? 0) >= 10, 'Expected at least 10 active GPU products');

$thinAliases = $db->query(
    "SELECT p.full_name, COUNT(pa.id) alias_count
     FROM products p
     JOIN product_categories c ON c.id=p.category_id
     LEFT JOIN product_aliases pa ON pa.product_id=p.id
     WHERE p.is_active=1 AND c.slug IN ('cpu','gpu')
     GROUP BY p.id
     HAVING alias_count < 5"
)->fetchAll();
assert_catalog(count($thinAliases) === 0, 'Every active CPU/GPU POC product should have at least 5 aliases');

$catalogRows = $db->query(
    "SELECT p.id, p.full_name, GROUP_CONCAT(pa.alias_text SEPARATOR '||') aliases
     FROM products p
     JOIN product_categories c ON c.id=p.category_id
     LEFT JOIN product_aliases pa ON pa.product_id=p.id
     WHERE p.is_active=1 AND c.slug IN ('cpu','gpu')
     GROUP BY p.id"
)->fetchAll();
$catalog = [];
foreach ($catalogRows as $row) {
    $catalog[] = [
        'id' => (int)$row['id'],
        'full_name' => (string)$row['full_name'],
        'aliases' => $row['aliases'] ? explode('||', (string)$row['aliases']) : [],
    ];
}

$resolver = new ProductResolver($catalog);
$cases = [
    'ขาย R7 5700X3D ประกันเหลือ' => 'AMD Ryzen 7 5700X3D',
    'i5 13600K มือสอง' => 'Intel Core i5-13600K',
    'RTX3060Ti 8G สวย' => 'NVIDIA GeForce RTX 3060 Ti',
    'RX 6700 XT 12GB' => 'AMD Radeon RX 6700 XT',
    'GTX1660S ใช้งานปกติ' => 'NVIDIA GeForce GTX 1660 Super',
    'RTX4070Ti ประกันร้าน' => 'NVIDIA GeForce RTX 4070 Ti',
];
$correct = 0;
$misses = [];
foreach ($cases as $title => $expectedName) {
    $resolution = $resolver->resolve($title);
    if ($resolution->matchedName === $expectedName) {
        $correct++;
    } else {
        $misses[] = $title . ' expected ' . $expectedName . ' got ' . ($resolution->matchedName ?? 'null');
    }
}
$accuracy = $correct / count($cases);
assert_catalog($accuracy >= 0.95, 'Resolver accuracy below 95% for Phase 1 DB catalog cases: ' . implode('; ', $misses));

echo "Phase 1 catalog test passed\n";
echo "CPU products: " . ($byCategory['cpu'] ?? 0) . "\n";
echo "GPU products: " . ($byCategory['gpu'] ?? 0) . "\n";
echo "Resolver accuracy: " . number_format($accuracy * 100, 2) . "%\n";
