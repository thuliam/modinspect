<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Data\SearchCandidate;
use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Validation\ModelMarker;
use App\Services\Validation\ObservationValidator;

final class PricezaProbeCollector
{
    private const MODE_PROBE = 'probe';
    private const MODE_BATCH = 'batch';

    private const BATCH_NOTE = 'Phase 3H-R1D authoritative REAL batch';

    private const PREVIOUS_PROBE_ITEM_IDS = [
        '780264444',
        '797673491',
        '865320369',
        '847980332',
        '860537750',
        '786505362',
        '866874820',
        '844150666',
        '788076753',
        '695454085',
        '854060677',
        '861358015',
        '844902990',
        '834580937',
        '858786738',
        '865141736',
    ];

    private const SEARCHES = [
        ['category' => 'cpu', 'product_hint' => 'AMD Ryzen 7 5700X3D', 'query' => 'ryzen 7 5700x3d มือสอง'],
        ['category' => 'cpu', 'product_hint' => 'AMD Ryzen 5 5600', 'query' => 'ryzen 5 5600 มือสอง'],
        ['category' => 'gpu', 'product_hint' => 'NVIDIA GeForce RTX 3070', 'query' => 'rtx 3070 มือสอง'],
        ['category' => 'gpu', 'product_hint' => 'NVIDIA GeForce RTX 4070', 'query' => 'rtx 4070 มือสอง'],
        ['category' => 'gpu', 'product_hint' => 'NVIDIA GeForce RTX 3060 Ti', 'query' => 'rtx 3060 ti มือสอง'],
        ['category' => 'gpu', 'product_hint' => 'AMD Radeon RX 6600 XT', 'query' => 'rx 6600 xt มือสอง'],
        ['category' => 'cpu', 'product_hint' => 'AMD Ryzen 7 5800X', 'query' => 'ryzen 7 5800x มือสอง'],
    ];

    public function collect(int $limit = 8, bool $import = false): array
    {
        return $this->collectForMode($limit, $import, self::MODE_PROBE);
    }

    public function collectAuthoritativeBatch(int $limit = 40, bool $import = false): array
    {
        return $this->collectForMode($limit, $import, self::MODE_BATCH);
    }

    private function collectForMode(int $limit, bool $import, string $mode): array
    {
        $isBatch = $mode === self::MODE_BATCH;
        $limit = $isBatch ? max(30, min(50, $limit)) : max(1, min(10, $limit));
        $startedAt = date('Y-m-d H:i:s');
        $records = [];
        $diagnostics = [];
        $perSearchTarget = $isBatch ? 10 : 2;
        $seenSourceItemIds = $this->initialSkippedSourceItemIds($isBatch);
        $seenListingKeys = [];
        $skipCounts = [
            'previous_or_duplicate_source_item_id' => 0,
            'duplicate_listing_key' => 0,
            'bad_variant_or_context' => 0,
            'missing_required_fields' => 0,
        ];
        $runId = ($isBatch ? 'priceza-batch-' : 'priceza-probe-') . date('YmdHis') . '-' . bin2hex(random_bytes(3));

        foreach (self::SEARCHES as $search) {
            if (count($records) >= $limit) {
                break;
            }
            $searchUrl = $this->searchUrl($search['query']);
            $html = $this->fetch($searchUrl);
            if ($html === null) {
                $diagnostics[] = ['query' => $search['query'], 'search_url' => $searchUrl, 'html_bytes' => 0, 'items_parsed' => 0];
                continue;
            }
            $items = $this->parseItems($html, $searchUrl, (string)$search['product_hint'], (string)$search['category'], $startedAt, $runId, $seenSourceItemIds, $seenListingKeys, $skipCounts, $isBatch);
            $diagnostics[] = ['query' => $search['query'], 'search_url' => $searchUrl, 'html_bytes' => strlen($html), 'items_parsed' => count($items)];
            foreach (array_slice($items, 0, $perSearchTarget) as $item) {
                $records[] = $item;
                if (count($records) >= $limit) {
                    break 2;
                }
            }
        }

        $dryRunSummary = null;
        if ($isBatch && count($records) > 0) {
            $dryRunFile = $this->writeImportFile($records, self::MODE_BATCH);
            $dryRunSummary = (new OfflineEvidenceImportService())->import($dryRunFile, 'real', true, count($records));
        }

        $summary = [
            'ok' => $isBatch ? $this->batchReady($records, $dryRunSummary) : count($records) >= 6,
            'mode' => $import ? 'import' : 'dry_run',
            'collection_mode' => $mode,
            'run_id' => $runId,
            'probe_run_id' => $runId,
            'records_collected' => count($records),
            'products_represented' => array_values(array_unique(array_map(static fn(array $row): string => (string)$row['product_hint'], $records))),
            'rows_per_product' => $this->rowsPerProduct($records),
            'skip_counts' => $skipCounts,
            'diagnostics' => $diagnostics,
            'records' => $this->recordSummary($records),
            'dry_run_import_summary' => $dryRunSummary,
            'import_summary' => null,
        ];

        if ($import && count($records) > 0 && (!$isBatch || $this->batchReady($records, $dryRunSummary))) {
            $file = $this->writeImportFile($records, $mode);
            $summary['import_file'] = $file;
            $summary['import_summary'] = (new OfflineEvidenceImportService())->import($file, 'real', false, count($records));
        }

        return $summary;
    }

