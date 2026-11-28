<?php
declare(strict_types=1);

/**
 * LS-90 T6: the unattended daily backup (for a scheduled task that has no console). Reads the database, writes one new file under
 * storage/backups/ and one line to storage/logs/backup.log. It never deletes or overwrites an earlier backup.
 *   php cli/backup_job.php
 * Exit 0 when the backup was written, 1 otherwise. The log line holds the file name, size and error code only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Ops\DatabaseBackup;

global $config;
$name = $config['db']['database'] . '-daily-' . date('Ymd-His') . '.sql';
$result = DatabaseBackup::backup($config['db'], dirname(__DIR__) . '/storage/backups/' . $name);
$line = date('c') . ($result['ok'] ? " OK $name {$result['bytes']} bytes" : ' FAIL ' . $result['error']) . PHP_EOL;
$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0700, true);
}
file_put_contents($logDir . '/backup.log', $line, FILE_APPEND | LOCK_EX);
echo $line;
exit($result['ok'] ? 0 : 1);
