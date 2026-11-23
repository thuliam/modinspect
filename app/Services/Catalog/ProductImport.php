<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/**
 * LS-67: bulk load of Product Master rows from a CSV/JSON file (admin import centre). Strict, never guesses:
 * category and brand must already exist, a slug that exists is updated (only the columns the row fills in), a new slug is created,
 * a repeated slug inside one file is refused. image_path must be a local path under assets/ (no URL is ever fetched).
 * plan() reads only; apply() writes in one transaction.
 */
final class ProductImport
{
    public const COLUMNS = ['category', 'brand', 'model_name', 'full_name', 'slug', 'generation', 'release_year', 'spec_summary', 'tdp_watt', 'recommended_psu_watt', 'socket', 'chipset', 'memory_type', 'form_factor', 'capacity_gb', 'memory_speed_mhz', 'kit_modules', 'interface_type', 'media_type', 'power_watt', 'efficiency_rating', 'modular_type', 'image_path', 'is_active'];
    private const TEXT = ['generation' => 100, 'spec_summary' => 2000, 'socket' => 40, 'chipset' => 40, 'memory_type' => 20, 'form_factor' => 30, 'interface_type' => 20, 'media_type' => 10, 'efficiency_rating' => 20, 'modular_type' => 20];
    private const INTS = ['release_year' => [1990, 2100], 'tdp_watt' => [1, 2000], 'recommended_psu_watt' => [1, 3000], 'capacity_gb' => [1, 1000000], 'memory_speed_mhz' => [100, 20000], 'kit_modules' => [1, 8], 'power_watt' => [100, 3000]];

