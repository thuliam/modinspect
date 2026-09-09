<?php
declare(strict_types=1);
namespace App\Services;

final class PriceEngine
{
    public function evaluate(array $product, float $price): array {
        if (!$product['q1'] || !$product['q3'] || (int)($product['accepted_observation_count'] ?? 0) <= 0) return ['label'=>'insufficient','recommendation'=>'ข้อมูลตลาดยังไม่เพียงพอสำหรับประเมินช่วงราคา','warning'=>'ต้องมี accepted observations ก่อนใช้เป็นช่วงอ้างอิงสาธารณะ'];
        $q1=(float)$product['q1']; $q3=(float)$product['q3'];
        if ($price < $q1) return ['label'=>'below_range','recommendation'=>'ราคานี้ต่ำกว่าช่วงตลาดที่ตรวจพบ ควรตรวจสภาพ ที่มา และเงื่อนไขให้ละเอียด','warning'=>'ราคาต่ำไม่ใช่สัญญาณให้ซื้อทันที'];
        if ($price > $q3) return ['label'=>'above_range','recommendation'=>'ราคานี้สูงกว่าช่วงตลาดที่ตรวจพบ แต่ประกัน สภาพ และอุปกรณ์ที่ครบอาจรองรับส่วนต่างได้','warning'=>'เปรียบเทียบประกันและหลักฐานการทดสอบก่อนตัดสินใจ'];
        return ['label'=>'in_range','recommendation'=>'ราคานี้อยู่ในช่วงตลาดที่ตรวจพบ','warning'=>'ข้อมูลเป็นราคาประกาศ ไม่ใช่ราคาปิดดีลจริง'];
    }
}
