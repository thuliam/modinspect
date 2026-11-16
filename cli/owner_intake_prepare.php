<?php
declare(strict_types=1);

/**
 * Owner-intake preparation (DL-013): turns the sanitised research CSV into an import file for
 * `php cli/import_market_evidence.php --dataset=owner_text`.
 *
 * Read-only on the CSV, writes one JSON file. Keeps only rows the owner could reasonably approve:
 * a single item, no repost duplicate, not wanted/trade, not defective, no price anomaly, a numeric price and a known category.
 * Every record carries provenance_grade=OWNER_TEXT_NO_URL and its row id as the reference. Nothing is sent to a database here.
 *
 * Usage: php cli/owner_intake_prepare.php [--in=storage/research/line_facebook_2026/research_listings_sanitized.csv]
 *        [--out=storage/research/line_facebook_2026/owner_intake_batch.json] [--limit=500] [--category=gpu]
 */
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Services/Research/SpecClassifier.php';
require ROOT_PATH . '/app/Services/Research/GpuVramFacts.php';
use App\Services\Research\SpecClassifier;
use App\Services\Research\GpuVramFacts;

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

$knownClasses = [];
if ($spec) {
    $mig = (string)file_get_contents(ROOT_PATH . '/database/migrations/014_catalog_spec_classes.sql');
    preg_match_all("/WHERE slug='([^']+)'\);/", $mig, $mm);
    $knownClasses = array_flip($mm[1]);
}
$fh = fopen($in, 'rb');
$header = fgetcsv($fh, 0, ',', '"', '');
$header[0] = ltrim((string)$header[0], "\xEF\xBB\xBF");
$records = []; $skipped = []; $seen = [];
$skip = static function (string $why) use (&$skipped) { $skipped[$why] = ($skipped[$why] ?? 0) + 1; };
while (($cells = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    if (count($cells) < count($header)) { $cells = array_pad($cells, count($header), ''); }
    $r = array_combine($header, array_slice($cells, 0, count($header)));
    if ($category !== null && strtolower($r['category']) !== $category) { continue; }
    if (($r['listing_type'] ?? '') !== 'single_item') { $skip('not_single_item'); continue; }
    if (($r['bundle_total_line'] ?? '') === 'yes') { $skip('bundle'); continue; }
    if (($r['wanted_or_trade'] ?? '') === 'yes') { $skip('wanted_or_trade'); continue; }
    if (($r['defective_hint'] ?? '') === 'yes') { $skip('defective'); continue; }
    if (($r['price_anomaly'] ?? '') !== '') { $skip('price_anomaly'); continue; }
    if (!is_numeric($r['asking_price_thb'] ?? '') || (float)$r['asking_price_thb'] <= 0) { $skip('no_price'); continue; }
    $vramInferred = false;
    if (!in_array(($r['enrichment_status'] ?? ''), ['complete', 'partial', 'not_applicable', ''], true)) {
        // a GPU whose only gap is the VRAM is still unambiguous when that model was only ever sold with one memory size (public fact)
        if (!GpuVramFacts::acceptsMissingVram($r)) { $skip('enrichment_' . $r['enrichment_status']); continue; }
        $vramInferred = true;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($r['note_date'] ?? ''))) { $skip('no_date'); continue; }
    $classSlug = null;
    if ($spec) {
        $k = SpecClassifier::classify($r);
        if (!$k || !isset($knownClasses[$k['slug']])) { $skip('no_spec_class'); continue; }
        if (($r['bundle_total_line'] ?? '') === 'yes') { $skip('bundle'); continue; }
        $classSlug = $k['slug'];
    } elseif (in_array($r['category'], ['ram', 'storage', 'psu'], true)) { $skip('use_spec_mode'); continue; }
    $title = trim((string)$r['title']);
    // reposts: the same text and price must count once, otherwise one seller inflates the sample
    $dupKey = md5(mb_strtolower(preg_replace('/\s+/u', '', $title)) . '|' . (int)$r['asking_price_thb']);
    if (isset($seen[$dupKey])) { $skip('repost_duplicate'); continue; }
    $seen[$dupKey] = true;
    $records[] = [
        'source_label' => $spec ? 'owner_collected_facebook_text_spec' : 'owner_collected_facebook_text',
        'provenance_grade' => 'OWNER_TEXT_NO_URL',
        'source' => 'owner_collected_facebook_text',
        'source_item_id' => (string)$r['row_id'],
        'external_ref' => (string)$r['row_id'],
        'listing_title' => $title,
        'title' => $title,
        'listing_text' => $title,
        'asking_price' => (string)(int)$r['asking_price_thb'],
        'currency' => 'THB',
        'observed_at' => $r['note_date'] . ' 12:00:00',
        'catalog_slug' => $classSlug,
        'ingestion_note' => 'category=' . $r['category'] . '; model_key=' . $r['model_key'] . '; variant=' . $r['variant_key'] . '; enrichment=' . $r['enrichment_status'] . ($vramInferred ? '; vram_inferred=single_size_model' : ''),
    ];
    if (count($records) >= $limit) { break; }
}
fclose($fh);
if (!is_dir(dirname($out))) { mkdir(dirname($out), 0777, true); }
file_put_contents($out, json_encode(['records' => $records], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode(['written' => count($records), 'file' => $out, 'skipped' => $skipped], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
