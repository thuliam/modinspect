<?php
declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * LS-90 T1: does a listing text say the item is brand new? Such a listing is a different market from a used one,
 * so it must not enter a used asking or sold price. Sellers also use the same words to describe a used item's condition
 * ("สภาพเหมือนมือ 1", "มือ 1.5"); those are not claims of a new item.
 * A text rule only: it never changes an observation, and a person can still review the listing.
 */
final class BrandNewClaim
{
    private const PHRASES = '/มือ\s*(?:หนึ่ง|1(?!\d|[.,]\d))|ของใหม่|ใหม่แกะกล่อง|ยังไม่(?:ได้)?แกะ|brand[\s-]*new|\bnew\s+sealed\b|\bsealed\b|\bnew\s+in\s+box\b/iu';

    /** words right before a phrase that turn it into a comparison, a purchase history or a denial */
    private const NOT_A_CLAIM = '/(?:เหมือน|คล้าย|ใกล้|เทียบ(?:เท่า)?|ประมาณ|สภาพ|น้อง\s*ๆ|เกือบ|กว่า|ราคา|ครบตาม|ตาม|ซื้อ(?:มา)?(?:จาก)?|ไม่ใช่|(?<![a-z])(?:not|like|as))[\s:()\-]*$/iu';

    /** the first phrase that claims a new item, or null */
    public static function detect(string $text): ?string
    {
        $text = (string)preg_replace('/\s+/u', ' ', $text);
        if ($text === '' || !preg_match_all(self::PHRASES, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        foreach ($matches[0] as [$phrase, $offset]) {
            $before = substr($text, 0, $offset);
            if (!preg_match(self::NOT_A_CLAIM, $before)) {
                return $phrase;
            }
        }
        return null;
    }
}
