<?php
declare(strict_types=1);

namespace App\Services\Deal;

/**
 * LS-28 B1: validation for "what happened after this result". Pure: takes the posted fields, returns a clean outcome or an error key.
 * The price, when given, is what the visitor actually paid or settled on (a final price, never an asking price). "passed" carries no price.
 */
final class DealOutcome
{
    public const OUTCOMES = ['bought' => 'ซื้อแล้ว', 'negotiated' => 'ต่อรองแล้วซื้อ', 'passed' => 'ไม่ซื้อ'];
    public const MAX_PRICE = 10000000;

    /** @return array{ok:bool,outcome:?string,final_price:?float,error:?string} */
    public static function validate(array $post): array
    {
        $o = is_string($post['outcome'] ?? null) ? $post['outcome'] : '';
        if (!isset(self::OUTCOMES[$o])) { return ['ok' => false, 'outcome' => null, 'final_price' => null, 'error' => 'outcome']; }
        $raw = is_string($post['final_price'] ?? null) ? preg_replace('/[\s,฿]/u', '', $post['final_price']) : '';
        if ($o === 'passed' || $raw === '' || $raw === null) { return ['ok' => true, 'outcome' => $o, 'final_price' => null, 'error' => null]; }
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $raw) || (float)$raw <= 0 || (float)$raw > self::MAX_PRICE) { return ['ok' => false, 'outcome' => $o, 'final_price' => null, 'error' => 'price']; }
        return ['ok' => true, 'outcome' => $o, 'final_price' => (float)$raw, 'error' => null];
    }
}
