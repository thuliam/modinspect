<?php
declare(strict_types=1);

namespace App\Services\Intake;

use App\Services\Auth\AuthService;
use App\Services\ProductResolver\ProductResolver;
use PDO;

/**
 * LS-23 B7: dataset F (confirmed SOLD prices) as PENDING observation candidates for the review queue. Same shape as an accepted community sold report: a raw row,
 * a low-level evidence row, an observation with price_type 'sold' and verified_status 'pending', and a review decision that says a person must decide.
 * It never approves anything and never creates a snapshot; the price engine only reads asking observations for the used price, so a sold row cannot move a
 * price on its own (pinned by tests/intake_sold_prices_test.php). Dry run writes nothing. A model is matched from the title by the catalog resolver; a title
 * that cannot be matched is reported, not guessed.
 */
final class IntakeSoldPrices
{
    public const SOURCE_KEY = 'collected_sold_listing';

    /**
     * @param list<array<string,string>> $rows valid rows of dataset F
     * @return array{rows:int,to_add:int,added:int,already_stored:int,unresolved:int}
     */
    public static function import(PDO $db, int $actorId, array $rows, ProductResolver $resolver, bool $apply): array
    {
        $res = ['rows' => count($rows), 'to_add' => 0, 'added' => 0, 'already_stored' => 0, 'unresolved' => 0];
        $own = $apply && !$db->inTransaction();
        if ($own) { $db->beginTransaction(); }
        try {
            $sid = null;
            foreach ($rows as $r) {
                $hit = $resolver->resolve($r['listing_title']);
                if ($hit->productId === null || $hit->confidence < 0.7) { $res['unresolved']++; continue; }
                $hash = hash('sha256', 'sold:' . trim($r['source_item_id']) . '|' . trim($r['source_listing_url']));
                if ($sid === null) { $sid = self::sourceId($db, $apply); }
                if ($sid !== null) {
                    $dup = $db->prepare('SELECT 1 FROM raw_price_observations WHERE source_id = :s AND external_reference_hash = :h'); $dup->execute(['s' => $sid, 'h' => $hash]);
                    if ($dup->fetchColumn()) { $res['already_stored']++; continue; }
                }
                $res['to_add']++;
                if (!$apply) { continue; }
                $when = trim($r['observed_at']);
                $conf = ['seller_post' => 'seller post', 'buyer_post' => 'buyer post', 'status_label' => 'status label'][$r['sold_confirmed_by']] ?? $r['sold_confirmed_by'];
                $db->prepare("INSERT INTO raw_price_observations (source_id, external_reference_hash, raw_title, raw_price_text, raw_condition_text, source_url_encrypted, captured_at, processing_status) VALUES (:s, :h, :t, :p, :c, :u, :w, 'review')")
                    ->execute(['s' => $sid, 'h' => $hash, 't' => mb_substr(trim($r['listing_title']), 0, 500), 'p' => trim($r['sold_price']), 'c' => trim($r['condition_hint'] ?? '') ?: null, 'u' => trim($r['source_listing_url']), 'w' => $when]);
                $rawId = (int)$db->lastInsertId();
                $excerpt = 'ราคาขายจริงที่ผู้เก็บข้อมูลบันทึก ยืนยันโดย: ' . $conf . ' · ขายได้ ฿' . number_format((int)$r['sold_price']) . (trim($r['asking_price_before'] ?? '') !== '' ? ' (ราคาเสนอก่อนหน้า ฿' . number_format((int)$r['asking_price_before']) . ')' : '');
                $db->prepare("INSERT INTO market_evidence (raw_observation_id, source_id, evidence_level, evidence_type, excerpt, content_hash, captured_at, is_public) VALUES (:r, :s, 'E', 'snippet', :x, :h, :c, 0)")
                    ->execute(['r' => $rawId, 's' => $sid, 'x' => $excerpt, 'h' => $hash, 'c' => $when]);
                $db->prepare("INSERT INTO price_observations (raw_observation_id, product_id, price_type, asking_price, price_value, currency, condition_level, listing_type, evidence_level, classification_confidence, quality_flags, verified_status, observed_at)
                    VALUES (:r, :p, 'sold', NULL, :v, 'THB', 'unknown', 'single_item', 1, 0, :q, 'pending', :o)")
                    ->execute(['r' => $rawId, 'p' => $hit->productId, 'v' => (int)$r['sold_price'], 'q' => json_encode(['COLLECTED_SOLD_PRICE'], JSON_UNESCAPED_UNICODE), 'o' => $when]);
                $obsId = (int)$db->lastInsertId();
                $db->prepare("INSERT INTO observation_review_decisions (price_observation_id, raw_observation_id, lane, decision, reason_codes, notes) VALUES (:o, :r, 'amber', 'review_required', :c, :n)")
                    ->execute(['o' => $obsId, 'r' => $rawId, 'c' => json_encode(['COLLECTED_SOLD_PRICE'], JSON_UNESCAPED_UNICODE), 'n' => 'Sold price recorded from a public listing; a reviewer decides. It is not an asking price.']);
                (new AuthService())->audit($actorId, 'sold_price_candidate_created', 'price_observation', $obsId, ['product_id' => $hit->productId]);
                $res['added']++;
            }
            if ($own) { $db->commit(); }
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
        return $res;
    }

    /** the source row; created only when a real import needs it (a dry run only looks) */
    private static function sourceId(PDO $db, bool $create): ?int
    {
        $id = $db->prepare('SELECT id FROM data_sources WHERE source_key = :k'); $id->execute(['k' => self::SOURCE_KEY]);
        $found = $id->fetchColumn();
        if ($found !== false) { return (int)$found; }
        if (!$create) { return null; }
        $db->prepare("INSERT INTO data_sources (source_key, name, source_type, access_method, allowed_collection_method, risk_level, reliability_score, evidence_quality, freshness_expectation_days, terms_note)
            VALUES (:k, 'Collected sold listing (recorded by a person)', 'user', 'manual', 'manual', 'high', 20.00, 'low', 90, 'Sold price copied from a public listing by the collector; reviewed by a person; never an asking price.')")->execute(['k' => self::SOURCE_KEY]);
        return (int)$db->lastInsertId();
    }
}
