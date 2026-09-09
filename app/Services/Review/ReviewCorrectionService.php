<?php
declare(strict_types=1);

namespace App\Services\Review;

use App\Core\Database;
use PDO;

final class ReviewCorrectionService
{
    private const MAX_PRICE = 10000000.0;
    private const PRICE_TYPES = ['asking', 'sold', 'trade_in', 'new'];
    private const LISTING_TYPES = ['single_item', 'bundle', 'whole_pc', 'wanted', 'unknown'];
    private const CONDITIONS = ['new', 'like_new', 'good', 'fair', 'poor', 'unknown'];
    private const CORRECTABLE_FIELDS = [
        'product_id',
        'price',
        'price_type',
        'listing_type',
        'condition_level',
        'warranty_months',
        'observed_at',
    ];

    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function correct(int $observationId, string $field, mixed $value, string $reason, ?int $actorId = null): array
    {
        $field = trim($field);
        $reason = trim($reason);
        if (!in_array($field, self::CORRECTABLE_FIELDS, true)) {
            return $this->error('UNSUPPORTED_FIELD');
        }
        if ($reason === '') {
            return $this->error('CORRECTION_REASON_REQUIRED');
        }

        $observation = $this->observation($observationId);
        if (!$observation) {
            return $this->error('OBSERVATION_NOT_FOUND');
        }
        if (($observation['verified_status'] ?? '') !== 'pending') {
            return $this->error('OBSERVATION_NOT_PENDING');
        }

        $normalized = $this->normalize($field, $value);
        if (!$normalized['valid']) {
            return $this->error($normalized['error']);
        }

        $original = $this->fieldValue($observation, $field);
        if ((string)$original === (string)$normalized['value']) {
            return $this->error('CORRECTION_UNCHANGED');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO review_corrections (price_observation_id,field_name,original_value,corrected_value,reason,corrected_by,corrected_at)
             VALUES (:observation_id,:field_name,:original_value,:corrected_value,:reason,:corrected_by,NOW())
             ON DUPLICATE KEY UPDATE corrected_value=VALUES(corrected_value),reason=VALUES(reason),corrected_by=VALUES(corrected_by),corrected_at=NOW(),updated_at=NOW()"
        );
        $stmt->execute([
            'observation_id' => $observationId,
            'field_name' => $field,
            'original_value' => $original === null ? null : (string)$original,
            'corrected_value' => (string)$normalized['value'],
            'reason' => $reason,
            'corrected_by' => $actorId,
        ]);

        $this->audit('review_correction_saved', 'price_observation', $observationId, ['field' => $field, 'original' => $original], ['field' => $field, 'corrected' => $normalized['value'], 'reason' => $reason]);

        return ['ok' => true, 'field' => $field, 'value' => $normalized['value']];
    }

