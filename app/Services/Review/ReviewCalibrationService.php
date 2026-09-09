<?php
declare(strict_types=1);

namespace App\Services\Review;

use App\Core\Database;
use PDO;

final class ReviewCalibrationService
{
    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function report(array $filters = []): array
    {
        $rows = $this->rows($filters);
        $summary = $this->aggregate($rows);
        $summary['filters'] = $this->normalizeFilters($filters);
        $summary['generated_at'] = date('Y-m-d H:i:s');
        return $summary;
    }

    private function rows(array $filters): array
    {
        $sql = "SELECT
                o.id observation_id,o.verified_status,o.quality_flags,o.created_at observation_created_at,o.updated_at observation_updated_at,
                p.id product_id,p.full_name product_name,c.slug category,
                r.id raw_id,r.collector_job_id,r.created_at raw_created_at,
                s.id source_id,s.source_key,s.name source_name,s.domain source_domain,
                j.job_type provider,j.run_id,
                od.id original_decision_id,od.lane original_lane,od.decision original_decision,od.reason_codes original_reasons,od.rule_result original_rule_result,od.created_at review_created_at,
                hd.id human_decision_id,hd.decision human_decision,hd.created_at human_decision_at,
                COALESCE(cc.correction_count,0) correction_count,
                cc.corrected_fields
            FROM price_observations o
            JOIN products p ON p.id=o.product_id
            JOIN product_categories c ON c.id=p.category_id
            LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
            LEFT JOIN data_sources s ON s.id=r.source_id
            LEFT JOIN collector_jobs j ON j.id=r.collector_job_id
            LEFT JOIN observation_review_decisions od ON od.id=(
                SELECT id FROM observation_review_decisions d
                WHERE d.price_observation_id=o.id
                ORDER BY d.id ASC LIMIT 1
            )
            LEFT JOIN observation_review_decisions hd ON hd.id=(
                SELECT id FROM observation_review_decisions d
                WHERE d.price_observation_id=o.id
                  AND d.decision IN ('approved','edited','rejected','quarantined')
                ORDER BY d.id DESC LIMIT 1
            )
            LEFT JOIN (
                SELECT price_observation_id,COUNT(*) correction_count,GROUP_CONCAT(field_name ORDER BY field_name SEPARATOR ',') corrected_fields
                FROM review_corrections
                GROUP BY price_observation_id
            ) cc ON cc.price_observation_id=o.id
            WHERE od.id IS NOT NULL";
        $params = [];
        $filters = $this->normalizeFilters($filters);
        if ($filters['category'] !== null) {
            $sql .= " AND c.slug=:category";
            $params['category'] = $filters['category'];
        }
        if ($filters['source_id'] !== null) {
            $sql .= " AND s.id=:source_id";
            $params['source_id'] = $filters['source_id'];
        }
        if ($filters['provider'] !== null) {
            $sql .= " AND j.job_type=:provider";
            $params['provider'] = $filters['provider'];
        }
        if ($filters['lane'] !== null) {
            $sql .= " AND od.lane=:lane";
            $params['lane'] = $filters['lane'];
        }
        if ($filters['outcome'] !== null) {
            $sql .= " AND o.verified_status=:outcome";
            $params['outcome'] = $filters['outcome'];
        }
        if ($filters['from'] !== null) {
            $sql .= " AND od.created_at>=:from_date";
            $params['from_date'] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== null) {
            $sql .= " AND od.created_at<=:to_date";
            $params['to_date'] = $filters['to'] . ' 23:59:59';
        }
        $sql .= " ORDER BY od.created_at DESC,o.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function normalizeFilters(array $filters): array
    {
        $category = isset($filters['category']) ? strtolower(trim((string)$filters['category'])) : null;
        $lane = isset($filters['lane']) ? strtolower(trim((string)$filters['lane'])) : null;
        $outcome = isset($filters['outcome']) ? strtolower(trim((string)$filters['outcome'])) : null;
        return [
            'category' => in_array($category, ['cpu', 'gpu', 'ram', 'storage', 'motherboard', 'psu', 'cooling', 'case'], true) ? $category : null,
            'source_id' => isset($filters['source_id']) && filter_var($filters['source_id'], FILTER_VALIDATE_INT) ? (int)$filters['source_id'] : null,
            'provider' => isset($filters['provider']) && trim((string)$filters['provider']) !== '' ? trim((string)$filters['provider']) : null,
            'from' => $this->validDate($filters['from'] ?? null),
            'to' => $this->validDate($filters['to'] ?? null),
            'lane' => in_array($lane, ['green', 'amber', 'red'], true) ? $lane : null,
            'outcome' => in_array($outcome, ['pending', 'approved', 'rejected', 'excluded'], true) ? $outcome : null,
        ];
    }

    private function validDate(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value ? $value : null;
    }

    private function aggregate(array $rows): array
    {
        $total = count($rows);
        $reviewed = 0;
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        $excluded = 0;
        $correctedReviews = 0;
        $mockRows = 0;
        $liveRows = 0;
        $liveReviewed = 0;
        $liveApprovedEligible = 0;
        $latencies = [];
        $oldestPending = null;

        $lanes = $this->emptyLaneBuckets();
        $flags = [];
        $fields = [];
        $sources = [];
        $ruleVersions = [];

        foreach ($rows as $row) {
            $lane = (string)($row['original_lane'] ?? 'amber');
            if (!isset($lanes[$lane])) {
                $lanes[$lane] = $this->emptyOutcomeBucket();
            }
            $status = (string)$row['verified_status'];
            $isReviewed = $status !== 'pending';
            $isCorrected = (int)$row['correction_count'] > 0;
            $datasetType = $this->datasetType($row);
            if ($datasetType === 'mock_test') {
                $mockRows++;
            } else {
                $liveRows++;
            }
            if ($isReviewed) {
                $reviewed++;
                if ($datasetType === 'real') {
                    $liveReviewed++;
                }
            } else {
                $pending++;
                $created = strtotime((string)$row['review_created_at']);
                if ($created !== false && ($oldestPending === null || $created < $oldestPending)) {
                    $oldestPending = $created;
                }
            }

            if ($status === 'approved') {
                $approved++;
                if ($datasetType === 'real' && $this->eligibleApproved($row)) {
                    $liveApprovedEligible++;
                }
            } elseif ($status === 'rejected') {
                $rejected++;
            } elseif ($status === 'excluded') {
                $excluded++;
            }
            if ($isCorrected) {
                $correctedReviews++;
            }

            $lanes[$lane]['total']++;
            $lanes[$lane][$status] = ($lanes[$lane][$status] ?? 0) + 1;
            if ($isReviewed) {
                $lanes[$lane]['reviewed']++;
            }
            if ($isCorrected) {
                $lanes[$lane]['corrected']++;
            }
            if ($lane === 'green' && $isReviewed && $status === 'approved' && !$isCorrected) {
                $lanes[$lane]['approved_clean']++;
            }
            if ($lane === 'green' && $isReviewed && $status === 'approved' && $isCorrected) {
                $lanes[$lane]['approved_after_correction']++;
            }

            if ($isReviewed && $row['review_created_at'] && $row['human_decision_at']) {
                $start = strtotime((string)$row['review_created_at']);
                $end = strtotime((string)$row['human_decision_at']);
                if ($start !== false && $end !== false && $end >= $start) {
                    $latencies[] = (int)floor(($end - $start) / 60);
                }
            }

            foreach ($this->jsonList($row['quality_flags'] ?? null) as $flag) {
                $this->bumpFlag($flags, $flag, $status, $isCorrected);
            }
            foreach ($this->jsonList($row['original_reasons'] ?? null) as $reason) {
                if (str_ends_with($reason, '-v1')) {
                    $ruleVersions[$reason] = ($ruleVersions[$reason] ?? 0) + 1;
                }
            }
            foreach ($this->csvList($row['corrected_fields'] ?? null) as $field) {
                $fields[$field] = ($fields[$field] ?? 0) + 1;
            }

            $sourceKey = (string)($row['source_key'] ?: 'unknown');
            if (!isset($sources[$sourceKey])) {
                $sources[$sourceKey] = [
                    'source_id' => $row['source_id'] === null ? null : (int)$row['source_id'],
                    'source_name' => (string)($row['source_name'] ?? 'unknown'),
                    'provider' => (string)($row['provider'] ?? 'unknown'),
                    'dataset_type' => $datasetType,
                    'candidates' => 0,
                    'reviewed' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'excluded' => 0,
                    'corrected' => 0,
                    'duplicates' => 0,
                    'green_reviewed' => 0,
                    'green_clean_approved' => 0,
                    'quality_flags' => [],
                ];
            }
            $sources[$sourceKey]['candidates']++;
            if ($isReviewed) {
                $sources[$sourceKey]['reviewed']++;
            }
            if (in_array($status, ['approved', 'rejected', 'excluded'], true)) {
                $sources[$sourceKey][$status]++;
            }
            if ($isCorrected) {
                $sources[$sourceKey]['corrected']++;
            }
            if ($lane === 'green' && $isReviewed) {
                $sources[$sourceKey]['green_reviewed']++;
                if ($status === 'approved' && !$isCorrected) {
                    $sources[$sourceKey]['green_clean_approved']++;
                }
            }
            foreach ($this->jsonList($row['quality_flags'] ?? null) as $flag) {
                $sources[$sourceKey]['quality_flags'][$flag] = ($sources[$sourceKey]['quality_flags'][$flag] ?? 0) + 1;
                if ($flag === 'DUPLICATE') {
                    $sources[$sourceKey]['duplicates']++;
                }
            }
        }

        foreach ($lanes as &$bucket) {
            $bucket['green_precision_clean'] = $bucket['reviewed'] > 0 ? round(($bucket['approved_clean'] / $bucket['reviewed']) * 100, 2) : null;
        }
        unset($bucket);

        uasort($flags, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
        arsort($fields);
        arsort($ruleVersions);

        foreach ($sources as &$source) {
            $source['valid_observation_yield'] = $source['reviewed'] > 0 ? round(($source['approved'] / $source['reviewed']) * 100, 2) : null;
            $source['green_precision_clean'] = $source['green_reviewed'] > 0 ? round(($source['green_clean_approved'] / $source['green_reviewed']) * 100, 2) : null;
            arsort($source['quality_flags']);
            $source['top_quality_flags'] = array_slice($source['quality_flags'], 0, 8, true);
            unset($source['quality_flags']);
        }
        unset($source);

        $greenReviewed = $lanes['green']['reviewed'];
        $greenCleanPrecision = $greenReviewed > 0 ? round(($lanes['green']['approved_clean'] / $greenReviewed) * 100, 2) : null;

        return [
            'dataset_label' => $liveRows > 0 && $mockRows > 0 ? 'MIXED' : ($liveRows > 0 ? 'REAL' : 'MOCK_TEST'),
            'sample_size' => $total,
            'mock_test_records' => $mockRows,
            'real_records' => $liveRows,
            'lifecycle' => [
                'pending_reviews' => $pending,
                'reviewed_count' => $reviewed,
                'approved' => $approved,
                'rejected' => $rejected,
                'excluded' => $excluded,
                'corrected_reviews' => $correctedReviews,
                'uncorrected_approvals' => max(0, $approved - $this->countApprovedCorrected($rows)),
                'review_backlog' => $pending,
                'decision_rate' => $total > 0 ? round(($reviewed / $total) * 100, 2) : 0.0,
                'correction_rate' => $reviewed > 0 ? round(($correctedReviews / $reviewed) * 100, 2) : 0.0,
                'median_review_latency_minutes' => $this->median($latencies),
                'oldest_pending_age_hours' => $oldestPending === null ? null : round((time() - $oldestPending) / 3600, 2),
            ],
            'lanes' => $lanes,
            'green_precision' => [
                'reviewed_green_records' => $greenReviewed,
                'approved_without_material_correction' => $lanes['green']['approved_clean'],
                'approved_after_correction' => $lanes['green']['approved_after_correction'],
                'clean_green_precision' => $greenCleanPrecision,
            ],
            'quality_flags' => array_slice($flags, 0, 20, true),
            'corrected_fields' => array_slice($fields, 0, 20, true),
            'source_quality' => array_values($sources),
            'valid_observation_yield' => [
                'definition' => 'real approved eligible observations divided by real reviewed acquisition candidates',
                'real_reviewed_candidates' => $liveReviewed,
                'real_approved_eligible_observations' => $liveApprovedEligible,
                'yield_percent' => $liveReviewed > 0 ? round(($liveApprovedEligible / $liveReviewed) * 100, 2) : null,
                'mock_test_excluded_from_live_kpi' => true,
            ],
            'calibration_status' => $this->calibrationStatus($liveReviewed, $mockRows, $total),
            'poc_gate_metrics' => [
                'product_resolution' => 'PASS_FIXTURE_ONLY',
                'price_extraction' => 'NOT_MEASURED_LIVE',
                'invalid_false_acceptance' => 'PASS_FIXTURE_ONLY',
                'valid_yield' => $liveReviewed > 0 ? 'LIVE_SAMPLE_INSUFFICIENT' : 'NOT_MEASURED_LIVE',
                'human_review_share' => 'NOT_MEASURED_LIVE',
                'owner_review_minutes_per_day' => 'NOT_MEASURED_LIVE',
                'scheduled_run_success' => 'PASS_FIXTURE_ONLY',
            ],
            'rule_versions' => $ruleVersions,
        ];
    }

    private function emptyLaneBuckets(): array
    {
        return ['green' => $this->emptyOutcomeBucket(), 'amber' => $this->emptyOutcomeBucket(), 'red' => $this->emptyOutcomeBucket()];
    }

    private function emptyOutcomeBucket(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'excluded' => 0,
            'reviewed' => 0,
            'corrected' => 0,
            'approved_clean' => 0,
            'approved_after_correction' => 0,
            'green_precision_clean' => null,
        ];
    }

    private function countApprovedCorrected(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (($row['verified_status'] ?? '') === 'approved' && (int)($row['correction_count'] ?? 0) > 0) {
                $count++;
            }
        }
        return $count;
    }

