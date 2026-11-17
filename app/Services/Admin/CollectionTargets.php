<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Research\OwnerApprovalPlan;
use App\Services\Research\OwnerIntakePlanner;
use PDO;

/**
 * LS-8 O-1: which products the owner should collect listings for next, in Thai. Read-only. Rows are [status, need, have, name]: `need` is how many more
 * usable asking rows the product needs to reach OwnerIntakePlanner::MIN_ROWS; `bimodal` products already have enough rows but they form two price groups,
 * which is a review problem, so they are listed last with that instruction. Search words come only from the catalog name (leading brand words dropped).
 */
final class CollectionTargets
{
    private const BRAND_WORDS = ['nvidia', 'geforce', 'amd', 'intel', 'core', 'radeon'];

    public static function searchWords(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        while (count($parts) > 1 && in_array(mb_strtolower($parts[0], 'UTF-8'), self::BRAND_WORDS, true)) { array_shift($parts); }
        return implode(' ', $parts);
    }

    /** @return list<array{0:string,1:int,2:int,3:string}> */
    public static function gapRows(PDO $db): array
    {
        $plan = OwnerApprovalPlan::build($db);
        $approved = [];
        foreach ($db->query("SELECT p.full_name n, COUNT(*) c FROM price_observations o JOIN products p ON p.id=o.product_id WHERE o.verified_status='approved' AND o.price_type='asking' AND o.is_duplicate=0 AND o.is_defective=0 AND o.is_deposit=0 AND COALESCE(o.price_value,o.asking_price)>0 GROUP BY p.id") as $r) { $approved[$r['n']] = (int)$r['c']; }
        $rows = [];
        foreach ($plan['left_pending'] as [$name, $usable, $status]) {
            $have = (int)$usable + ($approved[$name] ?? 0);
            $rows[] = [$status === 'bimodal' ? 'bimodal' : 'too_few', max(0, OwnerIntakePlanner::MIN_ROWS - $have), $have, (string)$name];
        }
        return $rows;
    }

    /**
     * @param list<array{0:string,1:int,2:int,3:string}> $rows
     * @return array{targets:list<array{status:string,need:int,have:int,name:string,search:string}>,total_needed:int,one_short:int,bimodal:int}
     */
    public static function build(array $rows): array
    {
        $targets = [];
        foreach ($rows as [$status, $need, $have, $name]) {
            $targets[] = ['status' => $status, 'need' => (int)$need, 'have' => (int)$have, 'name' => (string)$name, 'search' => self::searchWords((string)$name)];
        }
        usort($targets, static fn(array $a, array $b): int => [$a['status'] === 'bimodal', $a['need'], $a['name']] <=> [$b['status'] === 'bimodal', $b['need'], $b['name']]);
        $collect = array_filter($targets, static fn(array $t): bool => $t['status'] !== 'bimodal');
        return [
            'targets' => $targets,
            'total_needed' => array_sum(array_column($collect, 'need')),
            'one_short' => count(array_filter($collect, static fn(array $t): bool => $t['need'] === 1)),
            'bimodal' => count($targets) - count($collect),
        ];
    }

    /** the 8 categories of a PC, in the order the Build page shows them */
    public const PC_CATEGORIES = ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooling'];
    private const CANDIDATES_PER_CATEGORY = 5;

    /**
     * LS-13 S-1: for each PC category, whether it has a priced part (the Build page's own rule). A category with none stays locked on /build, so it lists
     * up to five catalog parts to collect first, closest to a price first. $have maps a catalog full_name to the usable asking rows it already holds.
     * @param list<array<string,mixed>> $products rows of Product::search()
     * @param array<string,int> $have
     * @return list<array{category:string,label:string,total:int,priced:int,candidates:list<array{name:string,have:int,need:int,search:string}>}>
     */
    public static function categoryNeeds(array $products, array $have): array
    {
        $out = [];
        foreach (self::PC_CATEGORIES as $key) {
            $inCat = array_values(array_filter($products, static fn(array $p): bool => strtolower((string)$p['category_name']) === $key));
            $priced = count(array_filter($inCat, [\App\Services\SpecBuilderService::class, 'isPriced']));
            $cands = [];
            if ($priced === 0) {
                $rows = array_map(static fn(array $p): array => ['name' => (string)$p['full_name'], 'have' => (int)($have[(string)$p['full_name']] ?? 0)], $inCat);
                usort($rows, static fn(array $a, array $b): int => [-$a['have'], $a['name']] <=> [-$b['have'], $b['name']]);
                foreach (array_slice($rows, 0, self::CANDIDATES_PER_CATEGORY) as $r) {
                    $cands[] = $r + ['need' => max(0, OwnerIntakePlanner::MIN_ROWS - $r['have']), 'search' => self::searchWords($r['name'])];
                }
            }
            $out[] = ['category' => $key, 'label' => \App\Services\SpecRecommender::LABELS[$key], 'total' => count($inCat), 'priced' => $priced, 'candidates' => $cands];
        }
        return $out;
    }

