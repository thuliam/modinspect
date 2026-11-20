<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\RetentionPurge;

/**
 * LS-24 M6/M9: retention for seller reports that were never verified (ADR-007 step 5) and for hidden forum content. Dry run touches nothing; apply removes only the
 * unverified reports (with their evidence files) and the hidden forum items older than the period; verified and disputed reports and visible content stay; the floor
 * is seven days. Test database inside a transaction, rolled back; evidence files live in a temporary folder.
 */
function ok_rt(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_rt($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$dir = sys_get_temp_dir() . '/mi_rt_' . bin2hex(random_bytes(4)); mkdir($dir, 0700, true);
$old = date('Y-m-d H:i:s', strtotime('-40 days')); $new = date('Y-m-d H:i:s', strtotime('-2 days'));
$db->beginTransaction();
try {
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('rt', NOW())"); $m = (int)$db->lastInsertId();
    $rep = static function (string $status, string $at, string $tag) use ($db, $m, $dir): int {
        $db->prepare("INSERT INTO seller_reports (uuid, member_id, identifier_type, identifier_hash, identifier_last4, problem_type, status, created_at) VALUES (:u, :m, 'phone', :h, '1234', 'no_delivery', :s, :c)")->execute(['u' => substr(md5($tag), 0, 32), 'm' => $m, 'h' => str_repeat('a', 64), 's' => $status, 'c' => $at]);
        $id = (int)$db->lastInsertId(); $f = "ev-$tag.jpg"; file_put_contents("$dir/$f", 'x');
        $db->prepare('INSERT INTO seller_report_evidence (report_id, file_name) VALUES (:r, :f)')->execute(['r' => $id, 'f' => $f]);
        return $id;
    };
    $pOld = $rep('pending', $old, 'p-old'); $rOld = $rep('rejected', $old, 'r-old'); $vOld = $rep('verified', $old, 'v-old'); $dOld = $rep('disputed', $old, 'd-old'); $pNew = $rep('pending', $new, 'p-new');

    $dry = RetentionPurge::run($db, ['seller_pending_days' => 30], $dir, false, null, $dir);
    ok_rt($dry['results']['seller_reports_unverified']['matched'] === 2 && $dry['results']['seller_reports_unverified']['deleted'] === 0 && count(glob("$dir/ev-*.jpg")) === 5, 'Dry run: two unverified old reports matched, nothing deleted, files stay');
    $r = RetentionPurge::run($db, ['seller_pending_days' => 30], $dir, true, null, $dir);
    ok_rt($r['results']['seller_reports_unverified']['deleted'] === 2, 'Apply deletes the two');
    $left = array_map('intval', $db->query('SELECT id FROM seller_reports WHERE id IN (' . implode(',', [$pOld, $rOld, $vOld, $dOld, $pNew]) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    ok_rt($left === [$vOld, $dOld, $pNew], 'Verified, disputed and recent reports stay');
    ok_rt(!is_file("$dir/ev-p-old.jpg") && !is_file("$dir/ev-r-old.jpg") && is_file("$dir/ev-v-old.jpg") && is_file("$dir/ev-d-old.jpg") && is_file("$dir/ev-p-new.jpg"), 'Only the evidence files of the deleted reports are removed');
    $threw = false; try { RetentionPurge::run($db, ['seller_pending_days' => 3], $dir, false); } catch (InvalidArgumentException $e) { $threw = true; }
    ok_rt($threw, 'The floor is seven days');

    // forum
    $th = static function (string $status, string $at, string $tag) use ($db, $m): int { $db->prepare("INSERT INTO community_threads (member_id, title, body, body_hash, status, created_at) VALUES (:m, :t, 'เนื้อหาทดสอบ', :h, :s, :c)")->execute(['m' => $m, 't' => "หัวข้อ $tag", 'h' => md5($tag), 's' => $status, 'c' => $at]); return (int)$db->lastInsertId(); };
    $tHiddenOld = $th('hidden', $old, 'a'); $tVisibleOld = $th('visible', $old, 'b'); $tHiddenNew = $th('hidden', $new, 'c');
    $db->prepare("INSERT INTO community_posts (thread_id, member_id, body, body_hash, status, created_at) VALUES (:t, :m, 'ตอบทดสอบ', 'x', 'hidden', :c)")->execute(['t' => $tVisibleOld, 'm' => $m, 'c' => $old]); $postHidden = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO community_flags (target_type, target_id, member_id, reason) VALUES ('post', :i, :m, 'spam')")->execute(['i' => $postHidden, 'm' => $m]);
    $fd = RetentionPurge::run($db, ['forum_days' => 30], $dir, false);
    ok_rt($fd['results']['forum_hidden']['matched'] === 2 && $fd['results']['forum_hidden']['deleted'] === 0, 'Dry run: the hidden old thread and the hidden old post are matched');
    $fa = RetentionPurge::run($db, ['forum_days' => 30], $dir, true);
    ok_rt($fa['results']['forum_hidden']['deleted'] === 2, 'Apply deletes the two hidden old items');
    $ids = array_map('intval', $db->query("SELECT id FROM community_threads WHERE id IN ($tHiddenOld, $tVisibleOld, $tHiddenNew) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
    ok_rt($ids === [$tVisibleOld, $tHiddenNew], 'Visible content and recently hidden content stay');
    ok_rt((int)$db->query("SELECT COUNT(*) FROM community_flags WHERE target_type = 'post' AND target_id = $postHidden")->fetchColumn() === 0, 'The flags of a deleted item go with it');
} finally {
    $db->rollBack();
    array_map('unlink', glob("$dir/*") ?: []); @rmdir($dir);
}
$cli = (string)file_get_contents(ROOT_PATH . '/cli/retention_purge.php');
ok_rt(str_contains($cli, 'seller-pending-days') && str_contains($cli, 'forum-days') && str_contains($cli, "storage/uploads/reports"), 'The CLI takes both options and knows the evidence folder');
echo "retention_ls24_test: PASS\n";
