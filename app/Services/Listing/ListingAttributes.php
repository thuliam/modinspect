<?php
declare(strict_types=1);

namespace App\Services\Listing;

use App\Services\Collection\CaptureMethod;

/**
 * LS-14 Module 9 (owner OD-5): one set of listing facts shared by imported market data and seller listings, so both describe a used part the same
 * way. Every field is optional except where a seller form says otherwise. Free text is short and may not carry a phone number or a link (seller
 * contact details stay out of the data). The condition percentage is the seller's own estimate, never a grade from ModInspect. Pure.
 */
final class ListingAttributes
{
    public const FIELDS = ['condition_percent', 'under_warranty', 'warranty_months', 'product_age_months', 'province', 'seller_warranty_terms',
        'is_defective', 'has_repair_history', 'shipping_option', 'shipping_cost', 'has_test_result', 'test_result_note', 'defect_codes', 'defect_note'];
    public const SHIPPING = ['free' => 'ส่งฟรี', 'paid' => 'มีค่าส่ง', 'pickup_only' => 'นัดรับเท่านั้น'];
    public const DEFECTS = ['scratch' => 'รอยขีดข่วน', 'dent' => 'บุบ/บิ่น', 'fan_noise' => 'พัดลมมีเสียง', 'coil_whine' => 'มีเสียงคอยล์', 'missing_parts' => 'อุปกรณ์ไม่ครบ',
        'bent_pins' => 'ขางอ', 'repaste' => 'เคยเปลี่ยนซิลิโคน/แผ่นระบายความร้อน', 'sticker_removed' => 'สติกเกอร์ประกันหลุด/ถูกแกะ', 'other' => 'อื่น ๆ'];
    public const PROVINCES = ['กรุงเทพมหานคร', 'กระบี่', 'กาญจนบุรี', 'กาฬสินธุ์', 'กำแพงเพชร', 'ขอนแก่น', 'จันทบุรี', 'ฉะเชิงเทรา', 'ชลบุรี', 'ชัยนาท', 'ชัยภูมิ', 'ชุมพร',
        'เชียงราย', 'เชียงใหม่', 'ตรัง', 'ตราด', 'ตาก', 'นครนายก', 'นครปฐม', 'นครพนม', 'นครราชสีมา', 'นครศรีธรรมราช', 'นครสวรรค์', 'นนทบุรี', 'นราธิวาส', 'น่าน',
        'บึงกาฬ', 'บุรีรัมย์', 'ปทุมธานี', 'ประจวบคีรีขันธ์', 'ปราจีนบุรี', 'ปัตตานี', 'พระนครศรีอยุธยา', 'พะเยา', 'พังงา', 'พัทลุง', 'พิจิตร', 'พิษณุโลก', 'เพชรบุรี',
        'เพชรบูรณ์', 'แพร่', 'ภูเก็ต', 'มหาสารคาม', 'มุกดาหาร', 'แม่ฮ่องสอน', 'ยโสธร', 'ยะลา', 'ร้อยเอ็ด', 'ระนอง', 'ระยอง', 'ราชบุรี', 'ลพบุรี', 'ลำปาง', 'ลำพูน', 'เลย',
        'ศรีสะเกษ', 'สกลนคร', 'สงขลา', 'สตูล', 'สมุทรปราการ', 'สมุทรสงคราม', 'สมุทรสาคร', 'สระแก้ว', 'สระบุรี', 'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี', 'สุราษฎร์ธานี',
        'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู', 'อ่างทอง', 'อำนาจเจริญ', 'อุดรธานี', 'อุตรดิตถ์', 'อุทัยธานี', 'อุบลราชธานี'];
    public const TEXT_MAX = 255;

