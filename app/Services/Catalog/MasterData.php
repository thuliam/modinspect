<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;
use Throwable;

/**
 * LS-89 BE-5: brands, categories, reference specs and product pictures edited one at a time in the admin. Every method returns
 * ['ok' => bool, 'error' => ?code, ...]; the caller records the audit line. Nothing here touches observations or prices.
 * Removing a picture removes the reference only: the file stays on disk, because several models may point at the same file.
 */
final class MasterData
{
    /** categories the application's own rules and pages are built around: they can be renamed and reordered, never deleted */
    public const BUILT_IN_CATEGORIES = ['cpu', 'gpu', 'ram', 'motherboard', 'storage', 'psu', 'case', 'cooling'];

    public function __construct(private PDO $db)
    {
    }

    /** @return list<array{id:int,name:string,slug:string,products:int}> */
    public function brands(): array
    {
        return $this->db->query('SELECT b.id, b.name, b.slug, COUNT(p.id) products FROM brands b LEFT JOIN products p ON p.brand_id = b.id GROUP BY b.id, b.name, b.slug ORDER BY b.name')->fetchAll();
    }

    /** @return list<array{id:int,name:string,slug:string,sort_order:int,products:int}> */
    public function categories(): array
    {
        return $this->db->query('SELECT c.id, c.name, c.slug, c.sort_order, COUNT(p.id) products FROM product_categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name, c.slug, c.sort_order ORDER BY c.sort_order, c.name')->fetchAll();
    }

