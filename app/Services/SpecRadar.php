<?php
declare(strict_types=1);
namespace App\Services;

/**
 * LS-11 C: the four axes of the radar on a saved build. Each is measured from what the saved build holds (parts, prices, confidence, budget).
 * There is deliberately no speed axis: this project holds no reviewed performance data yet.
 */
final class SpecRadar
{
    /** the eight categories of a PC the Build page knows about */
    public const CATEGORIES = 8;

    /** @param array<int,array<string,mixed>> $items rows of spec_build_items (component_role, price_low/expected/high, confidence_score, is_alternative) */
    public static function axes(array $items, float $budget): array
    {
        $main = array_values(array_filter($items, static fn(array $i): bool => (int)($i['is_alternative'] ?? 0) === 0));
        // LS-14 review: only a part with a used-market price (confidence above 0) is market data. A part with no price or with a new-price reference
        // (confidence 0) does not raise coverage, is not averaged into the confidence and has no price range to call stable; its price still counts as spend.
        $market = array_values(array_filter($main, static fn(array $i): bool => (float)$i['confidence_score'] > 0));
        $roles = array_unique(array_map(static fn(array $i): string => strtolower((string)$i['component_role']), $market));
        $coverage = count($roles) / self::CATEGORIES * 100;
        $confidence = $market ? array_sum(array_map(static fn(array $i): float => (float)$i['confidence_score'], $market)) / count($market) : 0.0;
        $expected = array_sum(array_map(static fn(array $i): float => (float)$i['price_expected'], $main));
        $use = $budget > 0 ? min(100.0, $expected / $budget * 100) : 0.0;
        $widths = [];
        foreach ($market as $i) {
            $med = (float)$i['price_expected'];
            if ($med > 0) { $widths[] = ((float)$i['price_high'] - (float)$i['price_low']) / $med; }
        }
        $stability = $widths ? max(0.0, 100 - array_sum($widths) / count($widths) * 100) : 0.0;
        return [
            ['key' => 'coverage', 'label' => 'ความครบของชิ้นส่วน', 'value' => round($coverage, 1), 'note' => 'จำนวนหมวดที่มีราคามือสองที่ตรวจแล้วจาก 8 หมวดของคอมพิวเตอร์ หมวดที่ยังไม่มีราคามือสอง (ไม่มีราคา หรือใช้ราคาของใหม่อ้างอิง) ไม่นับ ทำให้คะแนนต่ำ'],
            ['key' => 'confidence', 'label' => 'ความมั่นใจของข้อมูลราคา', 'value' => round((float)$confidence, 1), 'note' => 'ค่าเฉลี่ยคะแนนความมั่นใจของชิ้นส่วนที่เลือก'],
            ['key' => 'budget', 'label' => 'การใช้งบ', 'value' => round($use, 1), 'note' => 'ราคาคาดการณ์รวมเทียบกับงบ เต็ม 100 เมื่อเท่ากับหรือเกินงบ'],
            ['key' => 'stability', 'label' => 'ความนิ่งของราคา', 'value' => round($stability, 1), 'note' => '100 ลบความกว้างเฉลี่ยของช่วงราคา Q1 ถึง Q3 เทียบกับราคากลาง ช่วงแคบแปลว่าตลาดให้ราคาใกล้กัน'],
        ];
    }
}
