<?php
declare(strict_types=1);

/**
 * LS-86: price-change alerts for push subscribers. Reads each active subscription's watch list, compares it with today's published medians (the same
 * "has a checked price" rule as the product page) and plans at most one notification per subscription per 24 hours.
 * Dry run unless --send. Until a real Web Push sender is approved only NullPushSender exists, so --send records nothing and sends nothing.
 * Usage: php cli/push_price_alerts.php [--send]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Product;
use App\Services\Push\NullPushSender;
use App\Services\Push\PriceAlertPlanner;
use App\Services\Push\SubscriptionStore;

global $config;
if (empty($config['push']['enabled'])) { echo "PUSH_ENABLED is not true: nothing to do.\n"; exit(0); }
$db = Database::connection($config['db']);
$store = new SubscriptionStore($db);
$products = new Product();
$sender = new NullPushSender();
$send = isset(getopt('', ['send'])['send']);
$now = time(); $planned = 0; $sent = 0; $subs = 0;
foreach ($store->active() as $s) {
    $subs++;
    if (!PriceAlertPlanner::due($s['last_notified_at'] !== null ? (string)$s['last_notified_at'] : null, $now)) { continue; }
    $watch = json_decode((string)$s['watch_json'], true);
    if (!empty($s['member_id'])) { $watch = (new \App\Services\Member\MemberWatchList($db))->load((int)$s['member_id']); }   // LS-87: the account list is the truth for a member
    if (!is_array($watch) || $watch === []) { continue; }
    $current = [];
    foreach ($watch as $w) {
        $p = $products->findBySlug((string)$w['slug']);
        if ($p && !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0) { $current[(string)$w['slug']] = (float)$p['median']; }
    }
    $changes = PriceAlertPlanner::changes($watch, $current);
    if ($changes === []) { continue; }
    $planned++;
    $first = $changes[0];
    printf("subscription #%d: %d change(s), first %s %.0f -> %.0f (%+.1f%%)\n", $s['id'], count($changes), $first['slug'], $first['from'], $first['to'], $first['change_percent']);
    if ($send) {
        $r = $sender->send($s, 'ราคาที่คุณติดตามเปลี่ยน', count($changes) . ' รุ่นราคาเปลี่ยนตั้งแต่ครั้งก่อน', rtrim((string)$config['base_url'], '/') . '/watching');
        if ($r === 'sent') { $store->markNotified((int)$s['id']); $sent++; } elseif ($r === 'gone') { $store->markGone((int)$s['id']); } elseif ($r === 'failed') { $store->markFailed((int)$s['id']); }
    }
}
echo "subscriptions: $subs, planned: $planned, sent: $sent" . ($send ? '' : ' (dry run)') . "\n";
