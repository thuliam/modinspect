<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Ops\DatabaseBackup;

/**
 * Backup and restore drill (LS-2 H-11) on the ISOLATED test database only.
 * Part 1 (always): the restore target guard refuses anything that could touch live data.
 * Part 2 (when mysqldump and mysql are installed): back up the test DB, restore it into a scratch database, compare the exact row count of every
 * table, then drop the scratch database and delete the file. When the client tools are missing the drill says so in the output instead of passing silently.
 */
function ok_bk(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

foreach ([
    ['modinspect_test', 'modinspect_test', 'RESTORE_TARGET_IS_SOURCE'],
    ['modinspect_test', 'MODINSPECT_TEST', 'RESTORE_TARGET_IS_SOURCE'],
    ['modinspect_test', 'thuliam_modinspect', 'RESTORE_TARGET_MUST_END_WITH_TEST_OR_RESTORE'],
    ['modinspect_test', 'modinspect', 'RESTORE_TARGET_MUST_END_WITH_TEST_OR_RESTORE'],
    ['modinspect_test', 'x`; DROP DATABASE y; --', 'RESTORE_TARGET_INVALID_NAME'],
    ['modinspect_test', '', 'RESTORE_TARGET_INVALID_NAME'],
] as [$source, $target, $expected]) {
    try { DatabaseBackup::assertSafeTarget($source, $target); $got = 'accepted'; } catch (RuntimeException $e) { $got = $e->getMessage(); }
    ok_bk($got === $expected, "Target '$target' must give $expected, got $got");
}
foreach (['modinspect_drill_restore', 'scratch_test'] as $good) { DatabaseBackup::assertSafeTarget('modinspect_test', $good); }

global $config;
$db = $config['db'];
ok_bk(str_ends_with((string)$db['database'], '_test'), 'The drill must run on a *_test database');

$dump = DatabaseBackup::binary('mysqldump'); $client = DatabaseBackup::binary('mysql');
if ($dump === null || $client === null) {
    echo "db_backup_restore_test: guard checks PASS; DRILL SKIPPED (mysqldump/mysql not found; set MODINSPECT_MYSQL_BIN_DIR to run it)\n";
    exit(0);
}

$file = ROOT_PATH . '/storage/backups/drill-' . bin2hex(random_bytes(4)) . '.sql';
$target = 'modinspect_drill_restore';
$admin = null;
try {
    $b = DatabaseBackup::backup($db, $file);
    ok_bk($b['ok'] && $b['bytes'] > 1000, 'Backup must succeed and not be empty: ' . json_encode($b));
    ok_bk(!str_contains((string)file_get_contents($file, false, null, 0, 4096), (string)$db['password']) || $db['password'] === '', 'The password must not be written into the backup header');
    if (PHP_OS_FAMILY !== 'Windows') { ok_bk((fileperms($file) & 0077) === 0, 'The backup file must be readable by its owner only, got ' . decoct(fileperms($file) & 0777)); }
    $r = DatabaseBackup::restore($db, $file, $target);
    ok_bk($r['ok'], 'Restore must succeed: ' . json_encode($r));
    $src = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['username'], $db['password']);
    $dst = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $target), $db['username'], $db['password']);
    $a = DatabaseBackup::tableCounts($src, $db['database']); $c = DatabaseBackup::tableCounts($dst, $target);
    ok_bk(count($a) >= 20, 'The test database has its tables (' . count($a) . ')');
    ok_bk($a === $c, 'Every table has the same row count after the restore: ' . json_encode(array_diff_assoc($a, $c)));
    $r2 = DatabaseBackup::restore($db, ROOT_PATH . '/storage/backups/does-not-exist.sql', $target);
    ok_bk(!$r2['ok'] && $r2['error'] === 'BACKUP_FILE_NOT_READABLE', 'A missing file is reported, not silently ignored');
} finally {
    @unlink($file);
    $admin = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']), $db['username'], $db['password']);
    $admin->exec('DROP DATABASE IF EXISTS `' . $target . '`');
}

echo "db_backup_restore_test: PASS (guards + backup, restore, row counts match across " . count($a) . " tables)\n";
