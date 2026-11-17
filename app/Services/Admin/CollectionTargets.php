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
