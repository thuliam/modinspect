<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/**
 * LS-68: bulk load of reference specs (product_reference_specs) from a long-format CSV/JSON, one row per spec.
 * Strict: the product must match exactly one model (slug or full name), every spec carries its source URL and check date,
 * a repeated (product, key) in one file is refused, and a spec that already exists is updated, never duplicated.
 */
final class SpecImport
{
    public const STATUSES = ['official_checked', 'needs_variant_review'];

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBFproduct,spec_key,spec_value,unit,source_url,date_checked,review_status,review_note\nintel-core-i5-13400f,cores,10,,https://www.intel.com/content/www/us/en/products/sku/230580/,2026-11-20,official_checked,\nintel-core-i5-13400f,threads,16,,https://www.intel.com/content/www/us/en/products/sku/230580/,2026-11-20,official_checked,\n";
    }

    /** @return list<array{id:int|string,slug:string,full_name:string}> */
    public static function products(PDO $db): array
    {
        return $db->query('SELECT id, slug, full_name FROM products WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /** @return array{data: ?array, error: ?string} */
    public static function clean(array $row): array
    {
        $v = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $key = strtolower($v('spec_key')); $val = $v('spec_value'); $unit = $v('unit'); $url = $v('source_url');
        $date = $v('date_checked') === '' ? date('Y-m-d') : $v('date_checked'); $status = $v('review_status') === '' ? 'needs_variant_review' : strtolower($v('review_status')); $note = $v('review_note');
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        $err = match (true) {
            !preg_match('/^[a-z][a-z0-9_]{1,59}$/', $key) => 'spec_key must be lower case letters, digits and _ (for example vram_gb)',
            $val === '' || mb_strlen($val) > 120 => 'spec_value is required (up to 120 characters)',
            mb_strlen($unit) > 12 => 'unit is longer than 12 characters',
            !preg_match('~^https?://\S{3,}$~i', $url) || strlen($url) > 500 => 'source_url must be an http(s) address',
            !$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d') => 'date_checked must be a real date, YYYY-MM-DD, not in the future',
            !in_array($status, self::STATUSES, true) => 'review_status must be official_checked or needs_variant_review',
            mb_strlen($note) > 500 => 'review_note is too long',
            default => null,
        };
        return $err !== null ? ['data' => null, 'error' => $err] : ['data' => ['spec_key' => $key, 'spec_value' => $val, 'unit' => $unit === '' ? null : $unit, 'source_url' => $url, 'date_checked' => $date, 'review_status' => $status, 'review_note' => $note === '' ? null : $note], 'error' => null];
    }

    /** @return list<array{line:int,action:string,slug:string,name:string,error:?string,data:?array,product_id:?int}> action = create | update | refused */
    public static function plan(PDO $db, array $rows): array
    {
        $products = self::products($db); $out = []; $seen = []; $k = '';
        $have = []; foreach ($db->query('SELECT product_id, spec_key FROM product_reference_specs')->fetchAll() as $r) { $have[(int) $r['product_id'] . '|' . $r['spec_key']] = true; }
        foreach ($rows as $i => $row) {
            $c = SpecImport::clean($row); $m = GameFpsImport::matchProduct((string) ($row['product'] ?? ''), $products); $err = $c['error'] ?? $m['error'];
            $k = ''; $label = trim((string) ($row['product'] ?? '')) . ' · ' . trim((string) ($row['spec_key'] ?? '')) . ' = ' . trim((string) ($row['spec_value'] ?? ''));
            if ($err === null) {
                $k = $m['id'] . '|' . $c['data']['spec_key'];
                if (isset($seen[$k])) { $err = 'this product and spec_key are repeated in the file (line ' . $seen[$k] . ')'; } else { $seen[$k] = $i + 2; }
            }
            $out[] = ['line' => $i + 2, 'action' => $err !== null ? 'refused' : (isset($have[$k]) ? 'update' : 'create'), 'slug' => '', 'name' => $label, 'error' => $err, 'data' => $err === null ? $c['data'] : null, 'product_id' => $err === null ? $m['id'] : null];
        }
        return $out;
    }

    /** @return array{created:int,updated:int} one transaction (or the caller's) */
    public static function apply(PDO $db, array $plan): array
    {
        $created = 0; $updated = 0; $own = !$db->inTransaction();
        if ($own) { $db->beginTransaction(); }
        try {
            $st = $db->prepare('INSERT INTO product_reference_specs (product_id, spec_key, spec_value, unit, source_url, date_checked, review_status, review_note) VALUES (:p, :k, :v, :u, :s, :d, :r, :n)
                ON DUPLICATE KEY UPDATE spec_value = VALUES(spec_value), unit = VALUES(unit), source_url = VALUES(source_url), date_checked = VALUES(date_checked), review_status = VALUES(review_status), review_note = VALUES(review_note)');
            foreach ($plan as $p) {
                if ($p['data'] === null) { continue; }
                $d = $p['data'];
                $st->execute(['p' => (int) $p['product_id'], 'k' => $d['spec_key'], 'v' => $d['spec_value'], 'u' => $d['unit'], 's' => $d['source_url'], 'd' => $d['date_checked'], 'r' => $d['review_status'], 'n' => $d['review_note']]);
                $p['action'] === 'update' ? $updated++ : $created++;
            }
            if ($own) { $db->commit(); }
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
        return ['created' => $created, 'updated' => $updated];
    }
}
