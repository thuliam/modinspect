<?php
declare(strict_types=1);

namespace App\Services\Catalog;

use App\Services\Benchmark\BenchmarkMatcher;
use PDO;

/**
 * LS-60: validation, product matching and storage of in-game FPS rows, shared by the admin form and the file importer (cli/game_fps_import.php).
 * Strict and deterministic: a row is stored only when every required field is valid and its product matches exactly one CPU/GPU model; anything else is reported, never guessed.
 * Reference information only, never a price input. Rows come from a file the owner supplies (for example the output of an approved collection run); this class fetches nothing.
 */
final class GameFpsImport
{
    private const RES_ALIAS = ['fhd' => '1080p', '1080' => '1080p', '2k' => '1440p', '1440' => '1440p', 'qhd' => '1440p', '4k' => '2160p', '2160' => '2160p', 'uhd' => '2160p'];

    /** @return list<array<string,mixed>> rows of a .csv (header row) or .json (list of objects) file */
    public static function readFile(string $path): array
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            $d = json_decode((string) file_get_contents($path), true);
            return is_array($d) ? array_values(array_filter($d, 'is_array')) : [];
        }
        $fh = fopen($path, 'rb'); $header = null; $rows = [];
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($header === null) { $header = array_map(static fn($h) => strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF"))), $r); continue; }
            if (count($r) === count($header)) { $rows[] = array_combine($header, $r); }
        }
        fclose($fh);
        return $rows;
    }

    public static function gameKey(string $v): ?string
    {
        $v = strtolower(trim($v));
        if (isset(GameFps::GAMES[$v])) { return $v; }
        foreach (GameFps::GAMES as $k => $label) { if (strtolower($label) === $v) { return $k; } }
        return null;
    }

    public static function resolution(string $v): ?string
    {
        $v = strtolower(trim($v));
        $v = self::RES_ALIAS[$v] ?? $v;
        return isset(GameFps::RESOLUTIONS[$v]) ? $v : null;
    }

    /** @return array{data:?array<string,mixed>,error:?string} a clean row ready to store (without product_id), or the reason it was refused */
    public static function clean(array $row): array
    {
        $g = self::gameKey((string) ($row['game'] ?? $row['game_key'] ?? ''));
        $res = self::resolution((string) ($row['resolution'] ?? ''));
        $avg = trim((string) ($row['avg_fps'] ?? '')); $low = trim((string) ($row['low1_fps'] ?? ''));
        $paired = trim((string) ($row['paired_part'] ?? '')); $sn = trim((string) ($row['source_name'] ?? '')); $su = trim((string) ($row['source_url'] ?? '')); $ln = trim((string) ($row['license_note'] ?? ''));
        $quality = trim((string) ($row['quality'] ?? '')); $sponsor = trim((string) ($row['sponsor_name'] ?? '')); $tested = trim((string) ($row['tested_on'] ?? ''));
        $err = match (true) {
            $g === null => 'unknown game', $res === null => 'unknown resolution',
            !is_numeric($avg) || (float) $avg <= 0 || (float) $avg > 1000 => 'avg_fps must be a number between 0 and 1000',
            $low !== '' && (!is_numeric($low) || (float) $low <= 0 || (float) $low > (float) $avg) => 'low1_fps must be a number not above avg_fps',
            $paired === '' || mb_strlen($paired) > 120 => 'paired_part is required (the GPU for a CPU result, the CPU for a GPU result)',
            $quality === '' || mb_strlen($quality) > 30 => 'quality is required (for example High)',
            $sn === '' || mb_strlen($sn) > 80 => 'source_name is required',
            $ln === '' || mb_strlen($ln) > 80 => 'license_note is required',
            !preg_match('~^https?://\S{3,}$~i', $su) || strlen($su) > 500 => 'source_url must be an http(s) address',
            mb_strlen($sponsor) > 80 => 'sponsor_name is too long',
            $tested !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tested) => 'tested_on must be YYYY-MM-DD',
            default => null,
        };
        if ($err !== null) { return ['data' => null, 'error' => $err]; }
        return ['data' => ['game_key' => $g, 'resolution' => $res, 'quality' => $quality, 'avg_fps' => round((float) $avg, 1), 'low1_fps' => $low === '' ? null : round((float) $low, 1), 'paired_part' => $paired,
            'source_name' => $sn, 'source_url' => $su, 'license_note' => $ln, 'sponsor_name' => $sponsor === '' ? null : $sponsor, 'tested_on' => $tested === '' ? null : $tested], 'error' => null];
    }

    /**
     * @param list<array{id:int|string,slug:string,full_name:string}> $products active CPU/GPU models
     * @return array{id:?int,error:?string} the one product a reference (slug or name) points to
     */
    public static function matchProduct(string $ref, array $products): array
    {
        $ref = trim($ref);
        if ($ref === '') { return ['id' => null, 'error' => 'product is empty']; }
        foreach ($products as $p) { if (strtolower((string) $p['slug']) === strtolower($ref)) { return ['id' => (int) $p['id'], 'error' => null]; } }
        $key = BenchmarkMatcher::normalize($ref); $hits = [];
        foreach ($products as $p) { if ($key !== '' && BenchmarkMatcher::normalize((string) $p['full_name']) === $key) { $hits[] = (int) $p['id']; } }
        return count($hits) === 1 ? ['id' => $hits[0], 'error' => null] : ['id' => null, 'error' => $hits ? 'product name matches more than one model' : 'no CPU/GPU model matches this product'];
    }

    /** @return list<array{id:int|string,slug:string,full_name:string}> */
    public static function products(PDO $db): array
    {
        return $db->query("SELECT p.id, p.slug, p.full_name FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.slug IN ('cpu','gpu') AND p.is_active = 1 ORDER BY c.slug, p.full_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function tableExists(PDO $db): bool
    {
        try { $db->query('SELECT 1 FROM game_fps_results LIMIT 1'); return true; } catch (\PDOException) { return false; }
    }

    /** inserts, or updates the row with the same product/game/resolution/quality/pairing */
    public static function store(PDO $db, int $productId, array $d): void
    {
        $db->prepare("INSERT INTO game_fps_results (product_id, game_key, resolution, quality, avg_fps, low1_fps, paired_part, source_name, source_url, license_note, sponsor_name, tested_on, retrieved_on)
            VALUES (:p, :g, :r, :q, :a, :l, :pp, :sn, :su, :ln, :sp, :t, CURDATE())
            ON DUPLICATE KEY UPDATE avg_fps = VALUES(avg_fps), low1_fps = VALUES(low1_fps), source_name = VALUES(source_name), source_url = VALUES(source_url), license_note = VALUES(license_note), sponsor_name = VALUES(sponsor_name), tested_on = VALUES(tested_on), retrieved_on = CURDATE()")
            ->execute(['p' => $productId, 'g' => $d['game_key'], 'r' => $d['resolution'], 'q' => $d['quality'], 'a' => $d['avg_fps'], 'l' => $d['low1_fps'], 'pp' => $d['paired_part'], 'sn' => $d['source_name'], 'su' => $d['source_url'], 'ln' => $d['license_note'], 'sp' => $d['sponsor_name'], 't' => $d['tested_on']]);
    }
    /**
     * LS-67: classify every row (product match + validation) without writing; used by the admin import centre.
     * @return list<array{line:int,action:string,slug:string,name:string,error:?string,data:?array,product_id:?int}> action = create | refused
     */
    public static function plan(PDO $db, array $rows): array
    {
        $products = self::products($db); $out = [];
        foreach ($rows as $i => $row) {
            $c = self::clean($row); $m = self::matchProduct((string) ($row['product'] ?? ''), $products);
            $err = $c['error'] ?? $m['error'];
            $out[] = ['line' => $i + 2, 'action' => $err === null ? 'create' : 'refused', 'slug' => trim((string) ($row['product'] ?? '')), 'name' => trim((string) ($row['game'] ?? '')) . ' ' . trim((string) ($row['resolution'] ?? '')), 'error' => $err, 'data' => $err === null ? $c['data'] : null, 'product_id' => $err === null ? $m['id'] : null];
        }
        return $out;
    }

    /** @return array{created:int,updated:int} writes every planned row (store() upserts), one transaction */
    public static function apply(PDO $db, array $plan): array
    {
        $n = 0; $own = !$db->inTransaction(); if ($own) { $db->beginTransaction(); }
        try { foreach ($plan as $p) { if ($p['data'] !== null) { self::store($db, (int) $p['product_id'], $p['data']); $n++; } } if ($own) { $db->commit(); } }
        catch (\Throwable $e) { if ($own && $db->inTransaction()) { $db->rollBack(); } throw $e; }
        return ['created' => $n, 'updated' => 0];
    }

    public static function csvTemplate(): string
    {
        return "\xEF\xBB\xBFproduct,game,resolution,quality,avg_fps,low1_fps,paired_part,source_name,source_url,license_note,sponsor_name,tested_on\nnvidia-geforce-rtx-4070,Counter-Strike 2,1080p,High,310.5,220,Ryzen 7 7800X3D,Example Lab,https://example.org/review,CC BY 4.0,,2026-11-01\n";
    }
}
