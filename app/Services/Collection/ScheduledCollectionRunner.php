<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Contracts\SearchProviderInterface;
use App\Core\Database;
use App\Data\SearchRequest;
use App\Models\CollectionPipeline;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Validation\ObservationValidator;
use PDO;
use Throwable;

final class ScheduledCollectionRunner
{
    private PDO $db;
    private string $runId;
    private CollectionPipeline $pipeline;
    private RuleBasedListingExtractor $extractor;
    private ObservationValidator $validator;
    private CollectionRunRepository $runs;

    public function __construct(
        private SearchProviderInterface $provider,
        private int $sourceId,
        private string $jobType = 'coverage_mock_collection',
        private string $providerName = 'mock'
    ) {
        global $config;
        $this->db = Database::connection($config['db']);
        $this->runId = $this->newRunId();
        $this->pipeline = new CollectionPipeline();
        $this->extractor = new RuleBasedListingExtractor();
        $this->validator = new ObservationValidator(false);
        $this->runs = new CollectionRunRepository();
    }

    public function run(int $limit = 5, ?int $jobId = null, bool $dryRun = false): array
    {
        $this->runId = $this->newRunId();
        $started = microtime(true);
        $startedAt = date('Y-m-d H:i:s');
        $jobs = $this->sourceCanRun() ? $this->queuedJobs($limit, $jobId) : [];
        $summary = [
            'run_id' => $this->runId,
            'dry_run' => $dryRun,
            'provider' => $this->providerName,
            'source_id' => $this->sourceId,
            'source_status' => $this->sourceCanRun() ? 'enabled' : 'paused_or_disabled',
            'provider_requests' => 0,
            'provider_successful_requests' => 0,
            'provider_failed_requests' => 0,
            'configured_cost_state' => $this->providerName === 'mock' ? 'free_fixture' : 'free_tier_or_unknown',
            'api_cost' => 0,
            'jobs_scanned' => count($jobs),
            'jobs_claimed' => 0,
            'jobs_completed' => 0,
            'jobs_failed' => 0,
            'jobs_skipped' => 0,
            'candidates_created' => 0,
            'evidence_created' => 0,
            'extractions_created' => 0,
            'reviews_created' => 0,
            'green' => 0,
            'amber' => 0,
            'red' => 0,
            'duration_ms' => 0,
            'jobs' => [],
        ];

        foreach ($jobs as $job) {
            if ($dryRun) {
                $summary['jobs_skipped']++;
                $summary['jobs'][] = $this->dryRunJobResult($job);
                continue;
            }

            if (!$this->claimJob((int)$job['id'])) {
                $summary['jobs_skipped']++;
                $summary['jobs'][] = [
                    'job_id' => (int)$job['id'],
                    'product_id' => $job['product_id'] === null ? null : (int)$job['product_id'],
                    'status' => 'skipped',
                    'errors' => ['claim_failed_or_not_queued'],
                ];
                continue;
            }

            $summary['jobs_claimed']++;
            $result = $this->processClaimedJob((int)$job['id']);
            $summary['provider_requests']++;
            $summary['jobs'][] = $result;
            if ($result['status'] === 'completed') {
                $summary['jobs_completed']++;
                $summary['provider_successful_requests']++;
            } elseif ($result['status'] === 'failed') {
                $summary['jobs_failed']++;
                $summary['provider_failed_requests']++;
            }

            foreach (['candidates_created', 'evidence_created', 'extractions_created', 'reviews_created', 'green', 'amber', 'red'] as $key) {
                $summary[$key] += (int)$result[$key];
            }
        }

        $summary['duration_ms'] = (int)round((microtime(true) - $started) * 1000);
        $finishedAt = date('Y-m-d H:i:s');
        if (!$dryRun) {
            $this->runs->persist($summary, $this->sourceId, $this->providerName, $this->jobType, 'normal', $startedAt, $finishedAt);
            $this->audit('collection_runner_run', 'collection_run', null, null, $summary);
        }
        return $summary;
    }

