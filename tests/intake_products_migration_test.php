<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeProductsMigration as M;

/**
 * LS-23 B6: dataset D becomes an additive migration. Run on the test database (rolled back): the model and its aliases arrive with no price and no observation,
 * a second run adds nothing, an existing model or alias is untouched, and variants are not merged (an alias that equals another model's name is dropped).
 */
function ok_pm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$prod = static fn(string $cat, string $brand, string $model, string $full, array $more = []): array => array_merge(['category' => $cat, 'brand' => $brand, 'model_name' => $model, 'full_name' => $full, 'release_year' => '2019', 'generation' => 'Turing', 'official_url' => 'https://www.example-maker.com/x', 'market_note' => ''], $more);
$alias = static fn(string $p, string $a, string $kind = 'shorthand'): array => ['product_name' => $p, 'alias_text' => $a, 'alias_kind' => $kind, 'evidence_url' => ''];

$tag = strtoupper(bin2hex(random_bytes(2)));
$full = "Testbrand RTX $tag 2070 Super"; $fullPlain = "Testbrand RTX $tag 2070";
$g = M::generate([$prod('gpu', 'Testbrand', "RTX $tag 2070 Super", $full), $prod('gpu', 'Testbrand', "RTX $tag 2070", $fullPlain)],
    [$alias($full, "$tag 2070s"), $alias($full, "rtx $tag 2070", 'spacing'), $alias($fullPlain, "$tag 2070 plain"), $alias($full, '2070', 'shorthand'), $alias($full, "$tag 2070 typo", 'typo')]);
ok_pm($g['models'] === 2, 'Two models');
ok_pm(array_column($g['dropped'], 'alias') === ["rtx $tag 2070"] && $g['dropped'][0]['reason'] === 'SAME_NAME_AS_ANOTHER_MODEL', 'An alias equal to ANOTHER model name (2070 vs 2070 Super) is dropped, not merged: ' . json_encode($g['dropped']));
ok_pm($g['risky'] === ['2070'], 'A bare-number alias is flagged as risky');
ok_pm(!preg_match('/^(UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|CREATE)\b/m', $g['sql']) && !str_contains($g['sql'], 'price_observations') && !str_contains($g['sql'], 'price_indices'), 'Only INSERT statements; no price, observation or snapshot');

global $config;
$db = Database::connection($config['db']);
ok_pm($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$run = static function (string $sql) use ($db): void { foreach (array_filter(array_map('trim', explode(";\n", (string)preg_replace('/^--.*$/m', '', $sql))), static fn(string $s): bool => $s !== '') as $stmt) { $db->exec($stmt); } };
$db->beginTransaction();
try {
    $obs = (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(); $snaps = (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn();
    $existing = $db->query('SELECT full_name FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $nProducts = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $run($g['sql']);
    $n = $db->prepare('SELECT id, is_active, is_popular FROM products WHERE full_name = :n'); $n->execute(['n' => $full]); $row = $n->fetch(PDO::FETCH_ASSOC);
    ok_pm($row && (int)$row['is_active'] === 1 && (int)$row['is_popular'] === 0, 'The new model is in the catalog');
    ok_pm((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $nProducts + 2, 'Both models were added');
    $al = $db->prepare('SELECT COUNT(*) FROM product_aliases WHERE product_id = :p'); $al->execute(['p' => $row['id']]);
    ok_pm((int)$al->fetchColumn() >= 3, 'The model and its collector aliases were added');
    $tot = (int)$db->query('SELECT COUNT(*) FROM product_aliases')->fetchColumn();
    $run($g['sql']);
    ok_pm((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $nProducts + 2 && (int)$db->query('SELECT COUNT(*) FROM product_aliases')->fetchColumn() === $tot, 'A second run adds nothing');
    $run(M::generate([$prod('gpu', 'Testbrand', 'Dup', (string)$existing)], [])['sql']);
    ok_pm((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $nProducts + 2, 'A model that already exists is not added again');
    ok_pm((int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn() === $obs && (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn() === $snaps, 'No observation or snapshot appeared');
} finally {
    $db->rollBack();
}
echo "intake_products_migration_test: PASS\n";