    public function correctionsFor(int $observationId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM review_corrections WHERE price_observation_id=:id ORDER BY field_name");
        $stmt->execute(['id' => $observationId]);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[(string)$row['field_name']] = $row;
        }
        return $items;
    }

    public function finalValues(array $observation): array
    {
        $values = [
            'product_id' => (int)$observation['product_id'],
            'price' => $observation['price_value'] ?? $observation['asking_price'],
            'price_type' => (string)$observation['price_type'],
            'listing_type' => (string)$observation['listing_type'],
            'condition_level' => (string)$observation['condition_level'],
            'warranty_months' => $observation['warranty_months'] === null ? null : (int)$observation['warranty_months'],
            'observed_at' => (string)$observation['observed_at'],
        ];

        foreach ($this->correctionsFor((int)$observation['id']) as $field => $correction) {
            $normalized = $this->normalize($field, $correction['corrected_value']);
            if ($normalized['valid']) {
                $values[$field] = $normalized['value'];
            }
        }

        return $values;
    }

    public function validateFinalValues(array $observation, array $values): array
    {
        $errors = [];
        if (!$this->productExists((int)$values['product_id'])) {
            $errors[] = 'INVALID_PRODUCT';
        }
        if (!$this->validPrice($values['price'])) {
            $errors[] = 'INVALID_PRICE';
        }
        if (!in_array((string)$values['price_type'], self::PRICE_TYPES, true)) {
            $errors[] = 'INVALID_PRICE_TYPE';
        }
        if (!in_array((string)$values['listing_type'], self::LISTING_TYPES, true)) {
            $errors[] = 'INVALID_LISTING_TYPE';
        }
        if (!in_array((string)$values['condition_level'], self::CONDITIONS, true)) {
            $errors[] = 'INVALID_CONDITION';
        }
        if ($values['warranty_months'] !== null && ((int)$values['warranty_months'] < 0 || (int)$values['warranty_months'] > 120)) {
            $errors[] = 'INVALID_WARRANTY';
        }
        if (!$this->validObservedAt((string)$values['observed_at'])) {
            $errors[] = 'INVALID_OBSERVED_AT';
        }
        if ($this->duplicateApprovedLineage((int)$observation['id'], $observation['raw_observation_id'] === null ? null : (int)$observation['raw_observation_id'])) {
            $errors[] = 'DUPLICATE_ACCEPTED_LINEAGE';
        }

        return $errors;
    }

    public function qualityFlags(array $observation, array $values): array
    {
        $flags = [];
        if (!$this->validPrice($values['price'])) $flags[] = 'MISSING_PRICE';
        if (($observation['source_url_encrypted'] ?? null) === null || (string)$observation['source_url_encrypted'] === '') $flags[] = 'MISSING_URL';
        if ((float)($observation['classification_confidence'] ?? 0) < 90.0) $flags[] = 'WEAK_EVIDENCE';
        if (($values['listing_type'] ?? '') === 'bundle') $flags[] = 'BUNDLE';
        if (($values['listing_type'] ?? '') === 'whole_pc') $flags[] = 'WHOLE_PC';
        if ((int)($observation['is_deposit'] ?? 0) === 1) $flags[] = 'DEPOSIT';
        if ((int)($observation['is_defective'] ?? 0) === 1) $flags[] = 'DEFECTIVE';
        if ((int)($observation['is_duplicate'] ?? 0) === 1) $flags[] = 'DUPLICATE';
        if ($this->isStale((string)$values['observed_at'])) $flags[] = 'STALE';
        if ($this->isMockSource($observation)) $flags[] = 'MOCK_SOURCE';
        return array_values(array_unique($flags));
    }

    public function applyApprovedValues(int $observationId): array
    {
        $observation = $this->observation($observationId);
        if (!$observation) {
            return $this->error('OBSERVATION_NOT_FOUND');
        }
        $values = $this->finalValues($observation);
        $errors = $this->validateFinalValues($observation, $values);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'values' => $values];
        }

        $flags = $this->qualityFlags($observation, $values);
        $askingPrice = $values['price_type'] === 'asking' ? (float)$values['price'] : null;
        $stmt = $this->db->prepare(
            "UPDATE price_observations
             SET product_id=:product_id,price_type=:price_type,asking_price=:asking_price,price_value=:price_value,listing_type=:listing_type,condition_level=:condition_level,warranty_months=:warranty_months,observed_at=:observed_at,quality_flags=:quality_flags,updated_at=NOW()
             WHERE id=:id AND verified_status='pending'"
        );
        $stmt->execute([
            'product_id' => (int)$values['product_id'],
            'price_type' => (string)$values['price_type'],
            'asking_price' => $askingPrice,
            'price_value' => (float)$values['price'],
            'listing_type' => (string)$values['listing_type'],
            'condition_level' => (string)$values['condition_level'],
            'warranty_months' => $values['warranty_months'],
            'observed_at' => (string)$values['observed_at'],
            'quality_flags' => json_encode($flags, JSON_UNESCAPED_UNICODE),
            'id' => $observationId,
        ]);

        return ['ok' => $stmt->rowCount() === 1, 'values' => $values, 'quality_flags' => $flags];
    }

    public function productOptions(): array
    {
        return $this->db->query("SELECT id,full_name FROM products WHERE is_active=1 ORDER BY full_name")->fetchAll();
    }

    public function summary(): array
    {
        $flags = [];
        foreach ($this->db->query("SELECT quality_flags FROM price_observations WHERE quality_flags IS NOT NULL") as $row) {
            foreach ((json_decode((string)$row['quality_flags'], true) ?: []) as $flag) {
                $flags[$flag] = ($flags[$flag] ?? 0) + 1;
            }
        }
        arsort($flags);
        return [
            'total_pending' => (int)$this->db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='pending'")->fetchColumn(),
            'corrected' => (int)$this->db->query("SELECT COUNT(DISTINCT price_observation_id) FROM review_corrections")->fetchColumn(),
            'approved' => (int)$this->db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='approved'")->fetchColumn(),
            'rejected' => (int)$this->db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='rejected'")->fetchColumn(),
            'excluded' => (int)$this->db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='excluded'")->fetchColumn(),
            'lanes' => [
                'green' => (int)$this->db->query("SELECT COUNT(*) FROM observation_review_decisions WHERE lane='green'")->fetchColumn(),
                'amber' => (int)$this->db->query("SELECT COUNT(*) FROM observation_review_decisions WHERE lane='amber'")->fetchColumn(),
                'red' => (int)$this->db->query("SELECT COUNT(*) FROM observation_review_decisions WHERE lane='red'")->fetchColumn(),
            ],
            'quality_flags' => array_slice($flags, 0, 8, true),
            'correction_fields' => $this->correctionFieldCounts(),
        ];
    }

    private function correctionFieldCounts(): array
    {
        $counts = [];
        foreach ($this->db->query("SELECT field_name,COUNT(*) c FROM review_corrections GROUP BY field_name ORDER BY c DESC,field_name") as $row) {
            $counts[(string)$row['field_name']] = (int)$row['c'];
        }
        return $counts;
    }

    private function observation(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*,r.source_url_encrypted,s.source_key,s.name source_name,s.domain source_domain
             FROM price_observations o
             LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
             LEFT JOIN data_sources s ON s.id=r.source_id
             WHERE o.id=:id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private function normalize(string $field, mixed $value): array
    {
        return match ($field) {
            'product_id' => $this->normalizeProductId($value),
            'price' => $this->normalizePrice($value),
            'price_type' => $this->normalizeEnum($value, self::PRICE_TYPES, 'INVALID_PRICE_TYPE'),
            'listing_type' => $this->normalizeEnum($value, self::LISTING_TYPES, 'INVALID_LISTING_TYPE'),
            'condition_level' => $this->normalizeEnum($value, self::CONDITIONS, 'INVALID_CONDITION'),
            'warranty_months' => $this->normalizeWarranty($value),
            'observed_at' => $this->normalizeObservedAt($value),
            default => ['valid' => false, 'error' => 'UNSUPPORTED_FIELD'],
        };
    }

    private function normalizeProductId(mixed $value): array
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if (!$id || !$this->productExists((int)$id)) {
            return ['valid' => false, 'error' => 'INVALID_PRODUCT'];
        }
        return ['valid' => true, 'value' => (int)$id];
    }

    private function normalizePrice(mixed $value): array
    {
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => 'INVALID_PRICE'];
        }
        $price = round((float)$value, 2);
        if (!$this->validPrice($price)) {
            return ['valid' => false, 'error' => 'INVALID_PRICE'];
        }
        return ['valid' => true, 'value' => $price];
    }

    private function normalizeEnum(mixed $value, array $allowed, string $error): array
    {
        $value = trim((string)$value);
        if (!in_array($value, $allowed, true)) {
            return ['valid' => false, 'error' => $error];
        }
        return ['valid' => true, 'value' => $value];
    }

    private function normalizeWarranty(mixed $value): array
    {
        if ($value === null || trim((string)$value) === '') {
            return ['valid' => true, 'value' => null];
        }
        $months = filter_var($value, FILTER_VALIDATE_INT);
        if ($months === false || $months < 0 || $months > 120) {
            return ['valid' => false, 'error' => 'INVALID_WARRANTY'];
        }
        return ['valid' => true, 'value' => $months];
    }

    private function normalizeObservedAt(mixed $value): array
    {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => 'INVALID_OBSERVED_AT'];
        }
        $timestamp = strtotime($value);
        if ($timestamp === false || $timestamp > strtotime('+1 day')) {
            return ['valid' => false, 'error' => 'INVALID_OBSERVED_AT'];
        }
        return ['valid' => true, 'value' => date('Y-m-d H:i:s', $timestamp)];
    }

    private function fieldValue(array $observation, string $field): mixed
    {
        return match ($field) {
            'price' => $observation['price_value'] ?? $observation['asking_price'],
            default => $observation[$field] ?? null,
        };
    }

    private function productExists(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM products WHERE id=:id AND is_active=1 LIMIT 1");
        $stmt->execute(['id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    private function validPrice(mixed $value): bool
    {
        return is_numeric($value) && (float)$value > 0 && (float)$value <= self::MAX_PRICE;
    }

    private function validObservedAt(string $value): bool
    {
        $timestamp = strtotime($value);
        return $timestamp !== false && $timestamp <= strtotime('+1 day');
    }

    private function duplicateApprovedLineage(int $observationId, ?int $rawObservationId): bool
    {
        if ($rawObservationId === null) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM price_observations WHERE raw_observation_id=:raw_id AND verified_status='approved' AND id<>:id");
        $stmt->execute(['raw_id' => $rawObservationId, 'id' => $observationId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function isStale(string $observedAt): bool
    {
        $timestamp = strtotime($observedAt);
        return $timestamp !== false && $timestamp < strtotime('-90 days');
    }

    private function isMockSource(array $observation): bool
    {
        $sourceKey = strtolower((string)($observation['source_key'] ?? ''));
        $sourceName = strtolower((string)($observation['source_name'] ?? ''));
        $sourceDomain = strtolower((string)($observation['source_domain'] ?? ''));
        return str_contains($sourceKey, 'mock') || str_contains($sourceName, 'mock') || str_starts_with($sourceDomain, 'mock.');
    }

    private function audit(string $action, string $entityType, int $entityId, ?array $before, array $after): void
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

    private function error(string $code): array
    {
        return ['ok' => false, 'errors' => [$code]];
    }
}
