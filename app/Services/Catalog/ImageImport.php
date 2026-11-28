<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use PDO;

/**
 * LS-68: bulk load of product pictures (up to five per model, table product_images from migration 033).
 * Files are named after the model: `<slug>.jpg` or `<slug>_1.jpg` is slot 1 (the picture the public pages use), `<slug>_2.png` … `<slug>_5.webp` the rest.
 * Strict: the slug must be an existing model, the real file type (not the extension) must be JPEG, PNG or WebP, at most 3 MB, 200 to 6000 px, and the whole batch
 * carries a required source name and licence. Nothing is downloaded from anywhere; the importer only reads files the admin hands it.
 * A model in the batch has the slots named in the batch replaced; slots not named are kept.
 */
final class ImageImport
{
    public const MAX_SLOTS = 5;
    public const MAX_BYTES = 3145728;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function tableExists(PDO $db): bool
    {
        try { $db->query('SELECT 1 FROM product_images LIMIT 1'); return true; } catch (\PDOException) { return false; }
    }

    /** @return array{slug:string,slot:int}|null the model slug and slot a file name stands for */
    public static function parseName(string $name): ?array
    {
        $base = pathinfo(basename($name), PATHINFO_FILENAME);
        if (!preg_match('/^(.+?)(?:[_-]([1-5]))?$/u', $base, $m) || trim($m[1]) === '') { return null; }
        $slug = ProductImport::slug($m[1]); $slot = isset($m[2]) ? (int) $m[2] : 1;
        // a slug that itself ends in -2 (for example a model named "x-2") is matched by the caller if the shorter form is unknown
        return $slug === '' ? null : ['slug' => $slug, 'slot' => $slot, 'full' => ProductImport::slug($base)];
    }

    public static function csvNote(): string { return 'slug.jpg, slug_2.png … slug_5.webp'; }

