<?php
declare(strict_types=1);

/**
 * LS-60: loads in-game FPS rows from a CSV or JSON file (for example the output of an owner-approved collection run) into game_fps_results (migration 031).
 * Dry run unless --apply. Columns: product (slug or full name), game, resolution, quality, avg_fps, low1_fps, paired_part, source_name, source_url, license_note, sponsor_name, tested_on.
 * Rows that fail validation or do not match exactly one CPU/GPU model are listed, never guessed. Reference information only; this script fetches nothing from the network.
 * Usage: php cli/game_fps_import.php --file=<csv|json> [--apply]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\GameFpsImport as I;

$file = ''; $apply = false;
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--file=')) { $file = substr($a, 7); } elseif ($a === '--apply') { $apply = true; } }
if (!is_file($file)) { fwrite(STDERR, "Usage: php cli/game_fps_import.php --file=<csv|json> [--apply]\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
if (!I::tableExists($db)) { fwrite(STDERR, "game_fps_results does not exist: apply migration 031 first (owner order required for DEV)\n"); exit(3); }
$products = I::products($db); $rows = I::readFile($file); $ok = 0; $bad = [];
if ($apply) { $db->beginTransaction(); }
foreach ($rows as $n => $row) {
    $m = I::matchProduct((string) ($row['product'] ?? ''), $products); $c = I::clean($row);
    if ($m['id'] === null || $c['data'] === null) { $bad[] = ['row' => $n + 1, 'error' => $m['error'] ?? $c['error']]; continue; }
    if ($apply) { I::store($db, $m['id'], $c['data']); }
    $ok++;
}
if ($apply) { $db->commit(); }
echo json_encode(['apply' => $apply, 'rows' => count($rows), 'valid' => $ok, 'refused' => $bad], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
