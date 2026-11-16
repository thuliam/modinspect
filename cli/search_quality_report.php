<?php
declare(strict_types=1);

/**
 * Search quality report (LS-3 J-5): runs the golden queries of tests/fixtures/search_golden.php through the real public search and prints
 * hits, misses and noise. Read-only. Counts and the queries themselves (which are not seller data) only.
 *
 *   php cli/search_quality_report.php [--json]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Product;

global $config;
$db = Database::connection($config['db']);
$have = array_flip($db->query('SELECT slug FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN));
$golden = require dirname(__DIR__) . '/tests/fixtures/search_golden.php';
$model = new Product();
$rows = []; $skipped = 0; $hit = 0; $top = 0; $topTotal = 0; $noneOk = 0; $noneTotal = 0; $total = 0;
foreach ($golden as $g) {
    $must = $g['must'] ?? [];
    if ($must && array_diff($must, array_keys($have)) !== []) { $skipped++; continue; }
    $res = array_column($model->search($g['q']), 'slug');
    if (!empty($g['none'])) { $noneTotal++; $ok = $res === []; $noneOk += $ok ? 1 : 0; $rows[] = ['q' => $g['q'], 'ok' => $ok, 'results' => count($res), 'kind' => 'none']; continue; }
    $total++;
    $found = array_diff($must, $res) === [];
    $hit += $found ? 1 : 0;
    $isTop = !empty($g['top']);
    if ($isTop) { $topTotal++; $okTop = ($res[0] ?? null) === $must[0]; $top += $okTop ? 1 : 0; }
    $rows[] = ['q' => $g['q'], 'ok' => $found && (!$isTop || $okTop), 'results' => count($res), 'kind' => 'must'];
}
$summary = ['queries_run' => $total + $noneTotal, 'skipped_missing_products' => $skipped, 'must_find' => ['hit' => $hit, 'of' => $total, 'pct' => $total ? round(100 * $hit / $total, 1) : null],
    'top_first' => ['hit' => $top, 'of' => $topTotal], 'no_noise' => ['ok' => $noneOk, 'of' => $noneTotal]];
if (in_array('--json', $argv, true)) { echo json_encode(['summary' => $summary, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL; exit(0); }
echo json_encode($summary, JSON_UNESCAPED_UNICODE), PHP_EOL;
foreach ($rows as $r) { printf("  %-4s %-22s results=%d\n", $r['ok'] ? 'ok' : 'MISS', $r['q'], $r['results']); }
