<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Collection\PerfScoreCsvValidator;

/**
 * LS-11 D: the checker for the performance-figure CSV that comes back from the research helper (docs/GPT_HANDOFF_PERF_DATA.md). It only reads and reports:
 * it never writes the database. A row is accepted only when its figure is a real number with a source the owner can open, a read date and a baseline when
 * it is a percentage. The report also says how many independent sources each model has (Tom's Hardware is the first source and does not count) and where two
 * sources that measure the same thing disagree strongly.
 */
function ok_pv(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$H = 'category,model_slug,model_name,metric,value,scale_or_unit,baseline_100_is,source_name,source_url,page_date,date_read,test_setup,terms_url,reuse_note,note';
$catalog = ['amd-ryzen-5-5600' => 'CPU', 'nvidia-geforce-rtx-3050' => 'GPU', 'nvidia-geforce-rtx-3060' => 'GPU', 'nvidia-geforce-rtx-3070' => 'GPU'];
$today = date('Y-m-d');
$csv = static function (array $rows) use ($H): array {
    $f = tempnam(sys_get_temp_dir(), 'pv'); file_put_contents($f, $H . "\n" . implode("\n", $rows) . "\n");
    $r = \App\Services\Collection\OwnerRowPreparer::readCsv($f); unlink($f); return $r;
};
$codes = static fn(array $res): array => array_map(static fn(array $e): string => $e['code'], $res['errors']);
$warn = static fn(array $res): array => array_map(static fn(array $e): string => $e['code'], $res['warnings']);

$pm = "CPU,amd-ryzen-5-5600,Ryzen 5 5600,cpu_mark,19800,score,,PassMark,https://www.cpubenchmark.net/cpu.php?cpu=AMD+Ryzen+5+5600,2026-11-10,$today,PassMark CPU Mark,https://www.passmark.com/legal/terms.php,see terms,";
$tpu = "CPU,amd-ryzen-5-5600,Ryzen 5 5600,gaming_1080p,71.2,percent,Ryzen 7 9800X3D,TechPowerUp,https://www.techpowerup.com/review/x/,2026-10-01,$today,12 games,https://www.techpowerup.com/terms,,";
$tom = "GPU,nvidia-geforce-rtx-3050,RTX 3050,raster_1080p,21.9,percent,RTX 5090,Tom's Hardware,\"https://www.tomshardware.com/reviews/gpu-hierarchy,4388.html\",2026-06-25,$today,11 games,,,";
$empty = "GPU,nvidia-geforce-rtx-3060,RTX 3060,raster_1080p,,,,,,,,,,,model not on page";
$res = PerfScoreCsvValidator::validate($csv([$pm, $tpu, $tom, $empty]), $catalog);
ok_pv($res['errors'] === [], 'A clean file has no errors: ' . json_encode($res['errors']));
ok_pv($res['ok'] === true && $res['summary']['rows'] === 4 && $res['summary']['accepted'] === 3, 'Three rows carry a figure and are accepted, the empty one is not an error: ' . json_encode($res['summary']));
ok_pv(in_array('FIRST_SOURCE', $warn($res), true), "Tom's Hardware rows are marked as the first source");
ok_pv($res['coverage']['amd-ryzen-5-5600']['sources'] === 2, 'Two independent sources for the 5600');
ok_pv($res['coverage']['nvidia-geforce-rtx-3050']['sources'] === 0, "Tom's Hardware alone gives 0 independent sources");
ok_pv($res['coverage']['nvidia-geforce-rtx-3060']['sources'] === 0 && $res['coverage']['nvidia-geforce-rtx-3070']['sources'] === 0, 'Models with no figure are listed with 0 sources');
ok_pv($res['summary']['models_total'] === 4 && $res['summary']['models_with_2plus'] === 1 && $res['summary']['models_with_1'] === 0 && $res['summary']['models_with_0'] === 3, 'The summary buckets the models: ' . json_encode($res['summary']));
ok_pv($res['disagreements'] === [], 'Different metrics and scales are never compared');

// every kind of bad row is named, with its row number (the header is line 1)
$bad = $csv([
    "CPU,not-in-catalog,X,cpu_mark,100,score,,PassMark,https://www.cpubenchmark.net/a,2026-11-10,$today,,,,",                          // line 2 UNKNOWN_SLUG
    "TPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,PassMark,https://www.cpubenchmark.net/b,2026-11-10,$today,,,,",                       // line 3 BAD_CATEGORY
    "CPU,nvidia-geforce-rtx-3050,X,cpu_mark,100,score,,PassMark,https://www.cpubenchmark.net/c,2026-11-10,$today,,,,",                // line 4 CATEGORY_MISMATCH
    "CPU,amd-ryzen-5-5600,X,,100,score,,PassMark,https://www.cpubenchmark.net/d,2026-11-10,$today,,,,",                               // line 5 EMPTY_METRIC
    "CPU,amd-ryzen-5-5600,X,cpu_mark,n/a,score,,PassMark,https://www.cpubenchmark.net/e,2026-11-10,$today,,,,",                       // line 6 VALUE_NOT_NUMBER
    "CPU,amd-ryzen-5-5600,X,cpu_mark,0,score,,PassMark,https://www.cpubenchmark.net/f,2026-11-10,$today,,,,",                         // line 7 VALUE_RANGE
    "CPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,,https://www.cpubenchmark.net/g,2026-11-10,$today,,,,",                               // line 8 MISSING_SOURCE (name)
    "CPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,PassMark,,2026-11-10,$today,,,,",                                                     // line 9 MISSING_SOURCE (url)
    "CPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,PassMark,ftp://example.com/x,2026-11-10,$today,,,,",                                  // line 10 BAD_URL
    "CPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,PassMark,https://www.cpubenchmark.net/h,2026-11-10,,,,,",                             // line 11 BAD_DATE_READ (missing)
    "CPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,PassMark,https://www.cpubenchmark.net/i,2026-11-10,2999-01-01,,,,",                   // line 12 BAD_DATE_READ (future)
    "CPU,amd-ryzen-5-5600,X,gaming,70,percent,,TechPowerUp,https://www.techpowerup.com/j,2026-11-10,$today,,,,",                      // line 13 PERCENT_NEEDS_BASELINE
    $pm, $pm,                                                                                                                         // line 14 ok, line 15 DUPLICATE
]);
$r2 = PerfScoreCsvValidator::validate($bad, $catalog);
$byRow = []; foreach ($r2['errors'] as $e) { $byRow[$e['row']][] = $e['code']; }
$expect = [2 => 'UNKNOWN_SLUG', 3 => 'BAD_CATEGORY', 4 => 'CATEGORY_MISMATCH', 5 => 'EMPTY_METRIC', 6 => 'VALUE_NOT_NUMBER', 7 => 'VALUE_RANGE', 8 => 'MISSING_SOURCE', 9 => 'MISSING_SOURCE', 10 => 'BAD_URL', 11 => 'BAD_DATE_READ', 12 => 'BAD_DATE_READ', 13 => 'PERCENT_NEEDS_BASELINE', 15 => 'DUPLICATE'];
foreach ($expect as $row => $code) { ok_pv(in_array($code, $byRow[$row] ?? [], true), "Row $row must be refused as $code: " . json_encode($byRow[$row] ?? [])); }
ok_pv(!isset($byRow[14]), 'The first copy of a duplicated row is fine');
ok_pv($r2['ok'] === false && $r2['summary']['accepted'] === 1, 'Only the one good row is accepted: ' . json_encode($r2['summary']));

// a missing page date is allowed (some pages show none) but is flagged; missing terms link is flagged too
$nd = $csv(["CPU,amd-ryzen-5-5600,X,cpu_mark,100,score,,PassMark,https://www.cpubenchmark.net/k,,$today,,,,"]);
$r3 = PerfScoreCsvValidator::validate($nd, $catalog);
ok_pv($r3['errors'] === [] && in_array('NO_PAGE_DATE', $warn($r3), true) && in_array('NO_TERMS_URL', $warn($r3), true), 'No page date and no terms link are warnings: ' . json_encode($r3));

// a missing required column stops everything with one clear error
$f = tempnam(sys_get_temp_dir(), 'pv'); file_put_contents($f, "category,model_slug,value\nCPU,amd-ryzen-5-5600,1\n");
$r4 = PerfScoreCsvValidator::validate(\App\Services\Collection\OwnerRowPreparer::readCsv($f), $catalog); unlink($f);
ok_pv($codes($r4) === ['HEADER_MISSING'] && $r4['ok'] === false, 'Missing columns give one HEADER_MISSING error: ' . json_encode($r4['errors']));
ok_pv(PerfScoreCsvValidator::validate([], $catalog)['ok'] === false, 'An empty file is not ok');

// two independent sources measuring the same thing on the same scale and baseline must agree within 25%; otherwise it is reported
$a = "GPU,nvidia-geforce-rtx-3070,X,raster_1080p,100,percent,RTX 5090,TechPowerUp,https://www.techpowerup.com/l,2026-10-01,$today,,https://www.techpowerup.com/terms,,";
$b = "GPU,nvidia-geforce-rtx-3070,X,raster_1080p,160,percent,RTX 5090,Guru3D,https://www.guru3d.com/m,2026-10-01,$today,,https://www.guru3d.com/terms,,";
$c = "GPU,nvidia-geforce-rtx-3070,X,raster_1080p,110,percent,RTX 5090,Hardware Unboxed,https://www.youtube.com/watch?v=n,2026-10-01,$today,,https://www.youtube.com/t,,";
$r5 = PerfScoreCsvValidator::validate($csv([$a, $b]), $catalog);
ok_pv(count($r5['disagreements']) === 1 && $r5['disagreements'][0]['slug'] === 'nvidia-geforce-rtx-3070' && $r5['disagreements'][0]['metric'] === 'raster_1080p', 'A 60% spread is reported: ' . json_encode($r5['disagreements']));
ok_pv(PerfScoreCsvValidator::validate($csv([$a, $c]), $catalog)['disagreements'] === [], 'A 10% spread is fine');
$otherBaseline = str_replace('RTX 5090', 'RTX 4090', $b);
ok_pv(PerfScoreCsvValidator::validate($csv([$a, $otherBaseline]), $catalog)['disagreements'] === [], 'Different baselines are never compared');
ok_pv(PerfScoreCsvValidator::validate($csv([$a, str_replace('www.guru3d.com', 'www.techpowerup.com', $b)]), $catalog)['disagreements'] === [], 'Two pages of one site are not independent sources, so they are not compared either');

// the checker only reads: no database class is used
$src = (string)file_get_contents(dirname(__DIR__) . '/app/Services/Collection/PerfScoreCsvValidator.php');
ok_pv(!str_contains($src, 'PDO') && !str_contains($src, 'Database::'), 'The validator class never touches the database');
echo "perf_score_csv_validator_test: PASS\n";
