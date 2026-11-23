<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\PsuFormat;
use App\Services\Catalog\RamFormat;
use App\Services\Catalog\StorageFormat;

/**
 * LS-75: reads the evidence rows that could not be matched to a product (raw rows without an observation) and counts which GENERIC spec classes they ask for
 * (RAM type + speed + capacity, storage interface + form + capacity, PSU watts + 80 PLUS), using the same format parsers as the import centre. A class counts only
 * when the title states every fact the format needs. It writes one CSV per category, ready for the import centre (brand Generic), listing how many unmatched rows
 * each missing class would serve. SELECT only; nothing is added to the catalog until an admin imports the CSV.
 * Usage: php cli/catalog_candidates_from_unresolved.php [--out=storage/import/catalog_candidates] [--min=2] [--source=facebook]
 */
global $config;
$o = getopt('', ['out::', 'min::', 'source::']);
$out = rtrim(str_replace('\\', '/', (string) ($o['out'] ?? dirname(__DIR__) . '/storage/import/catalog_candidates')), '/'); $min = max(1, (int) ($o['min'] ?? 2)); $src = preg_replace('/[^a-z_]/', '', (string) ($o['source'] ?? 'facebook'));
$db = Database::connection($config['db']);
$st = $db->prepare("SELECT r.raw_title FROM raw_price_observations r JOIN data_sources s ON s.id = r.source_id LEFT JOIN price_observations o ON o.raw_observation_id = r.id WHERE s.source_key LIKE :s AND o.id IS NULL");
$st->execute(['s' => 'offline_real_%' . $src . '%']);
$titles = $st->fetchAll(PDO::FETCH_COLUMN);
$have = array_flip($db->query('SELECT slug FROM products')->fetchAll(PDO::FETCH_COLUMN));
$ram = []; $sto = []; $psu = []; $none = 0;
foreach ($titles as $t) {
    $t = (string) $t; $hit = false;
    $r = RamFormat::parse($t);
    if ($r['memory_type'] !== null && $r['memory_speed_mhz'] !== null && $r['capacity_gb'] !== null && RamFormat::check($r + ['form_factor' => $r['form_factor'] ?? 'DIMM']) === null) {
        $ff = $r['form_factor'] ?? 'DIMM'; $f = ['kit_modules' => null, 'form_factor' => $ff] + $r; $name = RamFormat::name('Generic', '', $f);
        $slug = App\Services\Catalog\ProductImport::slug($name); $k = $slug;
        $ram[$k] ??= ['n' => 0, 'row' => ['Generic', '', $f['memory_type'], $ff, $f['memory_speed_mhz'], $f['capacity_gb'], '', 1], 'slug' => $slug]; $ram[$k]['n']++; $hit = true;
    }
    $s = StorageFormat::parse($t);
    if (!$hit && $s['interface_type'] !== null && $s['media_type'] !== null && $s['form_factor'] !== null && $s['capacity_gb'] !== null && StorageFormat::check($s) === null) {
        $slug = StorageFormat::slug('Generic', '', $s);
        $sto[$slug] ??= ['n' => 0, 'row' => ['Generic', '', $s['media_type'], $s['interface_type'], $s['form_factor'], $s['capacity_gb'], 1], 'slug' => $slug]; $sto[$slug]['n']++; $hit = true;
    }
    $p = PsuFormat::parse($t);
    if (!$hit && $p['power_watt'] !== null && $p['efficiency_rating'] !== null && preg_match('/\b(psu|power ?supply|พาวเวอร์)/i', $t)) {
        $f = $p + ['modular_type' => $p['modular_type'] ?? 'Non-modular', 'form_factor' => $p['form_factor'] ?? 'ATX'];
        if (PsuFormat::check($f) === null) { $slug = PsuFormat::slug('Generic', '', $f); $psu[$slug] ??= ['n' => 0, 'row' => ['Generic', '', $f['power_watt'], $f['efficiency_rating'], $f['modular_type'], $f['form_factor'], 1], 'slug' => $slug]; $psu[$slug]['n']++; $hit = true; }
    }
    if (!$hit) { $none++; }
}
if (!is_dir($out) && !mkdir($out, 0775, true)) { fwrite(STDERR, "cannot create $out\n"); exit(2); }
$write = static function (string $file, array $head, array $classes) use ($have, $min, $out): array {
    $rows = array_filter($classes, static fn ($c) => $c['n'] >= $min && !isset($have[$c['slug']]));
    uasort($rows, static fn ($a, $b) => $b['n'] <=> $a['n']);
    $fh = fopen($out . '/' . $file, 'wb'); fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, array_merge($head, ['unmatched_rows_served']), ',', '"', '');
    foreach ($rows as $c) { fputcsv($fh, array_merge($c['row'], [$c['n']]), ',', '"', ''); }
    fclose($fh);
    return ['classes_seen' => count($classes), 'already_in_catalog' => count(array_filter($classes, static fn ($c) => isset($have[$c['slug']]))), 'new_candidates' => count($rows), 'unmatched_rows_they_would_serve' => array_sum(array_column($rows, 'n'))];
};
echo json_encode(['unmatched_rows_read' => count($titles), 'no_class_found' => $none,
    'ram' => $write('ram_candidates.csv', \App\Services\Catalog\RamImport::COLUMNS, $ram), 'storage' => $write('storage_candidates.csv', \App\Services\Catalog\StorageImport::COLUMNS, $sto), 'psu' => $write('psu_candidates.csv', \App\Services\Catalog\PsuImport::COLUMNS, $psu), 'out' => $out], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
