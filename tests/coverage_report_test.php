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
    $p = $db->query("SELECT p.id, p.full_name FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug = 'cpu' AND p.is_active = 1 ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ok_cv((bool)$p, 'The fixture has a CPU');
    $db->prepare('DELETE FROM product_reference_specs WHERE product_id = :p')->execute(['p' => $p['id']]);   // start this model from no specs (inside the transaction)
    $db->prepare('DELETE FROM new_price_references WHERE product_id = :p')->execute(['p' => $p['id']]);
    $base = $byCat(CoverageReport::build($db));
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

    // LS-90: two import batches of one platform are one source; another platform makes two
    $db->prepare("INSERT INTO products (category_id, brand_id, model_name, full_name, slug, is_active) SELECT category_id, brand_id, :m, :f, :s, 1 FROM products WHERE id = :p")
        ->execute(['m' => 'cv-two', 'f' => 'Coverage two sources', 's' => 'cv-two-' . bin2hex(random_bytes(4)), 'p' => $p['id']]);
    $two = (int)$db->lastInsertId();
    $before = $byCat(CoverageReport::build($db))['cpu']['with_two_sources'];
    $row = static function (string $key, string $domain) use ($db, $two): void {
        $db->prepare("INSERT INTO data_sources (source_key, name, domain, source_type, access_method, risk_level, is_active) VALUES (:k, :n, :d, 'research', 'manual', 'low', 1)")
            ->execute(['k' => $key . bin2hex(random_bytes(4)), 'n' => $key, 'd' => $domain]);
        $db->prepare("INSERT INTO raw_price_observations (source_id, external_reference_hash, raw_title, raw_price_text, captured_at, processing_status) VALUES (:s, :h, 'x', '100', NOW(), 'processed')")
            ->execute(['s' => (int)$db->lastInsertId(), 'h' => hash('sha256', random_bytes(16))]);
        $db->prepare("INSERT INTO price_observations (raw_observation_id, product_id, price_type, asking_price, price_value, currency, listing_type, evidence_level, classification_confidence, verified_status, observed_at) VALUES (:r, :p, 'asking', 100, 100, 'THB', 'single_item', 3, 95, 'approved', NOW())")
            ->execute(['r' => (int)$db->lastInsertId(), 'p' => $two]);
    };
    $row('cv_fb_one_', 'facebook.com');
    $row('cv_fb_two_', 'www.facebook.com');
    ok_cv($byCat(CoverageReport::build($db))['cpu']['with_two_sources'] === $before, 'Two batches of one platform are not two sources');
    $row('cv_other_', 'market.example.test');
    ok_cv($byCat(CoverageReport::build($db))['cpu']['with_two_sources'] === $before + 1, 'A second platform is a second source');
} finally {
    $db->rollBack();
}
echo "coverage_report_test: PASS\n";
