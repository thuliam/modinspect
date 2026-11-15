<?php
declare(strict_types=1);

/**
 * READ-ONLY price trust audit (Sprint T1). Writes nothing. For every product the PUBLIC read model shows a price for, it checks:
 *   - which snapshot feeds the price, its provenance status and method versions
 *   - that the snapshot replays deterministically (PriceSnapshotService::reproduceSnapshot = MATCH)
 *   - that every included member observation is still approved, asking-type, single-item, not mock/test/fixture
 *   - source concentration (distinct sources behind the sample) and how many members are owner-collected text (no listing URL)
 *   - units: freshness ratio is a percentage 0..100 (stored as DECIMAL(5,2)), q1 <= median <= q3, sample counts consistent with membership
 * Output: one line per product + a summary + findings (P0 = public price with a hard problem). No names, no raw text, no URLs.
 *
 *   php cli/price_trust_audit.php            (uses the configured DB; reads only)
 *   php cli/price_trust_audit.php --json     (machine-readable)
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Product;
use App\Services\Pricing\PriceSnapshotService;

$json = in_array('--json', $argv, true);
global $config;
$db = Database::connection($config['db']);
$snapshots = new PriceSnapshotService();

$isMock = static fn(string $key, string $name, string $domain): bool => (bool)preg_match('/mock|fixture|test/i', $key . ' ' . $name) || str_starts_with(strtolower($domain), 'mock.');

$rows = []; $findings = []; $summary = ['public_priced' => 0, 'p0' => 0, 'p1' => 0, 'replay_match' => 0, 'single_source' => 0, 'owner_text_only' => 0, 'multi_source' => 0];

foreach ((new Product())->search() as $p) {
    $priced = !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0;
    if (!$priced) { continue; }
    $summary['public_priced']++;
    $pid = (int)$p['id'];
    $snap = $db->prepare("SELECT id, provenance_status, formula_version, confidence_method_version, confidence_label, fresh_sample_ratio, q1, median, q3, valid_sample_size, last_calculated_at
        FROM price_indices WHERE product_id=:p AND product_variant_id IS NULL AND COALESCE(provenance_status,'legacy_unavailable')<>'legacy_unavailable' ORDER BY last_calculated_at DESC LIMIT 1");
    $snap->execute(['p' => $pid]);
    $s = $snap->fetch();
    $f = [];
    if (!$s) { $f[] = ['P0', 'public price without a recorded snapshot']; $rows[] = ['product' => $p['full_name'], 'findings' => $f]; $summary['p0']++; continue; }

    $replay = $snapshots->reproduceSnapshot((int)$s['id'])['result'] ?? 'UNKNOWN';
    if ($replay === 'MATCH') { $summary['replay_match']++; } else { $f[] = ['P0', "snapshot replay result $replay"]; }

    $m = $db->prepare("SELECT o.id, o.verified_status, o.price_type, o.listing_type, o.is_defective, o.is_duplicate, s.id source_id, COALESCE(s.source_key,'') source_key, COALESCE(s.name,'') source_name, COALESCE(s.domain,'') domain
        FROM price_snapshot_observations so JOIN price_observations o ON o.id=so.observation_id
        LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id
        WHERE so.snapshot_id=:sid AND so.inclusion_status='included'");
    $m->execute(['sid' => (int)$s['id']]);
    $members = $m->fetchAll();
    $sources = []; $ownerText = 0; $mock = 0; $notApproved = 0; $badType = 0;
    foreach ($members as $o) {
        $sources[$o['source_id'] ?? 'none'] = true;
        if (str_starts_with(strtolower($o['source_key']), 'offline_owner_text_')) { $ownerText++; }
        if ($isMock($o['source_key'], $o['source_name'], $o['domain'])) { $mock++; }
        if ($o['verified_status'] !== 'approved') { $notApproved++; }
        if ($o['price_type'] !== 'asking' || $o['listing_type'] !== 'single_item' || (int)$o['is_defective'] === 1 || (int)$o['is_duplicate'] === 1) { $badType++; }
    }
    $n = count($members);
    if ($mock > 0) { $f[] = ['P0', "$mock mock/test/fixture members in a public snapshot"]; }
    if ($notApproved > 0) { $f[] = ['P1', "$notApproved members no longer approved (snapshot stale; needs recalculation)"]; }
    if ($badType > 0) { $f[] = ['P1', "$badType members not asking/single-item or flagged defective/duplicate"]; }
    if ($n !== (int)$s['valid_sample_size']) { $f[] = ['P1', "sample count {$s['valid_sample_size']} differs from $n included members"]; }
    $fr = (float)$s['fresh_sample_ratio'];
    if ($fr < 0 || $fr > 100.0001) { $f[] = ["P1", "freshness ratio $fr outside 0..100 percent (unit problem)"]; }
    if (!((float)$s['q1'] <= (float)$s['median'] && (float)$s['median'] <= (float)$s['q3'])) { $f[] = ['P0', 'quartiles out of order']; }
    $distinct = count($sources);
    if ($distinct <= 1) { $summary['single_source']++; } else { $summary['multi_source']++; }
    if ($n > 0 && $ownerText === $n) { $summary['owner_text_only']++; }
    foreach ($f as [$sev]) { $summary[strtolower($sev)]++; }
    $rows[] = ['product' => $p['full_name'], 'snapshot' => (int)$s['id'], 'replay' => $replay, 'n' => $n, 'sources' => $distinct, 'owner_text' => $ownerText, 'fresh' => $fr, 'confidence' => $s['confidence_label'], 'method' => $s['confidence_method_version'], 'findings' => $f];
}

if ($json) { echo json_encode(['summary' => $summary, 'products' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL; exit($summary['p0'] > 0 ? 1 : 0); }

printf("%-36s %5s %-6s %4s %3s %4s %5s %-8s\n", 'product', 'snap', 'replay', 'n', 'src', 'txt', 'fresh', 'conf');
foreach ($rows as $r) {
    if (!isset($r['snapshot'])) { printf("%-36s %s\n", mb_substr($r['product'], 0, 36), 'NO SNAPSHOT'); continue; }
    printf("%-36s %5d %-6s %4d %3d %4d %5.2f %-8s %s\n", mb_substr($r['product'], 0, 36), $r['snapshot'], $r['replay'], $r['n'], $r['sources'], $r['owner_text'], $r['fresh'], $r['confidence'], $r['findings'] ? '<< ' . implode('; ', array_map(static fn($x) => $x[0] . ' ' . $x[1], $r['findings'])) : '');
}
echo "\nSUMMARY " . json_encode($summary) . "\n";
exit($summary['p0'] > 0 ? 1 : 0);
