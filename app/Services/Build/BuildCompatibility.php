<?php
declare(strict_types=1);

namespace App\Services\Build;

/**
 * LS-14 Module 6.6: does the chosen set of parts fit together. Each check appears only when both parts it needs are chosen, and has one of three
 * results: ok, fail, or unknown (a value is missing from the catalog). Unknown is never reported as ok. Socket and memory-type failures block saving
 * (the parts cannot work together); the other failures are warnings the visitor should act on. Pure.
 *
 * @phpstan-type Part array{product:array,specs:array<string,string>}
 */
final class BuildCompatibility
{
    /** watts added for the board, drives, fans and USB devices before the margin */
    public const PSU_BASE_WATT = 100;
    /** PSU headroom: 25 % above the estimated load (efficiency and peaks) */
    public const PSU_MARGIN = 1.25;

    /**
     * @param array<string,array{product:array,specs:array<string,string>}> $parts keyed by role: cpu, motherboard, gpu, ram, storage, psu, case, cooling
     * @return list<array{key:string,label:string,status:string,blocking:bool,text:string}>
     */
    public static function check(array $parts): array
    {
        $out = [];
        $has = static fn(string $r): bool => isset($parts[$r]);
        $p = static fn(string $r): array => $parts[$r]['product'];
        $s = static fn(string $r): array => $parts[$r]['specs'] ?? [];

        if ($has('cpu') && $has('motherboard')) {
            $a = BuildSpecs::socket($p('cpu'), $s('cpu')); $b = BuildSpecs::socket($p('motherboard'), $s('motherboard'));
            $out[] = $a === null || $b === null
                ? self::row('socket', 'Socket CPU กับเมนบอร์ด', 'unknown', false, 'ไม่ทราบ socket ของ CPU หรือเมนบอร์ด กรุณาตรวจเองก่อนซื้อ')
                : ($a === $b ? self::row('socket', 'Socket CPU กับเมนบอร์ด', 'ok', false, "ตรงกัน ($a)")
                    : self::row('socket', 'Socket CPU กับเมนบอร์ด', 'fail', true, "ไม่ตรงกัน: CPU เป็น $a แต่เมนบอร์ดเป็น $b"));
        }
        if ($has('ram') && $has('motherboard')) {
            $a = BuildSpecs::memoryType($p('ram'), $s('ram')); $b = BuildSpecs::memoryType($p('motherboard'), $s('motherboard'));
            $out[] = $a === null || $b === null
                ? self::row('memory', 'ชนิด RAM กับเมนบอร์ด', 'unknown', false, 'ไม่ทราบชนิด RAM หรือเมนบอร์ด กรุณาตรวจเองว่าเป็น DDR เดียวกัน')
                : ($a === $b ? self::row('memory', 'ชนิด RAM กับเมนบอร์ด', 'ok', false, "ตรงกัน ($a)")
                    : self::row('memory', 'ชนิด RAM กับเมนบอร์ด', 'fail', true, "ไม่ตรงกัน: RAM เป็น $a แต่เมนบอร์ดรองรับ $b"));
        }
        if ($has('ram') && $has('cpu')) {
            $ram = BuildSpecs::memoryType($p('ram'), $s('ram'));
            $support = strtoupper((string)($s('cpu')['memory_support'] ?? ''));
            if ($ram !== null && $support !== '' && preg_match('/DDR\d/', $support) === 1) {
                $out[] = str_contains($support, $ram)
                    ? self::row('cpu_memory', 'ชนิด RAM กับ CPU', 'ok', false, "CPU รองรับ $ram")
                    : self::row('cpu_memory', 'ชนิด RAM กับ CPU', 'fail', true, "CPU รองรับ $support แต่ RAM เป็น $ram");
            }
        }
        if ($has('psu') && ($has('cpu') || $has('gpu'))) { $out[] = self::psu($parts); }
        if ($has('case') && $has('motherboard')) {
            $caseRank = BuildSpecs::formRank(BuildSpecs::get($p('case'), $s('case'), 'form_factor', 'form_factor'));
            $mbRank = BuildSpecs::formRank(BuildSpecs::get($p('motherboard'), $s('motherboard'), 'form_factor', 'form_factor'));
            $out[] = $caseRank === null || $mbRank === null
                ? self::row('form_factor', 'ขนาดเมนบอร์ดกับเคส', 'unknown', false, 'ไม่ทราบขนาดเคสหรือเมนบอร์ด กรุณาตรวจเอง')
                : ($mbRank <= $caseRank ? self::row('form_factor', 'ขนาดเมนบอร์ดกับเคส', 'ok', false, 'เคสใส่เมนบอร์ดขนาดนี้ได้')
                    : self::row('form_factor', 'ขนาดเมนบอร์ดกับเคส', 'fail', false, 'เมนบอร์ดใหญ่กว่าที่เคสรองรับ'));
        }
        if ($has('case') && $has('cooling')) {
            $h = BuildSpecs::num($s('cooling')['cooler_height_mm'] ?? null); $max = BuildSpecs::num($s('case')['max_cpu_cooler_height_mm'] ?? null);
            $out[] = $h === null || $max === null
                ? self::row('cooler_height', 'ความสูงชุดระบายความร้อนกับเคส', 'unknown', false, 'ไม่ทราบความสูงของชุดระบายความร้อนหรือที่เคสรองรับ')
                : ($h <= $max ? self::row('cooler_height', 'ความสูงชุดระบายความร้อนกับเคส', 'ok', false, sprintf('สูง %d มม. เคสรองรับ %d มม.', $h, $max))
                    : self::row('cooler_height', 'ความสูงชุดระบายความร้อนกับเคส', 'fail', false, sprintf('สูง %d มม. แต่เคสรองรับ %d มม.', $h, $max)));
        }
        if ($has('cooling') && $has('cpu')) {
            $cpuSocket = BuildSpecs::socket($p('cpu'), $s('cpu'));
            $list = BuildSpecs::get($p('cooling'), $s('cooling'), 'socket', 'socket');
            if ($cpuSocket === null || $list === null) {
                $out[] = self::row('cooler_socket', 'ชุดระบายความร้อนกับ socket CPU', 'unknown', false, 'ไม่ทราบ socket ที่ชุดระบายความร้อนรองรับ');
            } else {
                $sockets = array_map(static fn(string $x): string => strtoupper(str_replace(' ', '', $x)), preg_split('/[;,\/]+/', $list) ?: []);
                $out[] = in_array($cpuSocket, $sockets, true)
                    ? self::row('cooler_socket', 'ชุดระบายความร้อนกับ socket CPU', 'ok', false, "รองรับ $cpuSocket")
                    : self::row('cooler_socket', 'ชุดระบายความร้อนกับ socket CPU', 'fail', false, "ไม่ได้ระบุว่ารองรับ $cpuSocket");
            }
        }
        if ($has('case') && $has('gpu')) {
            $len = BuildSpecs::num($s('gpu')['length_mm'] ?? null); $max = BuildSpecs::num($s('case')['max_gpu_length_mm'] ?? null);
            $out[] = $len === null || $max === null
                ? self::row('gpu_length', 'ความยาวการ์ดจอกับเคส', 'unknown', false, 'ความยาวการ์ดจอขึ้นกับรุ่นของแต่ละแบรนด์ กรุณาเทียบกับที่เคสรองรับ' . ($max !== null ? sprintf(' (%d มม.)', $max) : ''))
                : ($len <= $max ? self::row('gpu_length', 'ความยาวการ์ดจอกับเคส', 'ok', false, sprintf('ยาว %d มม. เคสรองรับ %d มม.', $len, $max))
                    : self::row('gpu_length', 'ความยาวการ์ดจอกับเคส', 'fail', false, sprintf('ยาว %d มม. แต่เคสรองรับ %d มม.', $len, $max)));
        }
        return $out;
    }