    public static function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9ก-๙]+/iu', '-', mb_strtolower(trim($value), 'UTF-8')) ?? '';
        return trim($slug, '-');
    }

    /** true when every named column exists on products (the matching migration was applied) */
    public static function hasColumns(PDO $db, array $cols): bool
    {
        try { $db->query('SELECT ' . implode(', ', array_map(static fn ($c) => preg_replace('/[^a-z_]/', '', $c), $cols)) . ' FROM products LIMIT 1'); return true; } catch (\PDOException) { return false; }
    }

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBF" . implode(',', self::COLUMNS) . "\ncpu,Intel,Core i5-13400F,Intel Core i5-13400F,,Raptor Lake,2023,\"10 cores / 16 threads\",65,550,LGA1700,,,,,assets/products/intel-core-i5-13400f.jpg,1\n";
    }

    /** @return array{categories: array<string,int>, brands: array<string,int>, existing: array<string,int>} lookups keyed in lower case */
    public static function lookups(PDO $db): array
    {
        $cat = []; foreach ($db->query('SELECT id, slug, name FROM product_categories')->fetchAll() as $r) { $cat[mb_strtolower((string) $r['slug'])] = (int) $r['id']; $cat[mb_strtolower((string) $r['name'])] = (int) $r['id']; }
        $br = []; foreach ($db->query('SELECT id, name FROM brands')->fetchAll() as $r) { $br[mb_strtolower((string) $r['name'])] = (int) $r['id']; }
        $ex = []; foreach ($db->query('SELECT id, slug FROM products')->fetchAll() as $r) { $ex[mb_strtolower((string) $r['slug'])] = (int) $r['id']; }
        return ['categories' => $cat, 'brands' => $br, 'existing' => $ex];
    }

    /** @return array{data: ?array, error: ?string} */
    public static function clean(array $row, array $lookups): array
    {
        $v = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $cat = $lookups['categories'][mb_strtolower($v('category'))] ?? null;
        if ($cat === null) { return ['data' => null, 'error' => 'unknown category "' . $v('category') . '"']; }
        $brand = $lookups['brands'][mb_strtolower($v('brand'))] ?? null;
        if ($brand === null) { return ['data' => null, 'error' => 'unknown brand "' . $v('brand') . '" (add the brand first)']; }
        $model = $v('model_name'); $full = $v('full_name');
        if ($model === '' || $full === '') { return ['data' => null, 'error' => 'model_name and full_name are required']; }
        if (mb_strlen($model) > 160 || mb_strlen($full) > 255) { return ['data' => null, 'error' => 'model_name or full_name is too long']; }
        $slug = self::slug($v('slug') !== '' ? $v('slug') : $full);
        if ($slug === '' || mb_strlen($slug) > 190) { return ['data' => null, 'error' => 'slug is empty or too long']; }
        $data = ['category_id' => $cat, 'brand_id' => $brand, 'model_name' => $model, 'full_name' => $full, 'slug' => $slug];
        foreach (self::TEXT as $k => $max) {
            if ($v($k) === '') { continue; }
            if (mb_strlen($v($k)) > $max) { return ['data' => null, 'error' => "$k is longer than $max characters"]; }
            $data[$k] = $v($k);
        }
        foreach (self::INTS as $k => [$lo, $hi]) {
            if ($v($k) === '') { continue; }
            if (!ctype_digit($v($k)) || (int) $v($k) < $lo || (int) $v($k) > $hi) { return ['data' => null, 'error' => "$k must be a whole number from $lo to $hi"]; }
            $data[$k] = (int) $v($k);
        }
        if ($v('image_path') !== '') {
            $p = str_replace('\\', '/', $v('image_path'));
            if (!str_starts_with($p, 'assets/') || str_contains($p, '..') || preg_match('~[\x00-\x1f"<>]~', $p) || strlen($p) > 255) { return ['data' => null, 'error' => 'image_path must be a local path under assets/']; }
            $data['image_path'] = $p;
        }
        if ($v('is_active') !== '') {
            $a = strtolower($v('is_active'));
            if (!in_array($a, ['1', '0', 'yes', 'no', 'true', 'false'], true)) { return ['data' => null, 'error' => 'is_active must be 1 or 0']; }
            $data['is_active'] = in_array($a, ['1', 'yes', 'true'], true) ? 1 : 0;
        }
        return ['data' => $data, 'error' => null];
    }

    /**
     * Classify every row without writing.
     * @return list<array{line: int, action: string, slug: string, name: string, error: ?string, data: ?array}> action = create | update | refused
     */
    public static function plan(PDO $db, array $rows): array
    {
        $lk = self::lookups($db); $seen = []; $out = [];
        foreach ($rows as $i => $row) {
            $c = self::clean($row, $lk); $line = $i + 2;
            if ($c['data'] === null) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => '', 'name' => trim((string) ($row['full_name'] ?? '')), 'error' => $c['error'], 'data' => null]; continue; }
            $slug = $c['data']['slug'];
            if (isset($seen[$slug])) { $out[] = ['line' => $line, 'action' => 'refused', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => 'slug repeated in this file (line ' . $seen[$slug] . ')', 'data' => null]; continue; }
            $seen[$slug] = $line;
            $out[] = ['line' => $line, 'action' => isset($lk['existing'][mb_strtolower($slug)]) ? 'update' : 'create', 'slug' => $slug, 'name' => $c['data']['full_name'], 'error' => null, 'data' => $c['data']];
        }
        return $out;
    }

    /** @return array{created: int, updated: int} one transaction; every planned row passed clean() */
    public static function apply(PDO $db, array $plan): array
    {
        $created = 0; $updated = 0; $own = !$db->inTransaction(); // a caller that already holds a transaction (the tests) keeps control of it
        if ($own) { $db->beginTransaction(); }
        try {
            foreach ($plan as $p) {
                if ($p['data'] === null) { continue; }
                $d = $p['data'];
                if ($p['action'] === 'update') {
                    $set = []; foreach (array_keys($d) as $k) { if ($k !== 'slug') { $set[] = "$k = :$k"; } }
                    $params = $d; unset($params['slug']); $params['slug_key'] = $d['slug'];
                    $db->prepare('UPDATE products SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE slug = :slug_key')->execute($params);
                    $updated++;
                } else {
                    $d += ['is_active' => 1];
                    $cols = array_keys($d);
                    $db->prepare('INSERT INTO products (' . implode(', ', $cols) . ', created_at, updated_at) VALUES (:' . implode(', :', $cols) . ', NOW(), NOW())')->execute($d);
                    $created++;
                }
            }
            if ($own) { $db->commit(); }
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
        return ['created' => $created, 'updated' => $updated];
    }
}
