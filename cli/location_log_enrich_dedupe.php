<?php
declare(strict_types=1);

/**
 * Add repost markers to listing_location_log from the two local, git-ignored research files (joined ONLY by row_id):
 *   research_listings_sanitized.csv  (row_id, dedupe_key, duplicate_of_row)
 *   location_hints.csv               (only used to know the file belongs to the same extraction)
 *
 *   php cli/location_log_enrich_dedupe.php --listings=<research_listings_sanitized.csv>
 *
 * Idempotent; prints counts only. Needs migration 017.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$file = '';
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--listings=')) { $file = substr($a, 11); } }
if ($file === '' || !is_file($file)) { fwrite(STDERR, "Usage: php cli/location_log_enrich_dedupe.php --listings=<research_listings_sanitized.csv>\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
$fh = fopen($file, 'rb');
$head = fgetcsv($fh, 0, ',', '"', '');
$head = array_map(static fn($h) => ltrim((string)$h, "\xEF\xBB\xBF"), $head ?: []);
foreach (['row_id', 'dedupe_key', 'duplicate_of_row'] as $need) { if (!in_array($need, $head, true)) { fwrite(STDERR, "Column missing: $need\n"); exit(2); } }
$upd = $db->prepare('UPDATE listing_location_log SET dedupe_key=?, is_repost=? WHERE row_ref=?');
$seen = 0; $matched = 0; $reposts = 0;
while (($line = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    $r = array_combine($head, array_pad($line, count($head), ''));
    if (!preg_match('/^LINEFB-\d{4}$/', (string)$r['row_id'])) { continue; }
    $seen++;
    $isRepost = trim((string)$r['duplicate_of_row']) !== '' ? 1 : 0;
    $upd->execute([substr((string)$r['dedupe_key'], 0, 12) ?: null, $isRepost, $r['row_id']]);
    if ($upd->rowCount() > 0) { $matched++; $reposts += $isRepost; }
}
fclose($fh);
echo json_encode(['listing_rows' => $seen, 'log_rows_updated' => $matched, 'marked_repost' => $reposts]) . PHP_EOL;
