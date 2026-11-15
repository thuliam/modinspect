<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/** Read-only admin report: monthly trend buckets per product and the private province log. Nothing here is public. */
final class MarketTrendReport
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }

    /** @return array{trends:array,locations:array,regions:array,totals:array} */
    public function build(): array
    {
        $trends = $this->db->query("SELECT p.id, p.full_name, p.slug, c.name category_name, COUNT(*) months, MIN(t.period_start) first_month, MAX(t.period_start) last_month, SUM(t.sample_size) samples,
                SUBSTRING_INDEX(GROUP_CONCAT(t.median ORDER BY t.period_start ASC), ',', 1) first_median,
                SUBSTRING_INDEX(GROUP_CONCAT(t.median ORDER BY t.period_start DESC), ',', 1) last_median
            FROM price_trend_points t JOIN products p ON p.id=t.product_id JOIN product_categories c ON c.id=p.category_id
            GROUP BY p.id, p.full_name, p.slug, c.name ORDER BY months DESC, samples DESC, p.full_name")->fetchAll();
        foreach ($trends as &$t) {
            $f = (float)$t['first_median'];
            $t['change_pct'] = ((int)$t['months'] > 1 && $f > 0) ? ((float)$t['last_median'] - $f) / $f * 100 : null;
        }
        unset($t);

        $rows = $this->db->query('SELECT province, region, category, asking_price_thb p FROM listing_location_log ORDER BY province, category, asking_price_thb')->fetchAll();
        $locations = []; $regions = []; $byProvince = []; $total = count($rows);
        foreach ($rows as $r) {
            $key = $r['province'] . '|' . $r['category'];
            $locations[$key]['province'] = $r['province'];
            $locations[$key]['region'] = $r['region'];
            $locations[$key]['category'] = $r['category'] ?: '-';
            if ($r['p'] !== null) { $locations[$key]['prices'][] = (int)$r['p']; }
            $locations[$key]['n'] = ($locations[$key]['n'] ?? 0) + 1;
            $regions[$r['region']] = ($regions[$r['region']] ?? 0) + 1;
            $byProvince[$r['province']] = ($byProvince[$r['province']] ?? 0) + 1;
        }
        foreach ($locations as &$l) {
            $pr = $l['prices'] ?? [];
            sort($pr);
            $n = count($pr);
            $l['median'] = $n >= 3 ? ($n % 2 ? $pr[intdiv($n, 2)] : ($pr[$n / 2 - 1] + $pr[$n / 2]) / 2) : null;
            unset($l['prices']);
        }
        unset($l);
        usort($locations, static fn(array $a, array $b): int => $b['n'] <=> $a['n']);
        arsort($regions);
        arsort($byProvince);
        $top = $byProvince ? (string)array_key_first($byProvince) : null;
        $topShare = ($total > 0 && $byProvince) ? reset($byProvince) / $total * 100 : 0.0;
        return [
            'trends' => $trends, 'locations' => array_values($locations), 'regions' => $regions,
            'totals' => [
                'trend_points' => (int)$this->db->query('SELECT COUNT(*) FROM price_trend_points')->fetchColumn(),
                'trend_products' => count($trends), 'location_rows' => $total, 'top_province' => $top, 'top_share' => $topShare,
            ],
        ];
    }
}
