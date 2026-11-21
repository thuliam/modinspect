<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Services\Ops\MigrationPlan;

/** LS-29 A1: the plan skips what exists, runs what is absent in file order, blocks on partial, leaves data-only files to a person; the CLI writes only under --apply. */
function ok_mp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$files = [
    '030_c.sql' => 'CREATE TABLE gamma (id INT);',
    '029_b.sql' => 'ALTER TABLE alpha ADD COLUMN colx INT; ALTER TABLE alpha ADD COLUMN coly INT;',
    '028_a.sql' => 'CREATE TABLE alpha (id INT);',
    '031_d.sql' => 'INSERT INTO alpha VALUES (1);',
    '010_old.sql' => 'CREATE TABLE ancient (id INT);',
];
$existing = ['alpha' => ['id']];
$p = MigrationPlan::build($files, $existing, 20);
$by = array_column($p['items'], 'action', 'file');
ok_mp(array_keys($by) === ['028_a.sql', '029_b.sql', '030_c.sql', '031_d.sql'], 'Files are ordered by name and those before --from are left out');
ok_mp($by['028_a.sql'] === 'skip' && $by['029_b.sql'] === 'run' && $by['030_c.sql'] === 'run' && $by['031_d.sql'] === 'manual', 'skip / run / run / manual: ' . json_encode($by));
ok_mp($p['to_run'] === ['029_b.sql', '030_c.sql'] && $p['safe'] === true, 'The run list is in order and the plan is safe');
$partial = MigrationPlan::build($files, ['alpha' => ['id', 'colx']], 20);
ok_mp($partial['safe'] === false && $partial['counts']['blocked'] === 1, 'A partly applied file blocks the plan');
$all = MigrationPlan::build($files, ['alpha' => ['id', 'colx', 'coly'], 'gamma' => ['id']], 20);
ok_mp($all['to_run'] === [] && $all['safe'] === true, 'When everything exists there is nothing to run');
$src = (string)file_get_contents(ROOT_PATH . '/cli/migrate_pending.php');
ok_mp(strpos($src, 'Dry run only') !== false && strpos($src, 'Dry run only') < strpos($src, 'DatabaseBackup::backup('), 'The dry run exits before any backup or migration is started');
ok_mp(strpos($src, 'Dry run only') < strpos($src, 'passthru('), 'No migration is started before the dry-run exit');
echo "migration_plan_test: PASS\n";
