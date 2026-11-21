<?php
declare(strict_types=1);

namespace App\Services\Ops;

/**
 * LS-29 A3: the one-page verdict of the pre-deploy checks. Pure: callers pass each check's result in. A check is pass, warn or fail; one fail makes the report FAIL,
 * otherwise any warn makes it PASS_WITH_WARNINGS. Messages never carry a secret value: callers pass names and counts only.
 */
final class PredeployReport
{
    public const LEVELS = ['pass', 'warn', 'fail'];

    /** @param list<array{name:string,level:string,note:string}> $checks @return array{verdict:string,counts:array<string,int>,checks:list<array{name:string,level:string,note:string}>} */
    public static function summarize(array $checks): array
    {
        $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0]; $clean = [];
        foreach ($checks as $c) {
            $level = in_array($c['level'], self::LEVELS, true) ? $c['level'] : 'fail';   // an unknown level is never read as a pass
            $counts[$level]++;
            $clean[] = ['name' => (string)$c['name'], 'level' => $level, 'note' => (string)$c['note']];
        }
        $verdict = $counts['fail'] > 0 ? 'FAIL' : ($counts['warn'] > 0 ? 'PASS_WITH_WARNINGS' : 'PASS');
        return ['verdict' => $verdict, 'counts' => $counts, 'checks' => $clean];
    }
}