    /** True when any check says the parts cannot work together. */
    public static function blocked(array $checks): bool
    {
        foreach ($checks as $c) { if ($c['status'] === 'fail' && $c['blocking']) { return true; } }
        return false;
    }

    /** Estimated PSU need in watts, rounded up to 50; null when a chosen CPU or GPU has no known power figure. */
    public static function psuNeed(array $parts): ?int
    {
        if (isset($parts['gpu'])) {
            $rec = BuildSpecs::num($parts['gpu']['specs']['system_psu_recommendation_watt'] ?? null) ?? BuildSpecs::num($parts['gpu']['product']['recommended_psu_watt'] ?? null);
            if ($rec !== null) { return (int)(ceil($rec / 50) * 50); }
        }
        $load = self::PSU_BASE_WATT;
        foreach (['cpu', 'gpu'] as $r) {
            if (!isset($parts[$r])) { continue; }
            $w = BuildSpecs::powerWatt($parts[$r]['product'], $parts[$r]['specs'] ?? []);
            if ($w === null) { return null; }
            $load += $w;
        }
        return (int)(ceil($load * self::PSU_MARGIN / 50) * 50);
    }

    private static function psu(array $parts): array
    {
        $have = BuildSpecs::psuWatt($parts['psu']['product'], $parts['psu']['specs'] ?? []);
        $need = self::psuNeed($parts);
        if ($have === null || $need === null) {
            return self::row('psu', 'กำลังไฟ PSU', 'unknown', false, 'ไม่ทราบกำลังไฟของ PSU หรือของ CPU/การ์ดจอ กรุณาตรวจเอง');
        }
        return $have >= $need
            ? self::row('psu', 'กำลังไฟ PSU', 'ok', false, sprintf('PSU %d W พอสำหรับที่ประเมินไว้ประมาณ %d W', $have, $need))
            : self::row('psu', 'กำลังไฟ PSU', 'fail', false, sprintf('PSU %d W ต่ำกว่าที่ประเมินไว้ประมาณ %d W', $have, $need));
    }

    /** roles each check involves: a candidate part for role R is judged only by the checks that list R */
    public const ROLES = [
        'socket' => ['cpu', 'motherboard'],
        'memory' => ['ram', 'motherboard'],
        'cpu_memory' => ['ram', 'cpu'],
        'form_factor' => ['case', 'motherboard'],
        'cooler_height' => ['case', 'cooling'],
        'cooler_socket' => ['cpu', 'cooling'],
        'gpu_length' => ['case', 'gpu'],
        'psu' => ['psu', 'cpu', 'gpu'],
    ];

    private static function row(string $key, string $label, string $status, bool $blocking, string $text): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'blocking' => $blocking, 'text' => $text, 'roles' => self::ROLES[$key] ?? []];
    }
}
