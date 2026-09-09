<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use PDO;

final class LiveCollectionPocService
{
    private PDO $db;
    private ProviderRegistry $registry;
    private LiveQueryPlanner $queryPlanner;

    public function __construct(private array $collectionConfig)
    {
        global $config;
        $this->db = Database::connection($config['db']);
        $this->registry = new ProviderRegistry($collectionConfig);
        $this->queryPlanner = new LiveQueryPlanner();
    }

    public function preflight(string $provider, array $productIds, int $maxRequests): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $queries = $this->queries($productIds, $maxRequests);
        $preflight = $this->registry->preflight($provider, count($queries), count($productIds));
        return [
            'provider' => $provider,
            'adapter_loaded' => $preflight['adapter_loaded'] ?? false,
            'live_collection_enabled' => $preflight['live_collection_enabled'] ?? false,
            'api_key_present' => $preflight['api_key_present'] ?? false,
            'source_id' => $preflight['source_id'] ?? null,
            'source_enabled' => $preflight['source_enabled'] ?? false,
            'source_paused' => $preflight['source_paused'] ?? true,
            'max_requests' => $maxRequests,
            'provider_max_requests' => $preflight['max_requests'] ?? 0,
            'paid_calls_enabled' => $preflight['paid_calls_enabled'] ?? false,
            'query_version' => LiveQueryPlanner::VERSION,
            'queries' => $queries,
            'live_execution_allowed' => $preflight['live_execution_allowed'] ?? false,
            'blockers' => $preflight['blockers'] ?? [],
        ];
    }

    public function dryRun(string $provider, array $productIds, int $maxRequests): array
    {
        return ['mode' => 'dry_run', 'network_requests_made' => 0] + $this->preflight($provider, $productIds, $maxRequests);
    }

    public function execute(string $provider, array $productIds, int $maxRequests): array
    {
        $preflight = $this->preflight($provider, $productIds, $maxRequests);
        if (!($preflight['live_execution_allowed'] ?? false)) {
            return ['mode' => 'refused', 'network_requests_made' => 0] + $preflight;
        }

        $sourceId = (int)$preflight['source_id'];
        $created = [];
        foreach ($preflight['queries'] as $query) {
            $stmt = $this->db->prepare(
                "INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at)
                 VALUES (:source_id,'live_provider_poc',:product_id,:query_text,'queued',NOW(),NOW())"
            );
            $stmt->execute([
                'source_id' => $sourceId,
                'product_id' => $query['product_id'],
                'query_text' => $query['query'],
            ]);
            $created[] = ['job_id' => (int)$this->db->lastInsertId()] + $query;
        }

        $runner = new ScheduledCollectionRunner($this->registry->provider($provider), $sourceId, 'live_provider_poc', $provider);
        return [
            'mode' => 'live_execution',
            'created_jobs' => $created,
            'runner' => $runner->run($maxRequests),
        ] + $preflight;
    }

    private function queries(array $productIds, int $maxRequests): array
    {
        $queries = [];
        foreach ($productIds as $productId) {
            $product = $this->queryPlanner->product($productId);
            foreach ($this->queryPlanner->queriesForProduct($productId, 3) as $query) {
                $queries[] = [
                    'product_id' => $productId,
                    'product_name' => $product['full_name'] ?? null,
                    'query' => $query,
                    'query_version' => LiveQueryPlanner::VERSION,
                ];
                if (count($queries) >= $maxRequests) {
                    return $queries;
                }
            }
        }
        return $queries;
    }
}
