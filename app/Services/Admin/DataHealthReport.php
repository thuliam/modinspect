<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/**
 * Admin-only, read-only overview of how healthy the market data is (LS-2 H-5). Counts, dates and labels only:
 * no source names, URLs, listing text or seller data. Uses the latest PUBLIC snapshot per product (price_indices keeps one row per recalculation; legacy_unavailable rows are never public).
 */
final class DataHealthReport
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }

    /** fresh_sample_ratio is stored as a percentage (0-100), not a fraction.
     * @return array{categories:list<array>,confidence:array<string,int>,totals:array,freshness:array,grades:array{owner_text:int,other:int},ladder:array{checked:int,flags:int}} */
    public function build(): array
    {
        $latest = "pi.id = " . \App\Services\Pricing\PublicSnapshot::idSql('pi.product_id');   // the public snapshot of the model, if it has one

        $categories = $this->db->query("SELECT c.name category, COUNT(DISTINCT p.id) products,
                COUNT(DISTINCT pi.product_id) priced,
                COUNT(DISTINCT CASE WHEN o.verified_status = 'pending' THEN o.id END) pending,
                COUNT(DISTINCT CASE WHEN o.verified_status = 'approved' THEN o.id END) approved
            FROM products p
            JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN price_indices pi ON pi.product_id = p.id AND $latest
            LEFT JOIN price_observations o ON o.product_id = p.id AND o.price_type = 'asking'
            WHERE p.is_active = 1 GROUP BY c.id, c.name ORDER BY products DESC, c.name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categories as &$c) {
            foreach (['products', 'priced', 'pending', 'approved'] as $k) { $c[$k] = (int)$c[$k]; }
            $c['priced_pct'] = $c['products'] > 0 ? round(100 * $c['priced'] / $c['products'], 1) : 0.0;
        }
        unset($c);

        $confidence = ['high' => 0, 'medium' => 0, 'low' => 0, 'insufficient' => 0];
        foreach ($this->db->query("SELECT LOWER(pi.confidence_label) l, COUNT(*) n FROM price_indices pi WHERE $latest GROUP BY LOWER(pi.confidence_label)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $confidence[$r['l']] = ($confidence[$r['l']] ?? 0) + (int)$r['n'];
        }

        $t = $this->db->query("SELECT
                SUM(o.verified_status = 'pending') pending, SUM(o.verified_status = 'approved') approved, SUM(o.verified_status = 'excluded') excluded,
                MAX(CASE WHEN o.verified_status = 'approved' THEN o.observed_at END) newest_approved,
                MIN(CASE WHEN o.verified_status = 'pending' THEN o.created_at END) oldest_pending
            FROM price_observations o WHERE o.price_type = 'asking'")->fetch(PDO::FETCH_ASSOC) ?: [];
        $oldest = $t['oldest_pending'] ?? null;
        $totals = [
            'pending' => (int)($t['pending'] ?? 0), 'approved' => (int)($t['approved'] ?? 0), 'excluded' => (int)($t['excluded'] ?? 0),
            'newest_approved' => $t['newest_approved'] ? substr((string)$t['newest_approved'], 0, 10) : null,
            'oldest_pending_days' => $oldest ? (int)floor((time() - strtotime((string)$oldest)) / 86400) : null,
        ];

        $fresh = $this->db->query("SELECT COUNT(*) n, SUM(pi.fresh_sample_ratio >= 50) fresh_majority, AVG(pi.fresh_sample_ratio) avg_fresh
            FROM price_indices pi WHERE $latest")->fetch(PDO::FETCH_ASSOC) ?: [];
        $freshness = ['snapshots' => (int)($fresh['n'] ?? 0), 'fresh_majority' => (int)($fresh['fresh_majority'] ?? 0), 'avg_fresh_pct' => round((float)($fresh['avg_fresh'] ?? 0), 1)];

        $g = $this->db->query("SELECT SUM(LOWER(COALESCE(s.source_key,'')) LIKE 'offline_owner_text_%') owner_text, SUM(LOWER(COALESCE(s.source_key,'')) NOT LIKE 'offline_owner_text_%') other
            FROM price_observations o JOIN raw_price_observations r ON r.id = o.raw_observation_id JOIN data_sources s ON s.id = r.source_id
            WHERE o.verified_status = 'approved' AND o.price_type = 'asking'")->fetch(PDO::FETCH_ASSOC) ?: [];

        $ladder = new PriceLadderCheck();
        $flags = $ladder->forPublicSnapshots();

        return [
            'categories' => $categories, 'confidence' => $confidence, 'totals' => $totals, 'freshness' => $freshness,
            'grades' => ['owner_text' => (int)($g['owner_text'] ?? 0), 'other' => (int)($g['other'] ?? 0)],
            'ladder' => ['checked' => $ladder->checked, 'flags' => count($flags), 'items' => $flags],
        ];
    }
}
