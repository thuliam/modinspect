<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use PDO;

final class SourceHealthService
{
    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function report(?int $sourceId = null): array
    {
        $sources = $this->sources($sourceId);
        $items = [];
        foreach ($sources as $source) {
            $items[] = $this->sourceReport($source);
        }
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'sources_scanned' => count($items),
            'items' => $items,
        ];
    }

    private function sourceReport(array $source): array
    {
        $sourceId = (int)$source['id'];
        $job = $this->jobMetrics($sourceId);
        $lanes = $this->laneMetrics($sourceId);
        $duplicate = $this->duplicateMetrics($sourceId);
        $consecutiveFailures = $this->consecutiveFailures($sourceId);
        $enabled = (int)$source['is_active'] === 1;
        $paused = (int)($source['is_paused'] ?? 0) === 1;

        return [
            'source_id' => $sourceId,
            'source_key' => $source['source_key'] ?? null,
            'name' => $source['name'],
            'source_type' => $source['source_type'],
            'enabled' => $enabled,
            'paused' => $paused,
            'allowed_collection_method' => $source['allowed_collection_method'] ?? $source['access_method'],
            'reliability_score' => (float)($source['reliability_score'] ?? 50),
            'evidence_quality' => $source['evidence_quality'] ?? 'unknown',
            'freshness_expectation_days' => $source['freshness_expectation_days'] === null ? null : (int)$source['freshness_expectation_days'],
            'jobs_attempted' => (int)$job['jobs_attempted'],
            'jobs_succeeded' => (int)$job['jobs_succeeded'],
            'jobs_failed' => (int)$job['jobs_failed'],
            'candidate_yield' => (int)$job['candidate_yield'],
            'green' => (int)$lanes['green'],
            'amber' => (int)$lanes['amber'],
            'red' => (int)$lanes['red'],
            'duplicate_count' => (int)$duplicate['duplicate_count'],
            'duplicate_rate' => (float)$duplicate['duplicate_rate'],
            'last_success' => $source['last_success_at'],
            'last_failure' => $source['last_failure_at'],
            'consecutive_failures' => $consecutiveFailures,
            'status' => $this->status($enabled, $paused, (int)$job['jobs_attempted'], (int)$job['jobs_failed'], $consecutiveFailures),
        ];
    }

    private function sources(?int $sourceId): array
    {
        if ($sourceId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM data_sources WHERE id=:id");
            $stmt->execute(['id' => $sourceId]);
            return $stmt->fetchAll();
        }
        return $this->db->query("SELECT * FROM data_sources ORDER BY id")->fetchAll();
    }

    private function jobMetrics(int $sourceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
               SUM(CASE WHEN status IN ('completed','partial','failed') THEN 1 ELSE 0 END) jobs_attempted,
               SUM(CASE WHEN status IN ('completed','partial') THEN 1 ELSE 0 END) jobs_succeeded,
               SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) jobs_failed,
               COALESCE(SUM(raw_count),0) candidate_yield
             FROM collector_jobs
             WHERE source_id=:source_id"
        );
        $stmt->execute(['source_id' => $sourceId]);
        return $stmt->fetch() ?: ['jobs_attempted' => 0, 'jobs_succeeded' => 0, 'jobs_failed' => 0, 'candidate_yield' => 0];
    }

    private function laneMetrics(int $sourceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.lane, COUNT(*) count
             FROM observation_review_decisions d
             JOIN raw_price_observations r ON r.id=d.raw_observation_id
             WHERE r.source_id=:source_id
             GROUP BY d.lane"
        );
        $stmt->execute(['source_id' => $sourceId]);
        $lanes = ['green' => 0, 'amber' => 0, 'red' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $lanes[(string)$row['lane']] = (int)$row['count'];
        }
        return $lanes;
    }

    private function duplicateMetrics(int $sourceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) total, SUM(CASE WHEN is_duplicate=1 THEN 1 ELSE 0 END) duplicate_count
             FROM price_observations o
             JOIN raw_price_observations r ON r.id=o.raw_observation_id
             WHERE r.source_id=:source_id"
        );
        $stmt->execute(['source_id' => $sourceId]);
        $row = $stmt->fetch() ?: ['total' => 0, 'duplicate_count' => 0];
        $total = (int)$row['total'];
        $duplicates = (int)$row['duplicate_count'];
        return [
            'duplicate_count' => $duplicates,
            'duplicate_rate' => $total === 0 ? 0.0 : round($duplicates / $total, 4),
        ];
    }

    private function consecutiveFailures(int $sourceId): int
    {
        $stmt = $this->db->prepare("SELECT status FROM collector_jobs WHERE source_id=:source_id AND status IN ('completed','partial','failed') ORDER BY completed_at DESC, id DESC LIMIT 20");
        $stmt->execute(['source_id' => $sourceId]);
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            if ($row['status'] !== 'failed') {
                break;
            }
            $count++;
        }
        return $count;
    }

    private function status(bool $enabled, bool $paused, int $attempted, int $failed, int $consecutiveFailures): string
    {
        if (!$enabled || $paused) {
            return 'paused';
        }
        if ($attempted === 0) {
            return 'unknown';
        }
        if ($consecutiveFailures >= 2 || ($failed / max(1, $attempted)) > 0.25) {
            return 'degraded';
        }
        return 'healthy';
    }
}
