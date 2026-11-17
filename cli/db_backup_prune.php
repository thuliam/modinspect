<?php
declare(strict_types=1);

// LS-4 K-4: keep the newest N backups in storage/backups. php cli/db_backup_prune.php [--keep=14]
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Ops\BackupRetention;

$keep = 14;
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--keep=')) { $keep = (int)substr($a, 7); } }
if ($keep < 1) { fwrite(STDERR, "--keep must be at least 1\n"); exit(2); }
$r = BackupRetention::prune(dirname(__DIR__) . '/storage/backups', $keep);
echo json_encode(['kept' => $r['kept'], 'deleted' => count($r['deleted'])]), PHP_EOL;
