<?php
declare(strict_types=1);

/**
 * LS-89 BE-1: a price snapshot and a review decision are each one unit of work, the acting user is recorded,
 * and the price and the monthly trend accept exactly the same rows.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/support/PriceFixture.php';

use App\Models\Dashboard;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\TrendBuilder;
use App\Services\Review\ReviewDecisionService;

function assert_dct(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$fx = new PriceFixture('core');
try {
    $pid = $fx->productId;
    $service = new PriceSnapshotService();
    $fx->observation(1000);
    $fx->observation(1100);
    $fx->observation(1200);
    $snapshots = static fn(): int => $fx->count('SELECT COUNT(*) FROM price_indices WHERE product_id=?', [$pid]);

    // 1) the last write of a snapshot fails: no snapshot and no members stay behind
    $members = $fx->count('SELECT COUNT(*) FROM price_snapshot_observations');
    $fx->breakInsert('price_histories');
    $thrown = false;
    try { $service->recalculateProduct($pid); } catch (PDOException) { $thrown = true; }
    $fx->repair();
    assert_dct($thrown, 'the forced failure must surface');
    assert_dct($snapshots() === 0, 'a failed snapshot write must leave no snapshot row');
    assert_dct($fx->count('SELECT COUNT(*) FROM price_snapshot_observations') === $members, 'a failed snapshot write must leave no member rows');
    assert_dct(!$fx->db->inTransaction(), 'the failed write must close its transaction');
    assert_dct($service->recalculateProduct($pid) !== null && $snapshots() === 1, 'the same call succeeds afterwards');

    // 2) the audit line of a decision fails: status, decision record and corrected values are all rolled back
    $pending = $fx->observation(1300, 'pending');
    $dashboard = new Dashboard();
    $corrected = $dashboard->correctObservation($pending, 'price', 1350, 'typo in the listing', $fx->userId);
    assert_dct(($corrected['ok'] ?? false) === true, 'correction expected');
    assert_dct($fx->count("SELECT COUNT(*) FROM audit_logs WHERE action='review_correction_saved' AND entity_id=? AND user_id=?", [$pending, $fx->userId]) === 1, 'the correction audit must name the acting user');

    $fx->breakInsert('audit_logs');
    $thrown = false;
    try { $dashboard->decideObservation($pending, 'approved', 'ok', $fx->userId); } catch (PDOException) { $thrown = true; }
    $fx->repair();
    assert_dct($thrown, 'the forced failure must surface');
    $row = $fx->db->query("SELECT verified_status, asking_price FROM price_observations WHERE id=$pending")->fetch();
    assert_dct($row['verified_status'] === 'pending', 'the status must stay pending');
    assert_dct((float)$row['asking_price'] === 1300.0, 'the corrected price must not be applied without the decision');
    assert_dct($fx->count('SELECT COUNT(*) FROM observation_review_decisions WHERE price_observation_id=?', [$pending]) === 0, 'no decision record');
    assert_dct($fx->count('SELECT COUNT(*) FROM review_corrections WHERE price_observation_id=? AND review_decision_id IS NOT NULL', [$pending]) === 0, 'the correction stays unlinked');

    // 3) a successful decision records the actor in both places and says which model changed
    $result = (new ReviewDecisionService())->decide($pending, 'approved', 'ok', $fx->userId);
    assert_dct($result['ok'] === true && $result['product_ids'] === [$pid], 'decision result: ' . json_encode($result));
    assert_dct($fx->count("SELECT COUNT(*) FROM audit_logs WHERE action='review_approved' AND entity_id=? AND user_id=?", [$pending, $fx->userId]) === 1, 'decision audit actor');
    assert_dct($fx->count('SELECT COUNT(*) FROM observation_review_decisions WHERE price_observation_id=? AND reviewer_id=?', [$pending, $fx->userId]) === 1, 'decision reviewer');
    assert_dct($fx->count('SELECT COUNT(*) FROM price_observations WHERE id=? AND price_value=1350', [$pending]) === 1, 'the correction is applied with the approval');
    $again = (new ReviewDecisionService())->decide($pending, 'rejected', '', $fx->userId);
    assert_dct($again['ok'] === false && $again['error'] === 'OBSERVATION_NOT_PENDING', 'a decided row cannot be decided again');
    assert_dct((new ReviewDecisionService())->decide(999999999, 'approved', '', $fx->userId)['error'] === 'OBSERVATION_NOT_FOUND', 'unknown row');

    // 4) inside a caller's transaction a refused decision must not undo the caller's own work
    $other = $fx->observation(1400, 'pending');
    $fx->db->beginTransaction();
    $fx->db->exec("UPDATE price_observations SET condition_level='fair' WHERE id=$other");
    (new ReviewDecisionService())->decide($pending, 'approved', '', $fx->userId);
    assert_dct($fx->db->inTransaction(), 'the caller keeps its transaction');
    assert_dct($fx->count("SELECT COUNT(*) FROM price_observations WHERE id=? AND condition_level='fair'", [$other]) === 1, 'the caller keeps its change');
    $fx->db->rollBack();

    // 5) price and trend accept the same rows
    $mock = $fx->source('mock_ls89_' . $pid, 'Mock source ls89 ' . $pid, 'mock.ls89.example');
    $fx->observation(50, 'approved', $mock);
    $reference = $fx->count("SELECT COUNT(*) FROM data_sources WHERE source_key LIKE 'new_price_%'");
    $variantLike = $fx->observation(60);
    $fx->db->exec("UPDATE price_observations SET quality_flags='[\"WANTED\"]' WHERE id=$variantLike");
    $bundle = $fx->observation(70);
    $fx->db->exec("UPDATE price_observations SET listing_type='bundle' WHERE id=$bundle");
    $snapshot = $service->recalculateProduct($pid);
    $buckets = (new TrendBuilder($fx->db))->buckets($pid);
    $month = date('Y-m-01');
    assert_dct($snapshot !== null && (int)$snapshot['valid_sample_size'] === 4, 'the price uses the four valid rows');
    assert_dct(($buckets[$pid][$month]['n'] ?? 0) === 4 && $buckets[$pid][$month]['median'] === (float)$snapshot['median'], 'the trend month uses the same rows and gives the same median: ' . json_encode($buckets));
    (new TrendBuilder($fx->db))->rebuild($pid);
    assert_dct($fx->count('SELECT sample_size FROM price_trend_points WHERE product_id=?', [$pid]) === 4, 'rebuild stores the month');
    $fx->breakInsert('price_trend_points');
    $thrown = false;
    try { (new TrendBuilder($fx->db))->rebuild($pid); } catch (PDOException) { $thrown = true; }
    $fx->repair();
    assert_dct($thrown && $fx->count('SELECT COUNT(*) FROM price_trend_points WHERE product_id=?', [$pid]) === 1, 'a failed rebuild keeps the stored months');

    echo "data_core_transaction_test: PASS\n";
} finally {
    $fx->cleanup();
}