    private function searchUrl(string $query): string
    {
        return 'https://www.priceza.com/s/%E0%B8%A3%E0%B8%B2%E0%B8%84%E0%B8%B2/' . rawurlencode(str_replace(' ', '-', $query));
    }

    private function fetch(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "Accept: text/html\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        return is_string($body) && $body !== '' ? $body : null;
    }

    /**
     * @param array<string,bool> $seenSourceItemIds
     * @param array<string,bool> $seenListingKeys
     * @param array<string,int> $skipCounts
     */
    private function parseItems(string $html, string $searchUrl, string $productHint, string $category, string $observedAt, string $runId, array &$seenSourceItemIds, array &$seenListingKeys, array &$skipCounts, bool $isBatch): array
    {
        $items = [];
        $parts = preg_split('/data-productid="/i', $html);
        if (!is_array($parts) || count($parts) < 2) {
            return $items;
        }

        foreach (array_slice($parts, 1) as $part) {
            $sourceItemId = $this->firstMatch('/^([0-9]+)/', $part);
            if ($sourceItemId === '') {
                continue;
            }
            if (isset($seenSourceItemIds[$sourceItemId]) || in_array($sourceItemId, self::PREVIOUS_PROBE_ITEM_IDS, true)) {
                $skipCounts['previous_or_duplicate_source_item_id']++;
                continue;
            }
            $card = substr($part, 0, 9000);
            $title = $this->clean($this->firstMatch('/<div class="pz-pdb_name[^>]*title="ราคา\s*([^"]+)"/i', $card));
            if ($title === '') {
                $title = $this->clean(strip_tags($this->firstMatch('/<div class="pz-pdb_name\b[^>]*>(.*?)<\/div>/is', $card)));
            }
            if ($title === '') {
                $title = $this->clean($this->firstMatch('/alt="ราคา\s*([^"]+)"/i', $card));
            }
            $price = $this->firstMatch('/<span content="([0-9]+(?:\.[0-9]+)?)">/i', $card);
            $merchants = $this->allMatches('/<span class="pz-pdb_merchant-seller[^"]*">([^<]+)<\/span>/i', $card);
            $merchant = $merchants !== [] ? $this->clean((string)end($merchants)) : '';
            if ($merchant === '') {
                $merchant = $this->clean($this->firstMatch('/ร้าน\s*([^:<]+):/i', $card));
            }
            if ($title === '' || $price === '' || $merchant === '') {
                $skipCounts['missing_required_fields']++;
                continue;
            }
            $listingKey = $this->listingKey($merchant, $title, $price);
            if (isset($seenListingKeys[$listingKey])) {
                $skipCounts['duplicate_listing_key']++;
                continue;
            }
            $acceptance = $this->acceptableProbeItem($title, $price, $productHint);
            if (!$acceptance['accepted']) {
                $skipCounts['bad_variant_or_context']++;
                continue;
            }

            $sourceListingUrl = 'https://www.priceza.com/r/redirect?id=' . $sourceItemId;
            $merchantTargetUrl = $this->merchantTargetUrl($sourceListingUrl);
            $snapshot = [
                'source' => 'priceza',
                'source_search_url' => $searchUrl,
                'source_listing_url' => $sourceListingUrl,
                'source_item_id' => $sourceItemId,
                'merchant' => $merchant,
                'listing_title' => $title,
                'asking_price' => $price,
                'observed_at' => $observedAt,
                'merchant_target_url' => $merchantTargetUrl,
                'product_hint' => $productHint,
                'product_master_id' => $acceptance['product_id'],
                'category' => $category,
                'probe_run_id' => $runId,
                'batch_type' => $isBatch ? 'authoritative_real_batch' : 'authoritative_probe',
            ];
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($snapshotJson)) {
                continue;
            }
            $evidenceHash = hash('sha256', $snapshotJson);

            $items[] = [
                'source' => 'priceza',
                'source_type' => 'priceza_public_result_probe',
                'source_search_url' => $searchUrl,
                'source_listing_url' => $sourceListingUrl,
                'source_item_id' => $sourceItemId,
                'external_ref' => 'priceza:' . $sourceItemId,
                'source_url' => $sourceListingUrl,
                'source_domain' => 'priceza.com',
                'merchant' => $merchant,
                'merchant_target_url' => $merchantTargetUrl ?? '',
                'listing_title' => $title,
                'title' => $title,
                'listing_text' => '',
                'asking_price' => $price,
                'displayed_price' => $price,
                'currency' => 'THB',
                'observed_at' => $observedAt,
                'product_hint' => $productHint,
                'product_master_id' => $acceptance['product_id'],
                'probe_run_id' => $runId,
                'evidence_snapshot_json' => $snapshotJson,
                'evidence_hash' => $evidenceHash,
                'ingestion_note' => $isBatch
                    ? 'Priceza authoritative REAL batch; public result item fields only; no private seller PII collected.'
                    : 'Priceza authoritative small probe; public result item fields only; no private seller PII collected.',
                'notes' => $isBatch ? self::BATCH_NOTE : 'Phase 3H-R1C authoritative provenance probe',
            ];
            $seenSourceItemIds[$sourceItemId] = true;
            $seenListingKeys[$listingKey] = true;
        }

