<?php
declare(strict_types=1);

/**
 * LS-23 B2: READ-ONLY check of the files a collector sends back (docs/DATA_CONTRACT.md, database/contracts/intake-v1.json).
 * Takes a folder or a .zip, finds the files by their contract names, reports per file: rows, valid rows, header problems, row problems as
 * (row number, column, code) and never prints a title, price or link; then says whether each dataset is COMPLETE against its criteria
 * (for example used listings: 5 per model from 2 sources within 45 days). Reads the catalog (SELECTs only) to know which models exist; writes nothing.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/intake_validate.php <folder-or-zip> [--json] [--no-db]
 *
 * Exit: 0 every file present is valid, 1 a problem was found, 2 usage error.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Intake\IntakeCoverage;
use App\Services\Intake\IntakeValidator;

$src = null; $json = false; $useDb = true;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--json') { $json = true; } elseif ($a === '--no-db') { $useDb = false; } elseif (!str_starts_with($a, '--')) { $src = $a; }
}
if ($src === null || (!is_dir($src) && !is_file($src))) { fwrite(STDERR, "Usage: php cli/intake_validate.php <folder-or-zip> [--json] [--no-db]\n"); exit(2); }

$contract = IntakeValidator::contract();
$work = sys_get_temp_dir() . '/mi_intake_' . bin2hex(random_bytes(6));
mkdir($work, 0700, true);
$paths = []; $notes = false;
if (is_dir($src)) {
    foreach ($contract['datasets'] as $key => $d) { if (is_file($src . '/' . $d['file'])) { $paths[$key] = $src . '/' . $d['file']; } }
    $notes = is_file($src . '/_batch_notes.txt');
} else {
    if (!class_exists('ZipArchive')) { fwrite(STDERR, "The zip extension is not available; unzip the file and pass the folder.\n"); exit(2); }
    $zip = new ZipArchive();
    if ($zip->open($src) !== true) { fwrite(STDERR, "Cannot open the zip.\n"); exit(2); }
    // entries are read by name and written under our own names: a path inside the archive is never used (no zip-slip)
    $byBase = [];
    for ($i = 0; $i < $zip->numFiles; $i++) { $n = (string)$zip->getNameIndex($i); if (!str_ends_with($n, '/')) { $byBase[basename($n)] = $i; } }
    foreach ($contract['datasets'] as $key => $d) {
        if (!isset($byBase[$d['file']])) { continue; }
        $stat = $zip->statIndex($byBase[$d['file']]);
        if (($stat['size'] ?? 0) > 5 * 1024 * 1024) { fwrite(STDERR, "A file in the zip is larger than 5 MB; refusing.\n"); exit(2); }
        file_put_contents($work . '/' . $key . '.csv', (string)$zip->getFromIndex($byBase[$d['file']]));
        $paths[$key] = $work . '/' . $key . '.csv';
    }
    $notes = isset($byBase['_batch_notes.txt']);
    $zip->close();
}

$ctx = ['now' => new DateTimeImmutable('now')];
if ($useDb) {
    try {
        global $config;
        $ctx = \App\Services\Intake\IntakeContext::fromDb(\App\Core\Database::connection($config['db']));
    } catch (\Throwable $e) { fwrite(STDERR, "Catalog not reachable; model checks skipped.
"); }
}
// models proposed in the same batch (D_new_products.csv) are known to the other files
if (isset($paths['D_products'], $ctx['products'])) {
    foreach (IntakeValidator::readCsv($paths['D_products'])['rows'] as $r) { if (!empty($r['full_name'])) { $ctx['batch_products'][IntakeValidator::norm($r['full_name'])] = true; $ctx['names'][IntakeValidator::norm($r['full_name'])] = $r['full_name']; $ctx['products'][IntakeValidator::norm($r['full_name'])] = (string)($r['category'] ?? ''); } }
}

$results = []; $problems = 0;
foreach ($paths as $key => $p) {
    $c = $ctx;
    if ($key === 'D_products') { unset($c['batch_products']); }
    $r = IntakeValidator::validateFile($key, $p, $c);
    $problems += count($r['header_errors']) + count($r['row_errors']);
    $results[$key] = $r;
}
$coverage = IntakeCoverage::evaluate($results, $ctx);
array_map('unlink', glob($work . '/*') ?: []); @rmdir($work);

$summary = ['files' => array_map(static fn(array $r): array => ['file' => $r['file'], 'rows' => $r['rows'], 'valid_rows' => $r['valid_rows'], 'header_errors' => $r['header_errors'], 'error_codes' => $r['codes'], 'first_errors' => array_slice($r['row_errors'], 0, 20)], $results),
    'missing_files' => array_values(array_diff(array_column($contract['datasets'], 'file'), array_map(static fn(array $r): string => $r['file'], $results))),
    'batch_notes_present' => $notes, 'coverage' => $coverage, 'problems' => $problems];
if (!$notes) { $summary['warnings'][] = '_batch_notes.txt is missing'; }

if ($json) { echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n"; exit($problems > 0 ? 1 : 0); }
echo "Intake check (contract " . $contract['version'] . ")\n";
foreach ($summary['files'] as $k => $f) {
    echo sprintf("%-12s %-28s rows %4d  valid %4d  problems %d\n", $k, $f['file'], $f['rows'], $f['valid_rows'], count($f['header_errors']) + array_sum($f['error_codes']));
    foreach ($f['header_errors'] as $h) { echo "    header: $h\n"; }
    foreach ($f['error_codes'] as $code => $n) { echo "    $code x$n\n"; }
    foreach (array_slice($f['first_errors'], 0, 5) as $e) { echo sprintf("      row %d column %s: %s\n", $e['row'], $e['column'], $e['code']); }
}
if ($summary['missing_files']) { echo "Not in this batch: " . implode(', ', $summary['missing_files']) . "\n"; }
foreach ($coverage as $k => $cv) {
    echo "Coverage $k:\n";
    foreach ($cv as $name => $val) {
        if (is_array($val)) { echo sprintf("    %s: %d%s\n", $name, count($val), $val !== [] && count($val) <= 12 ? ' -> ' . implode(' | ', array_map(static fn($x): string => is_array($x) ? ($x['name'] ?? $x['category'] ?? json_encode($x)) . (isset($x['listings']) ? " ({$x['listings']} rows, {$x['sources']} sources)" : '') . (isset($x['missing_required']) ? ' missing ' . implode(',', $x['missing_required']) : '') : (string)$x, $val)) : ''); }
        else { echo "    $name: $val\n"; }
    }
}
echo $problems === 0 ? "RESULT: no problems found\n" : "RESULT: $problems problem(s) found\n";
exit($problems > 0 ? 1 : 0);
