<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

/** LS-30: the migration runner must ignore ";" and apostrophes inside "--" comments (migration 028 stopped on one). Static check of cli/migrate.php plus a real run on a temp file in the test DB. */
function ok_ms(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
ok_ms($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');

$rel = 'tests/_tmp_split_migration.sql';
$full = ROOT_PATH . '/' . $rel;
file_put_contents($full, "-- a comment; with a semicolon and don't an apostrophe\nCREATE TABLE IF NOT EXISTS _split_probe (\n  id INT PRIMARY KEY, -- trailing; comment\n  note VARCHAR(20) DEFAULT 'a--b;c'\n);\n-- another; one\nINSERT INTO _split_probe (id) VALUES (1);\n");
$php = PHP_BINARY;
$out = [];
$code = 0;
exec('"' . $php . '" ' . escapeshellarg(ROOT_PATH . '/cli/migrate.php') . ' ' . escapeshellarg($rel) . ' 2>&1', $out, $code);
$db = App\Core\Database::connection($config['db']);
$n = (int)$db->query('SELECT COUNT(*) FROM _split_probe')->fetchColumn();
$default = (string)$db->query("SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '_split_probe' AND COLUMN_NAME = 'note'")->fetchColumn();
$db->exec('DROP TABLE IF EXISTS _split_probe');
@unlink($full);
ok_ms($code === 0 && $n === 1, 'The file with comment semicolons ran cleanly: ' . implode(' | ', $out));
ok_ms(str_contains($default, "a--b;c"), 'A "--" and ";" inside a quoted string are kept: ' . $default);
echo "migrate_splitter_test: PASS\n";
