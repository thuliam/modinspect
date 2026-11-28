<?php
declare(strict_types=1);

/**
 * LS-77b: find price rows (APPROVED or PENDING) whose own title names a different hardware category than the product they sit under
 * (a DDR5 kit under a Ryzen CPU, a motherboard under a CPU) and EXCLUDE them with a review decision and an audit entry under --actor,
 * then rebuild the snapshot of each affected product that had an approved row removed. Excluding is reversible and keeps the evidence.
 * Dry run unless --apply; --apply refuses unless --expect equals the row count.
 * Usage: php cli/archive/category_misfiled_repair.php --actor=<admin id> [--apply --expect=<rows>]
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\SpecConsistency;
use App\Services\Pricing\PriceSnapshotService;

$o = getopt('', ['actor:', 'apply', 'expect::']);
$actor = (int)($o['actor'] ?? 0);
if ($actor <= 0) { fwrite(STDERR, "--actor=<admin user id> is required\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
$chk = $db->prepare('SELECT role FROM users WHERE id=:id AND is_active=1');
$chk->execute(['id' => $actor]);
if (!in_array((string)$chk->fetchColumn(), ['admin', 'reviewer'], true)) { fwrite(STDERR, "Actor must be an active admin or reviewer\n"); exit(2); }

$rows = $db->query("SELECT o.id, o.product_id, o.verified_status st, p.full_name, c.name category, COALESCE(o.price_value,o.asking_price) price, r.raw_title
    FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id
    WHERE o.price_type='asking' AND o.verified_status IN ('approved','pending') ORDER BY o.product_id, o.id")->fetchAll();
$bad = [];
foreach ($rows as $r) {
    $why = SpecConsistency::categoryConflict((string)$r['raw_title'], (string)$r['category']);
    if ($why !== null) { $bad[] = $r + ['why' => $why]; }
}
foreach ($bad as $r) { printf("#%d %-8s %-28s %7.0f  %s | %s\n", $r['id'], $r['st'], mb_strimwidth((string)$r['full_name'], 0, 28), $r['price'], $r['why'], mb_strimwidth((string)$r['raw_title'], 0, 60)); }
$n = count($bad);
$products = array_values(array_unique(array_map(static fn($r) => (int)$r['product_id'], array_filter($bad, static fn($r) => $r['st'] === 'approved'))));
echo "\nrows filed under the wrong category: $n, products with an approved row removed: " . count($products) . "\n";
if (!isset($o['apply'])) { echo "Dry run: nothing written. Add --apply --expect=$n to exclude them.\n"; exit(0); }
if (!isset($o['expect']) || (int)$o['expect'] !== $n) { fwrite(STDERR, "Refused: --expect must equal $n.\n"); exit(2); }

$ok = 0; $failed = 0;
$sel = $db->prepare('SELECT * FROM price_observations WHERE id=:id');
$upd = $db->prepare("UPDATE price_observations SET verified_status='excluded', updated_at=NOW() WHERE id=:id AND verified_status=:st");
$dec = $db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes,human_value,notes) VALUES (:o,:raw,'red','rejected',:rc,:hv,:n)");
$aud = $db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (:u,'review_excluded_misfiled','price_observation',:id,:b,:a)");
foreach ($bad as $r) {
    $note = 'Excluded as filed under the wrong category: ' . $r['why'] . '. Evidence kept; recorded by the agent under the owner\'s account.';
    try {
        $db->beginTransaction();
        $sel->execute(['id' => $r['id']]); $before = $sel->fetch();
        $upd->execute(['id' => $r['id'], 'st' => $r['st']]);
        if ($upd->rowCount() !== 1) { throw new RuntimeException('row status changed'); }
        $dec->execute(['o' => $r['id'], 'raw' => $before['raw_observation_id'], 'rc' => json_encode(['misfiled_category_conflict']), 'hv' => json_encode(['verified_status' => 'excluded', 'conflict' => $r['why']], JSON_UNESCAPED_UNICODE), 'n' => $note]);
        $aud->execute(['u' => $actor, 'id' => $r['id'], 'b' => json_encode($before, JSON_UNESCAPED_UNICODE), 'a' => json_encode(['verified_status' => 'excluded', 'notes' => $note], JSON_UNESCAPED_UNICODE)]);
        $db->commit(); $ok++;
    } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } $failed++; fwrite(STDERR, "#{$r['id']}: " . $e->getMessage() . "\n"); }
}
echo "excluded=$ok failed=$failed\n";
$snap = new PriceSnapshotService();
foreach ($products as $pid) { $s = $snap->recalculateProduct($pid, 'asking'); echo "product #$pid " . ($s ? 'snapshot #' . $s['snapshot_id'] : 'no snapshot (fewer than 3 approved rows)') . "\n"; }
