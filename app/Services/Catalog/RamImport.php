<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/**
 * LS-71: bulk load of RAM products in the one RAM format (see RamFormat). One row = one RAM product: brand, series, memory_type, form_factor,
 * memory_speed_mhz, capacity_gb, kit_modules. The name, model name, slug and summary are built from those facts, so two files with the same facts always meet the same row.
 * The brand must already exist (use "Generic" for a spec class priced by spec). Rows go through ProductImport::apply, so a repeat updates and never duplicates.
 */
final class RamImport
{
    public const COLUMNS = ['brand', 'series', 'memory_type', 'form_factor', 'memory_speed_mhz', 'capacity_gb', 'kit_modules', 'is_active'];

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBF" . implode(',', self::COLUMNS) . "\nKingston,FURY Beast,DDR5,DIMM,6000,32,2,1\nGeneric,,DDR4,DIMM,3200,16,,1\nCorsair,Vengeance SODIMM,DDR4,SO-DIMM,3200,16,2,1\n";
    }

    /** the RAM columns exist (the RAM migration was applied) */
    public static function ready(PDO $db): bool
    {
        try { $db->query('SELECT memory_speed_mhz, kit_modules FROM products LIMIT 1'); return true; } catch (\PDOException) { return false; }
    }

    /** @return array{data: ?array, error: ?string} */
    public static function clean(array $row, array $lookups): array
    {
        $v = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $brand = $v('brand'); $brandId = $lookups['brands'][mb_strtolower($brand)] ?? null;
        if ($brandId === null) { return ['data' => null, 'error' => 'unknown brand "' . $brand . '" (add the brand first, or use Generic)']; }
        $catId = $lookups['categories']['ram'] ?? null;
        if ($catId === null) { return ['data' => null, 'error' => 'the RAM category does not exist']; }
        foreach (['memory_speed_mhz', 'capacity_gb', 'kit_modules'] as $k) { if ($v($k) !== '' && !ctype_digit($v($k))) { return ['data' => null, 'error' => "$k must be a whole number"]; } }
        $f = ['memory_type' => strtoupper($v('memory_type')), 'form_factor' => strtoupper($v('form_factor')) === 'SODIMM' ? 'SO-DIMM' : strtoupper($v('form_factor')),
            'memory_speed_mhz' => $v('memory_speed_mhz') === '' ? null : (int) $v('memory_speed_mhz'), 'capacity_gb' => $v('capacity_gb') === '' ? null : (int) $v('capacity_gb'), 'kit_modules' => $v('kit_modules') === '' ? null : (int) $v('kit_modules')];
        if ($f['form_factor'] === '') { return ['data' => null, 'error' => 'form_factor is required (DIMM for desktop, SO-DIMM for laptop)']; }
        $err = RamFormat::check($f);
        if ($err !== null) { return ['data' => null, 'error' => $err]; }
        $series = $v('series');
        if (strtolower($brand) !== 'generic' && $series === '') { return ['data' => null, 'error' => 'series is required for a branded product (for example FURY Beast)']; }
        if (mb_strlen($series) > 80) { return ['data' => null, 'error' => 'series is too long']; }
        $full = RamFormat::name($brand, strtolower($brand) === 'generic' ? '' : $series, $f);
        $active = strtolower($v('is_active'));
        $data = ['category_id' => $catId, 'brand_id' => $brandId, 'model_name' => RamFormat::modelName(strtolower($brand) === 'generic' ? '' : $series, $f), 'full_name' => $full, 'slug' => ProductImport::slug($full),
            'generation' => $f['memory_type'], 'spec_summary' => RamFormat::summary($f), 'memory_type' => $f['memory_type'], 'form_factor' => $f['form_factor'], 'capacity_gb' => $f['capacity_gb'], 'memory_speed_mhz' => $f['memory_speed_mhz'],
            'is_active' => in_array($active, ['0', 'no', 'false'], true) ? 0 : 1];
        if ($f['kit_modules'] !== null) { $data['kit_modules'] = $f['kit_modules']; }
        return ['data' => $data, 'error' => null];
    }

    /** @return list<array{line:int,action:string,slug:string,name:string,error:?string,data:?array}> create | update | refused */
    public static function plan(PDO $db, array $rows): array
    {
        $lk = ProductImport::lookups($db); $seen = []; $out = [];
        foreach ($rows as $i => $row) {
            $c = self::clean($row, $lk); $line = $i + 2;
            if ($c['data'] === null) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => '', 'name' => trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['series'] ?? '')), 'error' => $c['error'], 'data' => null]; continue; }
            $slug = $c['data']['slug'];
            if (isset($seen[$slug])) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => 'the same RAM is repeated in this file (line ' . $seen[$slug] . ')', 'data' => null]; continue; }
            $seen[$slug] = $line;
            $out[] = ['line' => $line, 'action' => isset($lk['existing'][mb_strtolower($slug)]) ? 'update' : 'create', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => null, 'data' => $c['data']];
        }
        return $out;
    }

    public static function apply(PDO $db, array $plan): array { return ProductImport::apply($db, $plan); }
}
