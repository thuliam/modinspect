<?php
declare(strict_types=1);

namespace App\Services\Ops;

/**
 * LS-29 A1: turns the verdicts of MigrationStatus into an ordered plan. Pure: callers pass the files and the existing tables in, nothing here connects or writes.
 * Actions: skip (already applied), run (nothing of it exists), manual (the file has nothing the checker can see, e.g. data only: a person decides), blocked (partial:
 * running it whole could fail or duplicate). Any partial file makes the whole plan unsafe, so `safe` is false and nothing should be applied until a person looks.
 */
final class MigrationPlan
{
    /**
     * @param array<string,string> $files file name => SQL, in the order to be considered
     * @param array<string,list<string>> $existing table => columns (lower case)
     * @return array{items:list<array{file:string,verdict:string,action:string,missing:list<string>}>,safe:bool,to_run:list<string>,counts:array<string,int>}
     */
    public static function build(array $files, array $existing, int $from = 0, array $recorded = []): array
    {
        $items = []; $counts = ['skip' => 0, 'run' => 0, 'manual' => 0, 'blocked' => 0];
        ksort($files, SORT_STRING);
        foreach ($files as $name => $sql) {
            if ((int)$name < $from) { continue; }
            $r = MigrationStatus::evaluate($sql, $existing);
            $action = match ($r['verdict']) { 'applied' => 'skip', 'pending' => 'run', 'partial' => 'blocked', default => 'manual' };
            // LS-89: a file the checker cannot see (data only) is not asked about again once the ledger says it ran
            if ($action === 'manual' && isset($recorded[(string)$name])) { $action = 'skip'; $r['verdict'] = 'recorded'; }
            $counts[$action]++;
            $items[] = ['file' => (string)$name, 'verdict' => $r['verdict'], 'action' => $action, 'missing' => $r['missing']];
        }
        $toRun = array_values(array_map(static fn(array $i): string => $i['file'], array_filter($items, static fn(array $i): bool => $i['action'] === 'run')));
        return ['items' => $items, 'safe' => $counts['blocked'] === 0, 'to_run' => $toRun, 'counts' => $counts];
    }
}
