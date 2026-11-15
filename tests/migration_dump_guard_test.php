<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * CI builds its test database from the tracked modinspect.sql, and fresh installs from database/schema.sql.
 * Every table or column a migration creates must therefore also be in BOTH files, or CI fails (it did on 2026-11-15).
 * Pure file check: no database needed.
 */
$root = dirname(__DIR__);
$schema = strtolower((string)file_get_contents($root . '/database/schema.sql'));
$dump = strtolower((string)file_get_contents($root . '/modinspect.sql'));

$tableBlock = static function (string $sql, string $table): ?string {
    if (preg_match('/create table (?:if not exists )?`?' . preg_quote($table, '/') . '`?\s*\((.*?)\)\s*engine/s', $sql, $m)) { return $m[1]; }
    return null;
};

// Documented exceptions: migrations that predate this guard and are known not to be mirrored. Keep empty; add a reason if ever needed.
$exceptions = [];

$schemaGuardFrom = 15;   // database/schema.sql is required for migration 015 and later; modinspect.sql (the CI source) for all
$problems = []; $preGuardGaps = 0;
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
    $name = basename($file);
    $number = (int)substr($name, 0, 3);
    $targets = ['modinspect.sql' => $dump] + ($number >= $schemaGuardFrom ? ['database/schema.sql' => $schema] : []);
    $sql = strtolower((string)preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($file)));
    if (preg_match_all('/create table (?:if not exists )?`?([a-z0-9_]+)`?/', $sql, $tm)) {
        foreach (array_unique($tm[1]) as $table) {
            foreach ($targets as $label => $content) {
                if ($tableBlock($content, $table) === null && !in_array("$name:$table", $exceptions, true)) { $problems[] = "$name creates table $table, missing in $label"; }
            }
        }
    }
    if (preg_match_all('/alter table `?([a-z0-9_]+)`?\s+(.*?);/s', $sql, $am, PREG_SET_ORDER)) {
        foreach ($am as $a) {
            if (!preg_match_all('/add column (?:if not exists )?`?([a-z0-9_]+)`?/', $a[2], $cols)) { continue; }
            foreach ($cols[1] as $col) {
                foreach ($targets as $label => $content) {
                    $block = $tableBlock($content, $a[1]);
                    if ($block === null || !preg_match('/`?' . preg_quote($col, '/') . '`?\s+[a-z]/', $block)) {
                        if (!in_array("$name:{$a[1]}.$col", $exceptions, true)) { $problems[] = "$name adds {$a[1]}.$col, missing in $label"; }
                    }
                }
            }
        }
    }
}
if ($problems) {
    fwrite(STDERR, "Migration/dump drift:\n  " . implode("\n  ", $problems) . "\n");
    throw new RuntimeException(count($problems) . ' migration object(s) are not mirrored in schema.sql and modinspect.sql');
}
echo "migration_dump_guard_test: PASS\n";
