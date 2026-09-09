<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use PDO;

final class CollectionRunRepository
{
    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function persist(array $summary, int $sourceId, string $provider, string $jobType, string $executionMode, string $startedAt, string $finishedAt): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO collection_runs
             (run_id,source_id,provider,job_type,execution_mode,started_at,finished_at,duration_ms,jobs_scanned,jobs_claimed,jobs_completed,jobs_failed,jobs_skipped,candidates_created,evidence_created,extractions_created,reviews_created,green_count,amber_count,red_count,error_count,summary_json)
             VALUES
             (:run_id,:source_id,:provider,:job_type,:execution_mode,:started_at,:finished_at,:duration_ms,:jobs_scanned,:jobs_claimed,:jobs_completed,:jobs_failed,:jobs_skipped,:candidates_created,:evidence_created,:extractions_created,:reviews_created,:green_count,:amber_count,:red_count,:error_count,:summary_json)"
        );
        $stmt->execute([
            'run_id' => $summary['run_id'],
            'source_id' => $sourceId,
            'provider' => $provider,
            'job_type' => $jobType,
            'execution_mode' => $executionMode,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => (int)$summary['duration_ms'],
            'jobs_scanned' => (int)$summary['jobs_scanned'],
            'jobs_claimed' => (int)$summary['jobs_claimed'],
            'jobs_completed' => (int)$summary['jobs_completed'],
            'jobs_failed' => (int)$summary['jobs_failed'],
            'jobs_skipped' => (int)$summary['jobs_skipped'],
            'candidates_created' => (int)$summary['candidates_created'],
            'evidence_created' => (int)$summary['evidence_created'],
            'extractions_created' => (int)$summary['extractions_created'],
            'reviews_created' => (int)$summary['reviews_created'],
            'green_count' => (int)$summary['green'],
            'amber_count' => (int)$summary['amber'],
            'red_count' => (int)$summary['red'],
            'error_count' => $this->errorCount($summary),
            'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function errorCount(array $summary): int
    {
        $count = 0;
        foreach ($summary['jobs'] ?? [] as $job) {
            $count += count($job['errors'] ?? []);
        }
        return $count;
    }
}
