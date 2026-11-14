<?php
declare(strict_types=1);

/**
 * PHP runs in Asia/Bangkok. The database session must use the same clock, otherwise cooldowns, freshness windows and
 * "last attempt" comparisons that mix NOW() with strtotime() break on any server whose timezone is not Bangkok (CI runs UTC).
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

function assert_timezone(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);

assert_timezone(date_default_timezone_get() === 'Asia/Bangkok', 'PHP default timezone must be Asia/Bangkok');
$dbNow = (string)$db->query('SELECT NOW()')->fetchColumn();
$skew = abs(strtotime($dbNow) - time());
assert_timezone($skew <= 5, "Database NOW() ({$dbNow}) differs from PHP time by {$skew}s: session time_zone is not aligned");

$session = (string)$db->query('SELECT @@session.time_zone')->fetchColumn();
assert_timezone($session === date('P'), "Database session time_zone should be pinned to the PHP offset, got {$session}");

echo "Timezone consistency test passed\n";
echo "Session time_zone: {$session}; clock skew: {$skew}s\n";
