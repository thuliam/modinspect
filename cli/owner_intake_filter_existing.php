<?php
declare(strict_types=1);

/**
 * Remove listings that are already in the database from a prepared owner-intake JSON (see OwnerIntakeDeduper for why row ids are not enough).
 *
 *   php cli/owner_intake_filter_existing.php --in=<prepared.json> [--out=<prepared_new.json>]
 *
 * Read-only on the database. Prints counts only (never titles). Default output file: <in without .json>_new.json.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Research\OwnerIntakeDeduper;

$in = ''; $out = '';
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--in=')) { $in = substr($a, 5); } elseif (str_starts_with($a, '--out=')) { $out = substr($a, 6); }
}
if ($in === '' || !is_file($in)) { fwrite(STDERR, "Usage: php cli/owner_intake_filter_existing.php --in=<prepared.json> [--out=<file>]\n"); exit(2); }
if ($out === '') { $out = preg_replace('/\.json$/', '', $in) . '_new.json'; }

$data = json_decode((string)file_get_contents($in), true);
if (!is_array($data) || !isset($data['records']) || !is_array($data['records'])) { fwrite(STDERR, "Input is not a prepared import file\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
$existing = OwnerIntakeDeduper::existingKeys($db);

$res = OwnerIntakeDeduper::filter($data['records'], $existing);
file_put_contents($out, json_encode(['records' => $res['kept']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode(['input' => count($data['records']), 'already_present' => $res['already_present'], 'kept' => count($res['kept']), 'existing_listings_in_db' => count($existing), 'out' => $out], JSON_UNESCAPED_UNICODE) . PHP_EOL;
