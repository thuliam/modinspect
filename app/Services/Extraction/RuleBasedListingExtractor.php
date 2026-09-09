<?php
declare(strict_types=1);

namespace App\Services\Extraction;

use App\Data\ExtractedObservation;
use App\Data\SearchCandidate;

final class RuleBasedListingExtractor
{
    public function extract(SearchCandidate $candidate): ExtractedObservation
    {
        $text = mb_strtolower($candidate->title . ' ' . (string)$candidate->priceText, 'UTF-8');
        $priceCandidates = $this->extractPrices((string)$candidate->priceText) ?: $this->extractPrices($candidate->title);
        $price = $priceCandidates[0] ?? null;

        $isWanted = (bool)preg_match('/\b(wtb|wanted|buying|looking\s*for)\b|รับซื้อ|ตามหา|หาอยู่|รับ\s*(rtx|gtx|rx|ryzen|i[3579])/u', $text);
        $isWholePc = (bool)preg_match('/\b(full\s*pc|set\s*pc|computer\s*set|gaming\s*pc|pc\s*build)\b|ทั้งเครื่อง|คอมทั้งชุด|ชุดคอม|ครบชุด|ขายยกเครื่อง/u', $text)
            || ((bool)preg_match('/\b(rtx|gtx|rx)\s*\d/u', $text) && (bool)preg_match('/\b(ryzen|core\s*i[3579]|i[3579]\s*-?\s*\d)/u', $text) && (bool)preg_match('/\b(ram|ssd|hdd)\b|แรม/u', $text));
        $isDeposit = (bool)preg_match('/deposit|มัดจำ|ดาวน์|ผ่อน|ต่อเดือน|monthly|เริ่มต้น|จอง/u', $text);
        $isDefective = (bool)preg_match('/เสีย|ซ่อม|อะไหล่|เปิดไม่ติด|มีตำหนิหนัก|defective|broken|repair|for\s*parts|dead|artifact/u', $text);
        $isBundle = (bool)preg_match('/\+|พร้อม\s*(บอร์ด|แรม|psu|power|เมนบอร์ด)|คู่กับ|bundle|combo|cpu\s*\+\s*(mb|mobo|board|ram)|gpu\s*\+\s*(psu|power)|แถม/u', $text) && !$isWholePc;
        $isMobileGpu = (bool)preg_match('/\b(laptop|notebook|mobile|laptop\s*gpu)\b|โน้ตบุ๊ก|โน๊ตบุ๊ค/u', $text);
        $hasMultiplePrices = count(array_unique(array_map('strval', $priceCandidates))) > 1;

        $warrantyMonths = null;
        if (preg_match('/ประกัน\s*(\d+)\s*(เดือน|mo|month)/u', $text, $match)) {
            $warrantyMonths = (int)$match[1];
        }

        $condition = $isDefective ? 'poor' : 'unknown';
        if (preg_match('/like new|สวย|ใหม่มาก|ใช้น้อย/u', $text)) {
            $condition = 'like_new';
        } elseif (preg_match('/good|ปกติ|ใช้งานได้ดี/u', $text)) {
            $condition = 'good';
        } elseif (preg_match('/fair|มีตำหนิ/u', $text)) {
            $condition = 'fair';
        }

        $confidence = $price !== null ? 0.95 : 0.45;
        if ($isWanted || $isWholePc || $isDeposit || $isDefective || $isMobileGpu) {
            $confidence = min($confidence, 0.80);
        }
        if ($isBundle || $hasMultiplePrices) {
            $confidence = min($confidence, 0.88);
        }

        $qualityFlags = [];
        if ($price === null) $qualityFlags[] = 'MISSING_PRICE';
        if ($hasMultiplePrices) $qualityFlags[] = 'MULTIPLE_PRICES';
        if ($isWanted) $qualityFlags[] = 'WANTED';
        if ($isDeposit) $qualityFlags[] = 'DEPOSIT';
        if ($isDefective) $qualityFlags[] = 'DEFECTIVE';
        if ($isWholePc) $qualityFlags[] = 'WHOLE_PC';
        if ($isBundle) $qualityFlags[] = 'BUNDLE';
        if ($isMobileGpu) $qualityFlags[] = 'MOBILE_GPU';
        if ($confidence < 0.90) $qualityFlags[] = 'WEAK_EVIDENCE';
        $variantContext = $this->variantContext($text);

        return new ExtractedObservation(
            $price,
            'THB',
            $isWholePc ? 'whole_pc' : ($isWanted ? 'wanted' : ($isBundle ? 'bundle' : 'single_item')),
            $condition,
            $warrantyMonths,
            str_contains($text, 'กล่อง') || str_contains($text, 'box') ? true : null,
            str_contains($text, 'ใบเสร็จ') || str_contains($text, 'receipt') ? true : null,
            $isWanted,
            $isDeposit,
            $isDefective,
            $isWholePc,
            $confidence,
            [
                'title' => $candidate->title,
                'price_text' => $candidate->priceText,
                'price_candidates' => $priceCandidates,
                'quality_flags' => array_values(array_unique($qualityFlags)),
                'variant_context' => $variantContext,
            ]
        );
    }

    /**
     * @return array<int,float>
     */
    private function extractPrices(string $text): array
    {
        $normalized = str_replace([',', '฿', 'บาท', 'thb'], '', mb_strtolower($text, 'UTF-8'));
        if (!preg_match_all('/(?<!\d)(\d{3,7})(?!\d)/', $normalized, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn(string $value): float => (float)$value, $matches[1])));
    }

    private function variantContext(string $text): ?string
    {
        if (preg_match('/\b(asus\s*tuf|asus\s*rog\s*strix|rog\s*strix|msi\s*gaming\s*x|gigabyte\s*gaming\s*oc|zotac|galax)\b/u', $text, $match)) {
            return trim($match[1]);
        }
        return null;
    }
}
