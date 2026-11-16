<?php
declare(strict_types=1);

namespace App\Services;

/**
 * What to check before paying for a used part (LS-3 J-6). A reminder list for the BUYER to work through, shown on the Deal Checker result.
 * It is not a certification, it says nothing about the seller, and it never touches the verdict label (PriceEngine is not called here):
 * the only link to the visitor's own input is a prompt for the evidence they said they do not have (no box, no receipt, no test result, no warranty).
 * Content is general inspection practice written in neutral words; no numbers, thresholds or claims about failure rates.
 */
final class ConditionChecklist
{
    public const NOTE = 'รายการนี้เป็นข้อเตือนความจำให้คุณตรวจเอง ไม่ใช่การรับรองสภาพสินค้าหรือผู้ขาย และไม่เปลี่ยนผลเทียบราคา';

    private const ITEMS = [
        'gpu' => [
            ['visual', 'ดูสภาพภายนอก: รอยไหม้ สนิม คราบ พอร์ตและขั้วไฟเสริมเสียหายหรือไม่'],
            ['serial', 'เทียบรุ่นและหน่วยความจำที่เครื่องรายงานกับที่ประกาศ'],
            ['run', 'ให้เปิดใช้งานต่อหน้าและรันงานหนักสักพัก ดูภาพผิดปกติ เสียงพัดลม และอุณหภูมิ'],
            ['history', 'ถามประวัติการใช้งานและการซ่อมหรือเปลี่ยนชิ้นส่วน'],
        ],
        'cpu' => [
            ['visual', 'ดูหน้าสัมผัสและฝาโลหะ: ขางอ รอยขูด หรือคราบ'],
            ['serial', 'เทียบรุ่นที่เครื่องรายงานกับที่ประกาศ'],
            ['run', 'ทดสอบบนเครื่องจริง ดูอุณหภูมิตอนว่างและตอนโหลด'],
            ['history', 'ถามว่าเคยโอเวอร์คล็อกหรือถอดฝาหรือไม่'],
        ],
        'ram' => [
            ['visual', 'ดูขาทองและแผงวงจร: รอยไหม้หรือรอยงอ'],
            ['serial', 'เทียบความจุ ความเร็ว และรุ่นบนสติกเกอร์กับที่เครื่องรายงาน'],
            ['run', 'ทดสอบหน่วยความจำก่อนใช้งานจริง และดูว่าเข้ากับเมนบอร์ดของคุณ'],
        ],
        'storage' => [
            ['serial', 'เทียบรุ่นและความจุที่เครื่องรายงานกับที่ประกาศ'],
            ['smart', 'ขอดูค่า SMART: ชั่วโมงใช้งาน ปริมาณข้อมูลที่เขียน และสถานะสุขภาพ'],
            ['run', 'ทดสอบอ่านเขียนสั้นๆ และดูว่าไม่มีข้อผิดพลาด'],
        ],
        'psu' => [
            ['visual', 'ดูสภาพ: กลิ่นไหม้ คราบ สายครบและไม่ชำรุด'],
            ['serial', 'เทียบวัตต์และเกรดบนฉลากกับที่ประกาศ'],
            ['run', 'ถามประวัติการใช้งานและกำลังไฟที่เคยใช้'],
        ],
        'motherboard' => [
            ['visual', 'ดูช่องซ็อกเก็ต ขาที่งอ ตัวเก็บประจุที่บวม และพอร์ตที่เสียหาย'],
            ['serial', 'เช็กว่ารุ่นและเวอร์ชัน BIOS รองรับซีพียูที่คุณจะใช้'],
            ['run', 'ให้เปิดเครื่องและตรวจว่าช่อง RAM และพอร์ตทำงานครบ'],
        ],
    ];

    private const GENERIC = [
        ['visual', 'ดูสภาพจริงของสินค้า'],
        ['serial', 'เทียบรุ่นที่เครื่องรายงานกับที่ประกาศ'],
        ['run', 'ขอทดสอบการใช้งานต่อหน้าก่อนจ่าย'],
    ];

    /**
     * @param array<string,mixed> $deal the deal check row: has_box, has_receipt, has_benchmark, warranty_months (null or '' = not given)
     * @return array{items:list<array{key:string,text:string}>,prompts:list<array{key:string,text:string}>,note:string}
     */
    public static function for(?string $categorySlug, array $deal): array
    {
        $slug = strtolower((string)$categorySlug);
        $items = [];
        foreach (self::ITEMS[$slug] ?? self::GENERIC as [$key, $text]) { $items[] = ['key' => $key, 'text' => $text]; }

        $prompts = [];
        if (isset($deal['has_box']) && (int)$deal['has_box'] === 0) { $prompts[] = ['key' => 'no_box', 'text' => 'คุณระบุว่าไม่มีกล่อง: ตรวจหมายเลขซีเรียลและประกันให้ละเอียดขึ้น']; }
        if (isset($deal['has_receipt']) && (int)$deal['has_receipt'] === 0) { $prompts[] = ['key' => 'no_receipt', 'text' => 'คุณระบุว่าไม่มีใบเสร็จ: ขอหลักฐานการซื้อหรือใบรับประกันของร้าน']; }
        if (in_array($slug, ['gpu', 'cpu'], true) && isset($deal['has_benchmark']) && (int)$deal['has_benchmark'] === 0) { $prompts[] = ['key' => 'no_test', 'text' => 'คุณระบุว่ายังไม่มีผลทดสอบ: ขอดูผลทดสอบหรือให้ทดสอบสดก่อนจ่าย']; }
        $w = $deal['warranty_months'] ?? null;
        if ($w === null || $w === '' || (int)$w === 0) { $prompts[] = ['key' => 'no_warranty', 'text' => 'ประกันคงเหลือเป็น 0 หรือไม่ระบุ: ถ้าเกิดปัญหาหลังซื้อ คุณอาจต้องรับความเสี่ยงเอง']; }
        return ['items' => $items, 'prompts' => $prompts, 'note' => self::NOTE];
    }
}
