<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Ops\MigrationStatus as M;

/** LS-26: the migration status report reads what a file creates and compares it with what exists. Pure; the CLI itself only runs SELECTs. */
function ok_ms(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$sql = "-- comment CREATE TABLE ghost (x INT);\nCREATE TABLE IF NOT EXISTS a_one (id INT);\nCREATE TABLE `b_two` (id INT);\nALTER TABLE orders ADD COLUMN note VARCHAR(5) NULL;\nALTER TABLE orders ADD INDEX idx_x (note);";
$e = M::expectations($sql);
ok_ms($e['tables'] === ['a_one', 'b_two'] && $e['columns'] === [['table' => 'orders', 'column' => 'note']], 'Tables and added columns are read, comments and indexes are not: ' . json_encode($e));
ok_ms(M::evaluate($sql, [])['verdict'] === 'pending', 'Nothing there is pending');
ok_ms(M::evaluate($sql, ['a_one' => ['id'], 'b_two' => ['id'], 'orders' => ['id', 'note']])['verdict'] === 'applied', 'Everything there is applied');
$p = M::evaluate($sql, ['a_one' => ['id'], 'orders' => ['id']]);
ok_ms($p['verdict'] === 'partial' && $p['missing'] === ['table b_two', 'column orders.note'] && $p['found'] === 1 && $p['expected'] === 3, 'Some there is partial and names what is missing');
ok_ms(M::evaluate('SELECT 1;', [])['verdict'] === 'unknown', 'A file that creates nothing is unknown');
$src = (string) file_get_contents(dirname(__DIR__) . '/cli/migration_status.php');
ok_ms(!preg_match('/->exec\(|INSERT |UPDATE |DELETE |ALTER |CREATE |DROP /', preg_replace('#/\*.*?\*/#s', '', $src)), 'The CLI contains no write or schema statement');
echo "migration_status_test: PASS\n";
