<?php
declare(strict_types=1);

namespace App\Services\Deal;

/**
 * LS-24 A3: "help me bargain". From the market band the site already shows (q1, median, q3 of asking prices) and the price in the listing, it names an opening offer
 * and a ceiling and writes three ready messages in Thai. It only places the price inside the band: no baht is added or taken off for condition, warranty or box
 * (the project never turns those into money), a price below the band gets no offer (a low price is a reason to check the item, not to push lower), and with no band
 * there is nothing to say. Deterministic, pure.
 */
final class Negotiation
{
    /** @return ?array{mode:string,offer:?int,ceiling:?int,basis:string,messages:list<array{tone:string,text:string}>,tips:list<string>} null when there is no market band to stand on */
    public static function plan(string $name, float $price, ?float $q1, ?float $q3, ?float $median, int $samples = 0): ?array
    {
        if ($q1 === null || $q3 === null || $median === null || $q1 <= 0 || $q3 < $q1 || $price <= 0) { return null; }
        $r50 = static fn(float $v): int => (int)(round($v / 50) * 50);
        $basis = 'อิงจากช่วงราคาประกาศที่พบ ฿' . number_format($q1) . ' – ฿' . number_format($q3) . ($samples > 0 ? ' จาก ' . $samples . ' ตัวอย่าง' : '') . ' ไม่ใช่ราคาปิดดีล';
        $tips = ['ขอทดสอบของก่อนจ่ายทุกครั้ง ราคาที่ต่อได้ไม่คุ้มถ้าของมีปัญหา', 'ต่อด้วยข้อเท็จจริง เช่น ประกันที่เหลือหรือตำหนิที่ประกาศไม่ได้บอก', 'อย่าโอนก่อนเจอของ'];
        if ($price < $q1) { return ['mode' => 'below_band', 'offer' => null, 'ceiling' => null, 'basis' => $basis, 'messages' => [], 'tips' => array_merge(['ราคานี้ต่ำกว่าช่วงที่พบ อย่าเพิ่งต่อให้ต่ำกว่านี้ ถามก่อนว่าทำไมถึงถูก (ประกัน สภาพ ประวัติ) และตรวจของให้ละเอียดขึ้น'], $tips)]; }
        if ($price > $q3) { $offer = $r50($median); $ceiling = $r50($q3); $mode = 'above_band'; }
        elseif ($price > $median) { $offer = $r50($q1 + ($median - $q1) / 2); $ceiling = $r50(min($price, $q3)); $mode = 'upper_band'; }
        else { $offer = $r50(max($q1, $price * 0.95)); $ceiling = $r50($price); $mode = 'lower_band'; }
        $offer = max($offer, $r50($q1)); $ceiling = max($ceiling, $offer);
        $o = '฿' . number_format($offer); $band = '฿' . number_format($q1) . '–' . number_format($q3);
        $messages = [
            ['tone' => 'สุภาพ', 'text' => "สวัสดีครับ สนใจ $name ที่ลงขายอยู่ ตอนนี้ยังอยู่ไหมครับ ถ้าได้ดูของและทดสอบก่อน ผมขอเสนอ $o ครับ ขอบคุณครับ"],
            ['tone' => 'ตรงไปตรงมา', 'text' => "ผมดูราคาของรุ่นนี้ในตลาดมือสองตอนนี้อยู่ราว $band ผมขอเสนอ $o ถ้าตกลงนัดรับดูของและจ่ายได้เลยครับ"],
            ['tone' => 'เป็นกันเอง', 'text' => "พี่ครับ $name ลดให้หน่อยได้ไหมครับ ผมให้ $o นัดรับได้เลย ขอลองเปิดเครื่องดูก่อนนะครับ"],
        ];
        return ['mode' => $mode, 'offer' => $offer, 'ceiling' => $ceiling, 'basis' => $basis, 'messages' => $messages, 'tips' => $tips];
    }
}
