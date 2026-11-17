<?php
declare(strict_types=1);

/**
 * LS-6 M-1: prepare the owner's daily rows for import (docs/OWNER_DAILY_COLLECTION_TH.md). Offline, no database, nothing fetched from any link.
 * Reads a loose CSV (link, title, price, date; Excel "CSV UTF-8" is fine) and writes the strict import template. Prints counts and row numbers with
 * error codes only: never a title, price or link.
 *
 * Usage: php cli/prepare_owner_rows.php --in=storage/import/raw_today.csv [--out=storage/import/second_source.csv] [--default-date=YYYY-MM-DD] [--force]
 */
$checkDb = in_array('--check-db', $argv, true);   // read-only comparison with the rows already stored (LS-7 N-3); needs the database
if ($checkDb) {
    require dirname(__DIR__) . '/app/bootstrap.php';   // defines ROOT_PATH and the autoloader
} else {
    define('ROOT_PATH', dirname(__DIR__));
    require ROOT_PATH . '/app/Services/Collection/CaptureMethod.php';
    require ROOT_PATH . '/app/Services/Research/OwnerIntakeDeduper.php';
    require ROOT_PATH . '/app/Services/Collection/OwnerRowPreparer.php';
}

use App\Services\Collection\OwnerRowDbCheck;
use App\Services\Collection\OwnerRowPreparer;

$in = null;
$out = ROOT_PATH . '/storage/import/second_source.csv';
$default = '';
$force = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--in=')) { $in = substr($arg, 5); }
    elseif (str_starts_with($arg, '--out=')) { $out = substr($arg, 6); }
    elseif (str_starts_with($arg, '--default-date=')) { $default = substr($arg, 15); }
    elseif ($arg === '--force') { $force = true; }
}
if ($in === null || !is_file($in) || !is_readable($in)) { fwrite(STDERR, "Usage: php cli/prepare_owner_rows.php --in=<csv> [--out=storage/import/second_source.csv] [--default-date=YYYY-MM-DD] [--force]\n"); exit(2); }
if ($default !== '') {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $default);
    if (!$dt || $dt->format('Y-m-d') !== $default) { fwrite(STDERR, "--default-date must be YYYY-MM-DD\n"); exit(2); }
}
// the output stays inside storage/import (not in git): real listing rows never go anywhere else
if (preg_match('#^([A-Za-z]:)?[\\\\/]#', $out) !== 1) { $out = ROOT_PATH . '/' . $out; }   // a relative --out is relative to the project root, like the import command
$base = str_replace('\\', '/', ROOT_PATH . '/storage/import/');
$norm = str_replace('\\', '/', $out);
if (!str_starts_with($norm, $base) || str_contains($norm, '/../') || strtolower(pathinfo($norm, PATHINFO_EXTENSION)) !== 'csv') { fwrite(STDERR, "--out must be a .csv file inside storage/import/\n"); exit(2); }
if (is_file($out) && !$force) { fwrite(STDERR, "Output exists; use --force to replace it: " . basename($out) . "\n"); exit(2); }

$result = OwnerRowPreparer::prepare(OwnerRowPreparer::readCsv($in), $default);
if ($checkDb) {
    // read-only: rows already stored are dropped (error), reposts of stored rows are kept with a warning; issues name the INPUT line
    global $config;
    $found = OwnerRowDbCheck::check(\App\Core\Database::connection($config['db']), $result['rows']);
    $drop = [];
    foreach ($found as $f) {
        $result['issues'][] = ['row' => $result['row_numbers'][$f['index']], 'level' => $f['level'], 'codes' => $f['codes']];
        if ($f['level'] === 'error') { $drop[$f['index']] = true; } else { $result['summary']['warnings']++; }
    }
    $result['rows'] = array_values(array_filter($result['rows'], static fn($k): bool => !isset($drop[$k]), ARRAY_FILTER_USE_KEY));
    $result['summary']['ready'] = count($result['rows']);
    $result['summary']['rejected'] = $result['summary']['read'] - $result['summary']['ready'];
    $result['summary']['already_stored'] = count($drop);
    usort($result['issues'], static fn(array $a, array $b): int => $a['row'] <=> $b['row']);
}
$written = false;
if ($result['rows'] !== []) {
    if (!is_dir(dirname($out))) { mkdir(dirname($out), 0777, true); }
    file_put_contents($out, OwnerRowPreparer::toCsv($result['rows']));
    $written = true;
}
$notes = [];
if ($result['summary']['ready'] > 100) { $notes[] = 'The importer reads 100 rows by default: add --limit=' . $result['summary']['ready'] . ' to the import command'; }
echo json_encode([
    'summary' => $result['summary'],
    'issues' => $result['issues'],   // row number, level, codes
    'output_written' => $written ? basename($out) : null,
    'next' => $written ? 'php cli/import_market_evidence.php --file=storage/import/' . basename($out) . ' --dataset=real --dry-run' : 'no usable rows',
    'notes' => $notes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
exit($written ? 0 : 1);