    private function datasetType(array $row): string
    {
        $text = strtolower(implode(' ', [
            (string)($row['source_key'] ?? ''),
            (string)($row['source_name'] ?? ''),
            (string)($row['source_domain'] ?? ''),
            (string)($row['provider'] ?? ''),
        ]));
        foreach (['mock', 'fixture', 'test', 'confidence', 'snapshot', 'ui-', 'auth-rbac'] as $needle) {
            if (str_contains($text, $needle)) {
                return 'mock_test';
            }
        }
        return 'real';
    }

    private function eligibleApproved(array $row): bool
    {
        if (($row['verified_status'] ?? '') !== 'approved') {
            return false;
        }
        foreach ($this->jsonList($row['quality_flags'] ?? null) as $flag) {
            if (in_array($flag, ['WRONG_PRODUCT', 'MOBILE_GPU', 'WANTED', 'WHOLE_PC', 'BUNDLE', 'DEPOSIT', 'DEFECTIVE', 'DUPLICATE'], true)) {
                return false;
            }
        }
        return true;
    }

    private function jsonList(mixed $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = is_array($json) ? $json : json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded), static fn(string $value): bool => $value !== ''));
    }

    private function csvList(mixed $value): array
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $item): bool => $item !== ''));
    }

    private function bumpFlag(array &$flags, string $flag, string $status, bool $corrected): void
    {
        if (!isset($flags[$flag])) {
            $flags[$flag] = ['count' => 0, 'approved' => 0, 'rejected' => 0, 'excluded' => 0, 'corrected' => 0];
        }
        $flags[$flag]['count']++;
        if (isset($flags[$flag][$status])) {
            $flags[$flag][$status]++;
        }
        if ($corrected) {
            $flags[$flag]['corrected']++;
        }
    }

    private function median(array $values): ?float
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        sort($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float)$values[$middle];
        }
        return round(((float)$values[$middle - 1] + (float)$values[$middle]) / 2, 2);
    }

    private function calibrationStatus(int $liveReviewed, int $mockRows, int $total): string
    {
        if ($liveReviewed >= 100) {
            return 'CALIBRATING';
        }
        if ($liveReviewed > 0) {
            return 'LIVE_SAMPLE_INSUFFICIENT';
        }
        if ($total > 0 && $mockRows === $total) {
            return 'FIXTURE_BASELINE';
        }
        return 'FIXTURE_BASELINE';
    }
}
