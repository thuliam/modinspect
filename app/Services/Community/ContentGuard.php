<?php
declare(strict_types=1);

namespace App\Services\Community;

use App\Services\Collection\CaptureMethod;

/**
 * LS-24 M9: what a member may not post. Pure. The point is to keep the forum from becoming a place that names people or carries contact details, whatever the
 * wording of the community rules ends up being: a post that holds a phone number, an e-mail, a LINE id, any link, a bank-account-like number, or a word that
 * brands a person ("scammer", "blacklist") is refused with a code and a Thai explanation, so the writer can fix it. Describing what happened to the writer
 * ("the card died after two days") stays allowed. Nothing here judges a seller; it only refuses names, contact details and accusations.
 */
final class ContentGuard
{
    public const MESSAGES = [
        'EMPTY' => 'กรอกข้อความก่อน',
        'TOO_SHORT' => 'ข้อความสั้นเกินไป เล่ารายละเอียดอีกนิด',
        'TOO_LONG' => 'ข้อความยาวเกินกำหนด',
        'PHONE' => 'ห้ามใส่เบอร์โทร ลบเบอร์ออกแล้วลองใหม่',
        'EMAIL' => 'ห้ามใส่อีเมล ลบอีเมลออกแล้วลองใหม่',
        'LINE_ID' => 'ห้ามใส่ไลน์ไอดี ลบออกแล้วลองใหม่',
        'SOCIAL' => 'ห้ามใส่ชื่อบัญชีโซเชียล ลบออกแล้วลองใหม่',
        'LINK' =>'ห้ามแนบลิงก์ในกระทู้ เล่าเป็นข้อความแทน',
        'ACCOUNT_NUMBER' => 'ห้ามใส่เลขบัญชีหรือเลขที่คล้ายเลขบัญชี',
        'ACCUSATION' => 'ห้ามเรียกบุคคลว่าโกงหรือมิจฉาชีพ เล่าสิ่งที่เกิดขึ้นกับคุณแทน (เช่น ของเสียหลังซื้อกี่วัน ผู้ขายตอบกลับหรือไม่)',
        'SPAM' => 'ข้อความนี้ดูเป็นสแปม (ตัวอักษรซ้ำหรือยาวเกินจริง)',
    ];

    private const ACCUSATION = '/โกง|มิจฉาชีพ|หลอกลวง|หลอกเงิน|ต้มตุ๋น|ขโมย|แบล็ค?ลิสต์|ขึ้นบัญชีดำ|บัญชีดำ|\bscam(?:mer|mers)?\b|\bfraud(?:ster)?\b|\bblacklist(?:ed)?\b|\bthief\b/iu';

    /** @return list<string> the codes that apply; empty = fine */
    public static function check(string $text, int $min = 5, int $max = 1500): array
    {
        $t = trim($text);
        if ($t === '') { return ['EMPTY']; }
        $e = [];
        $len = mb_strlen($t, 'UTF-8');
        if ($len < $min) { $e[] = 'TOO_SHORT'; }
        if ($len > $max) { $e[] = 'TOO_LONG'; }
        // LS-31: Thai digits and separators ("08-1234-5678", "๐๘๑ ๒๓๔") are read as digits before the phone check, so they cannot slip past it
        $ascii = strtr($t, ['๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4', '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9']);
        $compact = (string)preg_replace('/(?<=\d)[\s\-.]+(?=\d)/', '', $ascii);
        if (CaptureMethod::hasPhoneNumber($t) || CaptureMethod::hasPhoneNumber($ascii) || preg_match('/(?<!\d)0\d{8,9}(?!\d)|(?<![\d+])\+?66\d{8,9}(?!\d)/', $compact)) { $e[] = 'PHONE'; }
        if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $t)) { $e[] = 'EMAIL'; }
        if (preg_match('/(?:line\s*(?:id)?|ไลน์|ไลน)\s*[:：]\s*@?[A-Za-z0-9._\-]{4,}|(?:line\s*id|ไลน์ไอดี)\s*@?[A-Za-z0-9._\-]{4,}|(?<![\w.])@[A-Za-z0-9._]{4,}|(?:ไลน์|ไลน)\s*(?:id|ไอดี)\s*[:：]?\s*@?[A-Za-z0-9._\-]{4,}/iu', $t)) { $e[] = 'LINE_ID'; }
        if (preg_match('/(?:\big\b|instagram|ไอจี|\bfb\b|facebook|เฟส(?:บุ๊ก|บุ๊ค)?|tiktok|ติ๊กต๊อก|telegram|เทเลแกรม)\s*[:：]\s*@?[A-Za-z0-9._]{3,}/iu', $t)) { $e[] = 'SOCIAL'; }
        if (preg_match('#(?:https?://|www\.|line\.me|bit\.ly|\b[a-z0-9-]+\.(?:com|net|org|co\.th|in\.th|me|io|th)\b)#i', $t)) { $e[] = 'LINK'; }
        if (preg_match('/(?<!\d)\d{3}[\s-]?\d[\s-]?\d{5}[\s-]?\d(?!\d)|(?<!\d)\d{10,15}(?!\d)/', $t)) { $e[] = 'ACCOUNT_NUMBER'; }
        if (preg_match(self::ACCUSATION, $t)) { $e[] = 'ACCUSATION'; }
        if (preg_match('/(.)\1{11,}/u', $t) || (preg_match_all('/[^\s\w\x{0E00}-\x{0E7F}]/u', $t) > 40)) { $e[] = 'SPAM'; }
        return array_values(array_unique($e));
    }

    /** @param list<string> $codes @return list<string> Thai messages */
    public static function messages(array $codes): array
    {
        return array_map(static fn(string $c): string => self::MESSAGES[$c] ?? $c, $codes);
    }
}
