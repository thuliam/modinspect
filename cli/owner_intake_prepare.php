<?php
declare(strict_types=1);

/**
 * Owner-intake preparation (DL-013): turns the sanitised research CSV into an import file for
 * `php cli/import_market_evidence.php --dataset=owner_text`.
 *
 * Read-only on the CSV, writes one JSON file. Keeps only rows the owner could reasonably approve:
 * a single item, no repost duplicate, not wanted/trade, not defective, no price anomaly, a numeric price and a known category.
 * Every record carries provenance_grade=OWNER_TEXT_NO_URL and its row id as the reference. Nothing is sent to a database here.
 * The rules live in App\Services\Research\OwnerIntakePreparer, shared with the admin intake screen (LS-3 J-3).
 *
 * Usage: php cli/owner_intake_prepare.php [--in=storage/research/line_facebook_2026/research_listings_sanitized.csv]
 *        [--out=storage/research/line_facebook_2026/owner_intake_batch.json] [--limit=500] [--category=gpu] [--spec]
 */
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Services/Research/SpecClassifier.php';
require ROOT_PATH . '/app/Services/Research/GpuVramFacts.php';
require ROOT_PATH . '/app/Services/Research/OwnerIntakePreparer.php';
use App\Services\Research\OwnerIntakePreparer;

$in = ROOT_PATH . '/storage/research/line_facebook_2026/research_listings_sanitized.csv';
$out = ROOT_PATH . '/storage/research/line_facebook_2026/owner_intake_batch.json';
$limit = 500;
$category = null;
$spec = false;   // --spec: RAM / storage / PSU rows matched to spec-class products by catalog_slug
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--in=')) { $in = substr($arg, 5); }
    elseif (str_starts_with($arg, '--out=')) { $out = substr($arg, 6); }
    elseif (str_starts_with($arg, '--limit=')) { $limit = max(1, (int)substr($arg, 8)); }
    elseif (str_starts_with($arg, '--category=')) { $category = strtolower(substr($arg, 11)); }
    elseif ($arg === '--spec') { $spec = true; }
}
if (!is_file($in)) { fwrite(STDERR, "Input not found: $in\n"); exit(2); }

$result = OwnerIntakePreparer::prepare($in, $category, $spec, $limit, $spec ? OwnerIntakePreparer::knownSpecClasses(ROOT_PATH) : []);
if (!is_dir(dirname($out))) { mkdir(dirname($out), 0777, true); }
file_put_contents($out, json_encode(['records' => $result['records']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode(['written' => count($result['records']), 'file' => $out, 'skipped' => $result['skipped']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
