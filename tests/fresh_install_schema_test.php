<?php
declare(strict_types=1);

/**
 * LS-19 A2: database/schema.sql is the FULL current structure, and a fresh install runs it plus only the DATA statements of every migration
 * (cli/rehearse_fresh_install.php, CI job "Fresh install rehearsal"). That only works if every table, column and index a migration creates is also
 * in schema.sql. This test fails the moment a new migration adds structure that schema.sql does not have, so the two can never drift again
 * (the first CI rehearsal, 2026-11-19, found the old runbook rule "schema then every migration" failing at migration 009).
 */
function ok_fi(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/schema.sql');

// table name => its CREATE TABLE block in schema.sql
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?\s*\((.*?)\n\)\s*[^;]*;/is', $schema, $mm, PREG_SET_ORDER);
$tables = []; foreach ($mm as $m) { $tables[strtolower($m[1])] = strtolower($m[2]); }
ok_fi(count($tables) >= 30, 'schema.sql tables were parsed (' . count($tables) . ')');
$hasColumn = static fn(string $t, string $c): bool => isset($tables[$t]) && preg_match('/(^|\n)\s*`?' . preg_quote($c, '/') . '`?\s+[a-z]/i', $tables[$t]) === 1;
$hasIndex = static fn(string $t, string $i): bool => isset($tables[$t]) && preg_match('/\b(key|index|constraint)\s+`?' . preg_quote($i, '/') . '`?/i', $tables[$t]) === 1
    || preg_match('/CREATE\s+(UNIQUE\s+)?INDEX\s+`?' . preg_quote($i, '/') . '`?\s+ON\s+`?' . preg_quote($t, '/') . '/i', $schema) === 1;

$missing = []; $checked = 0;
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
    $sql = (string)preg_replace('/--[^\n]*/', '', (string)file_get_contents($file)); $name = basename($file);
    preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?/i', $sql, $ct);
    foreach ($ct[1] as $t) { $checked++; if (!isset($tables[strtolower($t)])) { $missing[] = "$name: table $t"; } }
    preg_match_all('/ALTER TABLE `?([a-z_]+)`?(.*?);/is', $sql, $at, PREG_SET_ORDER);
    foreach ($at as $a) {
        $t = strtolower($a[1]);
        preg_match_all('/ADD\s+COLUMN\s+(?:IF NOT EXISTS\s+)?`?([a-z_0-9]+)`?/i', $a[2], $cols);
        foreach ($cols[1] as $c) { $checked++; if (!$hasColumn($t, strtolower($c))) { $missing[] = "$name: column $t.$c"; } }
        preg_match_all('/ADD\s+(?:UNIQUE\s+)?(?:INDEX|KEY)\s+(?:IF NOT EXISTS\s+)?`?([a-z_0-9]+)`?/i', $a[2], $idx);
        foreach ($idx[1] as $i) {
            $checked++;
            if ($hasIndex($t, strtolower($i))) { continue; }
            // a one-column UNIQUE KEY is the same constraint as a column declared UNIQUE in schema.sql (only the index name differs)
            if (preg_match('/ADD\s+UNIQUE\s+(?:INDEX|KEY)\s+`?' . preg_quote($i, '/') . '`?\s*\(\s*`?([a-z_0-9]+)`?\s*\)/i', $a[2], $u) && isset($tables[$t])
                && preg_match('/(^|\n)\s*`?' . preg_quote(strtolower($u[1]), '/') . '`?\s+[^\n]*\bunique\b/i', $tables[$t])) { continue; }
            $missing[] = "$name: index $t.$i";
        }
    }
    preg_match_all('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF NOT EXISTS\s+)?`?([a-z_0-9]+)`?\s+ON\s+`?([a-z_]+)/i', $sql, $ci, PREG_SET_ORDER);
    foreach ($ci as $c) { $checked++; if (!$hasIndex(strtolower($c[2]), strtolower($c[1]))) { $missing[] = "$name: index {$c[2]}.{$c[1]}"; } }
}
ok_fi($checked >= 40, "Enough structure was found in the migrations to mean something ($checked items)");
ok_fi($missing === [], "schema.sql lacks structure that a migration adds (add it to schema.sql too):\n  " . implode("\n  ", $missing));

// the rehearsal and the runbook describe the same rule
$rehearsal = (string)file_get_contents($root . '/cli/rehearse_fresh_install.php');
ok_fi(str_contains($rehearsal, "preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\\b/i', \$body)") && str_contains($rehearsal, "const CATALOG_TABLES = ['product_categories', 'brands', 'products', 'product_aliases'];"), 'The rehearsal runs migration data only and takes only catalog rows from seed.sql in production');
$runbook = (string)file_get_contents($root . '/docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md');
ok_fi(str_contains($runbook, 'cli/rehearse_fresh_install.php') && !str_contains($runbook, 'then apply each file in `database/migrations/` in order (the CI job'), 'The runbook no longer says "schema then every migration" for a new database');
echo "fresh_install_schema_test: PASS ($checked structure items checked)\n";
