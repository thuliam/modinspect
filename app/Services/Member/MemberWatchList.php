<?php
declare(strict_types=1);

namespace App\Services\Member;

use App\Services\Catalog\WatchList;
use PDO;

/**
 * LS-87: the server-side watch list of a signed-in member (table watch_items). Same shape and rules as the cookie list (WatchList): slug plus the median
 * seen when watching began, newest last, at most $limit. The cookie list of a visitor is merged in once per sign-in; after that this table is the truth.
 */
final class MemberWatchList
{
    public function __construct(private PDO $db, private int $limit = WatchList::MAX) {}

    /** @return list<array{slug:string,median:?float}> in the order added */
    public function load(int $memberId): array
    {
        $st = $this->db->prepare('SELECT product_slug, median_at_watch FROM watch_items WHERE member_id = :m ORDER BY id LIMIT ' . max(1, $this->limit));
        $st->execute(['m' => $memberId]);
        return array_map(static fn(array $r): array => ['slug' => (string)$r['product_slug'], 'median' => $r['median_at_watch'] !== null ? (float)$r['median_at_watch'] : null], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** add cookie entries the member does not have yet, while there is room; returns the resulting list */
    public function merge(int $memberId, array $cookieList): array
    {
        $have = $this->load($memberId);
        foreach ($cookieList as $w) {
            if (count($have) >= $this->limit) { break; }
            if (WatchList::has($have, $w['slug'])) { continue; }
            $this->add($memberId, $w['slug'], $w['median']);
            $have[] = ['slug' => $w['slug'], 'median' => $w['median']];
        }
        return $have;
    }

    /** @return array{list:list<array{slug:string,median:?float}>,added:bool,full:bool} same contract as WatchList::toggle, persisted */
    public function toggle(int $memberId, string $slug, ?float $medianNow): array
    {
        $cur = $this->load($memberId);
        if (!WatchList::has($cur, $slug) && count($cur) >= $this->limit) { return ['list' => $cur, 'added' => false, 'full' => true]; }   // the member limit, which may be below the cookie ceiling
        $r = WatchList::toggle($cur, $slug, $medianNow);
        if ($r['added']) { $this->add($memberId, $slug, $medianNow !== null && $medianNow > 0 ? (float)round($medianNow) : null); }
        elseif (!$r['full']) { $this->db->prepare('DELETE FROM watch_items WHERE member_id = :m AND product_slug = :s')->execute(['m' => $memberId, 's' => $slug]); }
        return $r;
    }

    private function add(int $memberId, string $slug, ?float $median): void
    {
        $this->db->prepare('INSERT IGNORE INTO watch_items (member_id, product_slug, median_at_watch) VALUES (:m,:s,:d)')->execute(['m' => $memberId, 's' => $slug, 'd' => $median]);
    }
}
