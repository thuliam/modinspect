<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\BetaReadiness as B;

/** Beta readiness panel (LS-3 J-9): the parser follows the checklist file, the verdict follows its rule, live values override only the measured rows. */
function ok_br(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$md = <<<'MD'
## A. Data
| ID | Level | Requirement | How to verify | Status | Evidence |
|---|---|---|---|---|---|
| A-1 | MUST | Replay | `cmd` | MET | 49 of 49 |
| A-4 | MUST | Second source | page | **NOT MET / OWNER** | none yet |
| A-6 | SHOULD | Coverage | page | NOT MET | 49 of 99 |
| B-1 | MUST | Owner review | owner | **OWNER** | no sign-off |
| D-1 | MUST | Backup | cmd | MET (tool) | drill passes | with a pipe |
| F-9 | SHOULD | Something | x | N/A | n/a |
MD;
$rows = B::parse($md);
ok_br(array_column($rows, 'id') === ['A-1', 'A-4', 'A-6', 'B-1', 'D-1', 'F-9'], 'Only checklist rows are parsed: ' . implode(',', array_column($rows, 'id')));
$by = array_column($rows, null, 'id');
ok_br($by['A-1']['status'] === 'met' && $by['A-4']['status'] === 'not_met' && $by['A-4']['owner'] === true && $by['A-6']['status'] === 'not_met' && $by['A-6']['owner'] === false, 'Statuses are normalised and the owner flag is kept');
ok_br($by['B-1']['status'] === 'owner' && $by['B-1']['owner'] === true && $by['D-1']['status'] === 'met' && $by['F-9']['status'] === 'unknown', 'OWNER, MET (tool) and an unknown status are classified');
ok_br($by['D-1']['evidence'] === 'drill passes | with a pipe', 'Evidence keeps extra cells');
ok_br(B::parse('no table here') === [], 'A file without rows gives no rows');

$v = B::verdict($rows);
ok_br($v['verdict'] === 'NO-GO' && $v['unmet_must'] === ['A-4', 'B-1'] && $v['must'] === 4 && $v['must_met'] === 2 && $v['should'] === 2 && $v['should_met'] === 0, 'NO-GO lists the unmet MUST rows only: ' . json_encode($v));
ok_br(B::verdict(array_map(static fn($r) => array_merge($r, ['status' => 'met']), $rows))['verdict'] === 'GO', 'GO when every MUST row is met');
ok_br(B::verdict([])['verdict'] === 'NO-GO', 'No rows is never GO');

// overlay: measured rows change, others do not
$live = ['replay_total' => 10, 'replay_match' => 10, 'labels_present' => true, 'public_total' => 10, 'other_evidence' => 0, 'medium_or_better' => 0, 'ladder_flags' => 0, 'ladder_checked' => 44, 'app_env' => 'local', 'auth_override_present' => true];
$mdAll = $md . "\n| A-3 | MUST | l | h | MET | e |\n| A-7 | SHOULD | m | h | NOT MET | e |\n| A-8 | SHOULD | f | h | MET | e |\n| C-4 | MUST | env | h | NOT MET | e |\n";
$o = array_column(B::overlay(B::parse($mdAll), $live), null, 'id');
ok_br($o['A-1']['status'] === 'met' && $o['A-1']['live'] && str_contains($o['A-1']['evidence'], '10 of 10'), 'A-1 follows the live replay');
ok_br($o['A-4']['status'] === 'not_met' && $o['A-4']['owner'], 'A-4 stays unmet without a second source');
ok_br($o['A-7']['status'] === 'not_met' && $o['A-8']['status'] === 'met' && str_contains($o['A-8']['evidence'], '44'), 'A-7 and A-8 follow the live counts');
ok_br($o['C-4']['status'] === 'not_met' && str_contains($o['C-4']['evidence'], 'APP_ENV=local') && str_contains($o['C-4']['evidence'], 'AUTH_* overrides'), 'C-4 is unmet on a local environment with overrides');
ok_br($o['B-1']['live'] === false && $o['B-1']['status'] === 'owner' && $o['A-6']['live'] === false, 'Rows that cannot be measured keep the manual status');
$live2 = array_merge($live, ['replay_match' => 9, 'other_evidence' => 3, 'medium_or_better' => 2, 'ladder_flags' => 1, 'app_env' => 'production', 'auth_override_present' => false]);
$o2 = array_column(B::overlay(B::parse($mdAll), $live2), null, 'id');
ok_br($o2['A-1']['status'] === 'not_met' && $o2['A-4']['status'] === 'met' && $o2['A-7']['status'] === 'met' && $o2['A-8']['status'] === 'not_met' && $o2['C-4']['status'] === 'met', 'The same overlay flips both ways with different live values');

// the real checklist file parses completely and the build runs against the database
$real = B::parse((string)file_get_contents(__DIR__ . '/../docs/BETA_READINESS_CHECKLIST.md'));
ok_br(count($real) >= 30 && count(array_filter($real, static fn($r) => $r['level'] === 'MUST')) >= 15, 'The real checklist has its rows (' . count($real) . ')');
$res = (new B())->build();
ok_br($res['available'] && count($res['rows']) === count($real) && in_array($res['verdict']['verdict'], ['GO', 'NO-GO'], true), 'build() returns the rows with a verdict');
ok_br(count(array_filter($res['rows'], static fn($r) => $r['live'])) >= 5, 'At least five rows carry live values');

echo "beta_readiness_test: PASS\n";
