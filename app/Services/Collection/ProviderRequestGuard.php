<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use PDO;

final class ProviderRequestGuard
{
    private PDO $db;

    public function __construct(private array $collectionConfig)
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function evaluate(string $providerKey, int $sourceId, int $requestedRequests, int $productCount, int $runProductLimit = 3): array
    {
        $provider = $this->collectionConfig['providers'][$providerKey] ?? null;
        $source = $this->source($sourceId);
        $blockers = [];

        if (!($this->collectionConfig['live_collection_enabled'] ?? false)) {
            $blockers[] = 'LIVE_COLLECTION_DISABLED';
        }
        if (!$provider || !($provider['enabled'] ?? false)) {
            $blockers[] = 'PROVIDER_DISABLED';
        }
        if (!$source || (int)$source['is_active'] !== 1) {
            $blockers[] = 'SOURCE_DISABLED';
        }
        if ($source && (int)($source['is_paused'] ?? 0) === 1) {
            $blockers[] = 'SOURCE_PAUSED';
        }
        if (($provider['api_key'] ?? '') === '') {
            $blockers[] = 'MISSING_API_KEY';
        }
        if ($requestedRequests > (int)($provider['max_requests_per_run'] ?? 1)) {
            $blockers[] = 'REQUEST_LIMIT_REACHED';
        }
        if ($productCount > $runProductLimit) {
            $blockers[] = 'PRODUCT_LIMIT_REACHED';
        }
        if ($providerKey !== 'mock' && ($this->collectionConfig['paid_provider_calls_enabled'] ?? false)) {
            $blockers[] = 'PAID_CALL_BLOCKED';
        }

        return [
            'provider' => $providerKey,
            'source_id' => $sourceId,
            'requested_requests' => $requestedRequests,
            'product_count' => $productCount,
            'max_requests' => (int)($provider['max_requests_per_run'] ?? 0),
            'run_product_limit' => $runProductLimit,
            'live_collection_enabled' => (bool)($this->collectionConfig['live_collection_enabled'] ?? false),
            'provider_enabled' => (bool)($provider['enabled'] ?? false),
            'source_enabled' => $source ? (int)$source['is_active'] === 1 : false,
            'source_paused' => $source ? (int)($source['is_paused'] ?? 0) === 1 : true,
            'api_key_present' => ($provider['api_key'] ?? '') !== '',
            'paid_calls_enabled' => (bool)($this->collectionConfig['paid_provider_calls_enabled'] ?? false),
            'live_execution_allowed' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    private function source(int $sourceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM data_sources WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $sourceId]);
        return $stmt->fetch() ?: null;
    }
}
