<?php
declare(strict_types=1);

namespace App\Services\Pricing;

use App\Core\Database;
use PDO;

final class PriceSnapshotService
{
    public const FORMULA_VERSION = 'price-engine-v1';
    public const COHORT_VERSION = 'cohort-v1';
    public const QUARTILE_METHOD_VERSION = 'linear-percentile-v1';
    public const CONFIDENCE_METHOD_VERSION = SnapshotConfidenceService::METHOD_VERSION;

    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function recalculateAll(string $priceType = 'asking'): array
    {
        $products = $this->db->query("SELECT id FROM products WHERE is_active=1 ORDER BY id")->fetchAll();
        $results = [];
        foreach ($products as $product) {
            $snapshot = $this->recalculateProduct((int)$product['id'], $priceType);
            if ($snapshot !== null) {
                $results[] = $snapshot;
            }
        }
        return $results;
    }

    public function recalculateProduct(int $productId, string $priceType = 'asking'): ?array
    {
        $cohort = $this->buildCohort($productId, $priceType);
        $rows = $cohort['included'];

        if (count($rows) < 3) {
            return null;
        }

        $snapshot = $this->calculateSnapshot($productId, $priceType, $rows, $cohort);
        $snapshotId = $this->persistSnapshot($snapshot, $cohort);
        $snapshot['snapshot_id'] = $snapshotId;
        return $snapshot;
    }

