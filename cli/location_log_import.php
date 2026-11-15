<?php
declare(strict_types=1);

/**
 * Import the private location hints written by research_line_extract.php
 * (location_hints.csv: row_id,note_date,province,region,category,model_key,asking_price_thb) into listing_location_log,
 * then print where listings come from. Idempotent (row_ref is unique). Province granularity only.
 *
 *   php cli/location_log_import.php --file=storage/research/line_facebook_2026_loc/location_hints.csv   (import, then report)
 *   php cli/location_log_import.php --report                                                           (report only)
 *
 * The log is self-contained (raw source references are encrypted), so reports aggregate it directly.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Research\LocationHint;

$opts = getopt('', ['file:', 'report']);
global $config;
$db = Database::connection($config['db']);

if (!empty($opts['file'])) {
    $file = (string)$opts['file'];
    if (!is_file($file)) { fwrite(STDERR, "File not found\n"); exit(2); }
    $fh = fopen($file, 'rb');
    $head = fgetcsv($fh, 0, ',', '"', '');
    $head = array_map(static fn($h) => ltrim((string)$h, "\xEF\xBB\xBF"), $head ?: []);
    $ins = $db->prepare('INSERT INTO listing_location_log (row_ref, province, region, note_date, category, model_key, asking_price_thb) VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE province=VALUES(province), region=VALUES(region), note_date=VALUES(note_date), category=VALUES(category), model_key=VALUES(model_key), asking_price_thb=VALUES(asking_price_thb)');
    $n = 0; $bad = 0;
    while (($line = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        $r = array_combine($head, array_pad($line, count($head), ''));
        $region = LocationHint::regionOf((string)$r['province']);
        if ($region === null || !preg_match('/^LINEFB-\d{4}$/', (string)$r['row_id'])) { $bad++; continue; } // only whitelisted provinces are stored
        $ins->execute([
            $r['row_id'], $r['province'], $region,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r['note_date']) ? $r['note_date'] : null,
            substr((string)($r['category'] ?? ''), 0, 20) ?: null,
            substr((string)($r['model_key'] ?? ''), 0, 80) ?: null,
            (int)($r['asking_price_thb'] ?? 0) ?: null,
        ]);
        $n++;
    }
    fclose($fh);
    echo "imported=$n rejected=$bad\n";
}

echo "\nlocation log by province\n";
foreach ($db->query('SELECT province, region, COUNT(*) n FROM listing_location_log GROUP BY province, region ORDER BY n DESC')->fetchAll() as $r) {
    printf("  %-22s %-10s %d\n", $r['province'], $r['region'], $r['n']);
}

echo "\nprovince x category (count, median asking price; at least 3 listings)\n";
$rows = $db->query('SELECT province, category, asking_price_thb p FROM listing_location_log WHERE asking_price_thb IS NOT NULL ORDER BY province, category, asking_price_thb')->fetchAll();
$g = [];
foreach ($rows as $r) { $g[$r['province'] . '|' . $r['category']][] = (int)$r['p']; }
foreach ($g as $k => $prices) {
    [$prov, $cat] = explode('|', $k);
    $n = count($prices);
    if ($n < 3) { continue; }
    printf("  %-22s %-8s %3d  median %s\n", $prov, $cat, $n, number_format($n % 2 ? $prices[intdiv($n, 2)] : ($prices[$n / 2 - 1] + $prices[$n / 2]) / 2));
}
