<?php
declare(strict_types=1);

/**
 * LS-90 D5: writes queued member notifications to storage/logs/notifications.log and marks them. Nothing is e-mailed or pushed.
 *   php cli/notify_process.php            report only: how many wait
 *   php cli/notify_process.php --apply    write them to the log
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Market\NotificationQueue;

global $config;
$db = Database::connection($config['db']);
try {
    $waiting = (int)$db->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'queued'")->fetchColumn();
} catch (PDOException) {
    fwrite(STDERR, "No notification_queue table on this database (migration 043).\n");
    exit(1);
}
if (!in_array('--apply', $argv, true)) {
    echo "waiting: $waiting (nothing written; add --apply)\n";
    exit(0);
}
$dir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
echo 'written to the log: ' . (new NotificationQueue($db))->deliverToLog($dir . '/notifications.log') . " of $waiting\n";
