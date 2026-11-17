<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Services\Research\OwnerIntakeDeduper;
use PDO;

/**
 * LS-7 N-3: read-only check of prepared owner rows against the real offline rows already stored (raw_price_observations of offline_real_* sources).
 * ALREADY_STORED_ITEM (error): the same listing is stored, compared by canonical URL and item id so share-link tracking does not hide it.
 * REPOST_OF_STORED (warning): same first 30 letters/digits of the title and same price under another listing (OwnerIntakeDeduper key).
 * Never writes.
 */
final class OwnerRowDbCheck
{
    /**
     * @param list<array<string,string>> $rows rows from OwnerRowPreparer (template columns)
     * @return list<array{index:int,level:string,codes:list<string>}>
     */
    public static function check(PDO $db, array $rows): array
    {
        if ($rows === []) { return []; }
        $urls = [];
        $items = [];
        $keys = [];
        $stmt = $db->query("SELECT r.raw_title, r.raw_price_text, r.source_url_encrypted FROM raw_price_observations r JOIN data_sources s ON s.id = r.source_id WHERE s.source_key LIKE 'offline\\_real\\_%'");
        foreach ($stmt->fetchAll() as $r) {
            $canon = OwnerRowPreparer::canonicalUrl((string)($r['source_url_encrypted'] ?? ''));
            if ($canon !== null) {
                $urls[$canon] = true;
                $id = OwnerRowPreparer::itemId($canon);
                if ($id !== null) { $items[$id] = true; }
            }
            $price = (int)round((float)preg_replace('/[^0-9.]/', '', (string)($r['raw_price_text'] ?? '')));
            $keys[OwnerIntakeDeduper::keyFor((string)$r['raw_title'], $price)] = true;
        }
        $out = [];
        foreach ($rows as $i => $row) {
            $canon = OwnerRowPreparer::canonicalUrl((string)$row['source_listing_url']);
            $id = (string)$row['source_item_id'];
            if (($canon !== null && isset($urls[$canon])) || ($id !== '' && isset($items[$id]))) {
                $out[] = ['index' => $i, 'level' => 'error', 'codes' => ['ALREADY_STORED_ITEM']];
            } elseif (isset($keys[OwnerIntakeDeduper::keyFor((string)$row['listing_title'], (int)round((float)$row['asking_price']))])) {
                $out[] = ['index' => $i, 'level' => 'warning', 'codes' => ['REPOST_OF_STORED']];
            }
        }
        return $out;
    }
}
