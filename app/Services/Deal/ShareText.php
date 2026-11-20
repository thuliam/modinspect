<?php
declare(strict_types=1);

namespace App\Services\Deal;

/**
 * LS-24 C2: the line a visitor pastes into a LINE or Facebook group ("RTX 3070 มือสอง ฿7,300 – ฿8,199 ..."). Built only from the stored public snapshot the page itself
 * shows, so it can never say more than the page. It names the sample size and the day the figure was calculated and says asking prices, never a sold price. Pure.
 */
final class ShareText
{
    private const MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    /** @param array<string,mixed> $product a Product row with the public snapshot fields @return ?string null when the page shows no price (nothing is shared then) */
    public static function forProduct(array $product, string $url): ?string
    {
        if (empty($product['median']) || (int)($product['valid_sample_size'] ?? 0) <= 0 || (int)($product['accepted_observation_count'] ?? 0) <= 0 || empty($product['q1']) || empty($product['q3'])) { return null; }
        $when = '';
        $t = strtotime((string)($product['last_calculated_at'] ?? ''));
        if ($t) { $when = ' · ข้อมูล ณ ' . (int)date('j', $t) . ' ' . self::MONTHS[(int)date('n', $t) - 1] . ' ' . ((int)date('Y', $t) + 543); }
        return (string)$product['full_name'] . ' ราคาประกาศมือสองที่พบ ฿' . number_format((float)$product['q1']) . ' – ฿' . number_format((float)$product['q3'])
            . ' (ราคากลาง ฿' . number_format((float)$product['median']) . ', ตัวอย่าง ' . (int)$product['valid_sample_size'] . ' ประกาศ' . $when . ')'
            . "\nเป็นราคาประกาศขาย ไม่ใช่ราคาปิดดีล\n" . $url;
    }
}
