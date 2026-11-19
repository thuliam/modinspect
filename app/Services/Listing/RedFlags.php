<?php
declare(strict_types=1);

namespace App\Services\Listing;

/**
 * LS-24 A2: phrases in a pasted used-hardware listing that a buyer should ask about before paying. Pure.
 * Each entry describes what the TEXT says and what to ask; none of them says anything about the seller as a person, and none is a verdict: a "caution" is a
 * reason to ask, not a reason to refuse. The wording is a draft for the owner's approval (docs/PRODUCT_IDEAS_TH.md, A2). The matched phrase shown to the user is
 * cut to a few characters so the page never repeats a long passage of the pasted text.
 */
final class RedFlags
{
    /** code => [level, label, why, ask, regex] */
    private const RULES = [
        'show_unit' => ['info', 'ตัวโชว์ / ตัวเดโม', 'ประกาศระบุว่าเป็นตัวโชว์หรือตัวเดโม อาจผ่านการเปิดใช้งานมาหลายชั่วโมง', 'ถามว่าใช้งานมากี่เดือน และประกันเริ่มนับจากวันไหน', '/ตัวโชว์|ตัวเดโม|\bdemo\b/iu'],
        'no_warranty' => ['caution', 'ไม่มีประกัน / ประกันหมด', 'ประกาศระบุว่าไม่มีประกันหรือประกันหมดแล้ว ถ้าเสียหลังซื้อจะไม่มีทางเคลม', 'ถามว่าขายตามสภาพไหม ถ้าไม่มีประกัน ขอต่อราคาและขอทดสอบก่อนจ่าย', '/ไม่มีประกัน|หมดประกัน|ประกันหมด|ไม่รับประกัน|no warranty|out of warranty/iu'],
        'repaired' => ['caution', 'เคยซ่อม / เปลี่ยนชิ้นส่วน', 'ประกาศระบุว่าเคยซ่อมหรือเปลี่ยนชิ้นส่วน เช่น พัดลม ชิป หรือทำ reball', 'ถามว่าซ่อมอะไร เมื่อไร ที่ไหน และขอดูใบเสร็จการซ่อม', '/ซ่อมมา|เคยซ่อม|เปลี่ยน(?:ชิป|พัดลม|ซิลิโคน|ตัวเก็บประจุ|แพด)|reball|รีบอล|reflow|repaired/iu'],
        'mining' => ['caution', 'เคยใช้ขุดเหรียญ', 'ประกาศระบุว่าเคยใช้ขุดเหรียญ มักเปิดเครื่องต่อเนื่องนาน', 'ถามระยะเวลา อุณหภูมิตอนใช้ และขอให้รันโหลดหนักให้ดูก่อนจ่าย', '/ตัวขุด|เคยขุด|ขุดเหรียญ|ขุดบิท|mining|\bminer\b|ex-?mining/iu'],
        'prepay' => ['caution', 'ขอโอนก่อน / มัดจำก่อน', 'ประกาศขอให้โอนหรือมัดจำก่อนเจอสินค้า ซึ่งเสี่ยงเมื่อยังไม่เคยเห็นของจริง', 'เสนอนัดรับและตรวจของก่อนจ่าย หรือใช้ช่องทางที่มีคนกลางที่คุณเชื่อถือ', '/โอนก่อน|มัดจำก่อน|โอนมัดจำ|ส่งหลังโอน|โอนเงินก่อน/iu'],
        'no_test' => ['caution', 'ไม่ให้ทดสอบ / ไม่รับคืน', 'ประกาศระบุว่าขายตามสภาพ ไม่ให้ทดสอบ หรือไม่รับคืน', 'ถามว่าขอรันทดสอบสั้น ๆ ต่อหน้าได้ไหม ถ้าไม่ได้ พิจารณาราคาให้ต่ำลงตามความเสี่ยง', '/ไม่ให้ทดสอบ|ไม่รับเทส|ไม่ให้เทส|ไม่รับคืน|ไม่รับเคลม|ขายตามสภาพ|sold as is|\bas is\b/iu'],
        'urgent' => ['info', 'รีบขาย / ขายด่วน', 'ประกาศเน้นความเร่งรีบ ซึ่งอาจเป็นเหตุผลจริง (ย้ายบ้าน ต้องใช้เงิน) หรือเป็นการกดดันให้ตัดสินใจเร็ว', 'อย่ารีบ ขอเวลาเทียบราคากับช่วงตลาดก่อน', '/รีบขาย|ขายด่วน|ด่วนมาก|ย้ายบ้าน|เหลือชิ้นเดียว/iu'],
        'engineering' => ['caution', 'ตัวทดสอบ / ES / ไม่ใช่รุ่นขายปกติ', 'ประกาศระบุว่าเป็นตัวทดสอบ (engineering/qualification sample) ซึ่งอาจต่างจากรุ่นขายทั่วไป', 'ถามรหัสบนตัวชิปและขอให้ตรวจกับรุ่นที่ประกาศ', '/engineering sample|qualification sample|ตัวเทส|\bQS\b|\bES\b\s*(?:cpu|chip)/iu'],
        'bios_flash' => ['caution', 'แฟลช BIOS / ปรับแก้เฟิร์มแวร์', 'ประกาศระบุว่าแฟลชหรือแก้ BIOS การ์ด ซึ่งอาจทำให้รุ่นที่รายงานไม่ตรงกับตัวจริง', 'ถามเหตุผลที่แฟลช และขอเทียบรุ่นกับโปรแกรมตรวจฮาร์ดแวร์ก่อนจ่าย', '/แฟลช\s*bios|flash(?:ed)?\s*bios|แฟลชการ์ด|ลง\s*bios\s*(?:ใหม่|อื่น)/iu'],
        'overclock' => ['info', 'เคยโอเวอร์คล็อก', 'ประกาศระบุว่าเคยโอเวอร์คล็อก อาจใช้แรงดันสูงกว่าปกติ', 'ถามว่าปรับอะไร นานเท่าไร และอุณหภูมิตอนใช้', '/โอเวอร์คล็อก|\boverclock(?:ed)?\b|\boc\s*หนัก\b/iu'],
        'no_receipt' => ['info', 'ไม่มีกล่อง / ใบเสร็จ', 'ประกาศระบุว่าไม่มีกล่องหรือใบเสร็จ ทำให้ตรวจประวัติและประกันยากขึ้น', 'ถามว่าซื้อจากร้านไหน เมื่อไร และมีหลักฐานอื่นหรือไม่', '/ไม่มีกล่อง|ไม่มีใบเสร็จ|ไม่มีใบกำกับ|ไม่มีหลักฐาน/iu'],
    ];

    /** @return list<array{code:string,level:string,label:string,why:string,ask:string,phrase:string}> in the order found in the text, one per rule */
    public static function scan(string $text): array
    {
        $out = [];
        foreach (self::RULES as $code => [$level, $label, $why, $ask, $re]) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE) === 1) {
                $out[] = ['pos' => (int)$m[0][1], 'code' => $code, 'level' => $level, 'label' => $label, 'why' => $why, 'ask' => $ask, 'phrase' => mb_substr((string)$m[0][0], 0, 24, 'UTF-8')];
            }
        }
        usort($out, static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);
        return array_map(static function (array $r): array { unset($r['pos']); return $r; }, $out);
    }

    /** @return list<string> the rule codes (for tests and the contract that every rule has a label, a reason and a question) */
    public static function codes(): array { return array_keys(self::RULES); }
}
