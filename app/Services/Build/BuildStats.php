<?php
declare(strict_types=1);

namespace App\Services\Build;

/**
 * LS-14 Module 6 (owner decision OD-1): six "stats" of a chosen build, 0 to 100, for the hexagon radar on the Build page. They are computed from the
 * manufacturers' published specifications only (cores, clocks, GPU cores, VRAM, memory size, storage type) and the parts' prices. They are NOT a
 * benchmark: no frame rate, no test result, and GPU core counts of different makers or generations are not equal in real speed. The page says so.
 *
 * Every number is relative to the catalog: 100 means "as high as the strongest part of that kind in the ModInspect catalog". A component whose figure
 * is unknown is left out, never counted as 0; an axis is shown only when the known parts carry at least MIN_COVERAGE of its weight, otherwise it is
 * "no data". Weights are listed here with their reason; change them only with a new VERSION. Pure.
 */
final class BuildStats
{
    public const VERSION = 'build-stats-v1';
    public const NOTE = 'คะแนนจากสเปกของผู้ผลิต ไม่ใช่ผลทดสอบจริง 100 คือเท่ากับชิ้นที่สเปกสูงสุดในฐานข้อมูลของเรา จำนวนคอร์การ์ดจอต่างค่ายหรือต่างรุ่นเทียบกันตรง ๆ ไม่ได้';
    public const MIN_COVERAGE = 0.75;

    /** axis => [label, weights by component metric, reason] */
    public const AXES = [
        'overall' => ['ประสิทธิภาพรวม', ['cpu_mt' => 0.2, 'cpu_st' => 0.2, 'gpu' => 0.4, 'ram' => 0.1, 'storage' => 0.1], 'CPU และการ์ดจอเท่า ๆ กัน RAM และดิสก์เป็นส่วนเสริม'],
        'gaming' => ['Gaming', ['gpu' => 0.6, 'cpu_st' => 0.15, 'cpu_mt' => 0.1, 'vram' => 0.05, 'ram' => 0.1], 'เกมขึ้นกับการ์ดจอมากที่สุด รองลงมาคือความเร็วต่อคอร์ของ CPU'],
        'ai' => ['AI', ['vram' => 0.45, 'gpu' => 0.3, 'ram' => 0.15, 'cpu_mt' => 0.1], 'งาน AI บนเครื่องติดที่ขนาด VRAM ก่อน แล้วจึงเป็นพลังการ์ดจอ'],
        'workstation' => ['Workstation', ['cpu_mt' => 0.5, 'ram' => 0.25, 'gpu' => 0.15, 'storage' => 0.1], 'งานคำนวณและเรนเดอร์ใช้หลายคอร์และ RAM มาก'],
        'creator' => ['ตัดต่อ/สตรีม', ['cpu_mt' => 0.35, 'gpu' => 0.3, 'vram' => 0.15, 'ram' => 0.1, 'storage' => 0.1], 'ตัดต่อและสตรีมใช้ทั้ง CPU หลายคอร์และการ์ดจอช่วยเข้ารหัสวิดีโอ'],
        'value' => ['ความคุ้มค่า', [], 'พลัง CPU และการ์ดจอต่อบาท เทียบกับชิ้นที่คุ้มที่สุดในฐานข้อมูล'],
    ];
    /** memory beyond this does not raise the RAM score (more than most home builds use) */
    public const RAM_CAP_GB = 64;

    /** @return array{cpu_mt:?float,cpu_st:?float,gpu:?float,vram:?float,ram:?float,storage:?float} raw figures of one part (only its own kind is filled) */
    public static function metrics(string $role, array $product, array $specs): array
    {
        $m = ['cpu_mt' => null, 'cpu_st' => null, 'gpu' => null, 'vram' => null, 'ram' => null, 'storage' => null];
        if ($role === 'cpu') {
            $boost = BuildSpecs::num($specs['boost_ghz'] ?? null);
            $p = BuildSpecs::num($specs['p_cores'] ?? null); $e = BuildSpecs::num($specs['e_cores'] ?? null) ?? 0.0;
            $cores = $p !== null ? $p + 0.5 * $e : BuildSpecs::num($specs['cores'] ?? null);   // an efficiency core counted as half a performance core
            $m['cpu_st'] = $boost;
            $m['cpu_mt'] = $boost !== null && $cores !== null ? $cores * $boost : null;
        } elseif ($role === 'gpu') {
            $c = BuildSpecs::num($specs['gpu_cores'] ?? null); $mhz = BuildSpecs::num($specs['boost_mhz'] ?? null);
            $m['gpu'] = $c !== null && $mhz !== null ? $c * $mhz / 1000 : null;
            $m['vram'] = BuildSpecs::num($specs['vram_gb'] ?? null);
        } elseif ($role === 'ram') {
            $gb = BuildSpecs::ramGb($product, $specs);
            $m['ram'] = $gb !== null ? min($gb, (float)self::RAM_CAP_GB) : null;
        } elseif ($role === 'storage') {
            $cls = BuildSpecs::storageClass($product, $specs);
            $m['storage'] = $cls !== null ? (float)$cls : null;
        }
        return $m;
    }

