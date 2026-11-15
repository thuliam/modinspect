<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/**
 * Admin-only: how much of the province log is repeated posts. Counts and rates only: no seller, text, URL or row text.
 * A province with a high repost rate says "one poster", not "a market pattern".
 */
final class DuplicatePostReport
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }

    /** @return array{provinces:array<int,array{province:string,listings:int,reposts:int,repost_rate:float}>,overall:array{listings:int,reposts:int,repost_rate:float},top_groups:array<int,array{dedupe_key:string,posts:int}>} */
    public function build(): array
    {
        $rows = $this->db->query('SELECT province, COUNT(*) listings, SUM(is_repost) reposts FROM listing_location_log GROUP BY province ORDER BY listings DESC')->fetchAll();
        $provinces = []; $totalListings = 0; $totalReposts = 0;
        foreach ($rows as $r) {
            $l = (int)$r['listings']; $p = (int)$r['reposts'];
            $provinces[] = ['province' => (string)$r['province'], 'listings' => $l, 'reposts' => $p, 'repost_rate' => $l > 0 ? round($p / $l, 4) : 0.0];
            $totalListings += $l; $totalReposts += $p;
        }
        $groups = $this->db->query('SELECT dedupe_key, COUNT(*) posts FROM listing_location_log WHERE dedupe_key IS NOT NULL GROUP BY dedupe_key HAVING posts > 1 ORDER BY posts DESC LIMIT 5')->fetchAll();
        return [
            'provinces' => $provinces,
            'overall' => ['listings' => $totalListings, 'reposts' => $totalReposts, 'repost_rate' => $totalListings > 0 ? round($totalReposts / $totalListings, 4) : 0.0],
            'top_groups' => array_map(static fn(array $g): array => ['dedupe_key' => (string)$g['dedupe_key'], 'posts' => (int)$g['posts']], $groups),
        ];
    }
}
