<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

final class SpecBuild
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }

    public function create(array $build, array $items): string
    {
        $uuid = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int(0,65535),random_int(0,65535),random_int(0,65535),random_int(0,4095),random_int(0,16383)|0x8000,random_int(0,65535),random_int(0,65535),random_int(0,65535));
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO spec_builds (uuid,budget_min,budget_max,intended_use,target_resolution,mix_preference,estimated_low,estimated_expected,estimated_high,compatibility_notes) VALUES (:uuid,:budget_min,:budget_max,:intended_use,:target_resolution,:mix_preference,:estimated_low,:estimated_expected,:estimated_high,:compatibility_notes)");
            $stmt->execute(['uuid'=>$uuid] + $build);
            $buildId = (int)$this->db->lastInsertId();
            $itemStmt = $this->db->prepare("INSERT INTO spec_build_items (spec_build_id,product_id,component_role,quantity,price_low,price_expected,price_high,confidence_score,is_alternative,notes) VALUES (:spec_build_id,:product_id,:component_role,1,:price_low,:price_expected,:price_high,:confidence_score,:is_alternative,:notes)");
            foreach ($items as $item) $itemStmt->execute(['spec_build_id'=>$buildId] + $item);
            $this->db->commit();
            return $uuid;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function find(string $uuid): ?array
    {
        $stmt=$this->db->prepare("SELECT * FROM spec_builds WHERE uuid=:uuid LIMIT 1");
        $stmt->execute(['uuid'=>$uuid]); $build=$stmt->fetch();
        if (!$build) return null;
        $items=$this->db->prepare("SELECT i.*,p.full_name,p.slug,c.name category_name FROM spec_build_items i JOIN products p ON p.id=i.product_id JOIN product_categories c ON c.id=p.category_id WHERE i.spec_build_id=:id ORDER BY i.is_alternative,i.id");
        $items->execute(['id'=>$build['id']]); $build['items']=$items->fetchAll();
        return $build;
    }
}

