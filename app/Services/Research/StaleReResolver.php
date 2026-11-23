<?php
declare(strict_types=1);

namespace App\Services\Research;

use App\Data\ExtractedObservation;
use App\Data\ProductResolution;
use App\Services\Catalog\SpecClassResolver;
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
    /** LS-76: owner-transcribed Facebook captures stored under the REAL dataset are re-resolved too (every row still lands as PENDING; a person approves) */
    public const OWNER_TRANSCRIBED_REAL = ['offline_real_facebook_com', 'offline_real_www_facebook_com'];

    public const SKIPS = ['NOT_OWNER_TEXT_SOURCE', 'ALREADY_HAS_OBSERVATION', 'NO_STORED_EXTRACTION', 'BUNDLE_WANTED_OR_DEPOSIT', 'STILL_UNRESOLVED',
        'NO_ASKING_PRICE', 'RED_AFTER_REVALIDATION', 'REPOST_OF_EXISTING_LISTING'];

    /**
     * @param array{source_key:string,title:string,old_reasons:list<string>,has_observation:bool,extraction:?array<string,mixed>} $row
     * @param array<string,bool> $knownKeys listing keys that already have an observation (OwnerIntakeDeduper::existingKeys), extended as rows are accepted
     * @param array<string,int> $specClassIds LS-76: slug => product id of the active generic spec-class products (RAM, storage, PSU); a title that states a whole class resolves to it
     * @return array{outcome:string,lane:?string,product_id:?int,key:?string,price:?float,reasons:list<string>,method:?string}
     */
    public static function decide(array $row, ProductResolver $resolver, ObservationValidator $validator, array $knownKeys, array $specClassIds = []): array
    {
        $r = static fn(string $o, ?string $lane = null, ?int $pid = null, ?string $key = null, ?float $price = null, array $reasons = [], ?string $method = null): array => ['outcome' => $o, 'lane' => $lane, 'product_id' => $pid, 'key' => $key, 'price' => $price, 'reasons' => $reasons, 'method' => $method];
        $sk = strtolower($row['source_key']);
        if (!str_starts_with($sk, 'offline_owner_text_') && !in_array($sk, self::OWNER_TRANSCRIBED_REAL, true)) { return $r('NOT_OWNER_TEXT_SOURCE'); }
        if ($row['has_observation']) { return $r('ALREADY_HAS_OBSERVATION'); }
        if ($row['extraction'] === null) { return $r('NO_STORED_EXTRACTION'); }
        if (array_intersect($row['old_reasons'], ['BUNDLE', 'WANTED', 'DEPOSIT']) !== []) { return $r('BUNDLE_WANTED_OR_DEPOSIT'); }
        $x = $row['extraction'];
        $resolution = $resolver->resolve($row['title']);
        if ($resolution->productId === null) {
            $slug = SpecClassResolver::slugFor($row['title']);
            if ($slug !== null && isset($specClassIds[$slug])) { $resolution = new ProductResolution($specClassIds[$slug], $slug, 0.9, 'spec_class_from_title'); }
        }
        if ($resolution->productId === null) { return $r('STILL_UNRESOLVED'); }
        $price = isset($x['askingPrice']) ? (float)$x['askingPrice'] : null;
        if ($price === null || $price <= 0) { return $r('NO_ASKING_PRICE'); }
        $extraction = new ExtractedObservation($price, (string)($x['currency'] ?? 'THB'), (string)($x['listingType'] ?? 'single'), (string)($x['conditionLevel'] ?? 'unknown'),
            isset($x['warrantyMonths']) ? (int)$x['warrantyMonths'] : null, isset($x['hasBox']) ? (bool)$x['hasBox'] : null, isset($x['hasReceipt']) ? (bool)$x['hasReceipt'] : null,
            (bool)($x['isWantedPost'] ?? false), (bool)($x['isDeposit'] ?? false), (bool)($x['isDefective'] ?? false), (bool)($x['isWholePc'] ?? false),
            (float)($x['confidence'] ?? 0.0), is_array($x['raw'] ?? null) ? $x['raw'] : []);
        $validation = $validator->validate($extraction, new ProductResolution($resolution->productId, $resolution->matchedName, $resolution->confidence, $resolution->method));
        if ($validation->lane === 'red') { return $r('RED_AFTER_REVALIDATION', 'red', $resolution->productId, null, $price, $validation->reasonCodes, $resolution->method); }
        $key = OwnerIntakeDeduper::keyFor(OwnerIntakeDeduper::undouble($row['title']), (int)$price);
        if (isset($knownKeys[$key])) { return $r('REPOST_OF_EXISTING_LISTING', $validation->lane, $resolution->productId, $key, $price, $validation->reasonCodes, $resolution->method); }
        return $r('would_create', $validation->lane, $resolution->productId, $key, $price, $validation->reasonCodes, $resolution->method);
    }

    /** LS-76: slug => id of the active generic spec-class products (RAM, storage, PSU) */
    public static function specClassIds(PDO $db): array
    {
        $ids = [];
        foreach ($db->query("SELECT p.slug, p.id FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.is_active = 1 AND c.slug IN ('ram', 'storage', 'psu')") as $r) { $ids[(string)$r['slug']] = (int)$r['id']; }
        return $ids;
    }

    /**
     * @param bool $apply with true, at most $limit PENDING observations are created; with false nothing is written
     * @return array{total:int,outcomes:array<string,int>,lanes:array<string,int>,by_product:array<int,int>,created:int}
     */
    public static function report(PDO $db, ProductResolver $resolver, ObservationValidator $validator, bool $apply = false, int $limit = 0): array
    {
        $specIds = self::specClassIds($db); $created = 0;
        $decided = '';
        $has019 = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'unresolved_listing_reviews'")->fetchColumn() === 1;
        if ($has019) { $decided = 'AND NOT EXISTS (SELECT 1 FROM unresolved_listing_reviews u WHERE u.raw_observation_id = r.id)'; }
        $rows = $db->query("SELECT r.id, r.raw_title, COALESCE(s.source_key,'') source_key, d.reason_codes,
                (SELECT COUNT(*) FROM price_observations o WHERE o.raw_observation_id = r.id) obs,
                (SELECT e.extracted_data FROM extraction_runs e WHERE e.raw_observation_id = r.id ORDER BY e.id DESC LIMIT 1) extraction,
                (SELECT e.id FROM extraction_runs e WHERE e.raw_observation_id = r.id ORDER BY e.id DESC LIMIT 1) extraction_id,
                (SELECT m.evidence_level FROM market_evidence m WHERE m.raw_observation_id = r.id ORDER BY m.id DESC LIMIT 1) evidence_level, r.captured_at
            FROM observation_review_decisions d
            JOIN raw_price_observations r ON r.id = d.raw_observation_id
            LEFT JOIN data_sources s ON s.id = r.source_id
            WHERE d.price_observation_id IS NULL AND d.reason_codes LIKE '%UNRESOLVED_PRODUCT%' $decided
            GROUP BY r.id ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC);
        $known = OwnerIntakeDeduper::existingKeys($db);
        $out = ['total' => count($rows), 'outcomes' => [], 'lanes' => [], 'by_product' => [], 'created' => 0];
        foreach ($rows as $row) {
            $x = $row['extraction'] !== null ? json_decode((string)$row['extraction'], true) : null;
            $d = self::decide([
                'source_key' => (string)$row['source_key'], 'title' => (string)$row['raw_title'],
                'old_reasons' => array_map('strval', (array)(json_decode((string)$row['reason_codes'], true) ?: [])),
                'has_observation' => (int)$row['obs'] > 0, 'extraction' => is_array($x) ? $x : null,
            ], $resolver, $validator, $known, $specIds);
            $out['outcomes'][$d['outcome']] = ($out['outcomes'][$d['outcome']] ?? 0) + 1;
            if ($d['outcome'] === 'would_create') {
                $known[(string)$d['key']] = true;   // two stale rows of the same listing create one observation, not two
                $out['lanes'][(string)$d['lane']] = ($out['lanes'][(string)$d['lane']] ?? 0) + 1;
                $out['by_product'][(int)$d['product_id']] = ($out['by_product'][(int)$d['product_id']] ?? 0) + 1;
                if ($apply && $created < $limit) { self::create($db, $row, $d, is_array($x) ? $x : []); $created++; }
            }
        }
        ksort($out['outcomes']);
        $out['created'] = $created;
        return $out;
    }

    /**
     * LS-76: the one write. A PENDING observation as the importer would have stored it, plus a NEW review decision on the same raw row and extraction run
     * (the old red decision stays as history). One transaction per row. Approval, prices and snapshots are untouched: a person still reviews.
     * @param array<string,mixed> $row
     * @param array<string,mixed> $d
     * @param array<string,mixed> $x
     */
    private static function create(PDO $db, array $row, array $d, array $x): void
    {
        $flags = array_values(array_filter(array_unique($d['reasons']), static fn(string $c): bool => $c !== 'PASSES_RULES' && !str_starts_with($c, 'validation_version:') && !str_starts_with($c, 'category_validation_version:')));
        $flags[] = 'RERESOLVED_AFTER_CATALOG_GROWTH';
        $flags[] = 'resolved_by:' . (string)$d['method'];
        $db->beginTransaction();
        try {
            $level = ['E' => 1, 'D' => 2, 'C' => 3, 'B' => 4, 'A' => 5][(string)($row['evidence_level'] ?? 'C')] ?? 3;
            $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,warranty_months,has_box,has_receipt,listing_type,is_deposit,is_defective,evidence_level,classification_confidence,quality_flags,verified_status,observed_at)
                VALUES (:raw_id,:product_id,'asking',:price,:price_value,:currency,:condition_level,:warranty,:has_box,:has_receipt,:listing_type,:is_deposit,:is_defective,:level,:confidence,:flags,'pending',:observed_at)")->execute([
                'raw_id' => (int)$row['id'], 'product_id' => (int)$d['product_id'], 'price' => $d['price'], 'price_value' => $d['price'], 'currency' => (string)($x['currency'] ?? 'THB'), 'condition_level' => (string)($x['conditionLevel'] ?? 'unknown'),
                'warranty' => isset($x['warrantyMonths']) ? (int)$x['warrantyMonths'] : null, 'has_box' => isset($x['hasBox']) ? (int)(bool)$x['hasBox'] : null, 'has_receipt' => isset($x['hasReceipt']) ? (int)(bool)$x['hasReceipt'] : null,
                'listing_type' => (string)($x['listingType'] ?? 'single'), 'is_deposit' => (int)(bool)($x['isDeposit'] ?? false), 'is_defective' => (int)(bool)($x['isDefective'] ?? false),
                'level' => $level, 'confidence' => round((float)($x['confidence'] ?? 0.0) * 100, 2), 'flags' => json_encode($flags, JSON_UNESCAPED_UNICODE), 'observed_at' => (string)$row['captured_at'],
            ]);
            $oid = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,extraction_run_id,lane,decision,reason_codes,ai_value,rule_result) VALUES (:oid,:raw_id,:eid,:lane,'review_required',:reasons,:ai,:rule)")->execute([
                'oid' => $oid, 'raw_id' => (int)$row['id'], 'eid' => $row['extraction_id'] !== null ? (int)$row['extraction_id'] : null, 'lane' => (string)$d['lane'],
                'reasons' => json_encode($flags, JSON_UNESCAPED_UNICODE), 'ai' => json_encode($x, JSON_UNESCAPED_UNICODE), 'rule' => json_encode(['lane' => $d['lane'], 'method' => $d['method'], 'reasons' => $d['reasons']], JSON_UNESCAPED_UNICODE),
            ]);
            $db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (NULL,'observation_reresolved','price_observation',:id,NULL,:after)")->execute(['id' => $oid, 'after' => json_encode(['raw_observation_id' => (int)$row['id'], 'product_id' => (int)$d['product_id'], 'method' => $d['method'], 'lane' => $d['lane']])]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
    }
}
