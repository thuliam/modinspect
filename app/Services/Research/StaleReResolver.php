<?php
declare(strict_types=1);

namespace App\Services\Research;

use App\Data\ExtractedObservation;
use App\Data\ProductResolution;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Validation\ObservationValidator;
use PDO;

/**
 * LS-4 R-1: the READ-ONLY decision of the re-resolve design (docs/RERESOLVE_STALE_DESIGN.md). For every undecided row of the unresolved queue it answers
 * "would the current catalog turn this stored row into a pending observation, and if not, why not". It reuses the STORED extraction (no re-extraction),
 * resolves with the current resolver and validates with the same validator the importer uses. It never writes. Output is counts and product ids: no text.
 */
final class StaleReResolver
{
    public const SKIPS = ['NOT_OWNER_TEXT_SOURCE', 'ALREADY_HAS_OBSERVATION', 'NO_STORED_EXTRACTION', 'BUNDLE_WANTED_OR_DEPOSIT', 'STILL_UNRESOLVED',
        'NO_ASKING_PRICE', 'RED_AFTER_REVALIDATION', 'REPOST_OF_EXISTING_LISTING'];

    /**
     * @param array{source_key:string,title:string,old_reasons:list<string>,has_observation:bool,extraction:?array<string,mixed>} $row
     * @param array<string,bool> $knownKeys listing keys that already have an observation (OwnerIntakeDeduper::existingKeys), extended as rows are accepted
     * @return array{outcome:string,lane:?string,product_id:?int,key:?string}
     */
    public static function decide(array $row, ProductResolver $resolver, ObservationValidator $validator, array $knownKeys): array
    {
        $r = static fn(string $o, ?string $lane = null, ?int $pid = null, ?string $key = null): array => ['outcome' => $o, 'lane' => $lane, 'product_id' => $pid, 'key' => $key];
        if (!str_starts_with(strtolower($row['source_key']), 'offline_owner_text_')) { return $r('NOT_OWNER_TEXT_SOURCE'); }
        if ($row['has_observation']) { return $r('ALREADY_HAS_OBSERVATION'); }
        if ($row['extraction'] === null) { return $r('NO_STORED_EXTRACTION'); }
        if (array_intersect($row['old_reasons'], ['BUNDLE', 'WANTED', 'DEPOSIT']) !== []) { return $r('BUNDLE_WANTED_OR_DEPOSIT'); }
        $x = $row['extraction'];
        $resolution = $resolver->resolve($row['title']);
        if ($resolution->productId === null) { return $r('STILL_UNRESOLVED'); }
        $price = isset($x['askingPrice']) ? (float)$x['askingPrice'] : null;
        if ($price === null || $price <= 0) { return $r('NO_ASKING_PRICE'); }
        $extraction = new ExtractedObservation($price, (string)($x['currency'] ?? 'THB'), (string)($x['listingType'] ?? 'single'), (string)($x['conditionLevel'] ?? 'unknown'),
            isset($x['warrantyMonths']) ? (int)$x['warrantyMonths'] : null, isset($x['hasBox']) ? (bool)$x['hasBox'] : null, isset($x['hasReceipt']) ? (bool)$x['hasReceipt'] : null,
            (bool)($x['isWantedPost'] ?? false), (bool)($x['isDeposit'] ?? false), (bool)($x['isDefective'] ?? false), (bool)($x['isWholePc'] ?? false),
            (float)($x['confidence'] ?? 0.0), is_array($x['raw'] ?? null) ? $x['raw'] : []);
        $validation = $validator->validate($extraction, new ProductResolution($resolution->productId, $resolution->matchedName, $resolution->confidence, $resolution->method));
        if ($validation->lane === 'red') { return $r('RED_AFTER_REVALIDATION', 'red', $resolution->productId); }
        $key = OwnerIntakeDeduper::keyFor(OwnerIntakeDeduper::undouble($row['title']), (int)$price);
        if (isset($knownKeys[$key])) { return $r('REPOST_OF_EXISTING_LISTING', $validation->lane, $resolution->productId, $key); }
        return $r('would_create', $validation->lane, $resolution->productId, $key);
    }

    /**
     * @return array{total:int,outcomes:array<string,int>,lanes:array<string,int>,by_product:array<int,int>}
     */
    public static function report(PDO $db, ProductResolver $resolver, ObservationValidator $validator): array
    {
        $decided = '';
        $has019 = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'unresolved_listing_reviews'")->fetchColumn() === 1;
        if ($has019) { $decided = 'AND NOT EXISTS (SELECT 1 FROM unresolved_listing_reviews u WHERE u.raw_observation_id = r.id)'; }
        $rows = $db->query("SELECT r.id, r.raw_title, COALESCE(s.source_key,'') source_key, d.reason_codes,
                (SELECT COUNT(*) FROM price_observations o WHERE o.raw_observation_id = r.id) obs,
                (SELECT e.extracted_data FROM extraction_runs e WHERE e.raw_observation_id = r.id ORDER BY e.id DESC LIMIT 1) extraction
            FROM observation_review_decisions d
            JOIN raw_price_observations r ON r.id = d.raw_observation_id
            LEFT JOIN data_sources s ON s.id = r.source_id
            WHERE d.price_observation_id IS NULL AND d.reason_codes LIKE '%UNRESOLVED_PRODUCT%' $decided
            GROUP BY r.id ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC);
        $known = OwnerIntakeDeduper::existingKeys($db);
        $out = ['total' => count($rows), 'outcomes' => [], 'lanes' => [], 'by_product' => []];
        foreach ($rows as $row) {
            $x = $row['extraction'] !== null ? json_decode((string)$row['extraction'], true) : null;
            $d = self::decide([
                'source_key' => (string)$row['source_key'], 'title' => (string)$row['raw_title'],
                'old_reasons' => array_map('strval', (array)(json_decode((string)$row['reason_codes'], true) ?: [])),
                'has_observation' => (int)$row['obs'] > 0, 'extraction' => is_array($x) ? $x : null,
            ], $resolver, $validator, $known);
            $out['outcomes'][$d['outcome']] = ($out['outcomes'][$d['outcome']] ?? 0) + 1;
            if ($d['outcome'] === 'would_create') {
                $known[(string)$d['key']] = true;   // two stale rows of the same listing create one observation, not two
                $out['lanes'][(string)$d['lane']] = ($out['lanes'][(string)$d['lane']] ?? 0) + 1;
                $out['by_product'][(int)$d['product_id']] = ($out['by_product'][(int)$d['product_id']] ?? 0) + 1;
            }
        }
        ksort($out['outcomes']);
        return $out;
    }
}