    /** usable asking rows held per catalog full_name: approved rows plus the usable pending rows the gap list already counts */
    public static function haveByName(PDO $db): array
    {
        $have = [];
        foreach ($db->query("SELECT p.full_name n, COUNT(*) c FROM price_observations o JOIN products p ON p.id=o.product_id WHERE o.verified_status='approved' AND o.price_type='asking' AND o.is_duplicate=0 AND o.is_defective=0 AND o.is_deposit=0 AND COALESCE(o.price_value,o.asking_price)>0 GROUP BY p.id") as $r) { $have[(string)$r['n']] = (int)$r['c']; }
        foreach (self::gapRows($db) as [, , $h, $name]) { $have[(string)$name] = (int)$h; }
        return $have;
    }

    public static function renderCategoryNeeds(array $needs): string
    {
        $locked = array_values(array_filter($needs, static fn(array $n): bool => $n['priced'] === 0));
        $out = ['', 'ประกอบคอมครบชุดได้เมื่อทุกหมวดมีราคา (อย่างน้อย 1 รุ่นต่อหมวด)'];
        if (!$locked) { $out[] = 'ทุกหมวดมีราคาแล้ว ไม่มีหมวดที่ล็อกการประกอบ'; return implode("\n", $out) . "\n"; }
        $out[] = 'หมวดที่ยังไม่มีราคา: ' . count($locked) . ' จาก ' . count($needs);
        foreach ($locked as $n) {
            if ($n['total'] === 0) { $out[] = sprintf('- %s: ยังไม่มีรุ่นในแคตตาล็อก', $n['label']); continue; }
            $out[] = sprintf('- %s: มีราคาแล้ว 0 จาก %d รุ่น | เก็บรุ่นเหล่านี้ก่อน:', $n['label'], $n['total']);
            foreach ($n['candidates'] as $i => $c) {
                $out[] = $c['need'] === 0
                    ? sprintf('    %d) %s | ขาดอีก 0 แถว (มีแล้ว %d) | ไปตรวจในคิวตรวจ ไม่ต้องเก็บเพิ่ม', $i + 1, $c['name'], $c['have'])
                    : sprintf('    %d) %s | ขาดอีก %d แถว (มีแล้ว %d) | ค้นว่า: %s', $i + 1, $c['name'], $c['need'], $c['have'], $c['search']);
            }
        }
        return implode("\n", $out) . "\n";
    }
    public static function renderText(array $b): string
    {
        $out = ["รายการประกาศที่ควรเก็บต่อ (เรียงจากรุ่นที่ใกล้ได้ราคามากที่สุด)", "สินค้าที่ยังไม่มีราคา: " . count($b['targets']) . " รุ่น | ขาดอีกรวม " . $b['total_needed'] . " แถว | ขาดอีก 1 แถวเท่านั้น " . $b['one_short'] . " รุ่น", ''];
        $n = 0;
        foreach ($b['targets'] as $t) {
            $n++;
            if ($t['status'] === 'bimodal') {
                $out[] = sprintf('%d. %s | ตัวอย่างแยกเป็นสองกลุ่มราคา (มีแล้ว %d แถว) ตรวจประกาศเดิมก่อน ยังไม่ต้องเก็บเพิ่ม', $n, $t['name'], $t['have']);
            } else {
                $out[] = sprintf('%d. %s | ขาดอีก %d แถว (มีแล้ว %d) | ค้นว่า: %s', $n, $t['name'], $t['need'], $t['have'], $t['search']);
            }
        }
        return implode("\n", $out) . "\n";
    }

    public static function renderCsv(array $b): string
    {
        $h = fopen('php://temp', 'r+b');
        fputcsv($h, ['status', 'need', 'have', 'product', 'search_words'], ',', '"', '\\', "\n");
        foreach ($b['targets'] as $t) { fputcsv($h, [$t['status'], $t['need'], $t['have'], $t['name'], $t['search']], ',', '"', '\\', "\n"); }
        rewind($h);
        $csv = (string)stream_get_contents($h);
        fclose($h);
        return $csv;
    }
}
