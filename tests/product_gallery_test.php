<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Product;

/** LS-69: product picture gallery. Rows from product_images become a Bootstrap carousel with a credit per picture; paths outside assets/products are never drawn; no rows means nothing is drawn (the page keeps its legacy picture). */
function ok_pg(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$base = ''; $product = ['id' => 7, 'full_name' => 'Test Model'];
$render = static function (array $images) use ($base, $product): string { ob_start(); require ROOT_PATH . '/app/Views/components/product-gallery.php'; return (string) ob_get_clean(); };
$row = static fn (int $slot, string $path = null): array => ['slot' => $slot, 'path' => $path ?? "assets/products/test-model-$slot.jpg", 'alt_text' => '', 'source_name' => 'Owner <photos>', 'source_url' => 'https://example.test/src', 'license_note' => 'CC BY 4.0'];

ok_pg($render([]) === '', 'no pictures draws nothing');
$one = $render([$row(1)]);
ok_pg(str_contains($one, 'test-model-1.jpg') && !str_contains($one, 'carousel-control') && !str_contains($one, 'carousel-indicators'), 'one picture: no controls');
$five = $render(array_map($row, [1, 2, 3, 4, 5]));
ok_pg(substr_count($five, '<img') === 5 && substr_count($five, 'data-slide-to') === 5 && str_contains($five, 'carousel-control-next') && str_contains($five, 'mi-gallery-7'), 'five pictures: slides, indicators and controls');
ok_pg(substr_count($render(array_map($row, range(1, 8))), '<img') === 5, 'never more than five pictures');
ok_pg(str_contains($five, 'Owner &lt;photos&gt;') && str_contains($five, 'CC BY 4.0') && str_contains($five, 'rel="noopener noreferrer"'), 'credit and licence are escaped and shown');
ok_pg($render([$row(1, 'https://evil.test/a.jpg'), $row(2, 'assets/../secret.jpg'), $row(3, 'assets/products/ok.jpg')]) !== '' && substr_count($render([$row(1, 'https://evil.test/a.jpg'), $row(2, 'assets/../secret.jpg')]), '<img') === 0, 'a path outside assets/products is never drawn');

global $config;
$db = Database::connection($config['db']);
ok_pg($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$pid = (int) $db->query('SELECT id FROM products ORDER BY id LIMIT 1')->fetchColumn();
$db->beginTransaction();
try {
    $ins = $db->prepare("INSERT INTO product_images (product_id, slot, path, source_name, license_note) VALUES (:p, :s, :path, 'S', 'L')");
    $ins->execute(['p' => $pid, 's' => 3, 'path' => 'assets/products/x-3.jpg']); $ins->execute(['p' => $pid, 's' => 1, 'path' => 'assets/products/x-1.jpg']);
    $got = (new Product())->images($pid);
    ok_pg(array_column($got, 'slot') === [1, 3], 'Product::images returns the rows in slot order');
} finally { $db->rollBack(); }
echo "product_gallery_test: PASS\n";
