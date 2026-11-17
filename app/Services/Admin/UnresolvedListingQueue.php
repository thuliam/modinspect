<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Validation\ModelMarker;
use PDO;
use PDOException;

/**
 * Admin queue of listings the resolver could not match to a product (LS-3 J-1, decision D-5: a person confirms every alias, nothing is automatic).
 *  - suggest(): pure ranking of catalog products for a title, suffix-aware (a plain 5060 is never suggested above the 5060 Ti by accident).
 *  - addAlias(): validates the alias, proves it resolves the listing to the chosen product AND that it does not change how any other known listing
 *    resolves, then writes the alias and the decision in one transaction with an audit entry.
 *  - dismiss(): records that a listing should stay unresolved.
 * Nothing here creates or changes an observation, a snapshot or a price: an alias only affects FUTURE imports.
 */
final class UnresolvedListingQueue
{
    private const SUFFIX = '/(?<![a-z])(ti|super|xtx|xt|gre)(?![a-z])/';
    public const MIN_ALIAS_LENGTH = 5;

    private PDO $db;
    public function __construct(?PDO $db = null) { global $config; $this->db = $db ?? Database::connection($config['db']); }

    /** the decision table comes from migration 019; the queue still lists (read-only) before it is applied */
    public function actionsAvailable(): bool
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'unresolved_listing_reviews'")->fetchColumn() === 1;
    }

    /**
     * @param list<array{id:int,full_name:string,aliases:list<string>}> $catalog
     * @return list<array{product_id:int,name:string,score:int}> best first, at most $n, only products that share a model number with the title
     */
    public static function suggest(string $title, array $catalog, int $n = 3): array
    {
        // ModelMarker keeps the family prefix for names (rtx5060ti) but not for a bare number in a title (5060): compare the number cores
        $cores = static function (string $v): array {
            $out = [];
            foreach (ModelMarker::all($v) as $m) { if (preg_match('/(\d{3,5})/', $m, $mm)) { $out[$mm[1]] = true; } }
            return array_keys($out);
        };
        $tMarkers = $cores($title);
        if ($tMarkers === []) { return []; }
        $suffix = static function (string $v): array { preg_match_all(self::SUFFIX, mb_strtolower($v, 'UTF-8'), $m); $s = array_values(array_unique($m[1])); sort($s); return $s; };
        $tSuffix = $suffix($title);
        $out = [];
        foreach ($catalog as $p) {
            $shared = array_intersect($tMarkers, $cores((string)$p['full_name']));
            if ($shared === []) { continue; }
            $score = 100 * count($shared);
            if ($suffix((string)$p['full_name']) !== $tSuffix) { continue; }   // Ti/Super/XT must match exactly: a Super title is never offered the plain model
            $score += 50;
            similar_text(mb_strtolower($title, 'UTF-8'), mb_strtolower((string)$p['full_name'], 'UTF-8'), $pct);
            $out[] = ['product_id' => (int)$p['id'], 'name' => (string)$p['full_name'], 'score' => $score + (int)round($pct / 10)];
        }
        usort($out, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($out, 0, $n);
    }

    /** @return list<string> the model-suffix words (ti, super, xt, xtx, gre) found in a text, sorted */
    public static function suffixSet(string $text): array
    {
        preg_match_all(self::SUFFIX, mb_strtolower($text, 'UTF-8'), $m);
        $s = array_values(array_unique($m[1]));
        sort($s);
        return $s;
    }

    /** a starting point for the alias text the reviewer can edit: the model phrase found in the title, or '' */
    public static function proposeAlias(string $title): string
    {
        if (preg_match('/\b(rtx|gtx|rx|ryzen\s?[3579]?|i[3579])[\s\-]?\d{3,5}[a-z0-9]*(?:\s?(?:ti\s?super|ti|super|xtx|xt|x3d|gre))?/iu', $title, $m)) {
            return trim(preg_replace('/\s+/u', ' ', $m[0]) ?? '');
        }
        return '';
    }

    public static function normalize(string $alias): string
    {
        return preg_replace('/[^a-z0-9à¸-à¹™]+/iu', '', mb_strtolower($alias, 'UTF-8')) ?? '';
    }

    /** @return list<string> reasons the alias text itself is unacceptable (empty = acceptable) */
    public static function aliasProblems(string $alias): array
    {
        $n = self::normalize($alias); $p = [];
        if (mb_strlen($n, 'UTF-8') < self::MIN_ALIAS_LENGTH) { $p[] = 'ALIAS_TOO_SHORT'; }
        if ($n !== '' && ctype_digit($n)) { $p[] = 'ALIAS_ONLY_DIGITS'; }
        if ($n !== '' && !preg_match('/\d/', $n)) { $p[] = 'ALIAS_HAS_NO_MODEL_NUMBER'; }
        return $p;
    }

    /**
     * status: open = undecided AND the current catalog still cannot match it, stale = undecided but the current resolver already matches it
     * (decided before the catalog grew; it needs no alias, LS-4 K-1b), decided, all.
     * @return list<array<string,mixed>>
     */
    public function page(int $limit = 50, int $offset = 0, string $status = 'open'): array
    {
        $limit = max(1, min(200, $limit)); $offset = max(0, $offset);
        $filtered = in_array($status, ['open', 'stale'], true);
        $rows = $this->fetch($filtered ? 'undecided' : $status, $filtered ? null : $limit, $filtered ? null : $offset);
        if ($filtered) {
            $rows = $this->splitStale($rows)[$status === 'stale' ? 'stale' : 'open'];
            $rows = array_slice($rows, $offset, $limit);
        }
        $catalog = (new CollectionPipeline())->catalog();
        foreach ($rows as &$r) {
            $r['suggestions'] = self::suggest((string)$r['title'], $catalog);
            $r['proposed_alias'] = self::proposeAlias((string)$r['title']);
        }
        unset($r);
        return $rows;
    }

    public function count(string $status = 'open'): int
    {
        if (in_array($status, ['open', 'stale'], true)) {
            return count($this->splitStale($this->fetch('undecided', null, null))[$status === 'stale' ? 'stale' : 'open']);
        }
        $join = $this->actionsAvailable() ? 'LEFT JOIN unresolved_listing_reviews u ON u.raw_observation_id = r.id' : '';
        $where = $this->actionsAvailable() ? ($status === 'decided' ? 'AND u.id IS NOT NULL' : '') : '';
        return (int)$this->db->query("SELECT COUNT(DISTINCT r.id) FROM observation_review_decisions d JOIN raw_price_observations r ON r.id = d.raw_observation_id $join
            WHERE d.price_observation_id IS NULL AND d.reason_codes LIKE '%UNRESOLVED_PRODUCT%' $where")->fetchColumn();
    }

    /** @param list<array<string,mixed>> $rows @return array{open:list<array<string,mixed>>,stale:list<array<string,mixed>>} */
    private function splitStale(array $rows): array
    {
        $resolver = new ProductResolver((new CollectionPipeline())->catalog());
        $open = []; $stale = [];
        foreach ($rows as $r) { ($resolver->resolve((string)$r['title'])->productId !== null) ? $stale[] = $r : $open[] = $r; }
        return ['open' => $open, 'stale' => $stale];
    }

    /** @param 'undecided'|'decided'|'all' $which @return list<array<string,mixed>> */
    private function fetch(string $which, ?int $limit, ?int $offset): array
    {
        $join = $this->actionsAvailable() ? 'LEFT JOIN unresolved_listing_reviews u ON u.raw_observation_id = r.id' : '';
        $sel = $this->actionsAvailable() ? ', u.status decided, u.product_id decided_product' : ', NULL decided, NULL decided_product';
        $where = $this->actionsAvailable() ? ($which === 'undecided' ? 'AND u.id IS NULL' : ($which === 'decided' ? 'AND u.id IS NOT NULL' : '')) : '';
        $lim = $limit !== null ? 'LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset : '';
        return $this->db->query("SELECT r.id raw_id, r.raw_title title, r.raw_price_text price_text, r.captured_at,
                CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_owner_text_%' THEN 'owner_text' ELSE 'other' END grade $sel
            FROM observation_review_decisions d
            JOIN raw_price_observations r ON r.id = d.raw_observation_id
            LEFT JOIN data_sources s ON s.id = r.source_id
            $join
            WHERE d.price_observation_id IS NULL AND d.reason_codes LIKE '%UNRESOLVED_PRODUCT%' $where
            GROUP BY r.id ORDER BY r.id DESC $lim")->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Does adding $alias for $productId resolve $title to that product, and does it leave every title that ALREADY resolves where it was?
     * A title that resolved to product A and would now resolve to product B is "stolen" (refused); a title that was unresolved and becomes resolved is reported, not refused.
     * @return array{resolves_chosen:bool,changed_others:int,newly_resolved:int,checked_others:int}
     */
    public function previewImpact(string $title, int $productId, string $alias, int $sample = 400): array
    {
        $catalog = (new CollectionPipeline())->catalog();
        $augmented = $catalog;
        foreach ($augmented as &$p) { if ((int)$p['id'] === $productId) { $p['aliases'][] = $alias; } }
        unset($p);
        $before = new ProductResolver($catalog); $after = new ProductResolver($augmented);
        $resolves = $after->resolve($title)->productId === $productId;
        $titles = $this->db->query('SELECT raw_title FROM raw_price_observations ORDER BY id DESC LIMIT ' . (int)$sample)->fetchAll(PDO::FETCH_COLUMN);
        $changed = 0; $newly = 0;
        foreach ($titles as $t) {
            $a = $before->resolve((string)$t)->productId; $b = $after->resolve((string)$t)->productId;
            if ($a !== null && $a !== $b) { $changed++; } elseif ($a === null && $b !== null) { $newly++; }
        }
        return ['resolves_chosen' => $resolves, 'changed_others' => $changed, 'newly_resolved' => $newly, 'checked_others' => count($titles)];
    }

    /** @return array{ok:bool,error:?string,alias_id:?int} */
    public function addAlias(int $rawId, int $productId, string $alias, int $reviewerId, ?string $note = null): array
    {
        if (!$this->actionsAvailable()) { return ['ok' => false, 'error' => 'MIGRATION_019_NOT_APPLIED', 'alias_id' => null]; }
        $alias = trim($alias);
        if ($problems = self::aliasProblems($alias)) { return ['ok' => false, 'error' => $problems[0], 'alias_id' => null]; }
        $raw = $this->db->prepare("SELECT r.id, r.raw_title FROM raw_price_observations r JOIN observation_review_decisions d ON d.raw_observation_id = r.id
            WHERE r.id = :id AND d.price_observation_id IS NULL AND d.reason_codes LIKE '%UNRESOLVED_PRODUCT%' LIMIT 1");
        $raw->execute(['id' => $rawId]);
        $title = $raw->fetchColumn(1);
        if ($title === false) { return ['ok' => false, 'error' => 'LISTING_NOT_IN_QUEUE', 'alias_id' => null]; }
        $prod = $this->db->prepare('SELECT full_name FROM products WHERE id = :id AND is_active = 1');
        $prod->execute(['id' => $productId]);
        $productName = $prod->fetchColumn();
        if ($productName === false) { return ['ok' => false, 'error' => 'PRODUCT_NOT_FOUND', 'alias_id' => null]; }
        // the resolver bug of LS-2 H-1 in alias form: an alias that says Super/Ti/XT must point at a product that says the same, and a plain alias at a plain product
        if (self::suffixSet($alias) !== self::suffixSet((string)$productName)) { return ['ok' => false, 'error' => 'ALIAS_SUFFIX_DIFFERS_FROM_PRODUCT', 'alias_id' => null]; }
        $impact = $this->previewImpact((string)$title, $productId, $alias);
        if (!$impact['resolves_chosen']) { return ['ok' => false, 'error' => 'ALIAS_DOES_NOT_RESOLVE_THIS_LISTING', 'alias_id' => null]; }
        if ($impact['changed_others'] > 0) { return ['ok' => false, 'error' => 'ALIAS_WOULD_CHANGE_OTHER_LISTINGS:' . $impact['changed_others'], 'alias_id' => null]; }

        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $this->db->prepare('INSERT INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence,created_at,updated_at) VALUES (:p,:a,:n,:s,80,NOW(),NOW())')
                ->execute(['p' => $productId, 'a' => $alias, 'n' => self::normalize($alias), 's' => 'Confirmed from the unresolved listings queue']);
            $aliasId = (int)$this->db->lastInsertId();
            $this->db->prepare("INSERT INTO unresolved_listing_reviews (raw_observation_id,status,product_id,alias_id,reviewer_id,note) VALUES (:r,'alias_added',:p,:a,:u,:n)")
                ->execute(['r' => $rawId, 'p' => $productId, 'a' => $aliasId, 'u' => $reviewerId, 'n' => $note !== null ? mb_substr($note, 0, 255) : null]);
            $this->audit($reviewerId, 'unresolved_alias_added', 'product_alias', $aliasId, ['alias_text' => $alias, 'product_id' => $productId, 'raw_observation_id' => $rawId]);
            if ($own) { $this->db->commit(); }
            return ['ok' => true, 'error' => null, 'alias_id' => $aliasId];
        } catch (PDOException $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            return ['ok' => false, 'error' => $e->getCode() === '23000' ? 'ALIAS_OR_DECISION_ALREADY_EXISTS' : 'WRITE_FAILED', 'alias_id' => null];
        }
    }

    /** @return array{ok:bool,error:?string} */
    public function dismiss(int $rawId, int $reviewerId, ?string $note = null): array
    {
        if (!$this->actionsAvailable()) { return ['ok' => false, 'error' => 'MIGRATION_019_NOT_APPLIED']; }
        $in = $this->db->prepare("SELECT 1 FROM observation_review_decisions WHERE raw_observation_id = :id AND price_observation_id IS NULL AND reason_codes LIKE '%UNRESOLVED_PRODUCT%' LIMIT 1");
        $in->execute(['id' => $rawId]);
        if ($in->fetchColumn() === false) { return ['ok' => false, 'error' => 'LISTING_NOT_IN_QUEUE']; }
        try {
            $this->db->prepare("INSERT INTO unresolved_listing_reviews (raw_observation_id,status,reviewer_id,note) VALUES (:r,'dismissed',:u,:n)")
                ->execute(['r' => $rawId, 'u' => $reviewerId, 'n' => $note !== null ? mb_substr($note, 0, 255) : null]);
            $this->audit($reviewerId, 'unresolved_listing_dismissed', 'raw_price_observation', $rawId, ['raw_observation_id' => $rawId]);
            return ['ok' => true, 'error' => null];
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => $e->getCode() === '23000' ? 'ALREADY_DECIDED' : 'WRITE_FAILED'];
        }
    }

    private function audit(int $userId, string $action, string $entityType, int $entityId, array $after): void
    {
        $this->db->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (:u,:a,:t,:e,NULL,:d)')
            ->execute(['u' => $userId, 'a' => $action, 't' => $entityType, 'e' => $entityId, 'd' => json_encode($after, JSON_UNESCAPED_UNICODE)]);
    }
}
