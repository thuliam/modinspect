<?php
declare(strict_types=1);

namespace App\Services\Ops;

/** Keeps the newest $keep backup files of a directory and deletes the rest (LS-4 K-4). Only files named *.sql are ever touched; $keep below 1 is refused. */
final class BackupRetention
{
    /** @return array{kept:int,deleted:list<string>} */
    public static function prune(string $dir, int $keep): array
    {
        if ($keep < 1) { throw new \InvalidArgumentException('keep must be at least 1'); }
        clearstatcache();
        $files = glob(rtrim($dir, '/\\') . '/*.sql') ?: [];
        usort($files, static fn(string $a, string $b): int => [filemtime($b), $b] <=> [filemtime($a), $a]);
        $deleted = [];
        foreach (array_slice($files, $keep) as $f) { if (@unlink($f)) { $deleted[] = basename($f); } }
        return ['kept' => min($keep, count($files)), 'deleted' => $deleted];
    }
}
