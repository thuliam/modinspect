<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/** LS-74: bulk load of PSUs in the one PSU format (see PsuFormat). One row = one unit: brand, series, power_watt, efficiency_rating, modular_type, form_factor. Rows go through ProductImport::apply. */
final class PsuImport
{
    public const COLUMNS = ['brand', 'series', 'power_watt', 'efficiency_rating', 'modular_type', 'form_factor', 'is_active'];

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBF" . implode(',', self::COLUMNS) . "\nCorsair,RM750x,750,80+ Gold,Fully modular,ATX,1\nGeneric,,650,80+ Bronze,Non-modular,ATX,1\n";
    }

    public static function clean(array $row, array $lookups): array
    {
        $v = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $brand = $v('brand'); $brandId = $lookups['brands'][mb_strtolower($brand)] ?? null;
        if ($brandId === null) { return ['data' => null, 'error' => 'unknown brand "' . $brand . '" (add the brand first, or use Generic)']; }
        $catId = $lookups['categories']['psu'] ?? null;
        if ($catId === null) { return ['data' => null, 'error' => 'the PSU category does not exist']; }
        if ($v('power_watt') !== '' && !ctype_digit($v('power_watt'))) { return ['data' => null, 'error' => 'power_watt must be a whole number']; }
        $f = ['power_watt' => $v('power_watt') === '' ? null : (int) $v('power_watt'), 'efficiency_rating' => PsuFormat::efficiency($v('efficiency_rating')) ?? $v('efficiency_rating'), 'modular_type' => PsuFormat::modular($v('modular_type')) ?? $v('modular_type'), 'form_factor' => PsuFormat::formFactor($v('form_factor')) ?? $v('form_factor')];
        $err = PsuFormat::check($f);
        if ($err !== null) { return ['data' => null, 'error' => $err]; }
        $series = $v('series');
        if (strtolower($brand) !== 'generic' && $series === '') { return ['data' => null, 'error' => 'series is required for a branded unit']; }
        if (mb_strlen($series) > 100) { return ['data' => null, 'error' => 'series is too long']; }
        $s = strtolower($brand) === 'generic' ? '' : $series; $active = strtolower($v('is_active'));
        $full = PsuFormat::name($brand, $s, $f);
        return ['data' => ['category_id' => $catId, 'brand_id' => $brandId, 'model_name' => $s === '' ? $full : trim($s), 'full_name' => $full, 'slug' => PsuFormat::slug($brand, $s, $f), 'spec_summary' => PsuFormat::summary($f),
            'power_watt' => $f['power_watt'], 'efficiency_rating' => $f['efficiency_rating'], 'modular_type' => $f['modular_type'], 'form_factor' => $f['form_factor'], 'is_active' => in_array($active, ['0', 'no', 'false'], true) ? 0 : 1], 'error' => null];
    }

    public static function plan(PDO $db, array $rows): array
    {
        $lk = ProductImport::lookups($db); $seen = []; $out = [];
        foreach ($rows as $i => $row) {
            $c = self::clean($row, $lk); $line = $i + 2;
            if ($c['data'] === null) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => '', 'name' => trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['series'] ?? '')), 'error' => $c['error'], 'data' => null]; continue; }
            $slug = $c['data']['slug'];
            if (isset($seen[$slug])) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => 'the same unit is repeated in this file (line ' . $seen[$slug] . ')', 'data' => null]; continue; }
            $seen[$slug] = $line;
            $out[] = ['line' => $line, 'action' => isset($lk['existing'][mb_strtolower($slug)]) ? 'update' : 'create', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => null, 'data' => $c['data']];
        }
        return $out;
    }

    public static function apply(PDO $db, array $plan): array { return ProductImport::apply($db, $plan); }
}
