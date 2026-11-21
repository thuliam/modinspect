<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-28 A1: read-only audit of the alias table. It reports aliases that are likely to send a visitor to the wrong product, so a person can fix them; it never changes anything.
 * Findings: too_short (fewer than 3 characters once normalized), equals_other_name (the alias is the same text as ANOTHER product's name), digits_only (a bare number, which
 * several products can share, e.g. "3070" for a base card and its Ti), shared_prefix (the alias is the start of another product's alias, so a search for it is ambiguous).
 */
final class AliasAudit
{
    public const KINDS = ['too_short', 'equals_other_name', 'digits_only', 'shared_prefix'];

    /**
     * @param list<array{id:int,product_id:int,alias_text:string}> $aliases
     * @param array<int,string> $productNames product id => full name
     * @return array{findings:list<array{kind:string,alias_id:int,alias:string,product_id:int,other_product_id:?int}>,counts:array<string,int>,aliases:int}
     */
    public static function audit(array $aliases, array $productNames): array
    {
        $nameNorm = [];
        foreach ($productNames as $pid => $name) { $n = AliasImport::normalize($name); if ($n !== '') { $nameNorm[$n][] = (int)$pid; } }
        $norms = [];
        foreach ($aliases as $a) { $norms[$a['id']] = AliasImport::normalize($a['alias_text']); }
        $findings = []; $counts = array_fill_keys(self::KINDS, 0);
        $add = static function (string $kind, array $a, ?int $other) use (&$findings, &$counts): void {
            $findings[] = ['kind' => $kind, 'alias_id' => (int)$a['id'], 'alias' => (string)$a['alias_text'], 'product_id' => (int)$a['product_id'], 'other_product_id' => $other];
            $counts[$kind]++;
        };
        foreach ($aliases as $a) {
            $n = $norms[$a['id']];
            if ($n === '') { continue; }
            if (mb_strlen($n) < 3) { $add('too_short', $a, null); }
            if (isset($nameNorm[$n])) {
                foreach ($nameNorm[$n] as $pid) { if ($pid !== (int)$a['product_id']) { $add('equals_other_name', $a, $pid); break; } }
            }
            if (ctype_digit($n) && mb_strlen($n) >= 3) { $add('digits_only', $a, null); }
            foreach ($aliases as $b) {
                if ($b['product_id'] === $a['product_id']) { continue; }
                $m = $norms[$b['id']];
                if ($m !== '' && $m !== $n && mb_strlen($n) >= 4 && str_starts_with($m, $n)) { $add('shared_prefix', $a, (int)$b['product_id']); break; }
            }
        }
        return ['findings' => $findings, 'counts' => $counts, 'aliases' => count($aliases)];
    }
}