    public function reproduceSnapshot(int $snapshotId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM price_indices WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $snapshotId]);
        $stored = $stmt->fetch();
        if (!$stored) {
            return ['snapshot_id' => $snapshotId, 'result' => 'NOT_FOUND'];
        }

        if (($stored['provenance_status'] ?? '') !== 'recorded') {
            return [
                'snapshot_id' => $snapshotId,
                'formula_version' => $stored['formula_version'] ?? 'legacy-unversioned',
                'provenance_status' => $stored['provenance_status'] ?? 'legacy_unavailable',
                'result' => 'LEGACY_PROVENANCE_UNAVAILABLE',
            ];
        }

        $members = $this->snapshotMembers($snapshotId);
        $included = array_values(array_filter($members, static fn(array $row): bool => $row['inclusion_status'] === 'included'));
        usort($included, static fn(array $a, array $b): int => (int)$a['observation_id'] <=> (int)$b['observation_id']);
        $prices = array_map(static fn(array $row): float => (float)$row['calculation_price'], $included);
        sort($prices);

        $sampleSize = count($prices);
        $q1 = $sampleSize > 0 ? $this->percentile($prices, 0.25) : null;
        $median = $sampleSize > 0 ? $this->percentile($prices, 0.50) : null;
        $q3 = $sampleSize > 0 ? $this->percentile($prices, 0.75) : null;

        $manifest = json_decode((string)($stored['calculation_manifest'] ?? '{}'), true) ?: [];
        $confidence = $manifest['confidence'] ?? [];
        $hash = $this->calculationHash([
            'formula_version' => (string)$stored['formula_version'],
            'cohort_version' => (string)$stored['cohort_version'],
            'quartile_method_version' => (string)$stored['quartile_method_version'],
            'confidence_method_version' => (string)$stored['confidence_method_version'],
            'price_type' => (string)$stored['price_type'],
            'included' => array_map(static fn(array $row): array => [
                'observation_id' => (int)$row['observation_id'],
                'price' => (float)$row['calculation_price'],
                'observed_at' => (string)$row['snapshot_observed_at'],
            ], $included),
            'sample_size' => $sampleSize,
            'q1' => $q1,
            'median' => $median,
            'q3' => $q3,
            'confidence' => $confidence,
        ]);

        $valuesMatch = $sampleSize === (int)$stored['valid_sample_size']
            && (float)$stored['q1'] === (float)$q1
            && (float)$stored['median'] === (float)$median
            && (float)$stored['q3'] === (float)$q3;
        $confidenceMatch = isset($confidence['overall_score'], $confidence['label'])
            && (float)$stored['confidence_score'] === (float)$confidence['overall_score']
            && (string)$stored['confidence_label'] === (string)$confidence['label'];
        $hashMatch = hash_equals((string)$stored['calculation_hash'], $hash);

        return [
            'snapshot_id' => $snapshotId,
            'formula_version' => (string)$stored['formula_version'],
            'cohort_version' => (string)$stored['cohort_version'],
            'included_observations' => array_map(static fn(array $row): int => (int)$row['observation_id'], $included),
            'stored' => [
                'sample_size' => (int)$stored['valid_sample_size'],
                'q1' => (float)$stored['q1'],
                'median' => (float)$stored['median'],
                'q3' => (float)$stored['q3'],
                'confidence_score' => (float)$stored['confidence_score'],
                'confidence_label' => (string)$stored['confidence_label'],
                'calculation_hash' => (string)$stored['calculation_hash'],
            ],
            'recalculated' => [
                'sample_size' => $sampleSize,
                'q1' => $q1,
                'median' => $median,
                'q3' => $q3,
                'confidence_score' => isset($confidence['overall_score']) ? (float)$confidence['overall_score'] : null,
                'confidence_label' => isset($confidence['label']) ? (string)$confidence['label'] : null,
                'confidence_components' => $confidence['components'] ?? null,
                'calculation_hash' => $hash,
            ],
            'manifest_observation_count' => count($manifest['considered_observation_ids'] ?? []),
            'hash_match' => $hashMatch,
            'values_match' => $valuesMatch,
            'confidence_match' => $confidenceMatch,
            'result' => ($hashMatch && $valuesMatch && $confidenceMatch) ? 'MATCH' : 'MISMATCH',
        ];
    }

    private function buildCohort(int $productId, string $priceType): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.id observation_id,COALESCE(o.price_value,o.asking_price) calculation_price,o.asking_price,o.price_value,o.observed_at,o.product_variant_id,o.verified_status,o.price_type,o.listing_type,o.is_deposit,o.is_defective,o.is_duplicate,o.quality_flags,o.final_weight,r.source_id,s.source_key,s.name source_name,s.domain source_domain
             ,o.evidence_level,s.evidence_quality source_evidence_quality
             FROM price_observations o
             LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
             LEFT JOIN data_sources s ON s.id=r.source_id
             WHERE o.product_id=:product_id
             ORDER BY o.id"
        );
        $stmt->execute(['product_id' => $productId]);
        $considered = $stmt->fetchAll();

        $included = [];
        $excluded = [];
        foreach ($considered as $row) {
            $reason = $this->exclusionReason($row, $priceType);
            if ($reason === null) {
                $included[] = $row;
            } else {
                $row['exclusion_reason'] = $reason;
                $excluded[] = $row;
            }
        }

        usort($included, static function (array $a, array $b): int {
            $priceCompare = (float)$a['calculation_price'] <=> (float)$b['calculation_price'];
            return $priceCompare !== 0 ? $priceCompare : (int)$a['observation_id'] <=> (int)$b['observation_id'];
        });

        return ['considered' => $considered, 'included' => $included, 'excluded' => $excluded];
    }

    private function exclusionReason(array $row, string $priceType): ?string
    {
        if (($row['verified_status'] ?? '') !== 'approved') {
            return 'NOT_ACCEPTED';
        }
        if (($row['price_type'] ?? '') !== $priceType) {
            return 'WRONG_PRICE_TYPE';
        }
        if ($row['product_variant_id'] !== null) {
            return 'OUTSIDE_COHORT';
        }
        if (($row['listing_type'] ?? '') !== 'single_item') {
            return 'OUTSIDE_COHORT';
        }
        $qualityFlags = json_decode((string)($row['quality_flags'] ?? '[]'), true);
        $qualityFlags = is_array($qualityFlags) ? array_map('strval', $qualityFlags) : [];
        foreach (['WRONG_PRODUCT', 'MOBILE_GPU', 'WANTED', 'WHOLE_PC', 'BUNDLE', 'DEPOSIT', 'DEFECTIVE'] as $flag) {
            if (in_array($flag, $qualityFlags, true)) {
                return $flag;
            }
        }
        if ((float)$row['calculation_price'] <= 0) {
            return 'INVALID_PRICE';
        }
        if ((int)$row['is_deposit'] === 1) {
            return 'DEPOSIT';
        }
        if ((int)$row['is_defective'] === 1) {
            return 'DEFECTIVE';
        }
        if ((int)$row['is_duplicate'] === 1) {
            return 'DUPLICATE';
        }
        if ($this->isMockSource($row)) {
            return 'MOCK_SOURCE';
        }
        return null;
    }

    private function isMockSource(array $row): bool
    {
        $sourceKey = strtolower((string)($row['source_key'] ?? ''));
        $sourceName = strtolower((string)($row['source_name'] ?? ''));
        $sourceDomain = strtolower((string)($row['source_domain'] ?? ''));
        return str_contains($sourceKey, 'mock') || str_contains($sourceName, 'mock') || str_starts_with($sourceDomain, 'mock.');
    }

    private function calculateSnapshot(int $productId, string $priceType, array $rows, array $cohort): array
    {
        $prices = array_map(static fn(array $row): float => (float)$row['calculation_price'], $rows);
        sort($prices);
        $sampleSize = count($prices);
        $asOfDate = date('Y-m-d');
        $confidence = (new SnapshotConfidenceService())->calculate($rows, $priceType, $asOfDate);
        $freshRatio = (float)$confidence['components']['freshness']['fresh_ratio'];

        $snapshot = [
            'product_id' => $productId,
            'price_type' => $priceType,
            'price_low' => min($prices),
            'q1' => $this->percentile($prices, 0.25),
            'median' => $this->percentile($prices, 0.50),
            'q3' => $this->percentile($prices, 0.75),
            'price_high' => max($prices),
            'sample_size' => $sampleSize,
            'valid_sample_size' => $sampleSize,
            'fresh_sample_ratio' => $freshRatio,
            'confidence_score' => (float)$confidence['overall_score'],
            'confidence_label' => (string)$confidence['label'],
            'formula_version' => self::FORMULA_VERSION,
            'cohort_version' => self::COHORT_VERSION,
            'quartile_method_version' => self::QUARTILE_METHOD_VERSION,
            'confidence_method_version' => self::CONFIDENCE_METHOD_VERSION,
            'confidence' => $confidence,
        ];

        $manifest = $this->manifest($snapshot, $cohort);
        $snapshot['calculation_manifest'] = $manifest;
        $snapshot['calculation_hash'] = $this->calculationHash([
            'formula_version' => $snapshot['formula_version'],
            'cohort_version' => $snapshot['cohort_version'],
            'quartile_method_version' => $snapshot['quartile_method_version'],
            'confidence_method_version' => $snapshot['confidence_method_version'],
            'price_type' => $snapshot['price_type'],
            'included' => $this->hashRows($cohort['included'], 'calculation_price', 'observed_at'),
            'sample_size' => $snapshot['valid_sample_size'],
            'q1' => $snapshot['q1'],
            'median' => $snapshot['median'],
            'q3' => $snapshot['q3'],
            'confidence' => $snapshot['confidence'],
        ]);
        return $snapshot;
    }

    private function manifest(array $snapshot, array $cohort): array
    {
        return [
            'formula_version' => $snapshot['formula_version'],
            'product_id' => $snapshot['product_id'],
            'cohort_version' => $snapshot['cohort_version'],
            'quartile_method_version' => $snapshot['quartile_method_version'],
            'confidence_method_version' => $snapshot['confidence_method_version'],
            'price_type' => $snapshot['price_type'],
            'considered_observation_ids' => array_map(static fn(array $row): int => (int)$row['observation_id'], $cohort['considered']),
            'included_observation_ids' => array_map(static fn(array $row): int => (int)$row['observation_id'], $cohort['included']),
            'excluded' => array_map(static fn(array $row): array => [
                'observation_id' => (int)$row['observation_id'],
                'reason' => (string)$row['exclusion_reason'],
            ], $cohort['excluded']),
            'sample_size' => $snapshot['valid_sample_size'],
            'q1' => $snapshot['q1'],
            'median' => $snapshot['median'],
            'q3' => $snapshot['q3'],
            'confidence' => $snapshot['confidence'],
        ];
    }

    private function calculationHash(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map(fn(mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }

    private function hashRows(array $rows, string $priceKey, string $observedAtKey): array
    {
        usort($rows, static fn(array $a, array $b): int => (int)$a['observation_id'] <=> (int)$b['observation_id']);
        return array_map(static fn(array $row): array => [
            'observation_id' => (int)$row['observation_id'],
            'price' => (float)$row[$priceKey],
            'observed_at' => (string)$row[$observedAtKey],
        ], $rows);
    }

    private function percentile(array $values, float $percentile): float
    {
        $count = count($values);
        if ($count === 1) {
            return round($values[0], 2);
        }
        $position = ($count - 1) * $percentile;
        $lower = (int)floor($position);
        $upper = (int)ceil($position);
        if ($lower === $upper) {
            return round($values[$lower], 2);
        }
        $weight = $position - $lower;
        return round($values[$lower] * (1 - $weight) + $values[$upper] * $weight, 2);
    }

    private function persistSnapshot(array $snapshot, array $cohort): int
    {
        $insert = $this->db->prepare(
            "INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,fast_sale_min,fast_sale_max,market_min,market_max,premium_min,premium_max,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,formula_version,cohort_version,quartile_method_version,confidence_method_version,calculation_hash,calculation_manifest,provenance_status,last_calculated_at)
             VALUES (:product_id,:price_type,:price_low,:q1,:median,:q3,:price_high,:fast_sale_min,:fast_sale_max,:market_min,:market_max,:premium_min,:premium_max,:sample_size,:valid_sample_size,:fresh_sample_ratio,:confidence_score,:confidence_label,:formula_version,:cohort_version,:quartile_method_version,:confidence_method_version,:calculation_hash,:calculation_manifest,'recorded',NOW())"
        );
        $insert->execute([
            'product_id' => $snapshot['product_id'],
            'price_type' => $snapshot['price_type'],
            'price_low' => $snapshot['price_low'],
            'q1' => $snapshot['q1'],
            'median' => $snapshot['median'],
            'q3' => $snapshot['q3'],
            'price_high' => $snapshot['price_high'],
            'fast_sale_min' => $snapshot['price_low'],
            'fast_sale_max' => $snapshot['q1'],
            'market_min' => $snapshot['q1'],
            'market_max' => $snapshot['q3'],
            'premium_min' => $snapshot['q3'],
            'premium_max' => $snapshot['price_high'],
            'sample_size' => $snapshot['sample_size'],
            'valid_sample_size' => $snapshot['valid_sample_size'],
            'fresh_sample_ratio' => $snapshot['fresh_sample_ratio'],
            'confidence_score' => $snapshot['confidence_score'],
            'confidence_label' => $snapshot['confidence_label'],
            'formula_version' => $snapshot['formula_version'],
            'cohort_version' => $snapshot['cohort_version'],
            'quartile_method_version' => $snapshot['quartile_method_version'],
            'confidence_method_version' => $snapshot['confidence_method_version'],
            'calculation_hash' => $snapshot['calculation_hash'],
            'calculation_manifest' => json_encode($snapshot['calculation_manifest'], JSON_UNESCAPED_UNICODE),
        ]);
        $snapshotId = (int)$this->db->lastInsertId();

        $this->persistMembership($snapshotId, $cohort);

        $history = $this->db->prepare(
            "INSERT INTO price_histories (product_id,q1,median,q3,sample_size,confidence_score,snapshot_date)
             VALUES (:product_id,:q1,:median,:q3,:sample_size,:confidence_score,CURDATE())
             ON DUPLICATE KEY UPDATE q1=VALUES(q1), median=VALUES(median), q3=VALUES(q3), sample_size=VALUES(sample_size), confidence_score=VALUES(confidence_score)"
        );
        $history->execute([
            'product_id' => $snapshot['product_id'],
            'q1' => $snapshot['q1'],
            'median' => $snapshot['median'],
            'q3' => $snapshot['q3'],
            'sample_size' => $snapshot['valid_sample_size'],
            'confidence_score' => $snapshot['confidence_score'],
        ]);

        return $snapshotId;
    }

    private function persistMembership(int $snapshotId, array $cohort): void
    {
        $includedIds = [];
        foreach ($cohort['included'] as $row) {
            $includedIds[(int)$row['observation_id']] = true;
        }
        $excludedReasons = [];
        foreach ($cohort['excluded'] as $row) {
            $excludedReasons[(int)$row['observation_id']] = (string)$row['exclusion_reason'];
        }

        $insert = $this->db->prepare(
            "INSERT INTO price_snapshot_observations (snapshot_id,observation_id,inclusion_status,exclusion_reason,calculation_price,snapshot_verified_status,snapshot_price_type,snapshot_listing_type,snapshot_source_id,snapshot_observed_at,weight)
             VALUES (:snapshot_id,:observation_id,:inclusion_status,:exclusion_reason,:calculation_price,:snapshot_verified_status,:snapshot_price_type,:snapshot_listing_type,:snapshot_source_id,:snapshot_observed_at,:weight)"
        );

        foreach ($cohort['considered'] as $row) {
            $observationId = (int)$row['observation_id'];
            $included = isset($includedIds[$observationId]);
            $insert->execute([
                'snapshot_id' => $snapshotId,
                'observation_id' => $observationId,
                'inclusion_status' => $included ? 'included' : 'excluded',
                'exclusion_reason' => $included ? null : ($excludedReasons[$observationId] ?? 'OTHER_DETERMINISTIC_RULE'),
                'calculation_price' => $included ? (float)$row['calculation_price'] : null,
                'snapshot_verified_status' => (string)$row['verified_status'],
                'snapshot_price_type' => (string)$row['price_type'],
                'snapshot_listing_type' => (string)$row['listing_type'],
                'snapshot_source_id' => $row['source_id'] === null ? null : (int)$row['source_id'],
                'snapshot_observed_at' => (string)$row['observed_at'],
                'weight' => $included ? (float)$row['final_weight'] : null,
            ]);
        }
    }

    private function snapshotMembers(int $snapshotId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM price_snapshot_observations WHERE snapshot_id=:snapshot_id ORDER BY observation_id");
        $stmt->execute(['snapshot_id' => $snapshotId]);
        return $stmt->fetchAll();
    }
}
