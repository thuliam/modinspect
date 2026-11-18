<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\ConditionGuide as G;

/**
 * F-4 condition guide per category (owner scope 2026-11-18): CPU, GPU, RAM, storage, PSU, motherboard. A buyer's guide in neutral words:
 * what to look at, what to test before paying, what to ask the seller, what to be careful about. It is not a certification, says nothing about any
 * seller, gives no failure rates or thresholds, and never touches a price or verdict. Pure content (no database).
 */
function ok_cg(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_cg(array_column(G::categories(), 'slug') === ['cpu', 'gpu', 'ram', 'storage', 'psu', 'motherboard'], 'Six categories in a fixed order');

foreach (G::categories() as $c) {
    $g = G::for($c['slug']);
    ok_cg($g !== null && $g['slug'] === $c['slug'] && $g['name'] === $c['name'] && $g['name'] !== '', "Guide exists for {$c['slug']}");
    ok_cg(trim($g['intro']) !== '', "{$c['slug']} has an intro");
    $keys = array_column($g['sections'], 'key');
    ok_cg($keys === ['look', 'test', 'ask', 'caution'], "{$c['slug']} sections: " . json_encode($keys));
    foreach ($g['sections'] as $s) {
        ok_cg(trim($s['title']) !== '' && count($s['items']) >= 2, "{$c['slug']}/{$s['key']} has a title and at least two items");
        foreach ($s['items'] as $item) {
            ok_cg(trim($item) !== '', "{$c['slug']}/{$s['key']} has no empty item");
            ok_cg(!str_contains($item, '%'), "{$c['slug']}/{$s['key']} gives no percentage or failure rate");
        }
    }
    ok_cg(str_contains($g['note'], 'ไม่ใช่การรับรอง'), "{$c['slug']} says plainly it is not a certification");
}

ok_cg(G::for('case') === null && G::for('') === null && G::for('GPU') !== null, 'Unknown category gives null, slug is case-insensitive');

// the guide and the Deal Checker reminder list cover the same six categories
$chk = (string)file_get_contents(dirname(__DIR__) . '/app/Services/ConditionChecklist.php');
foreach (G::categories() as $c) { ok_cg(str_contains($chk, "'{$c['slug']}' =>"), "{$c['slug']} also has a Deal Checker reminder list"); }

// never links to price judgement
ok_cg(preg_match('/PriceEngine/', (string)file_get_contents(dirname(__DIR__) . '/app/Services/Catalog/ConditionGuide.php')) === 0, 'The guide never calls the price engine');

// page wiring
$root = dirname(__DIR__);
$idx = (string)file_get_contents($root . '/public/index.php');
ok_cg(str_contains($idx, "'/condition-guide'") && str_contains($idx, "'/condition-guide/{category}'"), 'Routes for the index and each category');
$ctl = (string)file_get_contents($root . '/app/Controllers/ModuleController.php');
ok_cg(str_contains($ctl, 'ConditionGuide::for') && str_contains($ctl, 'http_response_code(404)'), 'The controller serves the guide and 404s an unknown category');
$view = (string)file_get_contents($root . '/app/Views/modules/condition-guide.php');
ok_cg(str_contains($view, 'htmlspecialchars'), 'The view escapes its values');
echo "condition_guide_test: PASS\n";
