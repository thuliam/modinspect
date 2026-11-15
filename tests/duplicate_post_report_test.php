<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\DuplicatePostReport;

function assert_dpr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
$db->exec("DELETE FROM listing_location_log WHERE row_ref LIKE 'LINEFB-98%'");
$ins = $db->prepare('INSERT INTO listing_location_log (row_ref, province, region, category, asking_price_thb, dedupe_key, is_repost) VALUES (?,?,?,?,?,?,?)');
// province A: 4 posts, 3 are reposts of one listing; province B: 2 distinct posts
$ins->execute(['LINEFB-9801', 'ทดสอบเอ', 'central', 'gpu', 5000, 'aaaaaaaaaaaa', 0]);
$ins->execute(['LINEFB-9802', 'ทดสอบเอ', 'central', 'gpu', 5000, 'aaaaaaaaaaaa', 1]);
$ins->execute(['LINEFB-9803', 'ทดสอบเอ', 'central', 'gpu', 5000, 'aaaaaaaaaaaa', 1]);
$ins->execute(['LINEFB-9804', 'ทดสอบเอ', 'central', 'cpu', 3000, 'bbbbbbbbbbbb', 1]);
$ins->execute(['LINEFB-9805', 'ทดสอบบี', 'north', 'gpu', 6000, 'cccccccccccc', 0]);
$ins->execute(['LINEFB-9806', 'ทดสอบบี', 'north', 'gpu', 6100, 'dddddddddddd', 0]);

$report = (new DuplicatePostReport())->build();
$byName = [];
foreach ($report['provinces'] as $p) { $byName[$p['province']] = $p; }
assert_dpr(isset($byName['ทดสอบเอ'], $byName['ทดสอบบี']), 'Both test provinces must be reported');
assert_dpr($byName['ทดสอบเอ']['listings'] === 4 && $byName['ทดสอบเอ']['reposts'] === 3 && $byName['ทดสอบเอ']['repost_rate'] === 0.75, 'Province A must show 3 of 4 reposts (0.75)');
assert_dpr($byName['ทดสอบบี']['repost_rate'] === 0.0, 'Province B has no reposts');
assert_dpr($report['overall']['listings'] >= 6 && $report['overall']['reposts'] >= 3, 'Overall totals must include the test rows');
$keys = array_column($report['top_groups'], 'dedupe_key');
assert_dpr(in_array('aaaaaaaaaaaa', $keys, true), 'The 3-post group must be in the top groups');
$flat = json_encode($report, JSON_UNESCAPED_UNICODE);
assert_dpr(!preg_match('~https?://|@|LINEFB~', (string)$flat), 'The report must not expose row references, URLs or handles');

$db->exec("DELETE FROM listing_location_log WHERE row_ref LIKE 'LINEFB-98%'");
echo "duplicate_post_report_test: PASS\n";