    /**
     * The catalog's best figure per metric and the best spec-per-baht for CPUs and GPUs.
     * @param list<array{role:string,product:array,specs:array<string,string>,price:float}> $catalog
     */
    public static function reference(array $catalog): array
    {
        $ref = ['cpu_mt' => 0.0, 'cpu_st' => 0.0, 'gpu' => 0.0, 'vram' => 0.0, 'ram' => (float)self::RAM_CAP_GB, 'storage' => 100.0, 'cpu_value' => 0.0, 'gpu_value' => 0.0];
        foreach ($catalog as $c) {
            $m = self::metrics($c['role'], $c['product'], $c['specs']);
            foreach (['cpu_mt', 'cpu_st', 'gpu', 'vram'] as $k) { if ($m[$k] !== null && $m[$k] > $ref[$k]) { $ref[$k] = $m[$k]; } }
            if ($c['price'] > 0 && $c['role'] === 'cpu' && $m['cpu_mt'] !== null) { $ref['cpu_value'] = max($ref['cpu_value'], $m['cpu_mt'] / $c['price']); }
            if ($c['price'] > 0 && $c['role'] === 'gpu' && $m['gpu'] !== null) { $ref['gpu_value'] = max($ref['gpu_value'], $m['gpu'] / $c['price']); }
        }
        return $ref;
    }

    /**
     * @param array<string,array{product:array,specs:array<string,string>,price:float}> $parts chosen parts by role
     * @return list<array{key:string,label:string,value:?float,coverage:float,reason:string}>
     */
    public static function axes(array $parts, array $ref): array
    {
        $score = ['cpu_mt' => null, 'cpu_st' => null, 'gpu' => null, 'vram' => null, 'ram' => null, 'storage' => null];
        foreach ($parts as $role => $part) {
            foreach (self::metrics((string)$role, $part['product'], $part['specs']) as $k => $v) {
                if ($v !== null && ($ref[$k] ?? 0) > 0) { $score[$k] = min(100.0, 100.0 * $v / $ref[$k]); }
            }
        }
        $out = [];
        foreach (self::AXES as $key => [$label, $weights, $reason]) {
            if ($key === 'value') { $out[] = self::value($parts, $ref, $label, $reason); continue; }
            $sum = 0.0; $known = 0.0;
            foreach ($weights as $k => $w) { if ($score[$k] !== null) { $sum += $w * $score[$k]; $known += $w; } }
            $out[] = ['key' => $key, 'label' => $label, 'value' => $known >= self::MIN_COVERAGE ? round($sum / $known, 1) : null, 'coverage' => round($known, 2), 'reason' => $reason];
        }
        return $out;
    }

    private static function value(array $parts, array $ref, string $label, string $reason): array
    {
        $vals = [];
        foreach (['cpu' => ['cpu_mt', 'cpu_value'], 'gpu' => ['gpu', 'gpu_value']] as $role => [$metric, $best]) {
            if (!isset($parts[$role]) || ($parts[$role]['price'] ?? 0) <= 0 || $ref[$best] <= 0) { continue; }
            $m = self::metrics($role, $parts[$role]['product'], $parts[$role]['specs'])[$metric];
            if ($m !== null) { $vals[] = min(100.0, 100.0 * ($m / $parts[$role]['price']) / $ref[$best]); }
        }
        $needed = isset($parts['gpu']) ? 2 : 1;   // a build with a graphics card is judged on both parts
        return ['key' => 'value', 'label' => $label, 'value' => count($vals) >= $needed && $vals ? round(array_sum($vals) / count($vals), 1) : null, 'coverage' => (float)count($vals) / $needed, 'reason' => $reason];
    }
}
