<?php
declare(strict_types=1);

/**
 * LS-11 D: reads the performance-figure CSV returned by the research helper (docs/GPT_HANDOFF_PERF_DATA.md) and prints what is wrong with it and how many
 * independent sources each model has. Read-only: the database is only SELECTed (to learn the CPU/GPU slugs) and nothing is imported.
 *
 * Usage: php cli/validate_perf_scores.php <file.csv> [--catalog=<file>] [--format=text|json]
 *   --catalog   a CSV or text file with one "slug,CPU" or "slug,GPU" per line, to run without a database
 * Exit code: 0 no errors, 1 the file has errors, 2 usage problem.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\OwnerRowPreparer;
use App\Services\Collection\PerfScoreCsvValidator;

$file = null; $catalogFile = null; $format = 'text';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--catalog=')) { $catalogFile = substr($arg, 10); }
    elseif (str_starts_with($arg, '--format=')) { $format = substr($arg, 9); }
    elseif ($file === null && !str_starts_with($arg, '--')) { $file = $arg; }
}
if ($file === null || !is_file($file) || !in_array($format, ['text', 'json'], true)) {
    fwrite(STDERR, "Usage: php cli/validate_perf_scores.php <file.csv> [--catalog=<file>] [--format=text|json]\n");
    exit(2);
}
$catalog = [];
if ($catalogFile !== null) {
    if (!is_file($catalogFile)) { fwrite(STDERR, "Catalog file not found: $catalogFile\n"); exit(2); }
    foreach (preg_split('/\R/', (string)file_get_contents($catalogFile)) ?: [] as $line) {
        $p = array_map('trim', explode(',', $line));
        if (count($p) >= 2 && $p[0] !== '' && in_array(strtoupper($p[1]), ['CPU', 'GPU'], true)) { $catalog[$p[0]] = strtoupper($p[1]); }
    }
} else {
    global $config;
    $stmt = Database::connection($config['db'])->query("SELECT p.slug, c.slug AS cat FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.is_active = 1 AND c.slug IN ('cpu', 'gpu') ORDER BY p.slug");
    foreach ($stmt->fetchAll() as $r) { $catalog[(string)$r['slug']] = strtoupper((string)$r['cat']); }
}
$res = PerfScoreCsvValidator::validate(OwnerRowPreparer::readCsv($file), $catalog);

if ($format === 'json') {
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit($res['ok'] ? 0 : 1);
}
$s = $res['summary'];
echo "ตรวจไฟล์: $file\n";
echo "แถวทั้งหมด {$s['rows']} | รับได้ {$s['accepted']} | ถูกปฏิเสธ {$s['rejected']}\n";
echo "รุ่นในแคตตาล็อก {$s['models_total']} | ไม่มีแหล่งอิสระ {$s['models_with_0']} | 1 แหล่ง {$s['models_with_1']} | 2 แหล่งขึ้นไป {$s['models_with_2plus']}\n\n";
foreach (['errors' => 'ข้อผิดพลาด (ต้องแก้ก่อนนำไปใช้)', 'warnings' => 'คำเตือน'] as $key => $title) {
    if (!$res[$key]) { continue; }
    echo "$title: " . count($res[$key]) . "\n";
    foreach ($res[$key] as $e) { echo "  แถว {$e['row']} [{$e['code']}] {$e['message']}\n"; }
    echo "\n";
}
if ($res['disagreements']) {
    echo "สองแหล่งวัดสิ่งเดียวกันแต่ต่างกันเกิน " . (int)((PerfScoreCsvValidator::MAX_SPREAD - 1) * 100) . "%:\n";
    foreach ($res['disagreements'] as $d) { echo "  {$d['slug']} {$d['metric']} ({$d['baseline']}): {$d['min']} ถึง {$d['max']} (x{$d['ratio']}) จาก " . implode(', ', $d['sources']) . "\n"; }
    echo "\n";
}
$thin = array_keys(array_filter($res['coverage'], static fn(array $c): bool => $c['sources'] < 2));
if ($thin) { echo "รุ่นที่ยังมีแหล่งอิสระน้อยกว่า 2: " . implode(', ', $thin) . "\n"; }
echo $res['ok'] ? "ผล: ไม่พบข้อผิดพลาด\n" : "ผล: พบข้อผิดพลาด\n";
exit($res['ok'] ? 0 : 1);
