<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/** LS-74: bulk load of storage products in the one storage format (see StorageFormat). One row = one drive: brand, series, media_type, interface_type, form_factor, capacity_gb. Rows go through ProductImport::apply. */
final class StorageImport
{
    public const COLUMNS = ['brand', 'series', 'media_type', 'interface_type', 'form_factor', 'capacity_gb', 'is_active'];

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBF" . implode(',', self::COLUMNS) . "\nSamsung,990 EVO Plus,SSD,NVMe,M.2 2280,1000,1\nGeneric,,SSD,SATA,2.5\",SATA,480,1\n";
    }

    public static function clean(array $row, array $lookups): array
    {
        $v = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $brand = $v('brand'); $brandId = $lookups['brands'][mb_strtolower($brand)] ?? null;
        if ($brandId === null) { return ['data' => null, 'error' => 'unknown brand "' . $brand . '" (add the brand first, or use Generic)']; }
        $catId = $lookups['categories']['storage'] ?? null;
        if ($catId === null) { return ['data' => null, 'error' => 'the storage category does not exist']; }
        if ($v('capacity_gb') !== '' && !ctype_digit($v('capacity_gb'))) { return ['data' => null, 'error' => 'capacity_gb must be a whole number of GB (1 TB = 1000)']; }
        $ffRaw = str_replace(['“', '”', '″'], '"', $v('form_factor'));
        $ff = null; foreach (StorageFormat::FORM_FACTORS as $x) { if (strcasecmp($x, $ffRaw) === 0 || strcasecmp(str_replace('.', '', $x), str_replace('.', '', $ffRaw)) === 0) { $ff = $x; } }
        $f = ['interface_type' => strcasecmp($v('interface_type'), 'nvme') === 0 ? 'NVMe' : (strcasecmp($v('interface_type'), 'sata') === 0 ? 'SATA' : $v('interface_type')), 'media_type' => strtoupper($v('media_type')), 'form_factor' => $ff ?? $ffRaw, 'capacity_gb' => $v('capacity_gb') === '' ? null : (int) $v('capacity_gb')];
        $err = StorageFormat::check($f);
        if ($err !== null) { return ['data' => null, 'error' => $err]; }
        $series = $v('series');
        if (strtolower($brand) !== 'generic' && $series === '') { return ['data' => null, 'error' => 'series is required for a branded drive']; }
        if (mb_strlen($series) > 100) { return ['data' => null, 'error' => 'series is too long']; }
        $s = strtolower($brand) === 'generic' ? '' : $series; $active = strtolower($v('is_active'));
        return ['data' => ['category_id' => $catId, 'brand_id' => $brandId, 'model_name' => $s === '' ? StorageFormat::name('Generic', '', $f) : trim($s . ' ' . StorageFormat::capacityText($f['capacity_gb'])), 'full_name' => StorageFormat::name($brand, $s, $f), 'slug' => StorageFormat::slug($brand, $s, $f),
            'spec_summary' => StorageFormat::summary($f), 'interface_type' => $f['interface_type'], 'media_type' => $f['media_type'], 'form_factor' => $f['form_factor'], 'capacity_gb' => $f['capacity_gb'], 'is_active' => in_array($active, ['0', 'no', 'false'], true) ? 0 : 1], 'error' => null];
    }

    public static function plan(PDO $db, array $rows): array
    {
        $lk = ProductImport::lookups($db); $seen = []; $out = [];
        foreach ($rows as $i => $row) {
            $c = self::clean($row, $lk); $line = $i + 2;
            if ($c['data'] === null) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => '', 'name' => trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['series'] ?? '')), 'error' => $c['error'], 'data' => null]; continue; }
            $slug = $c['data']['slug'];
            if (isset($seen[$slug])) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => 'the same drive is repeated in this file (line ' . $seen[$slug] . ')', 'data' => null]; continue; }
            $seen[$slug] = $line;
            $out[] = ['line' => $line, 'action' => isset($lk['existing'][mb_strtolower($slug)]) ? 'update' : 'create', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => null, 'data' => $c['data']];
        }
        return $out;
    }

    public static function apply(PDO $db, array $plan): array { return ProductImport::apply($db, $plan); }
}
