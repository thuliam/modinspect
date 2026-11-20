<?php
declare(strict_types=1);

namespace App\Services\Blog;

/**
 * LS-24 C3: a draft article "the market today", written from the stored public prices only (Product::search rows): the intro counts, then the models with the most
 * samples per category, each as a {{price:slug}} line that the blog draws as a live price card. Plain templates, no AI, no figure that is not on a price page, and the
 * asking-price caveat in the text. It is a DRAFT: the owner pastes it into the article form and decides whether to publish. Weekly change is not claimed until the
 * data holds more than one period. Pure.
 */
final class MarketSnapshotDraft
{
    private const MONTHS = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    private const CATEGORY_TH = ['gpu' => 'การ์ดจอ', 'cpu' => 'ซีพียู', 'ram' => 'แรม', 'storage' => 'ที่เก็บข้อมูล', 'motherboard' => 'เมนบอร์ด', 'psu' => 'พาวเวอร์ซัพพลาย', 'case' => 'เคส', 'cooling' => 'ระบายความร้อน'];

    /** @param list<array<string,mixed>> $products rows of Product::search() @return array{title:string,slug:string,excerpt:string,body:string,priced:int,models:int} */
    public static function build(array $products, \DateTimeImmutable $now, int $perCategory = 3): array
    {
        $priced = array_values(array_filter($products, static fn(array $p): bool => !empty($p['median']) && !empty($p['q1']) && !empty($p['q3']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0 && !empty($p['slug'])));
        $date = (int)$now->format('j') . ' ' . self::MONTHS[(int)$now->format('n') - 1] . ' ' . ((int)$now->format('Y') + 543);
        $by = [];
        foreach ($priced as $p) { $by[strtolower((string)($p['category_slug'] ?? $p['category_name'] ?? 'other'))][] = $p; }
        $body = ["## ภาพตลาดวันนี้ ($date)", '', 'วันนี้มี ' . count($priced) . ' รุ่นจาก ' . count($products) . ' รุ่นในฐานข้อมูลที่มีช่วงราคาจากประกาศที่ตรวจแล้ว ตัวเลขทั้งหมดเป็นราคาประกาศขาย ไม่ใช่ราคาปิดดีล และแต่ละการ์ดบอกจำนวนตัวอย่างไว้ใต้ตัวเลข ยิ่งตัวอย่างน้อย ยิ่งใช้เป็นแนวทางมากกว่าคำตอบ', ''];
        foreach (self::CATEGORY_TH as $slug => $label) {
            $rows = $by[$slug] ?? [];
            if ($rows === []) { continue; }
            usort($rows, static fn(array $a, array $b): int => [-(int)$a['valid_sample_size'], (string)$a['full_name']] <=> [-(int)$b['valid_sample_size'], (string)$b['full_name']]);
            $rows = array_slice($rows, 0, max(1, $perCategory));
            $body[] = "### $label";
            $body[] = '';
            $first = $rows[0];
            $body[] = (string)$first['full_name'] . ' เป็นรุ่นที่มีตัวอย่างมากที่สุดในหมวดนี้ (' . (int)$first['valid_sample_size'] . ' ประกาศ) ช่วงราคาที่พบ ฿' . number_format((float)$first['q1']) . ' ถึง ฿' . number_format((float)$first['q3']) . ' ราคากลาง ฿' . number_format((float)$first['median']);
            $body[] = '';
            foreach ($rows as $p) { $body[] = '{{price:' . $p['slug'] . '}}'; $body[] = ''; }
        }
        $body[] = 'ข้อมูลเก็บเพิ่มทุกวัน ถ้าเจอประกาศที่อยากรู้ว่าราคานี้โอเคไหม ลองวางข้อความที่ [วางประกาศ](/read-listing) เราจะอ่านให้';
        return ['title' => "ภาพตลาดคอมมือสองวันนี้ ($date)", 'slug' => 'market-today-' . $now->format('Y-m-d'), 'excerpt' => 'ช่วงราคาประกาศคอมมือสองที่ตรวจแล้ว แยกตามหมวด พร้อมจำนวนตัวอย่างของแต่ละรุ่น', 'body' => implode("\n", $body), 'priced' => count($priced), 'models' => count($products)];
    }
}
