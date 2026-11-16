<?php
declare(strict_types=1);

/**
 * Restore a backup into a SCRATCH database and verify it (the "restore drill").
 *   php cli/db_restore.php --file=storage/backups/x.sql --target-db=modinspect_restore [--verify]
 * Safety: the target name must end in _test or _restore and must differ from the configured database. An existing target is dropped first.
 * --verify compares the exact row count of every table between the configured database and the restored copy.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Ops\DatabaseBackup;

global $config;
$file = ''; $target = ''; $verify = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--file=')) { $file = substr($a, 7); }
    elseif (str_starts_with($a, '--target-db=')) { $target = substr($a, 12); }
    elseif ($a === '--verify') { $verify = true; }
}
if ($file === '' || $target === '') { fwrite(STDERR, "Usage: --file=<backup.sql> --target-db=<name ending in _test or _restore> [--verify]\n"); exit(2); }
try { $r = DatabaseBackup::restore($config['db'], $file, $target); } catch (RuntimeException $e) { fwrite(STDERR, 'Refused: ' . $e->getMessage() . "\n"); exit(2); }
if (!$r['ok']) { fwrite(STDERR, 'Restore failed: ' . $r['error'] . "\n"); exit(1); }
$db = $config['db'];
$dsn = static fn(string $name): string => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $name);
$dst = new PDO($dsn($target), $db['username'], $db['password']);
$restored = DatabaseBackup::tableCounts($dst, $target);
echo json_encode(['restored_into' => $target, 'tables' => count($restored)]), PHP_EOL;
if ($verify) {
    $src = new PDO($dsn($db['database']), $db['username'], $db['password']);
    $orig = DatabaseBackup::tableCounts($src, $db['database']);
    $diff = [];
    foreach ($orig as $t => $n) { if (($restored[$t] ?? -1) !== $n) { $diff[] = $t . ' ' . $n . ' vs ' . ($restored[$t] ?? 'missing'); } }
    echo json_encode(['verify' => $diff === [] ? 'MATCH' : 'DIFFERENT', 'differences' => $diff]), PHP_EOL;
    exit($diff === [] ? 0 : 1);
}
