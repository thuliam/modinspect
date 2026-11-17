<?php
declare(strict_types=1);
namespace App\Services;

/**
 * LS-11 A: picks parts for the Build page from the priced catalog. Deterministic: no AI, no randomness, same input gives the same plan.
 *
 * 1. The budget is split by use (and a little by resolution) into a share per category.
 * 2. A preset says how much of each share to spend: saving 70%, balanced 90%, full 100% or more.
 * 3. Each category takes the priced part whose median is closest to its share without going over (the cheapest priced part when none fits).
 * 4. The categories with no priced part keep their share as a reserve. The priced parts may only use the rest, so the whole plan is scaled down until it fits.
 *    Full climbs the other way, in 5% steps up to 150%, while the plan still fits.
 * The choice follows price, not measured speed: this project holds no performance data yet.
 */
final class SpecRecommender
{
    /** the order matters: the motherboard is chosen after the cpu and the RAM after the motherboard, so compatibility is built in */
    private const ORDER = ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooling'];
    private const PRESETS = ['saving' => 70, 'balanced' => 90, 'full' => 100];
    private const SHARES = [
        'gaming'    => ['cpu' => .20, 'motherboard' => .10, 'gpu' => .40, 'ram' => .07, 'storage' => .08, 'psu' => .07, 'case' => .05, 'cooling' => .03],
        'streaming' => ['cpu' => .28, 'motherboard' => .11, 'gpu' => .30, 'ram' => .08, 'storage' => .08, 'psu' => .08, 'case' => .04, 'cooling' => .03],
        'ai'        => ['cpu' => .18, 'motherboard' => .09, 'gpu' => .45, 'ram' => .10, 'storage' => .08, 'psu' => .06, 'case' => .02, 'cooling' => .02],
        'work'      => ['cpu' => .30, 'motherboard' => .13, 'gpu' => .10, 'ram' => .13, 'storage' => .12, 'psu' => .09, 'case' => .08, 'cooling' => .05],
        'office'    => ['cpu' => .34, 'motherboard' => .16, 'gpu' => .00, 'ram' => .14, 'storage' => .14, 'psu' => .10, 'case' => .08, 'cooling' => .04],
    ];
    public const LABELS = ['cpu' => 'CPU', 'motherboard' => 'เมนบอร์ด', 'gpu' => 'การ์ดจอ', 'ram' => 'RAM', 'storage' => 'ที่เก็บข้อมูล', 'psu' => 'พาวเวอร์ซัพพลาย', 'case' => 'เคส', 'cooling' => 'ชุดระบายความร้อน'];

    /** @return array{selected: array<string,int>, expected: float, reserve: float, notes: string} */
    public function recommend(array $products, float $budget, string $use, string $resolution, string $preset): array
    {
        if (!isset(self::PRESETS[$preset])) { throw new \InvalidArgumentException("Unknown preset: $preset"); }
        $shares = self::SHARES[$use] ?? self::SHARES['gaming'];
        if ($shares['gpu'] > 0) {
            $shift = ['4k' => .08, '1080p' => -.05][$resolution] ?? 0.0;
            $shares['gpu'] += $shift; $shares['cpu'] -= $shift;
        }
        $byRole = [];
        foreach ($products as $p) {
            if (!SpecBuilderService::isPriced($p)) { continue; }
            $byRole[strtolower((string)$p['category_name'])][] = $p;
        }
        foreach ($byRole as &$list) {
            usort($list, static fn(array $a, array $b): int => [(float)$b['median'], (float)$b['confidence_score'], (int)$a['id']] <=> [(float)$a['median'], (float)$a['confidence_score'], (int)$b['id']]);
        }
        unset($list);
        $reserveShare = 0.0; $any = false;
        foreach (self::ORDER as $role) {
            if ($shares[$role] <= 0) { continue; }
            if (empty($byRole[$role])) { $reserveShare += $shares[$role]; } else { $any = true; }
        }
        if (!$any) { return ['selected' => [], 'expected' => 0.0, 'reserve' => $budget * $reserveShare, 'notes' => 'ไม่มีข้อมูลราคาที่เหมาะสมเพียงพอ']; }

        $spendable = $budget * (1 - $reserveShare);
        $step = self::PRESETS[$preset];   // percent, whole numbers so the steps never drift
        $sel = $this->pick($byRole, $shares, $budget, $step / 100);
        if ($this->total($sel) > $spendable) {
            while ($step > 20 && $this->total($sel) > $spendable) { $step -= 5; $sel = $this->pick($byRole, $shares, $budget, $step / 100); }
        } elseif ($preset === 'full') {
            for ($k = $step + 5; $k <= 150; $k += 5) {
                $try = $this->pick($byRole, $shares, $budget, $k / 100);
                if ($this->total($try) > $spendable) { break; }
                $sel = $try;
            }
        }
        $expected = $this->total($sel);
        $selected = []; foreach ($sel as $role => $p) { $selected[$role] = (int)$p['id']; }
        return ['selected' => $selected, 'expected' => $expected, 'reserve' => $budget * $reserveShare, 'notes' => $this->notes($sel, $selected, $shares, $byRole, $budget, $expected, $spendable, $reserveShare, $preset)];
    }