    /**
     * Normalise raw input (a form or a CSV row). Unknown keys are ignored; an empty value is null.
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public static function parse(array $in): array
    {
        $v = array_fill_keys(self::FIELDS, null); $e = [];
        $str = static fn(string $k): string => trim((string)($in[$k] ?? ''));
        $bool = static function (string $k) use ($str): ?int { $s = strtolower($str($k)); return $s === '' ? null : (in_array($s, ['1', 'true', 'yes', 'y', 'on', 'ใช่', 'มี'], true) ? 1 : (in_array($s, ['0', 'false', 'no', 'n', 'off', 'ไม่', 'ไม่มี'], true) ? 0 : -1)); };
        $int = static function (string $k, int $min, int $max) use ($str, &$e): ?int {
            $s = $str($k); if ($s === '') { return null; }
            if (!preg_match('/^\d+$/', $s) || (int)$s < $min || (int)$s > $max) { $e[$k] = "ต้องเป็นตัวเลข $min ถึง $max"; return null; }
            return (int)$s;
        };
        $text = static function (string $k) use ($str, &$e): ?string {
            $s = preg_replace('/\s+/u', ' ', $str($k)) ?? ''; if ($s === '') { return null; }
            if (mb_strlen($s) > self::TEXT_MAX) { $e[$k] = 'ยาวเกิน ' . self::TEXT_MAX . ' ตัวอักษร'; return null; }
            if (CaptureMethod::hasPhoneNumber($s) || preg_match('#(https?://|www\.|line\.me|@[a-z0-9_.]{3,})#i', $s)) { $e[$k] = 'ห้ามใส่เบอร์โทร ลิงก์ หรือไอดีติดต่อ'; return null; }
            return $s;
        };

        $v['condition_percent'] = $int('condition_percent', 0, 100);
        foreach (['under_warranty', 'is_defective', 'has_repair_history', 'has_test_result'] as $k) {
            $b = $bool($k); if ($b === -1) { $e[$k] = 'ต้องเป็น ใช่ หรือ ไม่'; $b = null; } $v[$k] = $b;
        }
        $v['warranty_months'] = $int('warranty_months', 0, 120);
        $v['product_age_months'] = $int('product_age_months', 0, 360);
        $p = $str('province');
        if ($p !== '') { if (in_array($p, self::PROVINCES, true)) { $v['province'] = $p; } else { $e['province'] = 'ไม่ใช่ชื่อจังหวัด'; } }
        $v['seller_warranty_terms'] = $text('seller_warranty_terms');
        $ship = $str('shipping_option');
        if ($ship !== '') { if (isset(self::SHIPPING[$ship])) { $v['shipping_option'] = $ship; } else { $e['shipping_option'] = 'ตัวเลือกค่าส่งไม่ถูกต้อง'; } }
        $cost = str_replace(',', '', $str('shipping_cost'));
        if ($cost !== '') {
            if (!is_numeric($cost) || (float)$cost < 0 || (float)$cost > 5000) { $e['shipping_cost'] = 'ค่าส่งต้องเป็น 0 ถึง 5,000 บาท'; } else { $v['shipping_cost'] = round((float)$cost, 2); }
        }
        if ($v['shipping_option'] === 'paid' && $v['shipping_cost'] === null && !isset($e['shipping_cost'])) { $e['shipping_cost'] = 'ระบุค่าส่ง'; }
        if ($v['shipping_option'] !== 'paid') { $v['shipping_cost'] = $v['shipping_option'] === 'free' ? 0.0 : null; }
        $v['test_result_note'] = $text('test_result_note');
        $codes = $in['defect_codes'] ?? [];
        if (is_string($codes)) { $codes = preg_split('/[;,|]+/', $codes) ?: []; }
        $codes = array_values(array_unique(array_filter(array_map(static fn($c) => trim((string)$c), (array)$codes), static fn($c) => $c !== '')));
        $bad = array_diff($codes, array_keys(self::DEFECTS));
        if ($bad) { $e['defect_codes'] = 'ตำหนิไม่อยู่ในรายการ'; } else { $v['defect_codes'] = $codes ? implode(',', $codes) : null; }
        $v['defect_note'] = $text('defect_note');
        if ($v['under_warranty'] === 0) { $v['warranty_months'] = null; }
        return ['values' => $v, 'errors' => $e];
    }

    /** Short Thai lines for display, only for fields that have a value. @return list<array{label:string,value:string}> */
    public static function describe(array $v): array
    {
        $out = [];
        $add = static function (string $l, ?string $val) use (&$out): void { if ($val !== null && $val !== '') { $out[] = ['label' => $l, 'value' => $val]; } };
        $yn = static fn($b): ?string => $b === null ? null : ((int)$b === 1 ? 'ใช่' : 'ไม่');
        $add('สภาพ (ผู้ขายประเมินเอง)', isset($v['condition_percent']) && $v['condition_percent'] !== null ? (int)$v['condition_percent'] . '%' : null);
        $add('อยู่ในประกัน', isset($v['under_warranty']) && $v['under_warranty'] !== null ? ((int)$v['under_warranty'] === 1 ? 'ใช่' . (!empty($v['warranty_months']) ? ' เหลือ ' . (int)$v['warranty_months'] . ' เดือน' : '') : 'ไม่') : null);
        $add('อายุสินค้า', !empty($v['product_age_months']) ? (int)$v['product_age_months'] . ' เดือน' : null);
        $add('จังหวัด', $v['province'] ?? null);
        $add('ประกันจากผู้ขาย', $v['seller_warranty_terms'] ?? null);
        $add('ขายแบบเสีย/ซ่อม', $yn($v['is_defective'] ?? null));
        $add('ผ่านงานช่าง', $yn($v['has_repair_history'] ?? null));
        $add('การส่ง', isset($v['shipping_option']) && $v['shipping_option'] !== null ? self::SHIPPING[$v['shipping_option']] . ($v['shipping_option'] === 'paid' && $v['shipping_cost'] !== null ? ' ฿' . number_format((float)$v['shipping_cost']) : '') : null);
        $add('มีผลเทส', $yn($v['has_test_result'] ?? null));
        $add('ผลเทส', $v['test_result_note'] ?? null);
        $add('ตำหนิ', !empty($v['defect_codes']) ? implode(', ', array_map(static fn($c) => self::DEFECTS[$c] ?? $c, explode(',', (string)$v['defect_codes']))) : null);
        $add('รายละเอียดตำหนิ', $v['defect_note'] ?? null);
        return $out;
    }
}
