<?php
declare(strict_types=1);

/**
 * LS-83: a review pack for the pending rows a person must decide, and the way to record those decisions.
 *  1) Export (default, writes nothing to the database):  php cli/archive/ls83_review_pack.php --csv=<dir>
 *     ls78_review.csv   one line per untrusted pending Facebook row, a suggested decision and an empty owner_decision column
 *     ls79_collect.csv  one line per product that still lacks a price snapshot: usable rows, rows missing, what to collect
 *  2) Record decisions:  php cli/archive/ls83_review_pack.php --actor=<admin id> --decisions=<ls78_review.csv> [--apply --expect=<rows>]
 *     owner_decision accepts approve | reject | exclude (empty = leave pending). Each goes through Dashboard::decideObservation under --actor
 *     (audit log and review decision record); dry run unless --apply, and --apply refuses unless --expect equals the number of decided rows.
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Research\OwnerApprovalPlan;

$o = getopt('', ['csv::', 'actor::', 'decisions::', 'apply', 'expect::']);
global $config;
$db = Database::connection($config['db']);
$like = 'offline_real_%facebook%';

if (isset($o['decisions'])) {
    $actor = (int)($o['actor'] ?? 0);
    $chk = $db->prepare('SELECT role FROM users WHERE id=:id AND is_active=1'); $chk->execute(['id' => $actor]);
    if ($actor <= 0 || !in_array((string)$chk->fetchColumn(), ['admin', 'reviewer'], true)) { fwrite(STDERR, "--actor must be an active admin or reviewer\n"); exit(2); }
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'exclude' => 'excluded'];
    $todo = [];
    $fh = fopen((string)$o['decisions'], 'r'); if (!$fh) { fwrite(STDERR, "cannot read decisions file\n"); exit(2); }
    $head = fgetcsv($fh); if ($head) { $head[0] = ltrim((string)$head[0], "\xEF\xBB\xBF"); }
    $ix = array_flip($head ?: []);
    if (!isset($ix['observation_id'], $ix['owner_decision'])) { fwrite(STDERR, "columns observation_id and owner_decision are required\n"); exit(2); }
    while (($r = fgetcsv($fh)) !== false) {
        $d = strtolower(trim((string)($r[$ix['owner_decision']] ?? '')));
        if ($d === '') { continue; }
        if (!isset($map[$d])) { fwrite(STDERR, "unknown decision '$d' for row " . ($r[$ix['observation_id']] ?? '?') . "\n"); exit(2); }
        $todo[(int)$r[$ix['observation_id']]] = [$map[$d], trim((string)($r[$ix['note']] ?? ''))];
    }
    fclose($fh);
    $n = count($todo);
    echo "decisions to record: $n\n";
    if (!isset($o['apply'])) { echo "Dry run: nothing written. Add --apply --expect=$n.\n"; exit(0); }
    if (!isset($o['expect']) || (int)$o['expect'] !== $n) { fwrite(STDERR, "Refused: --expect must equal $n.\n"); exit(2); }
    $dash = new Dashboard(); $ok = 0; $skipped = 0;
    foreach ($todo as $id => [$status, $note]) {
        if ($dash->decideObservation($id, $status, $note !== '' ? $note : 'LS-83 owner review decision', $actor)) { $ok++; } else { $skipped++; fwrite(STDERR, "#$id not pending or unknown: skipped\n"); }
    }
    echo "recorded=$ok skipped=$skipped\n";
    exit(0);
}

$st = $db->prepare("SELECT o.id, p.full_name, c.name category, COALESCE(o.price_value,o.asking_price) price, o.is_deposit, o.is_defective, o.is_duplicate, o.listing_type, r.raw_title,
        p.memory_type, p.memory_speed_mhz, p.capacity_gb
    FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN data_sources s ON s.id=r.source_id JOIN products p ON p.id=o.product_id
    JOIN product_categories c ON c.id=p.category_id
    WHERE s.source_key LIKE :s AND o.verified_status='pending' AND o.price_type='asking' ORDER BY p.full_name, o.id");
$st->execute(['s' => $like]);
$pack78 = [['observation_id', 'product', 'price', 'why_untrusted', 'suggested', 'owner_decision', 'note', 'title']];
$counts = [];
foreach ($st->fetchAll() as $r) {
    if (!OwnerApprovalPlan::untrusted($r)) { continue; }
    $t = (string)$r['raw_title'];
    if (preg_match('/(ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง)/iu', $t)) { $why = 'sold_or_reserved'; $sug = 'reject'; }
    elseif ((int)$r['is_deposit']) { $why = 'deposit'; $sug = 'reject'; }
    elseif ((int)$r['is_defective']) { $why = 'defective'; $sug = 'exclude'; }
    elseif ((int)$r['is_duplicate']) { $why = 'duplicate'; $sug = 'reject'; }
    elseif ($r['listing_type'] !== 'single_item') { $why = (string)$r['listing_type']; $sug = 'reject'; }   // a bundle price is not the price of this one product
    elseif (\App\Services\Catalog\SpecConsistency::categoryConflict($t, (string)$r['category']) !== null) { $why = 'wrong_category'; $sug = 'exclude'; }
    elseif (!empty($r['memory_type']) && \App\Services\Catalog\SpecConsistency::ramConflicts($t, $r) !== []) { $why = 'wrong_ram_class'; $sug = 'exclude'; }   // e.g. a 16GB kit under a 32GB class
    else { $why = 'new_item'; $sug = 'reject'; }                                                                   // asking prices here are used-market prices
    $counts[$why] = ($counts[$why] ?? 0) + 1;
    $pack78[] = [$r['id'], $r['full_name'], $r['price'], $why, $sug, '', '', $t];
}
$built = OwnerApprovalPlan::build($db, $like, true);
usort($built['left_pending'], static fn($a, $b) => $b[1] <=> $a[1]);
$pack79 = [['product', 'usable_rows', 'rows_missing', 'state', 'what_to_collect']];
foreach ($built['left_pending'] as [$name, $usable, $status]) {
    $miss = max(0, 3 - (int)$usable);
    $pack79[] = [$name, $usable, $miss, $status, $status === 'bimodal' ? 'check the prices: two clusters, maybe two variants (e.g. 8GB vs 12GB)' : "collect $miss more single used listings of: $name"];
}
echo 'LS-78 rows for review: ' . (count($pack78) - 1) . ' ' . json_encode($counts) . "\nLS-79 products without a snapshot: " . (count($pack79) - 1) . "\n";
if (isset($o['csv'])) {
    $dir = rtrim((string)$o['csv'], '/' . chr(92)); if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    foreach (['ls78_review' => $pack78, 'ls79_collect' => $pack79] as $name => $rows) {
        $f = fopen("$dir/$name.csv", 'w'); fwrite($f, "\xEF\xBB\xBF"); foreach ($rows as $r) { fputcsv($f, $r); } fclose($f);
    }
    echo "CSV written to $dir\n";
}
