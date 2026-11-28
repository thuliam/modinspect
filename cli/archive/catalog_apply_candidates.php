<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ProductImport;

/**
 * LS-75: loads a models_to_add.csv (cli/catalog_candidates_models.php) through the same ProductImport the import centre uses.
 * Keeps rows served by at least --min unmatched posts and whose brand exists. Dry run by default; --apply writes.
 * Usage: php cli/archive/catalog_apply_candidates.php --file=storage/import/catalog_candidates/models_to_add.csv [--min=2] [--apply]
 */
global $config;
$o = getopt('', ['file:', 'min::', 'apply']); $file = (string) ($o['file'] ?? ''); $min = max(1, (int) ($o['min'] ?? 2));
if ($file === '' || !is_file($file)) { fwrite(STDERR, "Usage: php cli/archive/catalog_apply_candidates.php --file=<csv> [--min=2] [--apply]\n"); exit(2); }
$fh = fopen($file, 'rb'); $head = fgetcsv($fh, 0, ',', '"', ''); $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]); $rows = [];
while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    $a = array_combine($head, array_pad($r, count($head), ''));
    if ((int) $a['unmatched_rows_served'] < $min || $a['brand_exists'] !== 'yes') { continue; }
    $rows[] = ['category' => $a['category'], 'brand' => $a['brand'], 'model_name' => $a['model_name'], 'full_name' => $a['full_name'], 'is_active' => '1'];
}
fclose($fh);
$db = Database::connection($config['db']);
$plan = ProductImport::plan($db, $rows);
$bad = array_values(array_filter($plan, static fn ($p) => $p['data'] === null));
$rep = ['apply' => isset($o['apply']), 'rows' => count($rows), 'create' => count(array_filter($plan, static fn ($p) => $p['action'] === 'create')), 'update' => count(array_filter($plan, static fn ($p) => $p['action'] === 'update')), 'refused' => array_map(static fn ($p) => $p['name'] . ': ' . $p['error'], $bad)];
if (isset($o['apply'])) { $rep += ProductImport::apply($db, $plan); }
echo json_encode($rep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
