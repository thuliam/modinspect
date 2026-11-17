<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\RetentionPurge;

/**
 * LS-8 O-2: retention tool for the public tools' data (Deal Checker results, Build results, rate-limit cache files). The owner has not set retention
 * periods yet, so the tool has NO default period and does nothing unless a period is given; it is a dry run unless --apply; periods below a floor are
 * refused so a typo cannot wipe everything. It deletes only rows older than the cut-off and records counts (never content) in the audit log.
 */
function ok_rp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
function rp_throws(callable $f): ?string { try { $f(); } catch (InvalidArgumentException $e) { return $e->getMessage(); } return null; }

global $config;
$db = Database::connection($config['db']);
$productId = (int)$db->query('SELECT id FROM products ORDER BY id LIMIT 1')->fetchColumn();
ok_rp($productId > 0, 'The test database has a product');
$cache = sys_get_temp_dir() . '/mi_rp_' . bin2hex(random_bytes(4));
mkdir($cache, 0777, true);
$now = time();
$old = date('Y-m-d H:i:s', $now - 100 * 86400);
$fresh = date('Y-m-d H:i:s', $now - 2 * 86400);
$db->beginTransaction();
try {
    $deal = $db->prepare("INSERT INTO deal_checks (uuid, product_id, user_price, result_label, recommendation, created_at) VALUES (:u, :p, 8000, 'in_range', 'x', :c)");
    $deal->execute(['u' => 'rp-old-' . bin2hex(random_bytes(6)), 'p' => $productId, 'c' => $old]);
    $deal->execute(['u' => 'rp-new-' . bin2hex(random_bytes(6)), 'p' => $productId, 'c' => $fresh]);
    $build = $db->prepare("INSERT INTO spec_builds (uuid, budget_min, budget_max, intended_use, created_at) VALUES (:u, 20000, 25000, 'gaming', :c)");
    $item = $db->prepare("INSERT INTO spec_build_items (spec_build_id, product_id, component_role) VALUES (:b, :p, 'gpu')");
    foreach ([['rp-bold-', $old], ['rp-bnew-', $fresh]] as [$pre, $when]) { $build->execute(['u' => $pre . bin2hex(random_bytes(6)), 'c' => $when]); $item->execute(['b' => $db->lastInsertId(), 'p' => $productId]); }
    foreach ([['old1.json', 5 * 3600], ['old2.json', 3 * 3600], ['fresh.json', 600]] as [$f, $age]) { file_put_contents("$cache/$f", '{}'); touch("$cache/$f", $now - $age); }
    $count = static fn(string $sql): int => (int)$db->query($sql)->fetchColumn();
    $dealsBefore = $count("SELECT COUNT(*) FROM deal_checks WHERE uuid LIKE 'rp-%'");
    $buildsBefore = $count("SELECT COUNT(*) FROM spec_builds WHERE uuid LIKE 'rp-%'");
    $itemsBefore = $count("SELECT COUNT(*) FROM spec_build_items i JOIN spec_builds b ON b.id = i.spec_build_id WHERE b.uuid LIKE 'rp-%'");
    ok_rp($dealsBefore === 2 && $buildsBefore === 2 && $itemsBefore === 2, 'Fixtures are in place');

    // guard rails
    ok_rp(rp_throws(fn() => RetentionPurge::run($db, [], $cache, false, $now)) !== null, 'No period given: refused, nothing is chosen for the owner');
    ok_rp(rp_throws(fn() => RetentionPurge::run($db, ['deal_checks_days' => 3], $cache, true, $now)) !== null, 'Fewer than 7 days for a database table is refused');
    ok_rp(rp_throws(fn() => RetentionPurge::run($db, ['builds_days' => 0], $cache, true, $now)) !== null, '0 days is refused');
    ok_rp(rp_throws(fn() => RetentionPurge::run($db, ['ratelimit_hours' => 0], $cache, true, $now)) !== null, '0 hours is refused for the cache');
    ok_rp($count("SELECT COUNT(*) FROM deal_checks WHERE uuid LIKE 'rp-%'") === 2 && count(glob("$cache/*.json")) === 3, 'Refusals changed nothing');

    // dry run
    $d = RetentionPurge::run($db, ['deal_checks_days' => 30, 'builds_days' => 30, 'ratelimit_hours' => 1], $cache, false, $now);
    ok_rp($d['apply'] === false && $d['results']['deal_checks']['matched'] >= 1 && $d['results']['builds']['matched'] >= 1 && $d['results']['ratelimit']['matched'] === 2, 'Dry run counts the matches: ' . json_encode($d['results']));
    ok_rp($d['results']['deal_checks']['deleted'] === 0 && $d['results']['ratelimit']['deleted'] === 0, 'Dry run deletes nothing');
    ok_rp($count("SELECT COUNT(*) FROM deal_checks WHERE uuid LIKE 'rp-%'") === 2 && $count("SELECT COUNT(*) FROM spec_builds WHERE uuid LIKE 'rp-%'") === 2 && count(glob("$cache/*.json")) === 3, 'Dry run left every row and file in place');

    // only one target given: the others are untouched
    RetentionPurge::run($db, ['ratelimit_hours' => 1], $cache, true, $now);
    ok_rp(array_map('basename', glob("$cache/*.json")) === ['fresh.json'] && $count("SELECT COUNT(*) FROM deal_checks WHERE uuid LIKE 'rp-%'") === 2, 'Apply with only the cache period removes only old cache files');

    // apply
    $auditBefore = $count("SELECT COUNT(*) FROM audit_logs WHERE action = 'retention_purge'");
    $a = RetentionPurge::run($db, ['deal_checks_days' => 30, 'builds_days' => 30], $cache, true, $now);
    ok_rp($count("SELECT COUNT(*) FROM deal_checks WHERE uuid LIKE 'rp-old-%'") === 0 && $count("SELECT COUNT(*) FROM deal_checks WHERE uuid LIKE 'rp-new-%'") === 1, 'Only the old Deal Checker row is gone');
    ok_rp($count("SELECT COUNT(*) FROM spec_builds WHERE uuid LIKE 'rp-bold-%'") === 0 && $count("SELECT COUNT(*) FROM spec_builds WHERE uuid LIKE 'rp-bnew-%'") === 1, 'Only the old Build row is gone');
    ok_rp($count("SELECT COUNT(*) FROM spec_build_items i JOIN spec_builds b ON b.id = i.spec_build_id WHERE b.uuid LIKE 'rp-%'") === 1, 'The old build\'s items went with it, the new build keeps its item');
    ok_rp($a['results']['deal_checks']['deleted'] >= 1 && $a['results']['builds']['deleted'] >= 1, 'Counts of deleted rows are reported');
    ok_rp($count("SELECT COUNT(*) FROM audit_logs WHERE action = 'retention_purge'") === $auditBefore + 1, 'One audit row records the purge');
    $payload = (string)$db->query("SELECT after_data FROM audit_logs WHERE action = 'retention_purge' ORDER BY id DESC LIMIT 1")->fetchColumn();
    ok_rp(!str_contains($payload, 'rp-') && str_contains($payload, 'deal_checks'), 'The audit row holds counts only, no uuid or content: ' . $payload);
} finally {
    $db->rollBack();
    foreach (glob("$cache/*") ?: [] as $f) { @unlink($f); }
    @rmdir($cache);
}
echo "retention_purge_test: PASS\n";
