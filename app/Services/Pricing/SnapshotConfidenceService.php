<?php
declare(strict_types=1);

namespace App\Services\Pricing;

final class SnapshotConfidenceService
{
    public const METHOD_VERSION = 'confidence-v1';

    public const WEIGHTS = [
        'sample' => 0.30,
        'freshness' => 0.30,
        'source_diversity' => 0.25,
        'evidence_quality' => 0.15,
    ];

    public const FRESHNESS_WINDOW_DAYS = 30;

    public function calculate(array $includedRows, string $priceType, string $asOfDate): array
    {
        $sampleSize = count($includedRows);
        $sample = $this->sampleComponent($sampleSize);
        $freshness = $this->freshnessComponent($includedRows, $asOfDate);
        $diversity = $this->sourceDiversityComponent($includedRows);
        $evidence = $this->evidenceQualityComponent($includedRows);

        $rawScore = round(
            $sample['score'] * self::WEIGHTS['sample']
            + $freshness['score'] * self::WEIGHTS['freshness']
            + $diversity['score'] * self::WEIGHTS['source_diversity']
            + $evidence['score'] * self::WEIGHTS['evidence_quality'],
            2
        );

        $score = $rawScore;
        $caps = [];
        if ($sampleSize < 3) {
            $score = min($score, 0.0);
            $caps[] = 'INSUFFICIENT_SAMPLE';
        } elseif ($sampleSize < 5) {
            $score = min($score, 64.0);
            $caps[] = 'TINY_SAMPLE_CAP';
        }
        if ($diversity['unique_source_count'] <= 1) {
            $score = min($score, 74.0);
            $caps[] = 'SINGLE_SOURCE_CAP';
        }
        if ($diversity['dominant_source_share'] >= 0.80 && $sampleSize >= 5) {
            $score = min($score, 79.0);
            $caps[] = 'SOURCE_CONCENTRATION_CAP';
        }
        if ($freshness['median_age_days'] > 90) {
            $score = min($score, 64.0);
            $caps[] = 'STALE_COHORT_CAP';
        }
        if ($evidence['weak_share'] >= 0.60) {
            $score = min($score, 69.0);
            $caps[] = 'WEAK_EVIDENCE_CAP';
        }

        $score = round(max(0.0, min(100.0, $score)), 2);
        $label = $this->label($score, $sampleSize);
        $reasons = array_values(array_unique(array_merge(
            $sample['reasons'],
            $freshness['reasons'],
            $diversity['reasons'],
            $evidence['reasons'],
            $caps
        )));

        return [
            'method_version' => self::METHOD_VERSION,
            'as_of_date' => $asOfDate,
            'price_type' => $priceType,
            'overall_score' => $score,
            'raw_score' => $rawScore,
            'label' => $label,
            'components' => [
                'sample' => $sample,
                'freshness' => $freshness,
                'source_diversity' => $diversity,
                'evidence_quality' => $evidence,
            ],
            'weights' => self::WEIGHTS,
            'policy_caps' => $caps,
            'reasons' => $reasons,
        ];
    }

    private function sampleComponent(int $sampleSize): array
    {
        if ($sampleSize < 3) {
            return ['score' => 0.0, 'sample_size' => $sampleSize, 'reasons' => ['LOW_SAMPLE_SIZE']];
        }
        if ($sampleSize < 5) {
            return ['score' => 45.0, 'sample_size' => $sampleSize, 'reasons' => ['LOW_SAMPLE_SIZE']];
        }
        if ($sampleSize < 8) {
            return ['score' => 65.0, 'sample_size' => $sampleSize, 'reasons' => ['MODERATE_SAMPLE_SIZE']];
        }
        if ($sampleSize < 15) {
            return ['score' => 82.0, 'sample_size' => $sampleSize, 'reasons' => ['SUFFICIENT_SAMPLE']];
        }
        return ['score' => 95.0, 'sample_size' => $sampleSize, 'reasons' => ['STRONG_SAMPLE_SIZE']];
    }

