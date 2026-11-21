<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\DealCheck;
use App\Services\Admin\DealOutcomeReport as R;

/** LS-29 B1: the outcome summary counts and compares only; it reads, never writes, and is null without the table. Test DB only. */
function ok_dr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$row = static fn(string $o, ?string $fp, string $label, string $up, ?string $min, ?string $max): array => ['outcome' => $o, 'final_price' => $fp, 'result_label' => $label, 'user_price' => $up, 'suggested_price_min' => $min, 'suggested_price_max' => $max];
$s = R::summarize([
    $row('bought', '4500', 'in_range', '5000', '4000', '5000'),
    $row('negotiated', '3500', 'above_range', '5000', '4000', '5000'),
    $row('negotiated', '5200', 'in_range', '5000', '4000', '5000'),
    $row('bought', '4000', 'insufficient', '4000', null, null),
    $row('passed', null, 'above_range', '9000', '4000', '5000'),
    $row('weird', '1', 'in_range', '1', null, null),
]);
ok_dr($s['total'] === 5 && $s['by_outcome'] === ['bought' => 2, 'negotiated' => 2, 'passed' => 1], 'An unknown outcome is ignored: ' . json_encode($s['by_outcome']));
ok_dr($s['paid']['with_price'] === 4 && $s['paid']['within_range'] === 1 && $s['paid']['below_range'] === 1 && $s['paid']['above_range'] === 1 && $s['paid']['no_range'] === 1, 'Paid prices are placed against the range shown: ' . json_encode($s['paid']));
ok_dr($s['paid']['avg_below_asking_pct'] === 9.0,'Average discount against the checked price: ' . var_export($s['paid']['avg_below_asking_pct'], true));
ok_dr(array_keys($s['by_label']) === ['above_range', 'in_range', 'insufficient'] && $s['by_label']['in_range']['bought'] === 1, 'Outcomes are grouped by the result shown');
$e = R::summarize([]);
ok_dr($e['total'] === 0 && $e['paid']['avg_below_asking_pct'] === null, 'No answers: zero and no average');

global $config;
$db = Database::connection($config['db']);
ok_dr($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$p = $db->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$model = new DealCheck();
$uuid = $model->create(['product_id' => $p['id'], 'user_price' => 5000, 'condition_level' => 'good', 'warranty_months' => null, 'has_box' => 0, 'has_receipt' => 0, 'has_benchmark' => 0,
    'result_label' => 'in_range', 'min' => 4000, 'max' => 5000, 'recommendation' => 'test', 'warning' => null, 'consent' => 0]);
$id = (int)$model->findByUuid($uuid)['id'];
$model->saveOutcome($id, 'bought', 4800.0);
$b = R::build($db);
ok_dr($b !== null && $b['total'] >= 1 && $b['paid']['within_range'] >= 1, 'build() reads the stored outcome');
$db->exec('DELETE FROM deal_checks WHERE uuid = ' . $db->quote($uuid));
$src = (string)file_get_contents(ROOT_PATH . '/app/Services/Admin/DealOutcomeReport.php');
ok_dr(!preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|DROP)\b/', $src), 'The report only reads');
$view = (string)file_get_contents(ROOT_PATH . '/app/Views/admin/data-health.php');
ok_dr(str_contains($view, 'never set or change a price'), 'The admin card says outcomes are not prices');
echo "deal_outcome_report_test: PASS\n";
