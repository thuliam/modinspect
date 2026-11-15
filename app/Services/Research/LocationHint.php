<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Coarse item-location hint: a Thai province (and its region) only, matched from a fixed whitelist.
 * It never returns a district, address, shop, seller or any free text (AGENTS.md: minimise seller PII).
 */
final class LocationHint
{
    private const REGIONS = [
        'central' => ['กรุงเทพมหานคร', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'สมุทรสาคร', 'นครปฐม', 'พระนครศรีอยุธยา', 'อ่างทอง', 'ลพบุรี', 'สิงห์บุรี', 'ชัยนาท', 'สระบุรี', 'นครนายก', 'สุพรรณบุรี', 'สมุทรสงคราม', 'กาญจนบุรี', 'ราชบุรี', 'เพชรบุรี', 'ประจวบคีรีขันธ์'],
        'east' => ['ชลบุรี', 'ระยอง', 'จันทบุรี', 'ตราด', 'ฉะเชิงเทรา', 'ปราจีนบุรี', 'สระแก้ว'],
        'north' => ['เชียงใหม่', 'เชียงราย', 'ลำปาง', 'ลำพูน', 'แม่ฮ่องสอน', 'น่าน', 'พะเยา', 'แพร่', 'อุตรดิตถ์', 'พิษณุโลก', 'สุโขทัย', 'ตาก', 'กำแพงเพชร', 'พิจิตร', 'เพชรบูรณ์', 'นครสวรรค์', 'อุทัยธานี'],
        'northeast' => ['นครราชสีมา', 'ขอนแก่น', 'อุดรธานี', 'อุบลราชธานี', 'บุรีรัมย์', 'สุรินทร์', 'ศรีสะเกษ', 'ชัยภูมิ', 'มหาสารคาม', 'ร้อยเอ็ด', 'กาฬสินธุ์', 'สกลนคร', 'นครพนม', 'มุกดาหาร', 'เลย', 'หนองคาย', 'หนองบัวลำภู', 'ยโสธร', 'อำนาจเจริญ', 'บึงกาฬ'],
        'south' => ['นครศรีธรรมราช', 'สุราษฎร์ธานี', 'ภูเก็ต', 'สงขลา', 'กระบี่', 'ตรัง', 'พัทลุง', 'ชุมพร', 'ระนอง', 'พังงา', 'สตูล', 'ปัตตานี', 'ยะลา', 'นราธิวาส'],
    ];
    /** Common short forms people write instead of the official province name. */
    private const ALIASES = [
        'กทม' => 'กรุงเทพมหานคร', 'กรุงเทพ' => 'กรุงเทพมหานคร', 'โคราช' => 'นครราชสีมา', 'อยุธยา' => 'พระนครศรีอยุธยา',
        'สุราษฎร์' => 'สุราษฎร์ธานี', 'อุบล' => 'อุบลราชธานี', 'อุดร' => 'อุดรธานี', 'นครศรี' => 'นครศรีธรรมราช', 'หาดใหญ่' => 'สงขลา',
    ];

    /** @return array{province:string,region:string}|null */
    public static function detect(string $text): ?array
    {
        if ($text === '') { return null; }
        $best = null; $bestPos = PHP_INT_MAX;
        foreach (self::REGIONS as $region => $provinces) {
            foreach ($provinces as $name) {
                $pos = mb_strpos($text, $name, 0, 'UTF-8');
                if ($pos !== false && $pos < $bestPos) { $best = [$name, $region]; $bestPos = $pos; }
            }
        }
        foreach (self::ALIASES as $short => $name) {
            $pos = mb_strpos($text, $short, 0, 'UTF-8');
            if ($pos !== false && $pos < $bestPos) { $best = [$name, self::regionOf($name)]; $bestPos = $pos; }
        }
        return $best === null ? null : ['province' => $best[0], 'region' => $best[1]];
    }

    public static function regionOf(string $province): ?string
    {
        foreach (self::REGIONS as $region => $provinces) {
            if (in_array($province, $provinces, true)) { return $region; }
        }
        return null;
    }
}
