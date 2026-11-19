<?php
declare(strict_types=1);

/**
 * READ-ONLY summary of Content-Security-Policy violations (LS-22 D2). The /csp-report endpoint writes one "[modinspect-csp]" line per violation to the PHP error log;
 * this reads a log file you name, counts those lines by directive and blocked thing, and prints the pages involved. It writes nothing and calls nothing.
 *
 *   php cli/csp_summary.php /path/to/php_error.log
 *   php cli/csp_summary.php /path/to/php_error.log --json
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Security\CspReport;

$file = $argv[1] ?? '';
if ($file === '' || str_starts_with($file, '--') || !is_file($file) || !is_readable($file)) { fwrite(STDERR, "Usage: php cli/csp_summary.php <error-log-file> [--json]\n"); exit(2); }
$lines = (static function () use ($file) { $h = fopen($file, 'rb'); while ($h && ($l = fgets($h)) !== false) { yield $l; } if ($h) { fclose($h); } })();
$sum = CspReport::summarize($lines);
if (in_array('--json', $argv, true)) { echo json_encode($sum, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; exit(0); }
if ($sum === []) { echo "No CSP violations in this log.\n"; exit(0); }
foreach ($sum as $key => $row) {
    echo sprintf("%5d  %s\n", $row['count'], $key);
    foreach (array_slice($row['pages'], 0, 5, true) as $page => $n) { echo sprintf("       %4d  %s\n", $n, $page); }
}
exit(0);
