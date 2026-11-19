<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Deal Checker verdict (deterministic, neutral). The stored label stays one of insufficient / below_range / in_range / above_range (a database ENUM);
 * LS-20 adds a severity TIER for the two outside cases, because "above the range" used to mean both ฿2,500 against a ฿2,240 top and ฿9,200 against it,
 * and the page called both "slightly above". The tier is how far the price is outside the edge it crossed, as a share of that edge:
 *   slight  up to 15 %      clear  15 % to 50 %      far  more than 50 %
 * These are presentation thresholds, not a valuation: nothing here says what a receipt, a warranty or a condition is worth (docs/PRICE_ENGINE.md).
 */
final class PriceEngine
{
    public const SLIGHT = 0.15;
    public const FAR = 0.50;

    /** verdict wording per direction and tier (the recommendation text is shown under the verdict) */
    public const TEXT = [
        'above' => [
            'slight' => ['label' => 'สูงกว่าช่วงที่พบเล็กน้อย', 'recommendation' => 'ราคานี้สูงกว่าช่วงตลาดที่ตรวจพบเล็กน้อย ประกัน สภาพ และอุปกรณ์ที่ครบอาจรองรับส่วนต่างระดับนี้ได้'],
            'clear' => ['label' => 'สูงกว่าช่วงที่พบ', 'recommendation' => 'ราคานี้สูงกว่าช่วงตลาดที่ตรวจพบพอสมควร ควรมีเหตุผลที่ตรวจสอบได้รองรับ เช่น ประกันที่เหลือ หลักฐานการซื้อ หรือผลทดสอบสภาพ'],
            'far' => ['label' => 'สูงกว่าช่วงที่พบมาก', 'recommendation' => 'ราคานี้สูงกว่าช่วงตลาดที่ตรวจพบมาก ระบบไม่มีข้อมูลที่ตีมูลค่าประกันหรือสภาพเป็นตัวเงินได้ จึงไม่บอกว่าส่วนต่างขนาดนี้สมเหตุสมผล ควรตรวจว่ารุ่นและความจุตรงกับของที่เจอ และขอเหตุผลจากผู้ขาย'],
        ],
        'below' => [
            'slight' => ['label' => 'ต่ำกว่าช่วงที่พบเล็กน้อย', 'recommendation' => 'ราคานี้ต่ำกว่าช่วงตลาดที่ตรวจพบเล็กน้อย ควรตรวจสภาพ ที่มา และเงื่อนไขตามปกติ'],
            'clear' => ['label' => 'ต่ำกว่าช่วงที่พบ', 'recommendation' => 'ราคานี้ต่ำกว่าช่วงตลาดที่ตรวจพบพอสมควร ควรตรวจสภาพ ที่มา และเงื่อนไขให้ละเอียด'],
            'far' => ['label' => 'ต่ำกว่าช่วงที่พบมาก', 'recommendation' => 'ราคานี้ต่ำกว่าช่วงตลาดที่ตรวจพบมาก ควรตรวจสภาพ ที่มา และเงื่อนไขให้ละเอียดที่สุด และเช็กว่ารุ่นตรงกับของที่ขายจริง'],
        ],
    ];

    /** how far outside the range a price is: null inside it (edges count as inside), otherwise slight, clear or far */
    public static function tier(float $price, float $q1, float $q3): ?string
    {
        if ($q3 > 0 && $price > $q3) { $d = ($price - $q3) / $q3; }
        elseif ($q1 > 0 && $price < $q1) { $d = ($q1 - $price) / $q1; }
        else { return null; }
        return $d <= self::SLIGHT ? 'slight' : ($d <= self::FAR ? 'clear' : 'far');
    }

    public function evaluate(array $product, float $price): array {
        if (!$product['q1'] || !$product['q3'] || (int)($product['accepted_observation_count'] ?? 0) <= 0) return ['label'=>'insufficient','tier'=>null,'recommendation'=>'ข้อมูลตลาดยังไม่เพียงพอสำหรับประเมินช่วงราคา','warning'=>'ต้องมีข้อมูลราคาที่ผ่านการตรวจสอบแล้วก่อน จึงจะใช้เป็นช่วงอ้างอิงสาธารณะได้'];
        $q1=(float)$product['q1']; $q3=(float)$product['q3'];
        $tier=self::tier($price,$q1,$q3);
        if ($price < $q1) return ['label'=>'below_range','tier'=>$tier,'recommendation'=>self::TEXT['below'][$tier]['recommendation'],'warning'=>'ราคาต่ำไม่ใช่สัญญาณให้ซื้อทันที'];
        if ($price > $q3) return ['label'=>'above_range','tier'=>$tier,'recommendation'=>self::TEXT['above'][$tier]['recommendation'],'warning'=>'เปรียบเทียบประกันและหลักฐานการทดสอบก่อนตัดสินใจ'];
        return ['label'=>'in_range','tier'=>null,'recommendation'=>'ราคานี้อยู่ในช่วงตลาดที่ตรวจพบ','warning'=>'ข้อมูลเป็นราคาประกาศ ไม่ใช่ราคาปิดดีลจริง'];
    }
}
