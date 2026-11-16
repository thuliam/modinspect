<?php
declare(strict_types=1);

/**
 * Logical backup of the configured database (read-only on the database).
 *   php cli/db_backup.php [--out=storage/backups/<db>-YYYYmmdd-HHMMSS.sql]
 * The file contains everything, including password hashes and raw listing text: keep it private (storage/backups/ is git-ignored).
 * Prints the file name and size only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Ops\DatabaseBackup;

global $config;
$out = dirname(__DIR__) . '/storage/backups/' . $config['db']['database'] . '-' . date('Ymd-His') . '.sql';
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--out=')) { $out = substr($a, 6); } }
$r = DatabaseBackup::backup($config['db'], $out);
if (!$r['ok']) { fwrite(STDERR, 'Backup failed: ' . $r['error'] . "\n"); exit(1); }
echo json_encode(['file' => $out, 'bytes' => $r['bytes']], JSON_UNESCAPED_SLASHES), PHP_EOL;