    private function newRunId(): string
    {
        return date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    private function queuedJobs(int $limit, ?int $jobId): array
    {
        if ($jobId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM collector_jobs WHERE id=:id AND job_type=:job_type AND source_id=:source_id AND status='queued' LIMIT 1");
            $stmt->execute(['id' => $jobId, 'job_type' => $this->jobType, 'source_id' => $this->sourceId]);
            $job = $stmt->fetch();
            return $job ? [$job] : [];
        }

        $stmt = $this->db->prepare("SELECT * FROM collector_jobs WHERE job_type=:job_type AND source_id=:source_id AND status='queued' ORDER BY id LIMIT :limit");
        $stmt->bindValue(':job_type', $this->jobType);
        $stmt->bindValue(':source_id', $this->sourceId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function claimJob(int $jobId): bool
    {
        $stmt = $this->db->prepare("UPDATE collector_jobs SET status='running',run_id=:run_id,attempt_count=attempt_count+1,started_at=NOW(),updated_at=NOW() WHERE id=:id AND status='queued'");
        $stmt->execute(['id' => $jobId, 'run_id' => $this->runId]);
        return $stmt->rowCount() === 1;
    }

    private function processClaimedJob(int $jobId): array
    {
        $job = $this->findJob($jobId);
        $result = [
            'job_id' => $jobId,
            'product_id' => $job['product_id'] === null ? null : (int)$job['product_id'],
            'status' => 'running',
            'candidates_created' => 0,
            'evidence_created' => 0,
            'extractions_created' => 0,
            'reviews_created' => 0,
            'green' => 0,
            'amber' => 0,
            'red' => 0,
            'skipped_wrong_product' => 0,
            'errors' => [],
        ];

        try {
            if (!$job || $job['product_id'] === null) {
                throw new \RuntimeException('Job has no product_id');
            }
            if (str_contains((string)$job['query_text'], '__fail__')) {
                throw new \RuntimeException('Controlled mock provider failure');
            }

            $product = $this->findProduct((int)$job['product_id']);
            if (!$product) {
                throw new \RuntimeException('Product not found for job');
            }

            $resolver = new ProductResolver($this->pipeline->catalog());
            $search = $this->provider->search(new SearchRequest($this->queryForJob($job, $product), (int)$job['product_id'], 20));

            foreach ($search->candidates as $candidate) {
                $extraction = $this->extractor->extract($candidate);
                $resolution = $resolver->resolve($candidate->title);
                if ($resolution->productId !== (int)$job['product_id']) {
                    $result['skipped_wrong_product']++;
                    continue;
                }

                $validation = $this->validator->validate($extraction, $resolution);
                $created = $this->pipeline->persistCandidate($this->sourceId, $jobId, $candidate, $extraction, $resolution, $validation);
                if ($created) {
                    $result['candidates_created']++;
                    $result['evidence_created']++;
                    $result['extractions_created']++;
                    $result['reviews_created']++;
                }
                $result[$validation->lane]++;
            }

            $result['status'] = 'completed';
            $this->completeJob($jobId, $result);
            return $result;
        } catch (Throwable $e) {
            $result['status'] = 'failed';
            $result['errors'][] = $this->safeError($e->getMessage());
            $this->failJob($jobId, $result['errors'][0], $result);
            return $result;
        }
    }

    private function dryRunJobResult(array $job): array
    {
        return [
            'job_id' => (int)$job['id'],
            'product_id' => $job['product_id'] === null ? null : (int)$job['product_id'],
            'status' => 'dry_run',
            'candidates_created' => 0,
            'evidence_created' => 0,
            'extractions_created' => 0,
            'reviews_created' => 0,
            'green' => 0,
            'amber' => 0,
            'red' => 0,
            'errors' => [],
        ];
    }

    private function findJob(int $jobId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM collector_jobs WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $jobId]);
        return $stmt->fetch() ?: null;
    }

    private function findProduct(int $productId): ?array
    {
        $stmt = $this->db->prepare("SELECT id,full_name FROM products WHERE id=:id AND is_active=1 LIMIT 1");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetch() ?: null;
    }

    private function queryForJob(array $job, array $product): string
    {
        if ($this->jobType === 'live_provider_poc' && !empty($job['query_text'])) {
            return (string)$job['query_text'];
        }
        $alias = $this->db->prepare("SELECT alias_text FROM product_aliases WHERE product_id=:product_id ORDER BY CHAR_LENGTH(alias_text) ASC LIMIT 1");
        $alias->execute(['product_id' => (int)$job['product_id']]);
        return (string)($alias->fetchColumn() ?: $job['query_text'] ?: $product['full_name']);
    }

    private function completeJob(int $jobId, array $result): void
    {
        $stmt = $this->db->prepare("UPDATE collector_jobs SET status='completed',completed_at=NOW(),raw_count=:raw_count,valid_count=:valid_count,error_message=:summary,updated_at=NOW() WHERE id=:id");
        $stmt->execute([
            'raw_count' => (int)$result['candidates_created'],
            'valid_count' => (int)$result['green'] + (int)$result['amber'],
            'summary' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'id' => $jobId,
        ]);
        $this->db->prepare("UPDATE data_sources SET last_success_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id' => $this->sourceId]);
        $this->audit('collection_job_completed', 'collector_job', $jobId, null, $result);
    }

    private function failJob(int $jobId, string $error, array $result): void
    {
        $stmt = $this->db->prepare("UPDATE collector_jobs SET status='failed',completed_at=NOW(),error_message=:error,updated_at=NOW() WHERE id=:id");
        $stmt->execute(['error' => $error, 'id' => $jobId]);
        $this->db->prepare("UPDATE data_sources SET last_failure_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id' => $this->sourceId]);
        $this->recordProviderIncident($error);
        $this->audit('collection_job_failed', 'collector_job', $jobId, null, $result);
    }

    private function recordProviderIncident(string $error): void
    {
        if ($this->providerName === 'mock') {
            return;
        }
        $type = match ($error) {
            'RATE_LIMITED' => 'rate_limited',
            'AUTHENTICATION_FAILURE', 'MISSING_API_KEY' => 'authentication_required',
            'MALFORMED_RESPONSE' => 'source_structure_changed',
            default => 'other',
        };
        (new SourceOperationsService())->recordIncident($this->sourceId, $type, $error, 'monitor');
    }

    private function sourceCanRun(): bool
    {
        $stmt = $this->db->prepare("SELECT is_active,is_paused FROM data_sources WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $this->sourceId]);
        $source = $stmt->fetch();
        if (!$source) {
            return false;
        }
        return (int)$source['is_active'] === 1 && (int)($source['is_paused'] ?? 0) === 0;
    }

    private function safeError(string $message): string
    {
        return substr(preg_replace('/[\\r\\n]+/', ' ', $message) ?? 'Unknown error', 0, 500);
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
