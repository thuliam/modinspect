<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Why a Deal Checker result reads the way it does, built from a SAVED deal row (so old results get it too, with no new column):
 *  - the numbers: how far the price is from the median and from the edge of the range it crossed (amount and percent);
 *  - the factors the visitor entered (receipt, box, warranty, condition, test result), split into what they have and what is missing,
 *    worded for the direction: they may support a price above the market, or may explain a price below it;
 *  - what the system does NOT do: it never turns those factors into money, so for a large gap it says it cannot call the difference reasonable
 *    and points at the two usual checks (is it the right model and size, what does the seller say).
 * Pure, neutral wording, no verdict beyond the engine's. Returns null when there is no range to compare with.
 */
final class DealExplanation
{
    private const CONDITION = ['like_new' => 'สภาพเหมือนใหม่', 'good' => 'สภาพดี'];
    private const CONDITION_LACK = ['fair' => 'สภาพพอใช้', 'poor' => 'สภาพไม่ดี', 'unknown' => 'ยังไม่ได้ระบุสภาพ'];

    /** @param array<string,mixed> $deal deal_checks row joined with the product's latest snapshot (q1, median, q3) */
    public static function build(array $deal): ?array
    {
        $label = (string)($deal['result_label'] ?? '');
        $price = (float)($deal['user_price'] ?? 0);
        $q1 = (float)($deal['suggested_price_min'] ?? 0) ?: (float)($deal['q1'] ?? 0);
        $q3 = (float)($deal['suggested_price_max'] ?? 0) ?: (float)($deal['q3'] ?? 0);
        $median = (float)($deal['median'] ?? 0);
        if (!in_array($label, ['below_range', 'in_range', 'above_range'], true) || $price <= 0 || $q1 <= 0 || $q3 <= 0) { return null; }

        $dir = $label === 'above_range' ? 'above' : ($label === 'below_range' ? 'below' : null);
        $tier = PriceEngine::tier($price, $q1, $q3);
        $text = $dir && $tier ? PriceEngine::TEXT[$dir][$tier] : null;
        $money = static fn(float $v): string => '฿' . number_format(abs($v));
        $pct = static fn(float $v, float $base): string => ($v >= 0 ? '+' : '−') . number_format(abs($v) / $base * 100, 0) . '%';
        $sign = static fn(float $v): string => ($v >= 0 ? '+' : '−');

        $numbers = [];
        if ($median > 0) { $numbers[] = ['label' => 'ต่างจากราคากลาง', 'value' => $sign($price - $median) . $money($price - $median) . ' (' . $pct($price - $median, $median) . ')']; }
        if ($dir === 'above') { $numbers[] = ['label' => 'สูงกว่าปลายบนของช่วง', 'value' => '+' . $money($price - $q3) . ' (' . $pct($price - $q3, $q3) . ')']; }
        if ($dir === 'below') { $numbers[] = ['label' => 'ต่ำกว่าปลายล่างของช่วง', 'value' => '−' . $money($q1 - $price) . ' (' . $pct($price - $q1, $q1) . ')']; }

        $have = []; $lack = [];
        $warranty = $deal['warranty_months'] ?? null;
        if ((int)($deal['has_receipt'] ?? 0) === 1) { $have[] = 'มีใบเสร็จ'; } else { $lack[] = 'ไม่มีใบเสร็จ'; }
        if ($warranty !== null && $warranty !== '' && (int)$warranty > 0) { $have[] = 'ประกันเหลือ ' . (int)$warranty . ' เดือน'; } else { $lack[] = 'ไม่มีประกันคงเหลือหรือไม่ได้ระบุ'; }
        $cond = (string)($deal['condition_level'] ?? 'unknown');
        if (isset(self::CONDITION[$cond])) { $have[] = self::CONDITION[$cond]; } else { $lack[] = self::CONDITION_LACK[$cond] ?? self::CONDITION_LACK['unknown']; }
        if ((int)($deal['has_box'] ?? 0) === 1) { $have[] = 'มีกล่อง'; } else { $lack[] = 'ไม่มีกล่อง'; }
        if ((int)($deal['has_benchmark'] ?? 0) === 1) { $have[] = 'มีผลทดสอบ'; } else { $lack[] = 'ยังไม่มีผลทดสอบ'; }

        $notes = [];
        if ($dir === 'above') {
            $notes[] = 'ระบบแสดงปัจจัยเหล่านี้เป็นเหตุผลประกอบ ไม่ได้คำนวณเป็นราคา และไม่เปลี่ยนผลเทียบราคา';
        } elseif ($dir === 'below') {
            $notes[] = 'ระบบแสดงปัจจัยเหล่านี้เป็นข้อสังเกต ไม่ได้คำนวณเป็นราคา และไม่เปลี่ยนผลเทียบราคา';
        }

        return [
            'direction' => $dir, 'tier' => $tier,
            'label' => $text['label'] ?? null, 'headline' => $text['recommendation'] ?? null,
            'tone' => $label === 'in_range' ? 'ok' : ($tier === 'far' ? 'bad' : 'warn'),
            'numbers' => $numbers,
            'have_heading' => $dir === 'above' ? 'ปัจจัยที่คุณระบุ ซึ่งอาจรองรับราคาที่สูงกว่าตลาด' : ($dir === 'below' ? 'ปัจจัยที่คุณระบุว่ามี' : null),
            'lack_heading' => $dir === 'above' ? 'ที่ยังไม่มี ยิ่งราคาสูงกว่าตลาด ยิ่งควรขอหลักฐานข้อนี้' : ($dir === 'below' ? 'ที่ยังไม่มี และอาจเป็นเหตุผลที่ราคาต่ำ' : null),
            'have' => $dir ? $have : [], 'lack' => $dir ? $lack : [], 'notes' => $notes,
        ];
    }
}
