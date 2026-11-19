<?php
declare(strict_types=1);

namespace App\Services\Intake;

use App\Services\Admin\NewPriceReferences;
use PDO;

/**
 * LS-23 B4: dataset B (new retail prices) into new_price_references through the existing NewPriceReferences service, so every row keeps the shop link, the
 * check day and an audit entry with the acting admin. Dry run by default: the same checks run and nothing is written. These rows are references for the Build
 * page; they are never observations and never touch a snapshot, a median or a confidence.
 */
final class IntakeNewPrices
{
    /**
     * @param list<array<string,string>> $rows valid rows of dataset B (IntakeValidator)
     * @return array{rows:int,to_add:int,added:int,already_stored:int,errors:array<string,int>,models:int}
     */
    public static function import(PDO $db, int $actorId, array $rows, bool $apply): array
    {
        $ids = [];
        foreach ($db->query('SELECT id, full_name FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $p) { $ids[IntakeValidator::norm((string)$p['full_name'])] = (int)$p['id']; }
        $res = ['rows' => count($rows), 'to_add' => 0, 'added' => 0, 'already_stored' => 0, 'errors' => [], 'models' => 0];
        $svc = new NewPriceReferences($db);
        $has = $db->prepare('SELECT 1 FROM new_price_references WHERE product_id = :p AND source_url = :u AND checked_on = :d');
        $models = [];
        $own = $apply && !$db->inTransaction();
        if ($own) { $db->beginTransaction(); }
        try {
            foreach ($rows as $r) {
                $pid = $ids[IntakeValidator::norm($r['product_name'])] ?? null;
                if ($pid === null) { $res['errors']['PRODUCT_NOT_FOUND'] = ($res['errors']['PRODUCT_NOT_FOUND'] ?? 0) + 1; continue; }
                $has->execute(['p' => $pid, 'u' => trim($r['source_url']), 'd' => trim($r['checked_on'])]);
                if ($has->fetchColumn()) { $res['already_stored']++; continue; }
                $note = trim($r['note'] ?? '');
                if (($r['in_stock'] ?? '') === 'no') { $note = trim('out of stock' . ($note !== '' ? '; ' . $note : '')); }
                if ($apply) {
                    $a = $svc->add($actorId, $pid, $r['price_thb'], $r['source_url'], $r['checked_on'], $note);
                    if (!$a['ok']) { $res['errors'][$a['error']] = ($res['errors'][$a['error']] ?? 0) + 1; continue; }
                    $res['added']++;
                }
                $res['to_add']++; $models[$pid] = true;
            }
            if ($own) { $db->commit(); }
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
        $res['models'] = count($models);
        return $res;
    }
}
