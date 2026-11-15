<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Cross-batch duplicate guard for owner-collected text listings.
 * Row ids (LINEFB-nnnn) are numbered per extraction, so a later, larger export gives the same listing a new id and the row-id
 * duplicate check cannot catch it. A listing is identified by its letters/digits only (lower-case, no spaces or punctuation),
 * the first 30 characters, plus the integer asking price. Same rule family as owner_intake_prepare.php (title + price, no date).
 */
final class OwnerIntakeDeduper
{
    private const PREFIX = 30;

    public static function keyFor(string $title, int $price): string
    {
        $t = mb_strtolower($title, 'UTF-8');
        $t = (string)preg_replace('/[^\p{L}\p{N}]+/u', '', $t);
        return mb_substr($t, 0, self::PREFIX, 'UTF-8') . '|' . $price;
    }

    /** The importer stores title and text one after the other ("T T"); halve a doubled string back to T. */
    public static function undouble(string $stored): string
    {
        $s = trim($stored);
        $len = mb_strlen($s, 'UTF-8');
        if ($len >= 3 && $len % 2 === 1) {
            $half = intdiv($len - 1, 2);
            if (mb_substr($s, $half, 1, 'UTF-8') === ' ' && mb_substr($s, 0, $half, 'UTF-8') === mb_substr($s, $half + 1, null, 'UTF-8')) {
                return mb_substr($s, 0, $half, 'UTF-8');
            }
        }
        return $s;
    }

    /**
     * @param array<int,array<string,mixed>> $records prepared import records (title, asking_price)
     * @param array<string,true> $existingKeys keys of listings already in the database
     * @return array{kept:array<int,array<string,mixed>>,already_present:int}
     */
    public static function filter(array $records, array $existingKeys): array
    {
        $kept = []; $present = 0;
        foreach ($records as $r) {
            $key = self::keyFor((string)($r['title'] ?? $r['listing_title'] ?? ''), (int)($r['asking_price'] ?? 0));
            if (isset($existingKeys[$key])) { $present++; continue; }
            $kept[] = $r;
        }
        return ['kept' => $kept, 'already_present' => $present];
    }
}