    public function saveBrand(?int $id, string $name): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            return $this->fail('NAME_INVALID');
        }
        if ($id !== null && !$this->exists('brands', $id)) {
            return $this->fail('NOT_FOUND');
        }
        if ($this->nameTaken('brands', $name, $id)) {
            return $this->fail('NAME_TAKEN');
        }
        if ($id !== null) {
            // the slug stays: links and imports that use it keep working
            $this->db->prepare('UPDATE brands SET name = ? WHERE id = ?')->execute([$name, $id]);
            return $this->ok(['id' => $id]);
        }
        $slug = trim((string)preg_replace('/[^a-z0-9ก-๙]+/iu', '-', mb_strtolower($name, 'UTF-8')), '-');
        if ($slug === '') {
            return $this->fail('NAME_INVALID');
        }
        $taken = $this->db->prepare('SELECT 1 FROM brands WHERE slug = ?');
        $base = $slug;
        for ($i = 2; ; $i++) {
            $taken->execute([$slug]);
            if (!$taken->fetchColumn()) { break; }
            $slug = $base . '-' . $i;
        }
        $this->db->prepare('INSERT INTO brands (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
        return $this->ok(['id' => (int)$this->db->lastInsertId()]);
    }

    public function deleteBrand(int $id): array
    {
        if (!$this->exists('brands', $id)) {
            return $this->fail('NOT_FOUND');
        }
        // the condition is part of the delete, so a product added in the meantime keeps its brand
        $stmt = $this->db->prepare('DELETE FROM brands WHERE id = ? AND NOT EXISTS (SELECT 1 FROM products WHERE brand_id = ?)');
        $stmt->execute([$id, $id]);
        return $stmt->rowCount() === 1 ? $this->ok(['id' => $id]) : $this->fail('IN_USE');
    }

    public function saveCategory(?int $id, string $name, string $slug, int $sortOrder): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            return $this->fail('NAME_INVALID');
        }
        if ($sortOrder < 0 || $sortOrder > 9999) {
            return $this->fail('SORT_INVALID');
        }
        if ($id !== null && !$this->exists('product_categories', $id)) {
            return $this->fail('NOT_FOUND');
        }
        if ($this->nameTaken('product_categories', $name, $id)) {
            return $this->fail('NAME_TAKEN');
        }
        if ($id !== null) {
            $this->db->prepare('UPDATE product_categories SET name = ?, sort_order = ? WHERE id = ?')->execute([$name, $sortOrder, $id]);
            return $this->ok(['id' => $id]);
        }
        $slug = strtolower(trim($slug));
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/', $slug) !== 1) {
            return $this->fail('SLUG_INVALID');
        }
        $taken = $this->db->prepare('SELECT 1 FROM product_categories WHERE slug = ?');
        $taken->execute([$slug]);
        if ($taken->fetchColumn()) {
            return $this->fail('SLUG_TAKEN');
        }
        $this->db->prepare('INSERT INTO product_categories (name, slug, sort_order) VALUES (?, ?, ?)')->execute([$name, $slug, $sortOrder]);
        return $this->ok(['id' => (int)$this->db->lastInsertId()]);
    }

    public function deleteCategory(int $id): array
    {
        $stmt = $this->db->prepare('SELECT slug FROM product_categories WHERE id = ?');
        $stmt->execute([$id]);
        $slug = $stmt->fetchColumn();
        if ($slug === false) {
            return $this->fail('NOT_FOUND');
        }
        if (in_array((string)$slug, self::BUILT_IN_CATEGORIES, true)) {
            return $this->fail('PROTECTED');
        }
        $delete = $this->db->prepare('DELETE FROM product_categories WHERE id = ? AND NOT EXISTS (SELECT 1 FROM products WHERE category_id = ?)');
        $delete->execute([$id, $id]);
        return $delete->rowCount() === 1 ? $this->ok(['id' => $id]) : $this->fail('IN_USE');
    }

    public function product(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, full_name, slug, image_path FROM products WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> */
    public function specs(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_reference_specs WHERE product_id = ? ORDER BY spec_key');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /** adds or replaces one spec; the same checks as the bulk import (a source address and a check date are required) */
    public function saveSpec(int $productId, array $row): array
    {
        if ($this->product($productId) === null) {
            return $this->fail('NOT_FOUND');
        }
        $clean = SpecImport::clean($row);
        if ($clean['error'] !== null) {
            return $this->fail('SPEC_INVALID') + ['detail' => $clean['error']];
        }
        $before = $this->spec($productId, $clean['data']['spec_key']);
        SpecImport::apply($this->db, [['data' => $clean['data'], 'product_id' => $productId, 'action' => $before === null ? 'create' : 'update']]);
        return $this->ok(['before' => $before, 'after' => $clean['data']]);
    }

    public function deleteSpec(int $productId, string $key): array
    {
        $before = $this->spec($productId, $key);
        if ($before === null) {
            return $this->fail('NOT_FOUND');
        }
        $this->db->prepare('DELETE FROM product_reference_specs WHERE product_id = ? AND spec_key = ?')->execute([$productId, $key]);
        return $this->ok(['before' => $before]);
    }

    /** @return array{slots:list<array>,primary:?string,shared:array<string,int>} shared = how many OTHER models use each file */
    public function pictures(int $productId): array
    {
        $product = $this->product($productId);
        $slots = [];
        if (ImageImport::tableExists($this->db)) {
            $stmt = $this->db->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY slot');
            $stmt->execute([$productId]);
            $slots = $stmt->fetchAll();
        }
        $primary = $product === null || (string)$product['image_path'] === '' ? null : (string)$product['image_path'];
        $paths = array_values(array_unique(array_filter(array_merge(array_column($slots, 'path'), [$primary]))));
        $shared = [];
        foreach ($paths as $path) {
            $shared[$path] = $this->otherModelsUsing((string)$path, $productId);
        }
        return ['slots' => $slots, 'primary' => $primary, 'shared' => $shared];
    }

    public function otherModelsUsing(string $path, int $productId): int
    {
        $sql = 'SELECT id FROM products WHERE image_path = ? AND id <> ?';
        $params = [$path, $productId];
        if (ImageImport::tableExists($this->db)) {
            $sql .= ' UNION SELECT product_id FROM product_images WHERE path = ? AND product_id <> ?';
            array_push($params, $path, $productId);
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM (' . $sql . ') u');
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function removePicture(int $productId, int $slot): array
    {
        $row = $this->picture($productId, $slot);
        if ($row === null) {
            return $this->fail('NOT_FOUND');
        }
        $this->transaction(function () use ($productId, $slot, $row): void {
            $this->db->prepare('DELETE FROM product_images WHERE product_id = ? AND slot = ?')->execute([$productId, $slot]);
            $this->syncPrimary($productId, (string)$row['path']);
        });
        return $this->ok(['path' => (string)$row['path']]);
    }

    /** swaps a picture with the one in $to (or moves it there when $to is empty) */
    public function movePicture(int $productId, int $from, int $to): array
    {
        if ($to < 1 || $to > ImageImport::MAX_SLOTS || $from === $to) {
            return $this->fail('SLOT_INVALID');
        }
        $moving = $this->picture($productId, $from);
        if ($moving === null) {
            return $this->fail('NOT_FOUND');
        }
        $other = $this->picture($productId, $to);
        $this->transaction(function () use ($productId, $from, $to, $other, $moving): void {
            $set = $this->db->prepare('UPDATE product_images SET slot = ? WHERE product_id = ? AND slot = ?');
            $parking = 200;
            if ($other !== null) { $set->execute([$parking, $productId, $to]); }
            $set->execute([$to, $productId, $from]);
            if ($other !== null) { $set->execute([$from, $productId, $parking]); }
            $this->syncPrimary($productId, (string)$moving['path']);
        });
        return $this->ok(['from' => $from, 'to' => $to]);
    }

    /** drops the single picture path of a model that has no slot pictures (the older way pictures were attached) */
    public function clearPrimary(int $productId): array
    {
        $product = $this->product($productId);
        if ($product === null || (string)$product['image_path'] === '' || $this->pictures($productId)['slots'] !== []) {
            return $this->fail('NOT_FOUND');
        }
        $this->db->prepare('UPDATE products SET image_path = NULL, updated_at = NOW() WHERE id = ?')->execute([$productId]);
        return $this->ok(['path' => (string)$product['image_path']]);
    }

    /** the main picture is the lowest slot; with no slot left it is cleared only if it was the picture just handled */
    private function syncPrimary(int $productId, string $handledPath): void
    {
        $stmt = $this->db->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY slot LIMIT 1');
        $stmt->execute([$productId]);
        $path = $stmt->fetchColumn();
        if ($path !== false) {
            $this->db->prepare('UPDATE products SET image_path = ?, updated_at = NOW() WHERE id = ?')->execute([$path, $productId]);
        } else {
            $this->db->prepare('UPDATE products SET image_path = NULL, updated_at = NOW() WHERE id = ? AND image_path = ?')->execute([$productId, $handledPath]);
        }
    }

    private function picture(int $productId, int $slot): ?array
    {
        if (!ImageImport::tableExists($this->db)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM product_images WHERE product_id = ? AND slot = ?');
        $stmt->execute([$productId, $slot]);
        return $stmt->fetch() ?: null;
    }

    private function spec(int $productId, string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT spec_key, spec_value, unit, source_url, date_checked, review_status, review_note FROM product_reference_specs WHERE product_id = ? AND spec_key = ?');
        $stmt->execute([$productId, $key]);
        return $stmt->fetch() ?: null;
    }

    private function exists(string $table, int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    private function nameTaken(string $table, string $name, ?int $exceptId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM `$table` WHERE LOWER(name) = LOWER(?) AND id <> ?");
        $stmt->execute([$name, $exceptId ?? 0]);
        return (bool)$stmt->fetchColumn();
    }

    private function transaction(callable $work): void
    {
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $work();
            if ($own) { $this->db->commit(); }
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    private function ok(array $more = []): array
    {
        return ['ok' => true, 'error' => null] + $more;
    }

    private function fail(string $code): array
    {
        return ['ok' => false, 'error' => $code];
    }
}