    private function freshnessComponent(array $rows, string $asOfDate): array
    {
        $asOf = strtotime($asOfDate . ' 23:59:59');
        $ages = [];
        $freshCount = 0;
        foreach ($rows as $row) {
            $observed = strtotime((string)($row['observed_at'] ?? $row['snapshot_observed_at'] ?? ''));
            if ($observed === false || $asOf === false) {
                $ages[] = 9999;
                continue;
            }
            $age = max(0, (int)floor(($asOf - $observed) / 86400));
            $ages[] = $age;
            if ($age <= self::FRESHNESS_WINDOW_DAYS) {
                $freshCount++;
            }
        }
        sort($ages);
        $count = count($ages);
        $medianAge = $count === 0 ? 9999 : $ages[(int)floor(($count - 1) / 2)];
        $freshRatio = $count === 0 ? 0.0 : round(($freshCount / $count) * 100, 2);

        if ($freshRatio >= 80.0 && $medianAge <= 21) {
            $score = 92.0;
            $reasons = ['FRESH_OBSERVATIONS'];
        } elseif ($freshRatio >= 50.0 && $medianAge <= 45) {
            $score = 72.0;
            $reasons = ['MIXED_FRESHNESS'];
        } elseif ($medianAge <= 90) {
            $score = 50.0;
            $reasons = ['LIMITED_FRESHNESS'];
        } else {
            $score = 25.0;
            $reasons = ['STALE_DATA'];
        }

        return [
            'score' => $score,
            'as_of_date' => $asOfDate,
            'freshness_window_days' => self::FRESHNESS_WINDOW_DAYS,
            'fresh_count' => $freshCount,
            'fresh_ratio' => $freshRatio,
            'median_age_days' => $medianAge,
            'oldest_age_days' => $count === 0 ? null : max($ages),
            'newest_age_days' => $count === 0 ? null : min($ages),
            'reasons' => $reasons,
        ];
    }

    private function sourceDiversityComponent(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $sourceKey = (string)($row['source_domain'] ?? '');
            if ($sourceKey === '') {
                $sourceKey = (string)($row['source_name'] ?? '');
            }
            if ($sourceKey === '') {
                $sourceKey = (string)($row['source_id'] ?? 'unknown');
            }
            $sourceKey = strtolower($sourceKey);
            $counts[$sourceKey] = ($counts[$sourceKey] ?? 0) + 1;
        }
        ksort($counts);
        $sampleSize = count($rows);
        $unique = count($counts);
        $dominant = $sampleSize === 0 ? 0.0 : round((max($counts ?: [0]) / $sampleSize) * 100, 2);

        if ($unique <= 1) {
            $score = 35.0;
            $reasons = ['SINGLE_SOURCE'];
        } elseif ($dominant >= 80.0) {
            $score = 55.0;
            $reasons = ['HIGH_SOURCE_CONCENTRATION'];
        } elseif ($unique >= 3 && $dominant <= 60.0) {
            $score = 88.0;
            $reasons = ['GOOD_SOURCE_DIVERSITY'];
        } else {
            $score = 70.0;
            $reasons = ['MODERATE_SOURCE_DIVERSITY'];
        }

        return [
            'score' => $score,
            'unique_source_count' => $unique,
            'dominant_source_share' => $dominant / 100,
            'source_distribution' => $counts,
            'reasons' => $reasons,
        ];
    }

    private function evidenceQualityComponent(array $rows): array
    {
        $scores = [];
        $weak = 0;
        foreach ($rows as $row) {
            $level = (int)($row['evidence_level'] ?? 1);
            $quality = strtolower((string)($row['source_evidence_quality'] ?? 'unknown'));
            $score = match (true) {
                $level >= 5 || $quality === 'high' => 95.0,
                $level >= 4 || $quality === 'medium' => 78.0,
                $level >= 2 => 55.0,
                default => 30.0,
            };
            if ($score < 60.0) {
                $weak++;
            }
            $scores[] = $score;
        }
        $count = count($scores);
        $average = $count === 0 ? 0.0 : round(array_sum($scores) / $count, 2);
        $weakShare = $count === 0 ? 1.0 : round($weak / $count, 4);

        $reasons = [];
        if ($average >= 80.0 && $weakShare < 0.25) {
            $reasons[] = 'STRONG_EVIDENCE';
        } elseif ($weakShare >= 0.60) {
            $reasons[] = 'WEAK_EVIDENCE';
        } else {
            $reasons[] = 'MIXED_EVIDENCE_QUALITY';
        }

        return [
            'score' => $average,
            'average_evidence_score' => $average,
            'weak_share' => $weakShare,
            'reasons' => $reasons,
        ];
    }

    private function label(float $score, int $sampleSize): string
    {
        if ($sampleSize < 3) {
            return 'insufficient';
        }
        if ($score >= 80.0) {
            return 'high';
        }
        if ($score >= 60.0) {
            return 'medium';
        }
        return 'low';
    }
}
