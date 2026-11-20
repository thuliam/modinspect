<?php
declare(strict_types=1);

namespace App\Services\Ops;

/**
 * LS-26: which migrations a database already has. The project keeps no migrations table, so this reads each file for what it creates (CREATE TABLE, ALTER TABLE
 * ADD COLUMN) and compares with the tables and columns that exist. Pure: callers pass the facts in, nothing here connects, reads credentials or writes.
 * Verdicts: applied (everything is there), pending (nothing is), partial (some are: a file that adds columns cannot simply be run again, so a person must look).
 */
final class MigrationStatus
{
    /** @return array{tables:list<string>,columns:list<array{table:string,column:string}>} */
    public static function expectations(string $sql): array
    {
        $sql = (string) preg_replace('/--[^\n]*/', '', $sql);
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?/i', $sql, $t);
        preg_match_all('/ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?/i', $sql, $c, PREG_SET_ORDER);
        $cols = [];
        foreach ($c as $m) { $cols[] = ['table' => strtolower($m[1]), 'column' => strtolower($m[2])]; }
        return ['tables' => array_values(array_unique(array_map('strtolower', $t[1]))), 'columns' => $cols];
    }

    /**
     * @param array<string,list<string>> $existing table => its columns (lower case); a table that does not exist is simply absent
     * @return array{verdict:string,missing:list<string>,found:int,expected:int}
     */
    public static function evaluate(string $sql, array $existing): array
    {
        $e = self::expectations($sql);
        $missing = []; $found = 0;
        foreach ($e['tables'] as $tbl) { isset($existing[$tbl]) ? $found++ : $missing[] = "table $tbl"; }
        foreach ($e['columns'] as $c) { in_array($c['column'], $existing[$c['table']] ?? [], true) ? $found++ : $missing[] = "column {$c['table']}.{$c['column']}"; }
        $expected = count($e['tables']) + count($e['columns']);
        $verdict = $expected === 0 ? 'unknown' : ($missing === [] ? 'applied' : ($found === 0 ? 'pending' : 'partial'));
        return ['verdict' => $verdict, 'missing' => $missing, 'found' => $found, 'expected' => $expected];
    }
}
