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
}

