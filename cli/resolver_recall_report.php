<?php
declare(strict_types=1);

/**
 * Resolver recall report (LS-2 H-1). Runs the production ProductResolver over every usable listing title of the sanitized research CSV
 * and compares the answer with the extractor's independent `model_key`. Read-only on the database and the CSV. Prints counts and model keys only:
 * never a listing title, seller, URL or text.
 *
 *   php cli/resolver_recall_report.php [--csv=storage/research/line_facebook_2026_loc/research_listings_sanitized.csv] [--json]
 *
 * Buckets per category (gpu, cpu):
 *   agree      resolver picked a product whose name ends with the model_key tokens
 *   unresolved resolver found no product although the extractor named a model
 *   conflict   resolver picked a product that does not match the model_key (a precision problem, worse than unresolved)
 *   no_model   the extractor itself named no model (nothing to compare)
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\CollectionPipeline;
use App\Services\ProductResolver\ProductResolver;

$csv = dirname(__DIR__) . '/storage/research/line_facebook_2026_loc/research_listings_sanitized.csv';
$json = in_array('--json', $argv, true);
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--csv=')) { $csv = substr($a, 6); } }
if (!is_file($csv)) { fwrite(STDERR, "CSV not found: $csv\n"); exit(2); }

$squash = static fn(string $v): string => preg_replace('/[^a-z0-9]+/', '', strtolower($v)) ?? '';
$resolver = new ProductResolver((new CollectionPipeline())->catalog());

$fh = fopen($csv, 'rb'); $h = fgetcsv($fh, 0, ',', '"', ''); $h[0] = ltrim((string)$h[0], "\xEF\xBB\xBF");
$stats = []; $unresolved = []; $conflicts = [];
$catalog = (new CollectionPipeline())->catalog();
$hasProduct = static function (string $mk) use ($catalog, $squash): ?string {
    $key = $squash($mk); $tail = preg_replace('/^(ryzen|rtx|gtx|rx|arc|i[3579])/', '', $key);
    foreach ($catalog as $p) { $n = $squash((string)$p['full_name']); if (str_ends_with($n, $key) || ($tail !== '' && str_ends_with($n, $tail))) { return (string)$p['full_name']; } }
    return null;
};
$aliasGap = []; $catalogGap = [];
while (($c = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    $c = array_pad($c, count($h), ''); $r = array_combine($h, array_slice($c, 0, count($h)));
    $cat = $r['category'];
    if (!in_array($cat, ['gpu', 'cpu'], true) || ($r['listing_type'] ?? '') !== 'single_item' || ($r['duplicate_of_row'] ?? '') !== '') { continue; }
    $stats[$cat] ??= ['rows' => 0, 'agree' => 0, 'unresolved' => 0, 'conflict' => 0, 'no_model' => 0];
    $stats[$cat]['rows']++;
    $mk = trim((string)$r['model_key']);
    if ($mk === '') { $stats[$cat]['no_model']++; continue; }
    $res = $resolver->resolve((string)$r['title']);
    if ($res->productId === null) { $stats[$cat]['unresolved']++; $unresolved[$cat . '|' . $mk] = ($unresolved[$cat . '|' . $mk] ?? 0) + 1;
        if ($hasProduct($mk) !== null) { $aliasGap[$cat . '|' . $mk] = ($aliasGap[$cat . '|' . $mk] ?? 0) + 1; } else { $catalogGap[$cat . '|' . $mk] = ($catalogGap[$cat . '|' . $mk] ?? 0) + 1; }
        continue; }
    // the model_key tokens must be the tail of the product name once spaces and the family word (e.g. "9" in "Ryzen 9 9800X3D") are ignored
    $name = $squash((string)$res->matchedName); $key = $squash($mk);
    $keyTail = preg_replace('/^(ryzen|rtx|gtx|rx|arc|i[3579])/', '', $key);
    if (str_ends_with($name, $key) || ($keyTail !== '' && str_ends_with($name, $keyTail))) { $stats[$cat]['agree']++; }
    else { $stats[$cat]['conflict']++; $conflicts[$cat . '|' . $mk . ' => ' . $res->matchedName] = ($conflicts[$cat . '|' . $mk . ' => ' . $res->matchedName] ?? 0) + 1; }
}
fclose($fh);
arsort($unresolved); arsort($conflicts); arsort($aliasGap); arsort($catalogGap);
$out = ['categories' => $stats, 'top_unresolved_model_keys' => array_slice($unresolved, 0, 25, true), 'conflicts' => array_slice($conflicts, 0, 15, true),
    'unresolved_split' => ['alias_gap_rows' => array_sum($aliasGap), 'alias_gap_models' => $aliasGap, 'catalog_gap_rows' => array_sum($catalogGap), 'catalog_gap_models' => count($catalogGap)],
    'catalog_gap_model_keys' => $catalogGap];
foreach ($stats as $cat => $s) { $cmp = $s['agree'] + $s['unresolved'] + $s['conflict']; $out['categories'][$cat]['recall_pct'] = $cmp ? round(100 * $s['agree'] / $cmp, 1) : null; $out['categories'][$cat]['conflict_pct'] = $cmp ? round(100 * $s['conflict'] / $cmp, 1) : null; }
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
