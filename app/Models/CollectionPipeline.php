<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Data\ExtractedObservation;
use App\Data\ProductResolution;
use App\Data\SearchCandidate;
use App\Data\ValidationResult;
use PDO;

final class CollectionPipeline
{
    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function catalog(): array
    {
        $products = $this->db->query("SELECT id, full_name FROM products WHERE is_active=1")->fetchAll();
        $aliases = $this->db->query("SELECT product_id, alias_text FROM product_aliases")->fetchAll();
        $byProduct = [];

        foreach ($products as $product) {
            $byProduct[(int)$product['id']] = [
                'id' => (int)$product['id'],
                'full_name' => (string)$product['full_name'],
                'aliases' => [],
            ];
        }

        foreach ($aliases as $alias) {
            $productId = (int)$alias['product_id'];
            if (isset($byProduct[$productId])) {
                $byProduct[$productId]['aliases'][] = (string)$alias['alias_text'];
            }
        }

        return array_values($byProduct);
    }

    public function ensureMockSource(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM data_sources WHERE name=:name LIMIT 1");
        $stmt->execute(['name' => 'Mock fixture provider']);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $insert = $this->db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,terms_note,is_active,is_paused,last_success_at) VALUES ('mock_fixture_provider',:name,:domain,'research','manual','manual','low',95,'high',14,:terms,1,0,NOW())");
        $insert->execute([
            'name' => 'Mock fixture provider',
            'domain' => 'fixture.local',
            'terms' => 'Local fixture-only source for dry-run and calibration tests.',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function startJob(int $sourceId, string $query): int
    {
        $stmt = $this->db->prepare("INSERT INTO collector_jobs (source_id,job_type,query_text,status,started_at) VALUES (:source_id,'mock_fixture_collection',:query,'running',NOW())");
        $stmt->execute(['source_id' => $sourceId, 'query' => $query]);
        return (int)$this->db->lastInsertId();
    }

    public function persistCandidate(
        int $sourceId,
        int $jobId,
        SearchCandidate $candidate,
        ExtractedObservation $extraction,
        ProductResolution $resolution,
        ValidationResult $validation
    ): bool {
        $useSavepoint = $this->db->inTransaction();
        if ($useSavepoint) {
            $this->db->exec('SAVEPOINT collection_pipeline_candidate');
        } else {
            $this->db->beginTransaction();
        }

        try {
            $hash = hash('sha256', $candidate->sourceName . '|' . ($candidate->url ?? '') . '|' . $candidate->title . '|' . (string)$candidate->priceText);

            $rawStmt = $this->db->prepare(
                "INSERT IGNORE INTO raw_price_observations (source_id,collector_job_id,external_reference_hash,raw_title,raw_price_text,source_url_encrypted,captured_at,processing_status)
                 VALUES (:source_id,:job_id,:hash,:title,:price_text,:url,:captured_at,:status)"
            );
            $rawStmt->execute([
                'source_id' => $sourceId,
                'job_id' => $jobId,
                'hash' => $hash,
                'title' => $candidate->title,
                'price_text' => $candidate->priceText,
                'url' => $candidate->url,
                'captured_at' => $candidate->capturedAt,
                'status' => $validation->lane === 'red' ? 'rejected' : 'review',
            ]);

            $rawId = (int)$this->db->lastInsertId();
            if ($rawId === 0) {
                $lookup = $this->db->prepare("SELECT id FROM raw_price_observations WHERE source_id=:source_id AND external_reference_hash=:hash");
                $lookup->execute(['source_id' => $sourceId, 'hash' => $hash]);
                $rawId = (int)$lookup->fetchColumn();
                $this->finishCandidateTransaction($useSavepoint);
                return false;
            }

            $evidence = $this->db->prepare(
                "INSERT INTO market_evidence (raw_observation_id,source_id,evidence_level,evidence_type,source_url_hash,excerpt,content_hash,captured_at,is_public)
                 VALUES (:raw_id,:source_id,:level,'fixture',:url_hash,:excerpt,:content_hash,:captured_at,0)"
            );
            $evidence->execute([
                'raw_id' => $rawId,
                'source_id' => $sourceId,
                'level' => $this->normalizeEvidenceLevel($candidate->evidenceLevel),
                'url_hash' => $candidate->url ? hash('sha256', $candidate->url) : null,
                'excerpt' => trim($candidate->title . ($candidate->snippet ? "\n" . $candidate->snippet : '')),
                'content_hash' => hash('sha256', $candidate->title . '|' . (string)$candidate->priceText),
                'captured_at' => $candidate->capturedAt,
            ]);
            $evidenceId = (int)$this->db->lastInsertId();

            $extractionRun = $this->db->prepare(
                "INSERT INTO extraction_runs (raw_observation_id,evidence_id,provider_name,model_name,prompt_version,schema_version,extracted_data,confidence)
                 VALUES (:raw_id,:evidence_id,'rule_based_fixture',NULL,'rules_v1','market_observation_v1',:data,:confidence)"
            );
            $extractionRun->execute([
                'raw_id' => $rawId,
                'evidence_id' => $evidenceId,
                'data' => json_encode($extraction, JSON_UNESCAPED_UNICODE),
                'confidence' => $extraction->confidence,
            ]);
            $extractionRunId = (int)$this->db->lastInsertId();

            $observationId = null;
            if ($validation->lane !== 'red' && $resolution->productId !== null && $extraction->askingPrice !== null) {
                $observation = $this->db->prepare(
                    "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,warranty_months,has_box,has_receipt,listing_type,is_deposit,is_defective,evidence_level,classification_confidence,quality_flags,verified_status,observed_at)
                     VALUES (:raw_id,:product_id,'asking',:asking_price,:price_value,:currency,:condition_level,:warranty_months,:has_box,:has_receipt,:listing_type,:is_deposit,:is_defective,:evidence_level,:confidence,:quality_flags,'pending',:observed_at)"
                );
                $observation->execute([
                    'raw_id' => $rawId,
                    'product_id' => $resolution->productId,
                    'asking_price' => $extraction->askingPrice,
                    'price_value' => $extraction->askingPrice,
                    'currency' => $extraction->currency,
                    'condition_level' => $extraction->conditionLevel,
                    'warranty_months' => $extraction->warrantyMonths,
                    'has_box' => $extraction->hasBox === null ? null : (int)$extraction->hasBox,
                    'has_receipt' => $extraction->hasReceipt === null ? null : (int)$extraction->hasReceipt,
                    'listing_type' => $extraction->listingType,
                    'is_deposit' => (int)$extraction->isDeposit,
                    'is_defective' => (int)$extraction->isDefective,
                    'evidence_level' => $this->evidenceLevelNumber($candidate->evidenceLevel),
                    'confidence' => round($extraction->confidence * 100, 2),
                    'quality_flags' => json_encode($this->qualityFlags($validation->reasonCodes), JSON_UNESCAPED_UNICODE),
                    'observed_at' => $candidate->capturedAt,
                ]);
                $observationId = (int)$this->db->lastInsertId();
            }

            $review = $this->db->prepare(
                "INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,extraction_run_id,lane,decision,reason_codes,ai_value,rule_result)
                 VALUES (:observation_id,:raw_id,:extraction_id,:lane,:decision,:reasons,:ai_value,:rule_result)"
            );
            $review->execute([
                'observation_id' => $observationId,
                'raw_id' => $rawId,
                'extraction_id' => $extractionRunId,
                'lane' => $validation->lane,
                'decision' => $validation->reviewState === 'rejected' ? 'rejected' : 'review_required',
                'reasons' => json_encode($validation->reasonCodes, JSON_UNESCAPED_UNICODE),
                'ai_value' => json_encode($extraction, JSON_UNESCAPED_UNICODE),
                'rule_result' => json_encode($validation, JSON_UNESCAPED_UNICODE),
            ]);

            $this->finishCandidateTransaction($useSavepoint);
            return true;
        } catch (\Throwable $e) {
            $this->rollbackCandidateTransaction($useSavepoint);
            throw $e;
        }
    }

    public function finishJob(int $jobId, int $rawCount, int $validCount): void
    {
        $stmt = $this->db->prepare("UPDATE collector_jobs SET status='completed',completed_at=NOW(),raw_count=:raw_count,valid_count=:valid_count WHERE id=:id");
        $stmt->execute(['raw_count' => $rawCount, 'valid_count' => $validCount, 'id' => $jobId]);
    }

    private function normalizeEvidenceLevel(string $level): string
    {
        return in_array($level, ['A', 'B', 'C', 'D', 'E'], true) ? $level : 'C';
    }

    private function evidenceLevelNumber(string $level): int
    {
        return ['E' => 1, 'D' => 2, 'C' => 3, 'B' => 4, 'A' => 5][$this->normalizeEvidenceLevel($level)] ?? 3;
    }

    /**
     * @param array<int,string> $reasonCodes
     * @return array<int,string>
     */
    private function qualityFlags(array $reasonCodes): array
    {
        return array_values(array_filter(array_unique($reasonCodes), static function (string $code): bool {
            return $code !== 'PASSES_RULES'
                && !str_starts_with($code, 'validation_version:')
                && !str_starts_with($code, 'category_validation_version:');
        }));
    }

    private function finishCandidateTransaction(bool $useSavepoint): void
    {
        if ($useSavepoint) {
            $this->db->exec('RELEASE SAVEPOINT collection_pipeline_candidate');
            return;
        }
        $this->db->commit();
    }

    private function rollbackCandidateTransaction(bool $useSavepoint): void
    {
        if ($useSavepoint) {
            $this->db->exec('ROLLBACK TO SAVEPOINT collection_pipeline_candidate');
            $this->db->exec('RELEASE SAVEPOINT collection_pipeline_candidate');
            return;
        }
        $this->db->rollBack();
    }
}
