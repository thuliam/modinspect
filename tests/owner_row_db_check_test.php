<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Collection\OwnerRowDbCheck;
use App\Services\Collection\OwnerRowPreparer;

/**
 * LS-7 N-3: read-only check of prepared rows against what is already stored, before the import. A listing already stored (same item, whatever tracking
 * its link carried) is an error; the same words and price under another item id is a warning (a repost the owner may still keep). Nothing is written by the
 * check itself; the test database is used and rolled back.
 */
function ok_dc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$tag = sprintf('%06d', random_int(0, 999999));
$file = $dir . "/dc_$tag.csv";
$day = date('Y-m-d 00:00:00', strtotime('-3 days'));
$db->beginTransaction();
try {
    $title = 'NVIDIA GeForce RTX 3070 8GB used';
    file_put_contents($file, "capture_method,source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint\nowner_transcription,https://www.facebook.com/marketplace/item/94{$tag}01?ref=old,FB-94{$tag}01,\"$title\",7800,$day,\n");
    $sum = (new OfflineEvidenceImportService())->import($file, 'real', false, 10);
    ok_dc($sum['candidates_created'] === 1, 'One row stored for the check: ' . json_encode([$sum['candidates_created'], $sum['row_errors']]));

    $before = (int)$db->query('SELECT COUNT(*) FROM raw_price_observations')->fetchColumn();
    $rows = OwnerRowPreparer::prepare([
        ['url' => "https://m.facebook.com/marketplace/item/94{$tag}01/?fbclid=zz", 'title' => $title, 'price' => '7800', 'date' => date('Y-m-d', strtotime('-2 days')), '_row' => 2],
        ['url' => "https://www.facebook.com/marketplace/item/94{$tag}02", 'title' => $title, 'price' => '7800', 'date' => date('Y-m-d', strtotime('-2 days')), '_row' => 3],
        ['url' => "https://www.facebook.com/marketplace/item/94{$tag}03", 'title' => $title . ' new box', 'price' => '9100', 'date' => date('Y-m-d', strtotime('-2 days')), '_row' => 4],
    ], '')['rows'];
    ok_dc(count($rows) === 3, 'The three prepared rows are valid on their own');
    $res = OwnerRowDbCheck::check($db, $rows);
    $by = []; foreach ($res as $r) { $by[$r['index']] = $r; }
    ok_dc(($by[0]['level'] ?? '') === 'error' && in_array('ALREADY_STORED_ITEM', $by[0]['codes'] ?? [], true), 'Same item with other tracking is already stored: ' . json_encode($by[0] ?? null));
    ok_dc(($by[1]['level'] ?? '') === 'warning' && in_array('REPOST_OF_STORED', $by[1]['codes'] ?? [], true), 'Another item with the same words and price is a repost warning: ' . json_encode($by[1] ?? null));
    ok_dc(!isset($by[2]), 'A new listing raises nothing: ' . json_encode($by[2] ?? null));
    ok_dc((int)$db->query('SELECT COUNT(*) FROM raw_price_observations')->fetchColumn() === $before, 'The check wrote nothing');
    ok_dc(OwnerRowDbCheck::check($db, []) === [], 'No rows, no findings');
} finally {
    $db->rollBack();
    @unlink($file);
}
echo "owner_row_db_check_test: PASS\n";
