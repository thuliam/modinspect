<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use PDO;

final class DealCheck
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }
    public function create(array $data): string {
        $uuid = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int(0,65535),random_int(0,65535),random_int(0,65535),random_int(0,4095),random_int(0,16383)|0x8000,random_int(0,65535),random_int(0,65535),random_int(0,65535));
        $sql="INSERT INTO deal_checks (uuid,product_id,user_price,condition_level,warranty_months,has_box,has_receipt,has_benchmark,result_label,suggested_price_min,suggested_price_max,recommendation,warning_note,consent_to_anonymous_observation) VALUES (:uuid,:product_id,:user_price,:condition_level,:warranty_months,:has_box,:has_receipt,:has_benchmark,:result_label,:min,:max,:recommendation,:warning,:consent)";
        $stmt=$this->db->prepare($sql); $stmt->execute(['uuid'=>$uuid]+$data); return $uuid;
    }
    public function findByUuid(string $uuid): ?array {
        $stmt=$this->db->prepare("SELECT d.*,p.full_name,p.slug,pi.q1,pi.median,pi.q3,pi.sample_size,pi.confidence_label FROM deal_checks d JOIN products p ON p.id=d.product_id LEFT JOIN price_indices pi ON pi.id=(SELECT id FROM price_indices WHERE product_id=p.id ORDER BY last_calculated_at DESC LIMIT 1) WHERE d.uuid=:uuid");
        $stmt->execute(['uuid'=>$uuid]); return $stmt->fetch() ?: null;
    }
    /** LS-28 B1: the saved outcome of a result, or null (also null when migration 028 is not applied yet, so the page still renders) */
    public function outcomeFor(int $dealId): ?array {
        try { $st=$this->db->prepare("SELECT outcome,final_price FROM deal_outcomes WHERE deal_check_id=:d"); $st->execute(['d'=>$dealId]); return $st->fetch() ?: null; }
        catch (\PDOException $e) { return null; }
    }
    public function outcomeAvailable(): bool {
        try { $this->db->query("SELECT 1 FROM deal_outcomes LIMIT 0"); return true; } catch (\PDOException $e) { return false; }
    }
    /** one row per result; saving again replaces it */
    public function saveOutcome(int $dealId, string $outcome, ?float $finalPrice): void {
        $st=$this->db->prepare("INSERT INTO deal_outcomes (deal_check_id,outcome,final_price) VALUES (:d,:o,:p) ON DUPLICATE KEY UPDATE outcome=VALUES(outcome),final_price=VALUES(final_price)");
        $st->execute(['d'=>$dealId,'o'=>$outcome,'p'=>$finalPrice]);
    }
}