    private function pick(array $byRole, array $shares, float $budget, float $factor): array
    {
        $sel = [];
        foreach (self::ORDER as $role) {
            if ($shares[$role] <= 0 || empty($byRole[$role])) { continue; }
            $cands = $byRole[$role];
            if ($role === 'motherboard' && isset($sel['cpu'])) { $cands = self::compatible($cands, 'socket', (string)($sel['cpu']['socket'] ?? '')); }
            if ($role === 'ram' && isset($sel['motherboard'])) { $cands = self::compatible($cands, 'memory_type', (string)($sel['motherboard']['memory_type'] ?? '')); }
            if (!$cands) { continue; }   // nothing priced fits the parts already chosen: leave the category out rather than break compatibility
            $cap = $shares[$role] * $budget * $factor;
            $best = $cands[count($cands) - 1];   // sorted dearest first, so the last is the cheapest
            foreach ($cands as $c) { if ((float)$c['median'] <= $cap) { $best = $c; break; } }
            $sel[$role] = $best;
        }
        return $sel;
    }

    /** an EMPTY value means "unknown", never "different": only two known, different values rule a part out (the rule SpecBuilderService uses) */
    private static function compatible(array $cands, string $key, string $want): array
    {
        if (trim($want) === '') { return $cands; }
        return array_values(array_filter($cands, static fn(array $c): bool => trim((string)($c[$key] ?? '')) === '' || (string)$c[$key] === $want));
    }

    private function total(array $sel): float
    {
        return array_sum(array_map(static fn(array $p): float => (float)$p['median'], $sel));
    }

    private function notes(array $sel, array $selected, array $shares, array $byRole, float $budget, float $expected, float $spendable, float $reserveShare, string $preset): string
    {
        $names = ['saving' => 'ประหยัด', 'balanced' => 'สมดุล', 'full' => 'ใช้งบเต็มที่'];
        $n = 'แนะนำแบบ' . $names[$preset] . ' เลือกตามราคาและส่วนแบ่งงบของแต่ละหมวด ไม่ได้วัดความแรง';
        $notes = [];
        $unpriced = [];
        foreach (self::ORDER as $role) {
            if ($shares[$role] > 0 && empty($byRole[$role])) { $unpriced[] = self::LABELS[$role]; }
        }
        $skipped = [];
        foreach (self::ORDER as $role) {
            if ($shares[$role] > 0 && !empty($byRole[$role]) && !isset($selected[$role])) { $skipped[] = self::LABELS[$role]; }
        }
        if ($unpriced) { $notes[] = 'หมวดที่ยังไม่มีข้อมูลราคา ได้แก่ ' . implode(', ', $unpriced) . ' กันงบไว้ ฿' . number_format($budget * $reserveShare); }
        if ($skipped) { $notes[] = 'ไม่มีรุ่นที่ราคาตรวจแล้วและเข้ากับชิ้นส่วนที่เลือก: ' . implode(', ', $skipped); }
        if ($expected > $spendable) { $notes[] = 'งบไม่พอสำหรับหมวดที่ยังไม่มีข้อมูลราคา'; }
        if ($expected > $budget) { $notes[] = 'เกินงบประมาณ ฿' . number_format($expected - $budget); }
        $conf = $sel ? array_sum(array_map(static fn(array $p): float => (float)$p['confidence_score'], $sel)) / count($sel) : 0.0;
        if ($conf < 60) { $notes[] = 'ความมั่นใจของข้อมูลราคายังต่ำ (เฉลี่ย ' . number_format($conf) . '%)'; }
        return $n . ($notes ? '; ' . implode('; ', $notes) : '');
    }
}
