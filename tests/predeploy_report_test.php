<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Services\Ops\PredeployReport as P;

/** LS-29 A3: one fail fails the report, a warn alone passes with warnings, an unknown level counts as a fail, and the CLI neither deploys nor applies anything. */
function ok_pr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$ok = ['name' => 'a', 'level' => 'pass', 'note' => ''];
ok_pr(P::summarize([$ok, $ok])['verdict'] === 'PASS', 'All pass is PASS');
ok_pr(P::summarize([$ok, ['name' => 'b', 'level' => 'warn', 'note' => 'x']])['verdict'] === 'PASS_WITH_WARNINGS', 'A warn alone passes with warnings');
ok_pr(P::summarize([$ok, ['name' => 'b', 'level' => 'warn', 'note' => ''], ['name' => 'c', 'level' => 'fail', 'note' => '']])['verdict'] === 'FAIL', 'One fail fails the report');
$u = P::summarize([['name' => 'z', 'level' => 'ok??', 'note' => '']]);
ok_pr($u['verdict'] === 'FAIL' && $u['checks'][0]['level'] === 'fail', 'An unknown level is read as a fail');
ok_pr(P::summarize([])['verdict'] === 'PASS', 'No checks is PASS');
$src = (string)file_get_contents(ROOT_PATH . '/cli/predeploy_report.php');
ok_pr(!str_contains($src, 'migrate_pending') && !str_contains($src, 'passthru(') && !preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|DROP)\b/', $src), 'The report never applies a migration or writes');
echo "predeploy_report_test: PASS\n";
