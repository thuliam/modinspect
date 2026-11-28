<?php
declare(strict_types=1);

namespace App\Services\Ops;

use App\Services\Auth\AuthService;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;
use App\Services\Pricing\TrendBuilder;
use PDO;

/**
 * LS-89 BE-3: the daily routine as one command (cli/daily_jobs.php): back up, bring prices up to date, rebuild the trend, check the system.
 * Without $apply the price and trend steps only count what would change. With $apply they write, and only after a backup that worked:
 * a failed backup skips every write. Nothing is approved and no migration is run.
 */
final class DailyJobs
{
    public const JOBS = ['backup', 'prices', 'trend', 'health'];

    /** @param callable():array{ok:bool,bytes:int,error:?string} $backup makes the backup file and prunes old ones */
    public function __construct(private PDO $db, private $backup)
    {
    }

    /** @return array<string,array{status:string,summary:array,error:?string}> by job name */
    public function run(bool $apply, ?int $actorId = null): array
    {
        $runner = new JobRunner($this->db);
        $out = [];
        $out['backup'] = $runner->run('backup', function (): array {
            $result = ($this->backup)();
            if (!($result['ok'] ?? false)) {
                throw new JobFailed('BACKUP_FAILED', ['reason' => (string)($result['error'] ?? 'unknown')]);
            }
            return ['bytes' => (int)$result['bytes']];
        });
        $write = $apply && $out['backup']['status'] === 'ok';
        $refused = $apply && !$write;

        $out['prices'] = $runner->run('prices', function () use ($write, $refused, $actorId): array {
            $recompute = new SnapshotRecompute($this->db, new PriceSnapshotService());
            $plan = $recompute->plan('asking');
            $summary = SnapshotRecompute::counts($plan) + ['applied' => $write, 'stored' => 0];
            if ($refused) {
                throw new JobFailed('SKIPPED_NO_BACKUP', $summary);
            }
            if ($write) {
                $summary['stored'] = $recompute->apply($plan, 'asking');
                (new AuthService())->audit($actorId, 'daily_jobs_prices_applied', 'price_snapshot', null, $summary);
            }
            return $summary;
        });

        $out['trend'] = $runner->run('trend', function () use ($write, $refused): array {
            $trend = new TrendBuilder($this->db);
            $diff = $trend->diff();
            $summary = ['stored' => $diff['stored'], 'rebuilt' => $diff['rebuilt'], 'changed' => count($diff['changed']), 'added' => count($diff['added']), 'removed' => count($diff['removed']), 'applied' => $write];
            if ($refused) {
                throw new JobFailed('SKIPPED_NO_BACKUP', $summary);
            }
            if ($write) {
                $trend->rebuild();
            }
            return $summary;
        });

        $out['health'] = $runner->run('health', function (): array {
            // the job record of this very run is still open, so the jobs themselves are not judged here
            $checks = (new SystemHealth($this->db))->checks(false);
            $failed = array_values(array_map(static fn(array $c): string => $c['code'], array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail')));
            $warned = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'warn'));
            $summary = ['checks' => count($checks), 'failed' => $failed, 'warnings' => $warned];
            if ($failed !== []) {
                throw new JobFailed('HEALTH_CHECK_FAILED', $summary);
            }
            return $summary;
        });
        return $out;
    }
}
