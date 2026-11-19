<?php
declare(strict_types=1);

/**
 * LS-23 B3: turns the validated used-listing files of a batch into ONE import file for `php cli/import_market_evidence.php --dataset=real`.
 * Validates A_used_listings.csv (and A_used_listings_extra.csv if present) with the intake contract, keeps only valid rows, joins the extras by source_item_id,
 * leaves out wanted / trade / bundle listings, and writes the CSV (UTF-8, BOM). It writes that one file and nothing else: no database write, nothing imported.
 * Prints counts and error codes only, never a title, price or link.
 *
 *   php cli/intake_prepare_import.php <folder> [--out=storage/import/second_source.csv]
 *
 * Exit: 0 written, 1 nothing valid to write (or problems found and --strict), 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Admin\IntakeTargets;
use App\Services\Intake\IntakeExtras;
use App\Services\Intake\IntakeValidator;

$dir = null; $out = ROOT_PATH . '/storage/import/second_source.csv'; $strict = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--out=')) { $out = substr($a, 6); } elseif ($a === '--strict') { $strict = true; } elseif (!str_starts_with($a, '--')) { $dir = $a; }
}
$c = IntakeValidator::contract();
$fileA = ($dir ?? '') . '/' . $c['datasets']['A']['file'];
if ($dir === null || !is_file($fileA)) { fwrite(STDERR, "Usage: php cli/intake_prepare_import.php <folder with A_used_listings.csv> [--out=file] [--strict]\n"); exit(2); }

$a = IntakeValidator::validateFile('A', $fileA);
$fileX = $dir . '/' . $c['datasets']['A_extra']['file'];
$x = is_file($fileX) ? IntakeValidator::validateFile('A_extra', $fileX) : ['valid' => [], 'rows' => 0, 'valid_rows' => 0, 'codes' => [], 'header_errors' => []];
$m = IntakeExtras::merge($a['valid'], $x['valid']);
$problems = count($a['row_errors']) + count($a['header_errors']) + count($x['row_errors']) + count($x['header_errors']);
$summary = ['listings' => $a['rows'], 'listings_valid' => $a['valid_rows'], 'extras' => $x['rows'], 'extras_valid' => $x['valid_rows'], 'extras_used' => $m['extras_used'],
    'extras_without_listing' => $m['extras_without_listing'], 'left_out' => $m['left_out'], 'written' => count($m['records']), 'problem_codes' => $a['codes'] + $x['codes'], 'header_errors' => array_merge($a['header_errors'], $x['header_errors'])];
if ($m['records'] === [] || ($strict && $problems > 0)) {
    echo json_encode($summary + ['result' => 'nothing written'], JSON_PRETTY_PRINT), "\n";
    exit(1);
}
if (!is_dir(dirname($out))) { mkdir(dirname($out), 0775, true); }
file_put_contents($out, IntakeTargets::csv(IntakeExtras::COLUMNS, array_map(static fn(array $r): array => array_values($r), $m['records'])));
echo json_encode($summary + ['result' => 'written', 'next' => 'php cli/import_market_evidence.php --file=' . $out . ' --dataset=real --dry-run'], JSON_PRETTY_PRINT), "\n";
exit(0);
