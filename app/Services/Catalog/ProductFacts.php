<?php
declare(strict_types=1);
namespace App\Services\Catalog;

/**
 * The facts block of the product page: when the model came out, how old the MODEL is, and its specifications. Pure.
 *
 * Model age is counted from the release YEAR (the catalog holds a year, not a day), so it is "about N years", and it is the age of the model, never the wear
 * of a unit someone is selling. A fact that came from a manufacturer page (product_reference_specs) keeps its source link and check date and replaces the catalog
 * value of the same fact. A missing fact is left out. No benchmark, rank or invented figure appears here.
 */
final class ProductFacts
{
    public const MIN_YEAR = 1990;

    /** catalog column => [label, spec key, unit] */
    private const CATALOG = [
        'generation'  => ['สถาปัตยกรรม / เจเนอเรชัน', 'generation', ''],
        'socket'      => ['Socket', 'socket', ''],
        'memory_type' => ['ชนิดหน่วยความจำ', 'memory_type', ''],
        'tdp_watt'    => ['TDP', 'tdp_watt', ' W'],
        'form_factor' => ['ขนาด / ฟอร์มแฟกเตอร์', 'form_factor', ''],
        'capacity_gb' => ['ความจุ', 'capacity_gb', ' GB'],
    ];
    /** manufacturer spec key => [label, unit shown after the value] */
    private const REFERENCE = [
        'socket' => ['Socket', ''], 'memory_type' => ['ชนิดหน่วยความจำ', ''], 'form_factor' => ['ขนาด / ฟอร์มแฟกเตอร์', ''], 'capacity_gb' => ['ความจุ', ' GB'],
        'board_power_watt' => ['กำลังไฟของการ์ด', ' W'], 'system_psu_recommendation_watt' => ['PSU ที่ผู้ผลิตแนะนำ', ' W'], 'psu_capacity_watt' => ['กำลังไฟ PSU', ' W'],
        'max_gpu_length_mm' => ['การ์ดจอยาวสุดที่ใส่ได้', ' mm'], 'max_cpu_cooler_height_mm' => ['ซิงก์ CPU สูงสุดที่ใส่ได้', ' mm'], 'cooler_height_mm' => ['ความสูงซิงก์', ' mm'],
    ];

    /** @return array{year:int,age:int,label:string,note:string}|null */
    public static function modelAge(?int $releaseYear, int $currentYear): ?array
    {
        if ($releaseYear === null || $releaseYear < self::MIN_YEAR || $releaseYear > $currentYear) { return null; }
        $age = $currentYear - $releaseYear;
        return [
            'year' => $releaseYear, 'age' => $age,
            'label' => $age === 0 ? "เปิดตัวปี $releaseYear · เป็นรุ่นที่เปิดตัวในปีนี้" : "เปิดตัวปี $releaseYear · อายุรุ่นประมาณ $age ปี",
            'note' => 'อายุรุ่นนับจากปีที่เปิดตัว ไม่ใช่อายุการใช้งานของเครื่องที่ประกาศขาย',
        ];
    }

    /**
     * @param array<string,mixed> $product a products row
     * @param list<array<string,mixed>> $reference rows of product_reference_specs
     * @return list<array{label:string,value:string,source:?string,checked:?string,note:?string}>
     */
    public static function rows(array $product, array $reference, int $currentYear): array
    {
        $rows = [];
        $age = self::modelAge(isset($product['release_year']) ? (int)$product['release_year'] : null, $currentYear);
        if ($age !== null) { $rows['release'] = ['label' => 'เปิดตัว', 'value' => $age['label'], 'source' => null, 'checked' => null, 'note' => $age['note']]; }
        foreach (self::CATALOG as $col => [$label, $key, $unit]) {
            $v = trim((string)($product[$col] ?? ''));
            if ($v === '' || $v === '0') { continue; }
            $rows[$key] = ['label' => $label, 'value' => $v . $unit, 'source' => null, 'checked' => null, 'note' => null];
        }
        foreach ($reference as $r) {
            $key = (string)($r['spec_key'] ?? '');
            if (!isset(self::REFERENCE[$key])) { continue; }
            $v = trim((string)($r['spec_value'] ?? ''));
            if ($v === '') { continue; }
            [$label, $unit] = self::REFERENCE[$key];
            $url = (string)($r['source_url'] ?? '');
            $rows[$key] = [
                'label' => $label, 'value' => $v . $unit,
                'source' => preg_match('#^https?://#i', $url) === 1 ? $url : null,
                'checked' => ($r['date_checked'] ?? null) !== null ? (string)$r['date_checked'] : null,
                'note' => ($r['review_status'] ?? '') === 'needs_variant_review' ? 'แหล่งที่มาระบุรุ่นย่อยเฉพาะ ตรวจรุ่นย่อยของชิ้นที่จะซื้อให้ตรงก่อน' : null,
            ];
        }
        return array_values($rows);
    }
}
