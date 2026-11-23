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
        'memory_speed_mhz' => ['ความเร็วบัสหน่วยความจำ', 'speed_mhz', ' MHz'], // LS-71 (RAM)
        'kit_modules' => ['จำนวนแท่งในชุด', 'modules_in_kit', ' แท่ง'],
        'interface_type' => ['ช่องต่อ', 'interface', ''], 'media_type' => ['ชนิดไดรฟ์', 'media_type', ''], // LS-74 (storage)
        'power_watt' => ['กำลังไฟ', 'psu_capacity_watt', ' W'], 'efficiency_rating' => ['มาตรฐานประสิทธิภาพ', 'efficiency_rating', ''], 'modular_type' => ['ชนิดสาย', 'modular_type', ''], // LS-74 (PSU)
    ];
    /** manufacturer spec key => [label, unit shown after the value] */
    private const REFERENCE = [
        'socket' => ['Socket', ''], 'memory_type' => ['ชนิดหน่วยความจำ', ''], 'form_factor' => ['ขนาด / ฟอร์มแฟกเตอร์', ''], 'capacity_gb' => ['ความจุ', ' GB'],
        'board_power_watt' => ['กำลังไฟของการ์ด', ' W'], 'system_psu_recommendation_watt' => ['PSU ที่ผู้ผลิตแนะนำ', ' W'], 'psu_capacity_watt' => ['กำลังไฟ PSU', ' W'],
        'max_gpu_length_mm' => ['การ์ดจอยาวสุดที่ใส่ได้', ' mm'], 'max_cpu_cooler_height_mm' => ['ซิงก์ CPU สูงสุดที่ใส่ได้', ' mm'], 'cooler_height_mm' => ['ความสูงซิงก์', ' mm'],
        // F-2: CPU/GPU figures read from AMD, Intel and NVIDIA pages (migration 021). 'architecture' takes the slot of the catalog generation.
        'architecture' => ['สถาปัตยกรรม / เจเนอเรชัน', '', 'generation'], 'cores' => ['จำนวนคอร์', ''], 'threads' => ['จำนวนเธรด', ''], 'p_cores' => ['P-core (คอร์แรง)', ''], 'e_cores' => ['E-core (คอร์ประหยัดไฟ)', ''],
        'base_ghz' => ['ความเร็วพื้นฐาน', ' GHz'], 'boost_ghz' => ['ความเร็วบูสต์สูงสุด', ' GHz'], 'l2_mb' => ['L2 cache', ' MB'], 'l3_mb' => ['L3 cache', ' MB'], 'memory_support' => ['หน่วยความจำที่รองรับ', ''],
        'gpu_cores' => ['จำนวนคอร์กราฟิก (CUDA / Stream)', ''], 'boost_mhz' => ['ความเร็วบูสต์ (การ์ดจอ)', ' MHz'], 'vram_gb' => ['VRAM', ' GB'], 'bus_width_bit' => ['บัสหน่วยความจำ', ' bit'],
        'power_connector' => ['ขั้วต่อไฟเสริม', ''], 'interface' => ['ช่องต่อ', ''],
        // LS-23 B5: keys of the collection contract (database/contracts/intake-v1.json); values come from manufacturer pages
        'chipset' => ['ชิปเซ็ต', ''], 'max_memory_gb' => ['หน่วยความจำสูงสุด', ' GB'], 'memory_slots' => ['จำนวนช่องแรม', ''], 'pcie_gen_x16' => ['PCIe ช่องการ์ดจอ', ''], 'm2_slots' => ['ช่อง M.2', ''],
        'speed_mhz' => ['ความเร็ว', ' MHz'], 'cas_latency' => ['CAS Latency', ''], 'modules_in_kit' => ['จำนวนแท่งในชุด', ''],
        'tbw' => ['ความทนทานการเขียน (TBW)', ' TB'], 'read_mbps' => ['ความเร็วอ่านสูงสุด', ' MB/s'], 'write_mbps' => ['ความเร็วเขียนสูงสุด', ' MB/s'],
        'efficiency_rating' => ['ประสิทธิภาพ (80 Plus)', ''], 'modular' => ['ระบบสายไฟ', ''],
        'cooler_type' => ['ชนิดการระบายความร้อน', ''], 'radiator_mm' => ['ขนาดหม้อน้ำ', ' mm'], 'socket_support' => ['Socket ที่รองรับ', ''],
        'length_mm' => ['ความยาวการ์ด', ' mm'], 'has_igpu' => ['กราฟิกในตัว', ''],
    ];
    private const VARIANT_NOTE = 'แหล่งที่มาระบุรุ่นย่อยเฉพาะ ตรวจรุ่นย่อยของชิ้นที่จะซื้อให้ตรงก่อน';

    /** @return array{year:int,age:int,label:string,note:string}|null */
    public static function modelAge(?int $releaseYear, int $currentYear, ?string $detail = null): ?array
    {
        if ($releaseYear === null || $releaseYear < self::MIN_YEAR || $releaseYear > $currentYear) { return null; }
        $age = $currentYear - $releaseYear;
        $when = $detail === null ? "ปี $releaseYear" : " $detail";
        return [
            'year' => $releaseYear, 'age' => $age,
            'label' => $age === 0 ? "เปิดตัว$when · เป็นรุ่นที่เปิดตัวในปีนี้" : "เปิดตัว$when · อายุรุ่นประมาณ $age ปี",
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
        $byKey = [];
        foreach ($reference as $r) { $byKey[(string)($r['spec_key'] ?? '')] = $r; }
        $age = null; $ref = null;
        foreach ([['release_date', 'date'], ['release_quarter', 'quarter']] as [$k, $kind]) {
            $cand = self::referenceRelease($byKey[$k] ?? null, $kind, $currentYear);
            if ($cand !== null) { [$year, $detail] = $cand; $age = self::modelAge($year, $currentYear, $detail); $ref = $byKey[$k]; break; }
        }
        if ($age === null) { $age = self::modelAge(isset($product['release_year']) ? (int)$product['release_year'] : null, $currentYear); }
        if ($age !== null) {
            $url = (string)($ref['source_url'] ?? '');
            $rows['release'] = [
                'label' => 'เปิดตัว', 'value' => $age['label'],
                'source' => $ref !== null && preg_match('#^https?://#i', $url) === 1 ? $url : null,
                'checked' => $ref !== null && ($ref['date_checked'] ?? null) !== null ? (string)$ref['date_checked'] : null,
                'note' => $age['note'] . ($ref !== null && ($ref['review_status'] ?? '') === 'needs_variant_review' ? ' · ' . self::VARIANT_NOTE : ''),
            ];
        }
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
            $slot = self::REFERENCE[$key][2] ?? $key;
            $url = (string)($r['source_url'] ?? '');
            $rows[$slot] = [
                'label' => $label, 'value' => $v . $unit,
                'source' => preg_match('#^https?://#i', $url) === 1 ? $url : null,
                'checked' => ($r['date_checked'] ?? null) !== null ? (string)$r['date_checked'] : null,
                'note' => ($r['review_status'] ?? '') === 'needs_variant_review' ? self::VARIANT_NOTE : null,
            ];
        }
        return array_values($rows);
    }

    /**
     * A manufacturer launch date (YYYY-MM-DD) or quarter (YYYY-Qn) as [year, text shown], or null when it is absent, not a date, or after the current year.
     * @param array<string,mixed>|null $row
     * @return array{0:int,1:string}|null
     */
    private static function referenceRelease(?array $row, string $kind, int $currentYear): ?array
    {
        $v = trim((string)($row['spec_value'] ?? ''));
        if ($kind === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1 && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) { $year = (int)$m[1]; $text = $v; }
        elseif ($kind === 'quarter' && preg_match('/^(\d{4})-Q([1-4])$/', $v, $m) === 1) { $year = (int)$m[1]; $text = "$year (Q$m[2])"; }
        else { return null; }
        return $year >= self::MIN_YEAR && $year <= $currentYear ? [$year, $text] : null;
    }
}
