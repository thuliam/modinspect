<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Review\ReviewBatchReport as R;

/** LS-24 M4: the review batch report groups pending rows by model, counts lanes, describes the pending prices, and says whether the green rows alone reach three samples. Read-only. */
function ok_rb(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_rb($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
ok_rb(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', (string)file_get_contents(ROOT_PATH . '/app/Services/Review/ReviewBatchReport.php') . (string)file_get_contents(ROOT_PATH . '/cli/review_batches.php')), 'The report is SELECT-only');
$db->beginTransaction();
try {
    $pid = (int)$db->query('SELECT id FROM products WHERE is_active = 1 AND id NOT IN (SELECT product_id FROM price_observations) ORDER BY id LIMIT 1')->fetchColumn();
    ok_rb($pid > 0, 'The fixture has a model with no observations');
    $add = static function (string $lane, float $price, string $status = 'pending') use ($db, $pid): int {
        $db->prepare("INSERT INTO price_observations (product_id, price_type, asking_price, price_value, currency, condition_level, listing_type, evidence_level, classification_confidence, quality_flags, verified_status, observed_at) VALUES (:p, 'asking', :v, :v2, 'THB', 'unknown', 'single_item', 1, 0, '[]', :s, NOW())")->execute(['p' => $pid, 'v' => $price, 'v2' => $price, 's' => $status]);
        $id = (int)$db->lastInsertId();
        if ($status === 'pending') { $db->prepare("INSERT INTO observation_review_decisions (price_observation_id, lane, decision, reason_codes) VALUES (:o, :l, 'review_required', '[]')")->execute(['o' => $id, 'l' => $lane]); }
        return $id;
    };
    $row = static fn(): ?array => array_values(array_filter(R::build($db), static fn(array $r): bool => $r['product_id'] === $pid))[0] ?? null;
    ok_rb($row() === null, 'A model with nothing pending is not listed');
    $add('green', 7000); $add('green', 7200); $add('amber', 7500); $add('red', 30000);
    $r = $row();
    ok_rb($r['pending'] === 4 && $r['green'] === 2 && $r['amber'] === 1 && $r['red'] === 1 && $r['min'] === 7000.0 && $r['median'] === 7350.0 && $r['max'] === 30000.0, 'Lanes and the plain spread of the pending prices: ' . json_encode($r));
    ok_rb($r['approved'] === 0 && $r['reaches_with_green'] === false && $r['needs'] === 3, 'Two green rows are not enough for three samples');
    $add('green', 7100);
    ok_rb($row()['reaches_with_green'] === true, 'Three green rows reach three samples');
    $add('green', 7300, 'approved');
    $r = $row();
    ok_rb($r['approved'] === 1 && $r['needs'] === 2, 'An approved row already held counts and lowers what is needed');
    $names = array_column(R::build($db), 'product_id');
    ok_rb(array_search($pid, $names, true) !== false, 'The model is in the list');
    $before = (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn();
    R::build($db);
    ok_rb((int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn() === $before, 'Building the report approves nothing');
} finally {
    $db->rollBack();
}
echo "review_batch_report_test: PASS\n";
