<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\CoverageReport;
use App\Services\Intake\IntakeValidator;

/**
 * LS-23 E3: the coverage report is read-only and moves when the data does: required specs completed, a new-price reference added. Test database, rolled back.
 */
function ok_cv(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_cv($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
ok_cv(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', (string)file_get_contents(ROOT_PATH . '/app/Services/Admin/CoverageReport.php') . (string)file_get_contents(ROOT_PATH . '/cli/coverage_report.php')), 'The coverage report is SELECT-only');
$byCat = static fn(array $rows): array => array_column($rows, null, 'category');
$base = $byCat(CoverageReport::build($db));
ok_cv(isset($base['cpu']) && $base['cpu']['models'] > 0 && $base['cpu']['specs_complete'] <= $base['cpu']['models'], 'Each category reports its models and a spec count within them');
ok_cv(array_sum(array_column($base, 'models')) === (int)$db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(), 'Every active model is counted once');

$db->beginTransaction();
try {
    $p = $db->query("SELECT p.id, p.full_name FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug = 'cpu' AND p.is_active = 1 AND NOT EXISTS (SELECT 1 FROM product_reference_specs s WHERE s.product_id = p.id) ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ok_cv((bool)$p, 'The fixture has a CPU with no reference specs');
    $req = IntakeValidator::contract()['spec_keys']['required_per_category']['cpu'];
    $ins = $db->prepare("INSERT INTO product_reference_specs (product_id, spec_key, spec_value, source_url, date_checked, review_status) VALUES (:p, :k, '1', 'https://example.test', '2026-11-10', 'official_checked')");
    foreach (array_slice($req, 0, -1) as $k) { $ins->execute(['p' => $p['id'], 'k' => $k]); }
    ok_cv($byCat(CoverageReport::build($db))['cpu']['specs_complete'] === $base['cpu']['specs_complete'], 'One required key short: still not complete');
    $ins->execute(['p' => $p['id'], 'k' => end($req)]);
    $after = $byCat(CoverageReport::build($db));
    ok_cv($after['cpu']['specs_complete'] === $base['cpu']['specs_complete'] + 1 && !isset($after['cpu']['missing_specs'][$p['full_name']]), 'All required keys present: the model counts as complete');
    $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('cv', :e, 'x', 'admin')")->execute(['e' => 'cv-' . bin2hex(random_bytes(4)) . '@example.test']);
    $u = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO new_price_references (product_id, price, source_url, checked_on, created_by) VALUES (:p, 100, 'https://example.test', '2026-11-10', :u)")->execute(['p' => $p['id'], 'u' => $u]);
    ok_cv($byCat(CoverageReport::build($db))['cpu']['with_new_price'] === $base['cpu']['with_new_price'] + 1, 'A new-price reference is counted');
} finally {
    $db->rollBack();
}
echo "coverage_report_test: PASS\n";
