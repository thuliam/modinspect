<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Benchmark\BenchmarkMatcher;

/**
 * LS-75: which MODELS does a market-evidence file talk about that the catalog does not have? Reads the file (every row's product_hint and title) and the catalog (SELECT only),
 * keeps the rows whose text names no catalog model, groups them by model (CPU, GPU, motherboard, cooler, case: categories where one model is one product) and writes
 * models_to_add.csv in the general import format of the import centre (category, brand, model_name, full_name) plus how many unmatched rows each model would serve.
 * RAM, storage and PSU rows are not listed here: they are priced by spec class (see catalog_candidates_from_unresolved.php). Bundles, new items, sold or reserved
 * posts and accessories are left out. Nothing is added to the catalog; an admin reviews the CSV and imports it.
 * Usage: php cli/catalog_candidates_models.php --file=storage/import/facebook_20261122/merged_for_import.json [--out=storage/import/catalog_candidates] [--min=1]
 */
global $config;
$o = getopt('', ['file:', 'out::', 'min::']);
$file = (string) ($o['file'] ?? ''); if ($file === '' || !is_file($file)) { fwrite(STDERR, "Usage: php cli/catalog_candidates_models.php --file=<json>\n"); exit(2); }
$out = rtrim(str_replace('\\', '/', (string) ($o['out'] ?? dirname(__DIR__) . '/storage/import/catalog_candidates')), '/'); $min = max(1, (int) ($o['min'] ?? 1));
$d = json_decode((string) file_get_contents($file), true); $rows = is_array($d) ? (array_is_list($d) ? $d : ($d['records'] ?? [])) : [];
$db = Database::connection($config['db']);
$norm = static fn (string $v): string => ' ' . trim((string) BenchmarkMatcher::normalize($v)) . ' ';
$catalog = []; foreach ($db->query('SELECT full_name FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN) as $n) { $catalog[] = $norm((string) $n); }
$brands = []; foreach ($db->query('SELECT name FROM brands')->fetchAll(PDO::FETCH_COLUMN) as $b) { $brands[mb_strtolower((string) $b)] = (string) $b; }
$knownBrands = ['asus', 'msi', 'gigabyte', 'asrock', 'biostar', 'colorful', 'galax', 'zotac', 'palit', 'sapphire', 'xfx', 'powercolor', 'inno3d', 'gainward', 'pny', 'evga', 'cooler master', 'deepcool', 'thermaltake', 'antec', 'lian li', 'nzxt', 'corsair', 'noctua', 'arctic', 'id-cooling', 'montech', 'tecware', 'enermax', 'aigo'];

$skip = '/(ชุด|และ|พร้อม| \+ |bundle|laptop|notebook|โน้ตบุ๊ก|เมาส์|mouse|keyboard|คีย์บอร์ด|หูฟัง|กล้อง|จอ ?\d|monitor|ขายแล้ว|จองแล้ว|มือหนึ่ง|มือ ?1\b|ใหม่|brand new|new sealed|defective|เสีย|อะไหล่|ซาก|ตำหนิ)/iu';
$cat = static function (string $h): ?string {
    if (preg_match('/\b(rtx|gtx|rx ?\d{3,4}|arc a\d{3})\b/i', $h)) { return 'gpu'; }
    if (preg_match('/\b(ryzen|core i[3579]|i[3579]-\d{4,5}|core ultra)\b/i', $h)) { return 'cpu'; }
    if (preg_match('/\b[abhxz]\d{2,3}[em]?\b.*(mainboard|เมนบอร์ด|motherboard)|(mainboard|เมนบอร์ด|motherboard)/iu', $h) || preg_match('/\b(b|h|z|x|a)[1-9]\d{2}m?\b/i', $h) && preg_match('/(am[45]|lga ?\d{4}|ddr[45])/i', $h)) { return 'motherboard'; }
    if (preg_match('/(ชุดน้ำ|aio|พัดลมซีพียู|ฮีทซิงค์|cpu cooler|tower cooler)/iu', $h)) { return 'cooling'; }
    if (preg_match('/(เคส|\bcase\b)/iu', $h)) { return 'case'; }
    return null;
};
$models = []; $left = ['matched' => 0, 'skipped_not_one_model' => 0, 'spec_class_or_other' => 0, 'no_model_text' => 0];
foreach ($rows as $r) {
    $hint = trim((string) ($r['product_hint'] ?? '')); $title = trim((string) ($r['title'] ?? '')); $text = $hint !== '' ? $hint : $title;
    if ($text === '') { $left['no_model_text']++; continue; }
    $hay = $norm($hint . ' ' . $title); $hit = false;
    foreach ($catalog as $c) { if (str_contains($hay, $c)) { $hit = true; break; } }
    if ($hit) { $left['matched']++; continue; }
    if (preg_match($skip, $hint . ' ' . $title)) { $left['skipped_not_one_model']++; continue; }
    $c = $cat($text); if ($c === null) { $left['spec_class_or_other']++; continue; }
    // brand: the first known brand word in the hint; CPU and GPU brands come from the family name
    $brand = ''; $low = mb_strtolower($text);
    if ($c === 'cpu') { $brand = preg_match('/ryzen|athlon/i', $text) ? 'AMD' : 'Intel'; }
    elseif ($c === 'gpu') { $brand = preg_match('/rx ?\d|radeon/i', $text) ? 'AMD' : (preg_match('/arc/i', $text) ? 'Intel' : 'NVIDIA'); }
    else { foreach ($knownBrands as $b) { if (preg_match('/(^|\s)' . preg_quote($b, '/') . '(\s|$)/i', $low)) { $brand = $b; break; } } }
    if ($brand === '') { $left['spec_class_or_other']++; continue; }
    $brandName = $brands[mb_strtolower($brand)] ?? ucwords($brand);
    $canon = null;
    if ($c === 'cpu') {
        if (preg_match('/ryzen\s*([3579])\s*(\d{4}[A-Za-z0-9]*)/i', $text, $m)) { $canon = 'Ryzen ' . $m[1] . ' ' . strtoupper($m[2]); }
        elseif (preg_match('/(core\s*)?(i[3579])[- ](\d{4,5}[A-Za-z]{0,2})/i', $text, $m)) { $canon = 'Core ' . strtolower($m[2]) . '-' . strtoupper($m[3]); }
    } elseif ($c === 'gpu') {
        if (preg_match('/\b(rtx|gtx)\s*(\d{3,4})(\s*ti)?(\s*super)?/i', $text, $m)) { $canon = 'GeForce ' . strtoupper($m[1]) . ' ' . $m[2] . (!empty($m[3]) ? ' Ti' : '') . (!empty($m[4]) ? ' Super' : ''); }
        elseif (preg_match('/\brx\s*(\d{3,4})(\s*xtx|\s*xt|\s*gre)?/i', $text, $m)) { $canon = 'Radeon RX ' . $m[1] . (!empty($m[2]) ? ' ' . strtoupper(trim($m[2])) : ''); }
    }
    if (($c === 'cpu' || $c === 'gpu') && $canon === null) { $left['spec_class_or_other']++; continue; }
    $model = $canon ?? trim(preg_replace('/^' . preg_quote($brand, '/') . '\s+/i', '', $text) ?? $text);
    $model = trim(preg_replace('/\b(mainboard|motherboard|เมนบอร์ด|การ์ดจอ|vga)\b/iu', '', $model) ?? $model);
    $model = trim(preg_replace('/\s+(cpu|vga|gpu|mainboard)\b/i', '', $model) ?? $model); $model = preg_replace('/\s+/', ' ', $model) ?? $model;
    $full = $brandName . ' ' . $model;
    if (in_array($norm($full), $catalog, true)) { $left['matched']++; continue; } // the catalog already has this exact model
    $key = $c . '|' . $norm($full);
    $models[$key] ??= ['n' => 0, 'row' => [$c, $brandName, $model, $full, isset($brands[mb_strtolower($brand)]) ? 'yes' : 'NO - add the brand first']]; $models[$key]['n']++;
}
uasort($models, static fn ($a, $b) => $b['n'] <=> $a['n']);
$models = array_filter($models, static fn ($m) => $m['n'] >= $min);
if (!is_dir($out) && !mkdir($out, 0775, true)) { fwrite(STDERR, "cannot create $out\n"); exit(2); }
$fh = fopen($out . '/models_to_add.csv', 'wb'); fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, ['category', 'brand', 'model_name', 'full_name', 'brand_exists', 'unmatched_rows_served'], ',', '"', '');
$by = []; $served = 0;
foreach ($models as $m) { fputcsv($fh, array_merge($m['row'], [$m['n']]), ',', '"', ''); $by[$m['row'][0]] = ($by[$m['row'][0]] ?? 0) + 1; $served += $m['n']; }
fclose($fh);
echo json_encode(['rows_read' => count($rows)] + $left + ['models_to_add' => count($models), 'by_category' => $by, 'unmatched_rows_they_would_serve' => $served, 'new_brands_needed' => array_values(array_unique(array_map(static fn ($m) => $m['row'][4] === 'yes' ? null : $m['row'][1], $models))), 'out' => $out . '/models_to_add.csv'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
