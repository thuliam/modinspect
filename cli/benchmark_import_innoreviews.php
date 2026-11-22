<?php
declare(strict_types=1);

/**
 * LS-32 A: loads InnoReviews scores (CC BY 4.0) for catalog models whose name matches exactly one row. Dry run unless --apply. Reference information only:
 * nothing here is read by the price engine. Idempotent (a model/metric pair is updated, never duplicated).
 * Usage: php cli/benchmark_import_innoreviews.php --category=gpu|cpu --file=<csv> [--apply]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Benchmark\BenchmarkMatcher;

$cat = ''; $file = ''; $apply = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--category=')) { $cat = substr($a, 11); }
    elseif (str_starts_with($a, '--file=')) { $file = substr($a, 7); }
    elseif ($a === '--apply') { $apply = true; }
}
if (!isset(BenchmarkMatcher::METRICS[$cat]) || !is_file($file)) { fwrite(STDERR, "Usage: php cli/benchmark_import_innoreviews.php --category=gpu|cpu --file=<csv> [--apply]\n"); exit(2); }

$fh = fopen($file, 'rb'); $header = null; $rows = [];
while (($r = fgetcsv($fh)) !== false) {
    if ($header === null) { $header = array_map(static fn($h) => ltrim((string)$h, "\xEF\xBB\xBF"), $r); continue; }
    if (count($r) === count($header)) { $rows[] = array_combine($header, $r); }
}
fclose($fh);

global $config;
$db = Database::connection($config['db']);
$stmt = $db->prepare("SELECT p.id, p.full_name FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug = :c AND p.is_active = 1");
$stmt->execute(['c' => $cat]);
$plan = BenchmarkMatcher::plan($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));

$sourceName = 'InnoReviews'; $license = 'CC BY 4.0'; $today = date('Y-m-d');
$ins = $db->prepare("INSERT INTO product_benchmark_scores (product_id, source_key, metric_key, score_value, scale_note, source_name, source_url, license_note, source_updated_on, retrieved_on)
    VALUES (:p, 'innoreviews', :m, :v, :s, :n, :u, :l, '2026-11-20', :d)
    ON DUPLICATE KEY UPDATE score_value = VALUES(score_value), scale_note = VALUES(scale_note), source_url = VALUES(source_url), retrieved_on = VALUES(retrieved_on)");
$written = 0;
if ($apply) { $db->beginTransaction(); }
foreach ($plan['matched'] as $m) {
    foreach (BenchmarkMatcher::METRICS[$cat] as $key => $label) {
        $v = trim((string)($m['row'][$key] ?? ''));
        if ($v === '' || !is_numeric($v)) { continue; }
        if ($apply) { $ins->execute(['p' => $m['product_id'], 'm' => $key, 'v' => $v, 's' => 'คะแนนบรรณาธิการ 0-100 ไม่ใช่ผลวัดดิบ', 'n' => $sourceName, 'u' => (string)$m['row']['url'], 'l' => $license, 'd' => $today]); }
        $written++;
    }
}
if ($apply) { $db->commit(); }
echo json_encode(['category' => $cat, 'apply' => $apply, 'csv_rows' => count($rows), 'models_matched' => count($plan['matched']), 'scores' => $written, 'ambiguous' => $plan['ambiguous'], 'catalog_models_without_a_row' => $plan['unmatched_products']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
