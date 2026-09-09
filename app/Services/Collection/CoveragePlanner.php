<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use App\Models\CollectionPipeline;
use PDO;

final class CoveragePlanner
{
    private PDO $db;
    private int $sourceId;

    public function __construct(
        ?int $sourceId = null,
        private int $targetSampleCount = 20,
        private int $freshnessWindowDays = 14,
        private int $freshTargetCount = 8,
        private string $jobType = 'coverage_mock_collection',
        private int $attemptCooldownMinutes = 60
    ) {
        global $config;
        $this->db = Database::connection($config['db']);
        $this->sourceId = $sourceId ?? (new CollectionPipeline())->ensureMockSource();
    }

    public function plan(): array
    {
        $products = $this->products();
        $items = [];

        foreach ($products as $product) {
            $metrics = $this->metricsFor((int)$product['id']);
            $reasons = $this->dueReasons($metrics);
            $hasActiveJob = (bool)$metrics['active_job_count'];
            $hasRecentAttempt = $this->hasRecentAttempt($metrics);
            $due = $reasons !== [] && !$hasActiveJob && !$hasRecentAttempt;
            if ($hasActiveJob) {
                $reasons[] = 'active_job_exists';
            }
            if ($hasRecentAttempt) {
                $reasons[] = 'recent_collection_attempt';
            }

            $sampleGap = max(0, $this->targetSampleCount - (int)$metrics['accepted_sample_count']);
            $freshGap = max(0, min($this->freshTargetCount, $this->targetSampleCount) - (int)$metrics['fresh_sample_count']);

            $items[] = [
                'product_id' => (int)$product['id'],
                'product_name' => (string)$product['full_name'],
                'category' => (string)$product['category_slug'],
                'accepted_sample_count' => (int)$metrics['accepted_sample_count'],
                'fresh_sample_count' => (int)$metrics['fresh_sample_count'],
                'target_count' => $this->targetSampleCount,
                'sample_gap' => $sampleGap,
                'fresh_gap' => $freshGap,
                'last_observation' => $metrics['last_observation'],
                'last_collection_attempt' => $metrics['last_collection_attempt'],
                'queued_or_running_jobs' => (int)$metrics['active_job_count'],
                'recent_failed_jobs' => (int)$metrics['recent_failed_jobs'],
                'due' => $due,
                'priority' => $this->priority($product, $metrics, $sampleGap, $freshGap, $hasActiveJob || $hasRecentAttempt),
                'due_reasons' => $reasons,
                'query_text' => $this->queryText((string)$product['full_name']),
            ];
        }

        usort($items, static fn(array $a, array $b): int => [$b['due'], $b['priority'], $b['sample_gap'], $a['product_name']] <=> [$a['due'], $a['priority'], $a['sample_gap'], $b['product_name']]);

        return [
            'provider' => 'mock',
            'source_id' => $this->sourceId,
            'job_type' => $this->jobType,
            'target_sample_count' => $this->targetSampleCount,
            'freshness_window_days' => $this->freshnessWindowDays,
            'fresh_target_count' => $this->freshTargetCount,
            'generated_at' => date('Y-m-d H:i:s'),
            'items' => $items,
            'summary' => $this->summary($items),
        ];
    }

    public function createJobs(array $plan, int $limit = 20): array
    {
        $created = [];
        $skipped = [];

        foreach ($plan['items'] as $item) {
            if (count($created) >= $limit) {
                break;
            }
            if (!$item['due']) {
                $skipped[] = ['product_id' => $item['product_id'], 'reason' => 'not_due'];
                continue;
            }
            if ($this->hasQueuedOrRunningJob((int)$item['product_id'])) {
                $skipped[] = ['product_id' => $item['product_id'], 'reason' => 'active_job_exists'];
                continue;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at)
                 VALUES (:source_id,:job_type,:product_id,:query_text,'queued',NOW(),NOW())"
            );
            $stmt->execute([
                'source_id' => $this->sourceId,
                'job_type' => $this->jobType,
                'product_id' => $item['product_id'],
                'query_text' => $item['query_text'],
            ]);
            $jobId = (int)$this->db->lastInsertId();
            $created[] = ['job_id' => $jobId, 'product_id' => $item['product_id'], 'query_text' => $item['query_text']];
            $this->audit('coverage_job_created', 'collector_job', $jobId, null, ['product_id' => $item['product_id'], 'reasons' => $item['due_reasons'], 'priority' => $item['priority']]);
        }

