<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Auth\AuthService;
use PDO;

/**
 * LS-14 Module 6.5 (OD-2): admin-entered NEW retail prices for the Build page, one row per look-up (the latest one is used). Each row keeps the shop
 * link and the day it was checked, and every add or delete is audited with the acting admin. These rows are never observations: they do not touch
 * snapshots, medians or confidence.
 */
final class NewPriceReferences
{
    public const MAX_PRICE = 10000000;

    public function __construct(private PDO $db) {}

    /** @return list<array> latest first, with product and category names */
    public function list(int $limit = 300): array
    {
        $stmt = $this->db->prepare("SELECT r.id, r.product_id, r.price, r.source_url, r.checked_on, r.note, r.created_at, p.full_name, c.name category_name, u.name created_by_name
            FROM new_price_references r JOIN products p ON p.id = r.product_id JOIN product_categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = r.created_by
            ORDER BY r.id DESC LIMIT " . max(1, min(1000, $limit)));
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     * Errors: PRODUCT_NOT_FOUND, INVALID_PRICE, INVALID_URL, INVALID_DATE
     */
    public function add(int $actorId, int $productId, string $price, string $url, string $checkedOn, string $note = ''): array
    {
        $p = filter_var(str_replace(',', '', trim($price)), FILTER_VALIDATE_FLOAT);
        if ($p === false || $p <= 0 || $p > self::MAX_PRICE) { return ['ok' => false, 'error' => 'INVALID_PRICE']; }
        $url = trim($url);
        if (strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $url)) { return ['ok' => false, 'error' => 'INVALID_URL']; }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($checkedOn));
        if (!$d || $d->format('Y-m-d') !== trim($checkedOn) || $d > new \DateTimeImmutable('tomorrow')) { return ['ok' => false, 'error' => 'INVALID_DATE']; }
        $exists = $this->db->prepare('SELECT 1 FROM products WHERE id = :id AND is_active = 1');
        $exists->execute(['id' => $productId]);
        if (!$exists->fetchColumn()) { return ['ok' => false, 'error' => 'PRODUCT_NOT_FOUND']; }
        $note = mb_substr(trim($note), 0, 255);
        $own = !$this->db->inTransaction();   // join a caller's transaction instead of failing
        if ($own) { $this->db->beginTransaction(); }
        try {
            $this->db->prepare('INSERT INTO new_price_references (product_id, price, source_url, checked_on, note, created_by) VALUES (:p, :price, :url, :d, :note, :by)')
                ->execute(['p' => $productId, 'price' => round($p, 2), 'url' => $url, 'd' => $d->format('Y-m-d'), 'note' => $note !== '' ? $note : null, 'by' => $actorId]);
            $id = (int)$this->db->lastInsertId();
            (new AuthService())->audit($actorId, 'new_price_reference_created', 'new_price_reference', $id, ['product_id' => $productId, 'price' => round($p, 2), 'checked_on' => $d->format('Y-m-d')]);
            if ($own) { $this->db->commit(); }
        } catch (\Throwable $e) {
            if ($own) { $this->db->rollBack(); }
            throw $e;
        }
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool,error?:string} */
    public function delete(int $actorId, int $id): array
    {
        $row = $this->db->prepare('SELECT product_id, price FROM new_price_references WHERE id = :id');
        $row->execute(['id' => $id]);
        $r = $row->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        $own = !$this->db->inTransaction();   // join a caller's transaction instead of failing
        if ($own) { $this->db->beginTransaction(); }
        try {
            $this->db->prepare('DELETE FROM new_price_references WHERE id = :id')->execute(['id' => $id]);
            (new AuthService())->audit($actorId, 'new_price_reference_deleted', 'new_price_reference', $id, ['product_id' => (int)$r['product_id'], 'price' => (float)$r['price']]);
            if ($own) { $this->db->commit(); }
        } catch (\Throwable $e) {
            if ($own) { $this->db->rollBack(); }
            throw $e;
        }
        return ['ok' => true];
    }
}
