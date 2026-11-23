<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ImageImport;

/**
 * LS-68: bulk load of product pictures from a folder (the admin page takes 20 files at a time; this takes any number).
 * Usage: php cli/image_import.php --dir=storage/import/images --source="Owner photos" --license="Owner permission" [--url=https://...] [--alt="..."] [--apply]
 * Files are named after the model slug: slug.jpg (picture 1), slug_2.png ... slug_5.webp. Dry run by default; --apply writes. Reads local files only, no network.
 */
global $config;
$o = getopt('', ['dir:', 'source:', 'license:', 'url::', 'alt::', 'apply']);
$dir = (string) ($o['dir'] ?? '');
if ($dir === '' || !is_dir($dir)) { fwrite(STDERR, "Usage: php cli/image_import.php --dir=<folder> --source=<name> --license=<text> [--url=] [--alt=] [--apply]\n"); exit(2); }
$db = Database::connection($config['db']);
if (!ImageImport::tableExists($db)) { fwrite(STDERR, "The product_images table is missing (migration 033 has not been applied to this database).\n"); exit(3); }
$files = [];
foreach (glob(rtrim($dir, '/\\') . '/*') ?: [] as $p) {
    if (is_file($p) && preg_match('/\.(jpe?g|png|webp)$/i', $p)) { $files[] = ['name' => basename($p), 'path' => $p]; }
}
sort($files);
$plan = ImageImport::plan($db, $files, ['source_name' => (string) ($o['source'] ?? ''), 'license_note' => (string) ($o['license'] ?? ''), 'source_url' => (string) ($o['url'] ?? ''), 'alt' => (string) ($o['alt'] ?? '')]);
$ready = array_filter($plan, static fn($p) => $p['data'] !== null);
$report = ['apply' => isset($o['apply']), 'files' => count($files), 'valid' => count($ready), 'refused' => array_values(array_map(static fn($p) => $p['name'] . ': ' . $p['error'], array_filter($plan, static fn($p) => $p['data'] === null)))];
if (isset($o['apply'])) {
    if ($ready === []) { echo json_encode($report + ['error' => 'nothing valid to import'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"; exit(1); }
    $report += ImageImport::apply($db, $plan, dirname(__DIR__) . '/public/assets/products');
}
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