    /**
     * @param list<array{name:string,path:string}> $files uploaded or folder files
     * @return list<array{line:int,action:string,slug:string,name:string,error:?string,data:?array,product_id:?int}> action = create | update | refused
     */
    public static function plan(PDO $db, array $files, array $meta): array
    {
        $out = []; $by = []; foreach ($db->query('SELECT id, slug FROM products')->fetchAll() as $r) { $by[strtolower((string) $r['slug'])] = (int) $r['id']; }
        $have = []; if (self::tableExists($db)) { foreach ($db->query('SELECT product_id, slot FROM product_images')->fetchAll() as $r) { $have[(int) $r['product_id'] . '|' . (int) $r['slot']] = true; } }
        $metaErr = null;
        if (trim((string) ($meta['source_name'] ?? '')) === '' || mb_strlen((string) $meta['source_name']) > 120) { $metaErr = 'source name is required for the batch'; }
        elseif (trim((string) ($meta['license_note'] ?? '')) === '' || mb_strlen((string) $meta['license_note']) > 120) { $metaErr = 'licence or permission is required for the batch'; }
        elseif (($meta['source_url'] ?? '') !== '' && !preg_match('~^https?://\S{3,}$~i', (string) $meta['source_url'])) { $metaErr = 'source URL must be an http(s) address'; }
        $seen = [];
        foreach ($files as $i => $f) {
            $err = $metaErr; $pid = null; $slug = ''; $slot = 0; $ext = null;
            $p = self::parseName($f['name']);
            if ($err === null) {
                if ($p === null) { $err = 'the file name does not name a model'; }
                else {
                    // "<slug>_2" is slot 2 of "<slug>"; a model whose own slug ends in _2 is matched whole first
                    if (isset($by[$p['full']])) { $slug = $p['full']; $slot = 1; } else { $slug = $p['slug']; $slot = $p['slot']; }
                    $pid = $by[$slug] ?? null;
                    if ($pid === null) { $err = 'no model has the slug "' . $slug . '"'; }
                }
            }
            if ($err === null) {
                $size = @filesize($f['path']);
                if ($size === false || $size < 1) { $err = 'the file is empty or unreadable'; }
                elseif ($size > self::MAX_BYTES) { $err = 'the file is larger than 3 MB'; }
                else {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['path']);
                    $ext = self::TYPES[$mime] ?? null; $dim = @getimagesize($f['path']);
                    if ($ext === null || $dim === false) { $err = 'the file is not a JPEG, PNG or WebP picture'; }
                    elseif (min($dim[0], $dim[1]) < 200 || max($dim[0], $dim[1]) > 6000) { $err = 'the picture must be between 200 and 6000 px'; }
                }
            }
            if ($err === null) {
                $k = $pid . '|' . $slot;
                if (isset($seen[$k])) { $err = 'slot ' . $slot . ' of this model is named twice in the batch'; } else { $seen[$k] = true; }
            }
            $out[] = ['line' => $i + 1, 'action' => $err !== null ? 'refused' : (isset($have[$pid . '|' . $slot]) ? 'update' : 'create'), 'slug' => $slug, 'name' => $f['name'] . ($slug !== '' ? ' → ' . $slug . ' #' . $slot : ''), 'error' => $err,
                'data' => $err === null ? ['slot' => $slot, 'ext' => $ext, 'tmp' => $f['path'], 'slug' => $slug, 'source_name' => trim((string) $meta['source_name']), 'source_url' => trim((string) ($meta['source_url'] ?? '')) ?: null, 'license_note' => trim((string) $meta['license_note']), 'alt' => trim((string) ($meta['alt'] ?? '')) ?: null] : null,
                'product_id' => $pid];
        }
        return $out;
    }

    /** how many pictures and main pictures point at $path, not counting slot $slot of model $productId (nor that model's own main picture) */
    public static function references(PDO $db, string $path, int $productId = 0, int $slot = 0): int
    {
        $a = $db->prepare('SELECT COUNT(*) FROM product_images WHERE path = ? AND NOT (product_id = ? AND slot = ?)'); $a->execute([$path, $productId, $slot]);
        $b = $db->prepare('SELECT COUNT(*) FROM products WHERE image_path = ? AND id <> ?'); $b->execute([$path, $productId]);
        return (int) $a->fetchColumn() + (int) $b->fetchColumn();
    }

    /**
     * Copies the files under $destDir (public/assets/products), writes the rows and keeps products.image_path equal to slot 1. Files are written first; on any failure
     * the files written by this call are removed and the transaction is rolled back.
     * @return array{created:int,updated:int}
     */
    public static function apply(PDO $db, array $plan, string $destDir): array
    {
        $created = 0; $updated = 0; $written = []; $stale = []; $own = !$db->inTransaction();
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true)) { throw new \RuntimeException('cannot create the picture folder'); }
        if ($own) { $db->beginTransaction(); }
        try {
            $up = $db->prepare('INSERT INTO product_images (product_id, slot, path, alt_text, source_name, source_url, license_note) VALUES (:p, :s, :path, :alt, :sn, :su, :ln)
                ON DUPLICATE KEY UPDATE path = VALUES(path), alt_text = VALUES(alt_text), source_name = VALUES(source_name), source_url = VALUES(source_url), license_note = VALUES(license_note)');
            $primary = $db->prepare('UPDATE products SET image_path = :path, updated_at = NOW() WHERE id = :id');
            foreach ($plan as $p) {
                if ($p['data'] === null) { continue; }
                $d = $p['data']; $file = $d['slug'] . '-' . $d['slot'] . '.' . $d['ext']; $rel = 'assets/products/' . $file;
                $dest = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $file;
                $old = $db->prepare('SELECT path FROM product_images WHERE product_id = :p AND slot = :s'); $old->execute(['p' => (int) $p['product_id'], 's' => $d['slot']]); $oldPath = (string) $old->fetchColumn();
                // LS-89: after pictures are swapped (or shared between models) this name may belong to someone else: use another name
                if (self::references($db, $rel, (int) $p['product_id'], $d['slot']) > 0) {
                    $file = $d['slug'] . '-' . $d['slot'] . '-' . substr((string) hash_file('sha256', $d['tmp']), 0, 8) . '.' . $d['ext']; $rel = 'assets/products/' . $file;
                    $dest = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $file;
                }
                if (!copy($d['tmp'], $dest)) { throw new \RuntimeException('cannot write ' . $file); }
                $written[] = $dest;
                $up->execute(['p' => (int) $p['product_id'], 's' => $d['slot'], 'path' => $rel, 'alt' => $d['alt'], 'sn' => $d['source_name'], 'su' => $d['source_url'], 'ln' => $d['license_note']]);
                if ($d['slot'] === 1) { $primary->execute(['path' => $rel, 'id' => (int) $p['product_id']]); }
                $p['action'] === 'update' ? $updated++ : $created++;
                if ($oldPath !== '' && $oldPath !== $rel && str_starts_with($oldPath, 'assets/products/')) { $stale[$oldPath] = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . basename($oldPath); } // the replaced picture of this slot
            }
            if ($own) { $db->commit(); }
            if ($own) { foreach ($stale as $oldPath => $sf) { if (self::references($db, (string) $oldPath) === 0) { @unlink($sf); } } }
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            foreach ($written as $w) { @unlink($w); }
            throw $e;
        }
        return ['created' => $created, 'updated' => $updated];
    }
}