        return $items;
    }

    private function isAcceptableProbeItem(string $title, string $price, string $productHint): bool
    {
        return $this->acceptableProbeItem($title, $price, $productHint)['accepted'];
    }

    /**
     * @return array{accepted:bool,product_id:?int,reason:string}
     */
    private function acceptableProbeItem(string $title, string $price, string $productHint): array
    {
        if (!$this->hasExactTargetModel($title, $productHint)) {
            return ['accepted' => false, 'product_id' => null, 'reason' => 'MODEL_MARKER_MISMATCH'];
        }
        if ($this->hasRejectedContext($title)) {
            return ['accepted' => false, 'product_id' => null, 'reason' => 'REJECTED_CONTEXT'];
        }

        $candidate = new SearchCandidate(
            'Priceza public result probe',
            $title,
            $price,
            null,
            date('Y-m-d H:i:s'),
            'B',
            '',
            'priceza_public_result_probe'
        );
        $pipeline = new CollectionPipeline();
        $resolver = new ProductResolver($pipeline->catalog());
        $extraction = (new RuleBasedListingExtractor())->extract($candidate);
        $resolution = $resolver->resolve($title);
        $validation = (new ObservationValidator(false))->validate($extraction, $resolution);

        $accepted = $validation->lane !== 'red'
            && $resolution->matchedName === $productHint
            && $resolution->confidence >= 0.80;

        return [
            'accepted' => $accepted,
            'product_id' => $accepted ? $resolution->productId : null,
            'reason' => $accepted ? 'ACCEPTED' : 'VALIDATION_OR_RESOLUTION_FAILED',
        ];
    }

    private function hasExactTargetModel(string $title, string $productHint): bool
    {
        $targetMarker = ModelMarker::primary($productHint);
        if ($targetMarker === null) {
            return false;
        }
        $titleMarkers = ModelMarker::all($title);
        if (!in_array($targetMarker, $titleMarkers, true)) {
            return false;
        }
        $sameCategoryMarkers = array_values(array_filter($titleMarkers, static fn(string $marker): bool => ModelMarker::category($marker) === ModelMarker::category($targetMarker)));
        return $sameCategoryMarkers === [$targetMarker];
    }

    private function hasRejectedContext(string $title): bool
    {
        $text = mb_strtolower($title, 'UTF-8');
        return (bool)preg_match('/\b(full\s*pc|set\s*pc|computer\s*set|gaming\s*pc|pc\s*build|bundle|combo|accessory|adapter|bracket|fan|cooler|heatsink|case|notebook|laptop|mobile|wanted|buying|wtb|deposit|installment|monthly)\b|\+|ชุด|เซ็ต|คอมทั้งชุด|ทั้งเครื่อง|ครบชุด|ยกเครื่อง|รับซื้อ|ตามหา|มัดจำ|ดาวน์|ผ่อน|จอง|อะไหล่|ซ่อม|เสีย/u', $text);
    }

    private function merchantTargetUrl(string $sourceListingUrl): ?string
    {
        $html = $this->fetch($sourceListingUrl);
        if ($html === null) {
            return null;
        }
        $encoded = $this->firstMatch('/origin_link=([^"&\\\\]+(?:\\\\\/[^"&\\\\]+)*)/i', $html);
        if ($encoded === '') {
            return null;
        }
        $encoded = str_replace('\/', '/', $encoded);
        $decoded = urldecode($encoded);
        return preg_match('#^https?://#i', $decoded) === 1 ? $decoded : null;
    }

    private function writeImportFile(array $records, string $mode = self::MODE_PROBE): string
    {
        $dir = ROOT_PATH . '/storage/import';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $prefix = $mode === self::MODE_BATCH ? 'priceza_authoritative_batch_' : 'priceza_authoritative_probe_';
        $relative = 'storage/import/' . $prefix . date('Ymd_His') . '.json';
        $path = ROOT_PATH . '/' . $relative;
        file_put_contents($path, json_encode(['records' => $records], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $relative;
    }

    private function recordSummary(array $records): array
    {
        return array_map(static fn(array $row): array => [
            'source_item_id' => $row['source_item_id'],
            'product_hint' => $row['product_hint'],
            'product_master_id' => $row['product_master_id'] ?? null,
            'title' => $row['listing_title'],
            'asking_price' => $row['asking_price'],
            'merchant' => $row['merchant'],
            'source_listing_url' => $row['source_listing_url'],
            'merchant_target_url_captured' => $row['merchant_target_url'] !== '',
            'evidence_hash' => $row['evidence_hash'],
        ], $records);
    }

    /**
     * @return array<string,bool>
     */
    private function initialSkippedSourceItemIds(bool $includeExistingEvidence): array
    {
        $ids = array_fill_keys(self::PREVIOUS_PROBE_ITEM_IDS, true);
        if (!$includeExistingEvidence) {
            return $ids;
        }

        global $config;
        $db = Database::connection($config['db']);
        $stmt = $db->query(
            "SELECT e.excerpt
             FROM market_evidence e
             WHERE e.excerpt LIKE '%SOURCE=priceza%'
               AND e.excerpt LIKE '%SOURCE_ITEM_ID=%'"
        );
        foreach ($stmt->fetchAll() as $row) {
            if (preg_match('/(?:^|\R)SOURCE_ITEM_ID=([0-9]+)/', (string)$row['excerpt'], $match) === 1) {
                $ids[(string)$match[1]] = true;
            }
        }
        return $ids;
    }

    /**
     * @param array<int,array<string,mixed>> $records
     * @return array<string,int>
     */
    private function rowsPerProduct(array $records): array
    {
        $counts = [];
        foreach ($records as $record) {
            $product = (string)($record['product_hint'] ?? 'UNKNOWN');
            $counts[$product] = ($counts[$product] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }

    private function batchReady(array $records, ?array $dryRunSummary): bool
    {
        if (count($records) < 30) {
            return false;
        }
        if ($dryRunSummary === null) {
            return false;
        }
        return (int)($dryRunSummary['valid_rows'] ?? 0) === count($records)
            && (int)($dryRunSummary['invalid_rows'] ?? 0) === 0
            && (int)($dryRunSummary['duplicate_rows'] ?? 0) === 0
            && (int)($dryRunSummary['source_policy_blocked'] ?? 0) === 0;
    }

    private function listingKey(string $merchant, string $title, string $price): string
    {
        return hash('sha256', mb_strtolower($merchant . '|' . $title . '|' . $price, 'UTF-8'));
    }

    private function firstMatch(string $pattern, string $subject): string
    {
        return preg_match($pattern, $subject, $matches) === 1 ? (string)$matches[1] : '';
    }

    private function allMatches(string $pattern, string $subject): array
    {
        if (preg_match_all($pattern, $subject, $matches) !== 1) {
            return [];
        }
        return $matches[1] ?? [];
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }
}
