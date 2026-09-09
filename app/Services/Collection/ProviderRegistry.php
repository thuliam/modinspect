<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Contracts\SearchProviderInterface;
use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\Providers\GeminiSearchProvider;
use App\Services\Providers\MockSearchProvider;
use PDO;

final class ProviderRegistry
{
    private PDO $db;

    public function __construct(private array $collectionConfig)
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function provider(string $key): SearchProviderInterface
    {
        return match ($key) {
            'mock' => new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json'),
            'gemini' => new GeminiSearchProvider(
                (string)($this->collectionConfig['providers']['gemini']['api_key'] ?? ''),
                (string)($this->collectionConfig['providers']['gemini']['model'] ?? 'gemini-1.5-flash'),
                (string)($this->collectionConfig['providers']['gemini']['endpoint'] ?? ''),
                (int)($this->collectionConfig['providers']['gemini']['request_timeout_seconds'] ?? 10)
            ),
            default => throw new \InvalidArgumentException('Unknown provider'),
        };
    }

    public function sourceId(string $key): ?int
    {
        if ($key === 'mock') {
            return (new CollectionPipeline())->ensureMockSource();
        }
        $sourceKey = $this->collectionConfig['providers'][$key]['source_key'] ?? null;
        if (!$sourceKey) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT id FROM data_sources WHERE source_key=:source_key LIMIT 1");
        $stmt->execute(['source_key' => $sourceKey]);
        $id = $stmt->fetchColumn();
        if (!$id && $key === 'gemini') {
            $this->ensureGeminiSource($sourceKey);
            $stmt->execute(['source_key' => $sourceKey]);
            $id = $stmt->fetchColumn();
        }
        return $id ? (int)$id : null;
    }

    public function preflight(string $key, int $requestedRequests, int $productCount): array
    {
        $sourceId = $this->sourceId($key);
        if ($sourceId === null) {
            return [
                'provider' => $key,
                'adapter_loaded' => $this->adapterExists($key),
                'source_id' => null,
                'live_execution_allowed' => false,
                'blockers' => ['SOURCE_MISSING'],
            ];
        }
        $guard = new ProviderRequestGuard($this->collectionConfig);
        return ['adapter_loaded' => $this->adapterExists($key)] + $guard->evaluate($key, $sourceId, $requestedRequests, $productCount);
    }

    private function adapterExists(string $key): bool
    {
        return in_array($key, ['mock', 'gemini'], true);
    }

    private function ensureGeminiSource(string $sourceKey): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO data_sources
             (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,request_budget_per_day,cost_budget_per_day,terms_note,policy_note,is_active,is_paused)
             VALUES
             (:source_key,'Gemini Google Search POC','googleapis.com','research','api','api','medium',60,'medium',7,3,NULL,'Provider source is inert until live and provider guards pass.','Phase 2D-A POC source; no bypass behavior allowed.',1,0)"
        );
        $stmt->execute(['source_key' => $sourceKey]);
    }
}
