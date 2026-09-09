<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use App\Data\SearchCandidate;
use App\Data\SearchRequest;
use App\Models\CollectionPipeline;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Providers\OfflineFileProvider;
use App\Services\Validation\ObservationValidator;
use PDO;

final class OfflineEvidenceImportService
{
    private const MAX_FILE_BYTES = 5242880;
    private const MAX_ROWS = 1000;
    private const MAX_TITLE_LENGTH = 240;
    private const MAX_TEXT_LENGTH = 2000;
    private const SUPPORTED_CURRENCIES = ['THB', ''];

    private PDO $db;
    private CollectionPipeline $pipeline;
    private RuleBasedListingExtractor $extractor;
    private ObservationValidator $validator;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
        $this->pipeline = new CollectionPipeline();
        $this->extractor = new RuleBasedListingExtractor();
        $this->validator = new ObservationValidator(false);
    }

    public function import(string $file, string $dataset = 'test', bool $dryRun = false, int $limit = 100): array
    {
        $started = microtime(true);
        $startedAt = date('Y-m-d H:i:s');
        $runId = 'offline-import-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $dataset = strtolower(trim($dataset)) === 'real' ? 'real' : 'test';
        $limit = max(1, min($limit, self::MAX_ROWS));

        $summary = [
            'run_id' => $runId,
            'provider' => 'offline_file_import',
            'dry_run' => $dryRun,
            'dataset' => strtoupper($dataset),
            'file' => basename($file),
            'format' => null,
            'records_scanned' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'source_policy_blocked' => 0,
            'estimated_candidate_count' => 0,
            'candidates_created' => 0,
            'evidence_created' => 0,
            'extractions_created' => 0,
            'reviews_created' => 0,
            'green' => 0,
            'amber' => 0,
            'red' => 0,
            'source_distribution' => [],
            'product_hints' => [],
            'row_errors' => [],
            'duration_ms' => 0,
        ];

        $path = $this->safePath($file);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            $summary['row_errors'][] = ['row' => null, 'errors' => ['FILE_NOT_READABLE']];
            $summary['invalid_rows']++;
            return $this->finishSummary($summary, $started);
        }
        if (filesize($path) > self::MAX_FILE_BYTES) {
            $summary['row_errors'][] = ['row' => null, 'errors' => ['FILE_TOO_LARGE']];
            $summary['invalid_rows']++;
            return $this->finishSummary($summary, $started);
        }

        try {
            [$format, $records] = $this->parseFile($path);
            $summary['format'] = $format;
        } catch (\Throwable $e) {
            $summary['row_errors'][] = ['row' => null, 'errors' => [$this->safeError($e->getMessage())]];
            $summary['invalid_rows']++;
            return $this->finishSummary($summary, $started);
        }

        $records = array_slice($records, 0, $limit);
        $summary['records_scanned'] = count($records);
        $planned = [];

        foreach ($records as $record) {
            $rowNumber = (int)$record['_row'];
            $validation = $this->validateRecord($record, $dataset);
            if ($validation !== []) {
                $summary['invalid_rows']++;
                $summary['row_errors'][] = ['row' => $rowNumber, 'errors' => $validation];
                continue;
            }

            $sourceKey = $this->sourceKey($record, $dataset);
            $sourceName = $this->sourceName($record, $dataset);
            $summary['source_distribution'][$sourceKey] = ($summary['source_distribution'][$sourceKey] ?? 0) + 1;
            if (!empty($record['product_hint'])) {
                $summary['product_hints'][] = (string)$record['product_hint'];
            }

            $candidate = $this->candidateFromRecord($record, $dataset, $runId, $sourceName);
            $hash = $this->candidateHash($candidate);
            if ($this->isDuplicate($sourceKey, $hash)) {
                $summary['duplicate_rows']++;
                continue;
            }

            if (!$dryRun) {
                $source = $this->ensureSource($record, $dataset, $sourceKey, $sourceName);
                if (!$this->sourceCanImport($source)) {
                    $summary['source_policy_blocked']++;
                    $summary['row_errors'][] = ['row' => $rowNumber, 'errors' => ['SOURCE_PAUSED_OR_DISABLED']];
                    continue;
                }
                $planned[] = ['record' => $record, 'candidate' => $candidate, 'source_id' => (int)$source['id']];
            } else {
                $planned[] = ['record' => $record, 'candidate' => $candidate, 'source_id' => null];
            }
            $summary['valid_rows']++;
        }

        $summary['product_hints'] = array_values(array_unique($summary['product_hints']));
        $summary['estimated_candidate_count'] = count($planned);
        ksort($summary['source_distribution']);

        if ($dryRun) {
            return $this->finishSummary($summary, $started);
        }

        $provider = new OfflineFileProvider(array_column($planned, 'candidate'));
        $resultSet = $provider->search(new SearchRequest('offline_file_import', null, count($planned)));
        $resolver = new ProductResolver($this->pipeline->catalog());

        foreach ($planned as $index => $item) {
            /** @var SearchCandidate $candidate */
            $candidate = $resultSet->candidates[$index];
            $sourceId = (int)$item['source_id'];
            $jobId = $this->startImportJob($sourceId, $runId, $candidate);
            $extraction = $this->extractor->extract($candidate);
            $resolution = $resolver->resolve($candidate->title);
            $validation = $this->validator->validate($extraction, $resolution);
            $created = $this->pipeline->persistCandidate($sourceId, $jobId, $candidate, $extraction, $resolution, $validation);
            $summary[$validation->lane]++;
            if ($created) {
                $summary['candidates_created']++;
                $summary['evidence_created']++;
                $summary['extractions_created']++;
                $summary['reviews_created']++;
            } else {
                $summary['duplicate_rows']++;
            }
            $this->completeImportJob($jobId, $created, $validation->lane, $candidate);
        }

        $summary = $this->finishSummary($summary, $started);
        $runSourceId = $this->ensureImportRunSource($dataset);
        (new CollectionRunRepository())->persist($this->collectionRunSummary($summary), $runSourceId, 'offline_file_import', 'offline_file_import', 'normal', $startedAt, date('Y-m-d H:i:s'));
        $this->audit('offline_import_completed', 'collection_run', null, null, $summary);
        return $summary;
    }

    private function safePath(string $file): ?string
    {
        $file = trim($file);
        if ($file === '' || str_contains($file, "\0")) {
            return null;
        }
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $file)) {
            return $file;
        }
        return ROOT_PATH . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
    }

    private function parseFile(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            $rows = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (isset($rows['records']) && is_array($rows['records'])) {
                $rows = $rows['records'];
            }
            if (!is_array($rows)) {
                throw new \RuntimeException('MALFORMED_JSON');
            }
            $records = [];
            foreach (array_values($rows) as $i => $row) {
                if (is_array($row)) {
                    $row['_row'] = $i + 1;
                    $records[] = $row;
                }
            }
            return ['json', $records];
        }
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            if (!$handle) {
                throw new \RuntimeException('CSV_NOT_READABLE');
            }
            $header = fgetcsv($handle);
            if (!is_array($header)) {
                fclose($handle);
                throw new \RuntimeException('CSV_EMPTY');
            }
            $header = array_map(static fn(string $value): string => trim($value), $header);
            $required = ['title', 'listing_text', 'observed_at'];
            foreach ($required as $field) {
                if (!in_array($field, $header, true)) {
                    fclose($handle);
                    throw new \RuntimeException('CSV_MISSING_HEADER_' . strtoupper($field));
                }
            }
            $records = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false && count($records) < self::MAX_ROWS) {
                $rowNumber++;
                $item = [];
                foreach ($header as $i => $field) {
                    $item[$field] = $row[$i] ?? null;
                }
                $item['_row'] = $rowNumber;
                $records[] = $item;
            }
            fclose($handle);
            return ['csv', $records];
        }
        throw new \RuntimeException('UNSUPPORTED_FORMAT');
    }

    private function validateRecord(array $record, string $dataset): array
    {
        $errors = [];
        $title = trim((string)($record['title'] ?? ''));
        $text = trim((string)($record['listing_text'] ?? ''));
        $url = trim((string)($record['source_url'] ?? ''));
        $reference = trim((string)($record['source_reference'] ?? $record['external_listing_id'] ?? ''));
        $currency = strtoupper(trim((string)($record['currency'] ?? 'THB')));
        $price = trim((string)($record['displayed_price'] ?? ''));
        $observedAt = trim((string)($record['observed_at'] ?? ''));

        if ($title === '' && $text === '') $errors[] = 'MISSING_TITLE_OR_TEXT';
        if (mb_strlen($title, 'UTF-8') > self::MAX_TITLE_LENGTH) $errors[] = 'TITLE_TOO_LONG';
        if (mb_strlen($text, 'UTF-8') > self::MAX_TEXT_LENGTH) $errors[] = 'LISTING_TEXT_TOO_LONG';
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'INVALID_URL';
        if ($dataset === 'real' && $url === '' && $reference === '') $errors[] = 'REAL_DATASET_REQUIRES_EXTERNAL_REFERENCE';
        if ($dataset === 'real' && $this->looksMockOrTest($record)) $errors[] = 'REAL_DATASET_CANNOT_USE_MOCK_TEST_SOURCE';
        if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) $errors[] = 'UNSUPPORTED_CURRENCY';
        if ($price !== '') {
            $normalized = str_replace([',', '฿', 'บาท', 'THB', 'thb'], '', $price);
            if (!is_numeric(trim($normalized)) || (float)$normalized <= 0 || (float)$normalized > 10000000) {
                $errors[] = 'INVALID_PRICE_HINT';
            }
        }
        if ($observedAt === '' || strtotime($observedAt) === false) {
            $errors[] = 'INVALID_OBSERVED_DATE';
        } elseif (strtotime($observedAt) > time() + 86400) {
            $errors[] = 'FUTURE_OBSERVED_DATE';
        }
        return array_values(array_unique($errors));
    }

    private function candidateFromRecord(array $record, string $dataset, string $runId, string $sourceName): SearchCandidate
    {
        $title = trim((string)($record['title'] ?? ''));
        $text = trim((string)($record['listing_text'] ?? ''));
        $combined = trim($title . ' ' . $text);
        if (mb_strlen($combined, 'UTF-8') > 490) {
            $combined = mb_substr($combined, 0, 490, 'UTF-8');
        }
        $url = trim((string)($record['source_url'] ?? ''));
        $reference = trim((string)($record['source_reference'] ?? $record['external_listing_id'] ?? ''));
        if ($url === '' && $reference !== '') {
            $url = 'offline-ref://' . rawurlencode($reference);
        }
        return new SearchCandidate(
            $sourceName,
            $combined,
            trim((string)($record['displayed_price'] ?? '')) ?: null,
            $url ?: null,
            date('Y-m-d H:i:s', strtotime((string)$record['observed_at'])),
            $dataset === 'real' ? 'B' : 'C',
            $this->snippet($record, $dataset, $runId),
            'offline_file_import',
            null,
            'offline_import',
            date('Y-m-d H:i:s'),
            [
                'import_run_id' => $runId,
                'dataset' => strtoupper($dataset),
                'row' => (int)$record['_row'],
                'source_reference' => $reference ?: null,
                'source_domain' => $record['source_domain'] ?? null,
            ]
        );
    }

    private function snippet(array $record, string $dataset, string $runId): string
    {
        return trim(implode("\n", array_filter([
            'IMPORT_PROVIDER=offline_file_import',
            'IMPORT_RUN_ID=' . $runId,
            'DATASET=' . strtoupper($dataset),
            'ROW=' . (string)$record['_row'],
            isset($record['notes']) ? 'NOTES=' . trim((string)$record['notes']) : null,
        ])));
    }

    private function sourceKey(array $record, string $dataset): string
    {
        $domain = strtolower(trim((string)($record['source_domain'] ?? '')));
        if ($domain === '' && !empty($record['source_url']) && filter_var((string)$record['source_url'], FILTER_VALIDATE_URL)) {
            $domain = strtolower((string)(parse_url((string)$record['source_url'], PHP_URL_HOST) ?: 'offline'));
        }
        if ($domain === '') {
            $domain = 'offline_reference';
        }
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $domain) ?: 'offline_reference';
        return 'offline_' . $dataset . '_' . trim($normalized, '_');
    }

    private function sourceName(array $record, string $dataset): string
    {
        $domain = trim((string)($record['source_domain'] ?? ''));
        if ($domain === '' && !empty($record['source_url']) && filter_var((string)$record['source_url'], FILTER_VALIDATE_URL)) {
            $domain = (string)(parse_url((string)$record['source_url'], PHP_URL_HOST) ?: 'offline reference');
        }
        return 'Offline ' . strtoupper($dataset) . ' Import - ' . ($domain ?: 'external reference');
    }

    private function ensureSource(array $record, string $dataset, string $sourceKey, string $sourceName): array
    {
        $stmt = $this->db->prepare("SELECT * FROM data_sources WHERE source_key=:source_key LIMIT 1");
        $stmt->execute(['source_key' => $sourceKey]);
        $source = $stmt->fetch();
        if ($source) {
            return $source;
        }
        $domain = trim((string)($record['source_domain'] ?? ''));
        if ($domain === '' && !empty($record['source_url']) && filter_var((string)$record['source_url'], FILTER_VALIDATE_URL)) {
            $domain = (string)(parse_url((string)$record['source_url'], PHP_URL_HOST) ?: '');
        }
        $insert = $this->db->prepare(
            "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,terms_note,policy_note,is_active,is_paused,last_success_at)
             VALUES (:source_key,:name,:domain,'marketplace','manual','manual','medium',60,:quality,14,:terms,:policy,1,0,NOW())"
        );
        $insert->execute([
            'source_key' => $sourceKey,
            'name' => $sourceName,
            'domain' => $domain ?: null,
            'quality' => $dataset === 'real' ? 'medium' : 'low',
            'terms' => 'Offline file import source. Import does not perform scraping or network access.',
            'policy' => strtoupper($dataset) . ' dataset classification declared by import CLI.',
        ]);
        $stmt->execute(['source_key' => $sourceKey]);
        return $stmt->fetch();
    }

    private function ensureImportRunSource(string $dataset): int
    {
        $key = 'offline_file_import_' . $dataset;
        $stmt = $this->db->prepare("SELECT id FROM data_sources WHERE source_key=:source_key LIMIT 1");
        $stmt->execute(['source_key' => $key]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $insert = $this->db->prepare(
            "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,terms_note,policy_note,is_active,is_paused,last_success_at)
             VALUES (:source_key,:name,NULL,'manual','manual','manual','low',50,:quality,14,'Local offline import run summary source.','No network access; stores import-run audit only.',1,0,NOW())"
        );
        $insert->execute([
            'source_key' => $key,
            'name' => 'Offline File Import ' . strtoupper($dataset),
            'quality' => $dataset === 'real' ? 'medium' : 'low',
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function sourceCanImport(array $source): bool
    {
        return (int)$source['is_active'] === 1 && (int)$source['is_paused'] === 0 && (string)$source['allowed_collection_method'] === 'manual';
    }

    private function isDuplicate(string $sourceKey, string $hash): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM raw_price_observations r JOIN data_sources s ON s.id=r.source_id WHERE s.source_key=:source_key AND r.external_reference_hash=:hash"
        );
        $stmt->execute(['source_key' => $sourceKey, 'hash' => $hash]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function candidateHash(SearchCandidate $candidate): string
    {
        return hash('sha256', $candidate->sourceName . '|' . ($candidate->url ?? '') . '|' . $candidate->title . '|' . (string)$candidate->priceText);
    }

    private function looksMockOrTest(array $record): bool
    {
        $text = strtolower(implode(' ', [
            (string)($record['source_type'] ?? ''),
            (string)($record['source_domain'] ?? ''),
            (string)($record['source_url'] ?? ''),
            (string)($record['source_reference'] ?? ''),
        ]));
        return str_contains($text, 'mock') || str_contains($text, 'fixture') || str_contains($text, 'test');
    }

    private function startImportJob(int $sourceId, string $runId, SearchCandidate $candidate): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO collector_jobs (source_id,job_type,query_text,status,attempt_count,run_id,started_at,created_at,updated_at)
             VALUES (:source_id,'offline_file_import',:query,'running',1,:run_id,NOW(),NOW(),NOW())"
        );
        $stmt->execute(['source_id' => $sourceId, 'query' => mb_substr($candidate->title, 0, 240, 'UTF-8'), 'run_id' => $runId]);
        return (int)$this->db->lastInsertId();
    }

    private function completeImportJob(int $jobId, bool $created, string $lane, SearchCandidate $candidate): void
    {
        $summary = ['provider' => 'offline_file_import', 'lane' => $lane, 'dataset' => $candidate->rawMetadata['dataset'] ?? null, 'row' => $candidate->rawMetadata['row'] ?? null, 'created' => $created];
        $stmt = $this->db->prepare("UPDATE collector_jobs SET status='completed',completed_at=NOW(),raw_count=:raw_count,valid_count=:valid_count,error_message=:summary,updated_at=NOW() WHERE id=:id");
        $stmt->execute(['raw_count' => $created ? 1 : 0, 'valid_count' => $created && $lane !== 'red' ? 1 : 0, 'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE), 'id' => $jobId]);
    }

    private function collectionRunSummary(array $summary): array
    {
        return [
            'run_id' => $summary['run_id'],
            'duration_ms' => $summary['duration_ms'],
            'jobs_scanned' => $summary['records_scanned'],
            'jobs_claimed' => $summary['estimated_candidate_count'],
            'jobs_completed' => $summary['estimated_candidate_count'],
            'jobs_failed' => 0,
            'jobs_skipped' => $summary['invalid_rows'] + $summary['duplicate_rows'] + $summary['source_policy_blocked'],
            'candidates_created' => $summary['candidates_created'],
            'evidence_created' => $summary['evidence_created'],
            'extractions_created' => $summary['extractions_created'],
            'reviews_created' => $summary['reviews_created'],
            'green' => $summary['green'],
            'amber' => $summary['amber'],
            'red' => $summary['red'],
            'jobs' => [],
            'offline_import' => $summary,
        ];
    }

    private function finishSummary(array $summary, float $started): array
    {
        $summary['duration_ms'] = (int)round((microtime(true) - $started) * 1000);
        return $summary;
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

    private function safeError(string $message): string
    {
        return substr(preg_replace('/[\\r\\n]+/', ' ', $message) ?? 'UNKNOWN_ERROR', 0, 200);
    }
}
