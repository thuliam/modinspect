<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-24 A5: how the used price range sits against the NEW retail price the owner recorded (new_price_references). Pure. It states a difference in percent and says
 * what the difference is made of (no warranty, age, wear), never "buy this" or "buy that". It needs both a used median from the public snapshot and a new price;
 * a new price older than STALE_DAYS is shown with a note that the shop may have changed it. The new price is a reference and never changes the used price.
 */
final class UsedVsNew
{
    public const STALE_DAYS = 30;

    /** @param ?array{price:mixed,source_url?:mixed,checked_on?:mixed} $newRef @return ?array{used:float,new:float,diff_percent:int,direction:string,stale:bool,checked_on:string,source_url:string} */
    public static function compare(?float $usedMedian, ?array $newRef, ?\DateTimeImmutable $now = null): ?array
    {
        if ($usedMedian === null || $usedMedian <= 0 || $newRef === null) { return null; }
        $new = (float)($newRef['price'] ?? 0);
        if ($new <= 0) { return null; }
        $now ??= new \DateTimeImmutable('now');
        $checked = (string)($newRef['checked_on'] ?? '');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $checked);
        $diff = (int)round(abs($new - $usedMedian) / $new * 100);
        return ['used' => $usedMedian, 'new' => $new, 'diff_percent' => $diff, 'direction' => $usedMedian < $new ? 'cheaper' : ($usedMedian > $new ? 'dearer' : 'same'),
            'stale' => $d === false || $d < $now->modify('-' . self::STALE_DAYS . ' days'), 'checked_on' => $checked, 'source_url' => (string)($newRef['source_url'] ?? '')];
    }
}
