<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/**
 * LS-73: bulk load of motherboards in the one motherboard format (see MotherboardFormat). One row = one board: brand, model_name, socket, chipset, memory_type,
 * form_factor. The full name is "brand + model" (the brand is not repeated when the model already starts with it) and the slug comes from it, so a repeat
 * updates the same product. The brand must already exist. Rows go through ProductImport::apply.
 */
final class MotherboardImport
{
    public const COLUMNS = ['brand', 'model_name', 'socket', 'chipset', 'memory_type', 'form_factor', 'is_active'];

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBF" . implode(',', self::COLUMNS) . "\nASUS,TUF Gaming B650-PLUS WIFI,AM5,B650,DDR5,ATX,1\nMSI,PRO B760M-A WIFI D4,LGA1700,B760,DDR4,mATX,1\n";
    }

    /** @return array{data: ?array, error: ?string} */
    public static function clean(array $row, array $lookups): array
    {
        $v = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $brand = $v('brand'); $brandId = $lookups['brands'][mb_strtolower($brand)] ?? null;
        if ($brandId === null) { return ['data' => null, 'error' => 'unknown brand "' . $brand . '" (add the brand first)']; }
        $catId = $lookups['categories']['motherboard'] ?? null;
        if ($catId === null) { return ['data' => null, 'error' => 'the motherboard category does not exist']; }
        $model = $v('model_name');
        if ($model === '' || mb_strlen($model) > 160) { return ['data' => null, 'error' => 'model_name is required (up to 160 characters)']; }
        $ff = MotherboardFormat::formFactor($v('form_factor'));
        $socketRaw = $v('socket'); $socket = '';
        foreach (MotherboardFormat::SOCKETS as $s) { if (strcasecmp($s, $socketRaw) === 0) { $socket = $s; } }
        $f = ['socket' => $socket, 'chipset' => strtoupper($v('chipset')), 'memory_type' => strtoupper($v('memory_type')), 'form_factor' => $ff ?? ''];
        $err = MotherboardFormat::check($f);
        if ($err !== null) { return ['data' => null, 'error' => $err]; }
        $full = stripos($model, $brand . ' ') === 0 ? $model : $brand . ' ' . $model;
        if (mb_strlen($full) > 255) { return ['data' => null, 'error' => 'the name is too long']; }
        $active = strtolower($v('is_active'));
        return ['data' => ['category_id' => $catId, 'brand_id' => $brandId, 'model_name' => $model, 'full_name' => $full, 'slug' => ProductImport::slug($full), 'generation' => $f['chipset'], 'spec_summary' => MotherboardFormat::summary($f),
            'socket' => $f['socket'], 'chipset' => $f['chipset'], 'memory_type' => $f['memory_type'], 'form_factor' => $f['form_factor'], 'is_active' => in_array($active, ['0', 'no', 'false'], true) ? 0 : 1], 'error' => null];
    }

    /** @return list<array{line:int,action:string,slug:string,name:string,error:?string,data:?array}> create | update | refused */
    public static function plan(PDO $db, array $rows): array
    {
        $lk = ProductImport::lookups($db); $seen = []; $out = [];
        foreach ($rows as $i => $row) {
            $c = self::clean($row, $lk); $line = $i + 2;
            if ($c['data'] === null) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => '', 'name' => trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model_name'] ?? '')), 'error' => $c['error'], 'data' => null]; continue; }
            $slug = $c['data']['slug'];
            if (isset($seen[$slug])) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => 'the same board is repeated in this file (line ' . $seen[$slug] . ')', 'data' => null]; continue; }
            $seen[$slug] = $line;
            $out[] = ['line' => $line, 'action' => isset($lk['existing'][mb_strtolower($slug)]) ? 'update' : 'create', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => null, 'data' => $c['data']];
        }
        return $out;
    }

    public static function apply(PDO $db, array $plan): array { return ProductImport::apply($db, $plan); }
}