        $this->audit('coverage_plan_run', 'coverage_plan', null, null, ['created' => count($created), 'skipped' => count($skipped), 'summary' => $plan['summary']]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function products(): array
    {
        $stmt = $this->db->query(
            "SELECT p.id,p.full_name,p.is_popular,c.slug category_slug
             FROM products p
             JOIN product_categories c ON c.id=p.category_id
             WHERE p.is_active=1 AND c.slug IN ('cpu','gpu')
             ORDER BY c.slug,p.full_name"
        );
        return $stmt->fetchAll();
    }

    private function metricsFor(int $productId): array
    {
        $freshCutoff = date('Y-m-d H:i:s', strtotime("-{$this->freshnessWindowDays} days"));
        $metrics = [
            'accepted_sample_count' => 0,
            'fresh_sample_count' => 0,
            'last_observation' => null,
            'last_collection_attempt' => null,
            'active_job_count' => 0,
            'recent_failed_jobs' => 0,
        ];

        $obs = $this->db->prepare(
            "SELECT COUNT(*) accepted_sample_count,
                    SUM(CASE WHEN observed_at >= :fresh_cutoff THEN 1 ELSE 0 END) fresh_sample_count,
                    MAX(observed_at) last_observation
             FROM price_observations
             WHERE product_id=:product_id
               AND verified_status='approved'
               AND price_type='asking'
               AND listing_type='single_item'
               AND is_deposit=0
               AND is_defective=0
               AND is_duplicate=0"
        );
        $obs->execute(['product_id' => $productId, 'fresh_cutoff' => $freshCutoff]);
        $row = $obs->fetch() ?: [];
        $metrics['accepted_sample_count'] = (int)($row['accepted_sample_count'] ?? 0);
        $metrics['fresh_sample_count'] = (int)($row['fresh_sample_count'] ?? 0);
        $metrics['last_observation'] = $row['last_observation'] ?? null;

        $jobs = $this->db->prepare(
            "SELECT MAX(created_at) last_collection_attempt,
                    SUM(CASE WHEN status IN ('queued','running') THEN 1 ELSE 0 END) active_job_count,
                    SUM(CASE WHEN status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) recent_failed_jobs
             FROM collector_jobs
             WHERE product_id=:product_id AND source_id=:source_id AND job_type=:job_type"
        );
        $jobs->execute(['product_id' => $productId, 'source_id' => $this->sourceId, 'job_type' => $this->jobType]);
        $jobRow = $jobs->fetch() ?: [];
        $metrics['last_collection_attempt'] = $jobRow['last_collection_attempt'] ?? null;
        $metrics['active_job_count'] = (int)($jobRow['active_job_count'] ?? 0);
        $metrics['recent_failed_jobs'] = (int)($jobRow['recent_failed_jobs'] ?? 0);

        return $metrics;
    }

    private function dueReasons(array $metrics): array
    {
        $reasons = [];
        if ((int)$metrics['accepted_sample_count'] === 0) {
            $reasons[] = 'no_accepted_observations';
        }
        if ((int)$metrics['accepted_sample_count'] < $this->targetSampleCount) {
            $reasons[] = 'sample_gap';
        }
        if ((int)$metrics['fresh_sample_count'] < min($this->freshTargetCount, $this->targetSampleCount)) {
            $reasons[] = 'fresh_sample_gap';
        }
        if ($metrics['last_observation'] === null) {
            $reasons[] = 'never_observed';
        } elseif (strtotime((string)$metrics['last_observation']) < strtotime("-{$this->freshnessWindowDays} days")) {
            $reasons[] = 'stale_observations';
        }
        if ((int)$metrics['recent_failed_jobs'] > 0) {
            $reasons[] = 'recent_failures';
        }
        return array_values(array_unique($reasons));
    }

    private function hasRecentAttempt(array $metrics): bool
    {
        if ($this->attemptCooldownMinutes <= 0 || $metrics['last_collection_attempt'] === null) {
            return false;
        }
        return strtotime((string)$metrics['last_collection_attempt']) >= strtotime("-{$this->attemptCooldownMinutes} minutes");
    }

    private function priority(array $product, array $metrics, int $sampleGap, int $freshGap, bool $hasActiveJob): int
    {
        if ($hasActiveJob) {
            return 0;
        }

        $priority = (string)$product['category_slug'] === 'gpu' ? 10 : 8;
        if ((int)$product['is_popular'] === 1) {
            $priority += 10;
        }
        $priority += min(35, $sampleGap * 2);
        $priority += min(25, $freshGap * 3);
        if ($metrics['last_observation'] === null) {
            $priority += 20;
        } else {
            $ageDays = (int)floor((time() - strtotime((string)$metrics['last_observation'])) / 86400);
            if ($ageDays > $this->freshnessWindowDays) {
                $priority += min(20, $ageDays - $this->freshnessWindowDays);
            }
        }
        if ((int)$metrics['recent_failed_jobs'] > 0) {
            $priority = max(1, $priority - min(25, (int)$metrics['recent_failed_jobs'] * 8));
        }
        return min(100, max(0, $priority));
    }

    private function queryText(string $productName): string
    {
        return $productName . ' มือสอง';
    }

    private function hasQueuedOrRunningJob(int $productId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM collector_jobs
             WHERE source_id=:source_id
               AND product_id=:product_id
               AND job_type=:job_type
               AND status IN ('queued','running')"
        );
        $stmt->execute(['source_id' => $this->sourceId, 'product_id' => $productId, 'job_type' => $this->jobType]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function summary(array $items): array
    {
        $due = array_values(array_filter($items, static fn(array $item): bool => (bool)$item['due']));
        return [
            'products_scanned' => count($items),
            'due_products' => count($due),
            'blocked_by_active_jobs' => count(array_filter($items, static fn(array $item): bool => in_array('active_job_exists', $item['due_reasons'], true))),
            'total_sample_gap' => array_sum(array_column($items, 'sample_gap')),
            'total_fresh_gap' => array_sum(array_column($items, 'fresh_gap')),
        ];
    }

    private function audit(string $action, string $entityType, ?int $entityId, ?array $before, array $after): void
    {
        $stmt = $this->db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (NULL,:action,:entity_type,:entity_id,:before_data,:after_data)");
        $stmt->execute([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_data' => json_encode($after, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
