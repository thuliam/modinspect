<?php
declare(strict_types=1);

namespace App\Services\Listing;

use App\Data\SearchCandidate;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;

/**
 * LS-24 A1: reads a pasted listing (Facebook, Pantip, Kaidee ...) and says what it is, what the price is, which phrases to ask about, and where the price sits.
 * Deterministic PHP only: the extractor, resolver and price engine already in the project; no AI, no network, nothing stored. The text is never echoed back
 * with a phone number, e-mail or LINE id in it, and it is cut to MAX_CHARS. The price engine's own wording is used for the verdict; this class adds no new
 * judgement about price. A price it cannot read with confidence is left for the visitor to type (never guessed).
 */
final class ListingReader
{
    public const MAX_CHARS = 3000;

    /** the text with contact details masked and length capped: the only form of the paste that is ever shown or logged */
    public static function clean(string $raw): string
    {
        $t = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw));
        $t = mb_substr($t, 0, self::MAX_CHARS, 'UTF-8');
        $t = (string)preg_replace('/(?<!\d)(?:\+?66[\s.\-]?|0)[2-9]\d(?:[\s.\-]?\d){6,7}(?!\d)/', '[เบอร์ถูกซ่อน]', $t);
        $t = (string)preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[อีเมลถูกซ่อน]', $t);
        $t = (string)preg_replace('/(?:line\s*(?:id)?|ไลน์|ไลน)\s*[:：]?\s*@?[A-Za-z0-9._\-]{4,}/iu', '[ไลน์ถูกซ่อน]', $t);
        return trim($t);
    }

    /**
     * The asking price in the text. Looks for a number marked as a price (฿, บาท, "ราคา", "ขาย", thousands comma) before any bare number; numbers that belong to the
     * model ("3070"), a size or a speed (8GB, 3200MHz, 750W) are never taken. Several different marked prices, or none, mean "ask the visitor".
     * @param list<string> $modelNumbers digits that appear in the resolved model name
     * @return array{price:?float,confidence:string,candidates:list<float>}
     */
    public static function findPrice(string $text, array $modelNumbers = []): array
    {
        $flat = str_replace(["\u{00A0}"], ' ', $text);
        $marked = [];
        $patterns = [
            '/(?:ราคา|ขาย|เหลือ|ลดเหลือ|ลดราคา|เพียง|เพียงแค่|only|price)\s*[:=@]?\s*฿?\s*(\d{1,3}(?:,\d{3})+|\d{3,6})(?!\d)(?!\s*(?:gb|tb|mhz|ghz|w\b|mm|hz|fps|mb))/iu',
            '/฿\s*(\d{1,3}(?:,\d{3})+|\d{3,6})(?!\d)/u',
            '/(?<![\d,])(\d{1,3}(?:,\d{3})+|\d{3,6})\s*(?:บาท|฿|thb|\.-|บ\.)/iu',
        ];
        foreach ($patterns as $p) {
            if (preg_match_all($p, $flat, $m)) { foreach ($m[1] as $n) { $marked[(string)(float)str_replace(',', '', $n)] = (float)str_replace(',', '', $n); } }
        }
        $marked = array_values(array_filter($marked, static fn(float $v): bool => $v >= 100 && $v <= 10000000 && !in_array((string)(int)$v, $modelNumbers, true)));
        if (count($marked) === 1) { return ['price' => $marked[0], 'confidence' => 'high', 'candidates' => $marked]; }
        if (count($marked) > 1) { return ['price' => null, 'confidence' => 'ambiguous', 'candidates' => $marked]; }
        // no marked price: a single bare number that is not a spec or a model number is a weak guess, shown as such
        preg_match_all('/(?<![\d,.])(\d{4,6})(?![\d,.])(?!\s*(?:gb|tb|mhz|ghz|w\b|mm|hz|fps|mb))/iu', $flat, $m);
        $bare = array_values(array_unique(array_filter(array_map('floatval', $m[1]), static fn(float $v): bool => $v >= 500 && !in_array((string)(int)$v, $modelNumbers, true))));
        if (count($bare) === 1) { return ['price' => $bare[0], 'confidence' => 'low', 'candidates' => $bare]; }
        return ['price' => null, 'confidence' => $bare === [] ? 'none' : 'ambiguous', 'candidates' => $bare];
    }

    /** "ประกันเหลือ 6 เดือน", "ประกันศูนย์อีก 8 เดือน", "warranty 1 year" and similar; the extractor only knows "ประกัน 6 เดือน" written without a word in between */
    public static function warrantyMonths(string $text): ?int
    {
        if (preg_match('/ประกัน(?:ศูนย์|ร้าน|เหลือ|อีก|คงเหลือ|ไทย|\s)*\s*(\d{1,3})\s*(เดือน|ด\.|ปี|months?|mo\b|years?|yr)/iu', $text, $m) !== 1) { return null; }
        $n = (int)$m[1]; $unit = mb_strtolower($m[2], 'UTF-8');
        $months = in_array($unit, ['ปี', 'year', 'years', 'yr'], true) ? $n * 12 : $n;
        return $months >= 0 && $months <= 120 ? $months : null;
    }

    /**
     * @param array<int,array{id:int,full_name:string,aliases:array<int,string>}> $catalog
     * @param callable(int):?array $findProduct id => product row with q1, q3, median, accepted_observation_count (Product::find)
     * @param callable(array,float):array $evaluate PriceEngine::evaluate
     * @return array<string,mixed>
     */
    public static function read(string $raw, array $catalog, callable $findProduct, callable $evaluate, ?float $priceOverride = null): array
    {
        $text = self::clean($raw);
        $res = ['text' => $text, 'empty' => $text === '', 'product' => null, 'confidence' => 0.0, 'price' => null, 'price_confidence' => 'none', 'price_candidates' => [], 'facts' => [], 'flags' => [], 'questions' => [], 'notes' => [], 'verdict' => null];
        if ($text === '') { return $res; }

        $hit = (new ProductResolver($catalog))->resolve(mb_substr($text, 0, 400, 'UTF-8'));
        $product = $hit->productId !== null && $hit->confidence >= 0.7 ? $findProduct($hit->productId) : null;
        $res['confidence'] = $hit->confidence;
        $modelNumbers = [];
        if ($hit->matchedName !== null) { preg_match_all('/\d{3,5}/', $hit->matchedName, $mm); $modelNumbers = array_map(static fn($n) => (string)(int)$n, $mm[0]); }

        $pf = self::findPrice($text, $modelNumbers);
        $price = $priceOverride !== null && $priceOverride > 0 && $priceOverride <= 10000000 ? $priceOverride : $pf['price'];
        $res['price'] = $price; $res['price_confidence'] = $priceOverride !== null ? 'typed' : $pf['confidence']; $res['price_candidates'] = $pf['candidates'];

        // what the existing extractor reads (warranty, box, receipt, condition, kind of post)
        $x = (new RuleBasedListingExtractor())->extract(new SearchCandidate('paste', $text, $price !== null ? (string)(int)$price : null, null, date('Y-m-d H:i:s')));
        $warranty = $x->warrantyMonths ?? self::warrantyMonths($text);
        $res['facts'] = ['warranty_months' => $warranty, 'has_box' => $x->hasBox, 'has_receipt' => $x->hasReceipt, 'condition' => $x->conditionLevel, 'kind' => $x->listingType,
            'wanted' => $x->isWantedPost, 'deposit' => $x->isDeposit, 'defective' => $x->isDefective, 'whole_pc' => $x->isWholePc, 'quality_flags' => $x->raw['quality_flags'] ?? []];
        $res['flags'] = RedFlags::scan($text);

        if ($x->isWantedPost) { $res['notes'][] = 'ประกาศนี้ดูเป็นประกาศรับซื้อ ไม่ใช่ประกาศขาย จึงไม่เทียบราคากับตลาด'; }
        elseif ($x->isWholePc || $x->listingType === 'bundle') { $res['notes'][] = 'ประกาศนี้ขายเป็นชุดหรือทั้งเครื่อง ราคารวมของหลายชิ้นเทียบกับราคาชิ้นเดียวไม่ได้ ลองถามราคาแยกชิ้น'; }
        elseif ($x->isDeposit) { $res['notes'][] = 'ประกาศนี้พูดถึงมัดจำ ผ่อน หรือจอง ราคาที่เห็นอาจไม่ใช่ราคาเต็ม'; }
        elseif ($x->isDefective) { $res['notes'][] = 'ประกาศนี้ระบุว่าเสียหรือซ่อม ราคาจึงไม่เทียบกับของที่ใช้งานปกติ'; }

        if ($product !== null) {
            $res['product'] = ['id' => (int)$product['id'], 'name' => (string)$product['full_name'], 'slug' => (string)$product['slug'], 'q1' => $product['q1'] !== null ? (float)$product['q1'] : null, 'q3' => $product['q3'] !== null ? (float)$product['q3'] : null,
                'median' => isset($product['median']) && $product['median'] !== null ? (float)$product['median'] : null, 'samples' => (int)($product['accepted_observation_count'] ?? 0)];
            $comparable = !$x->isWantedPost && !$x->isWholePc && $x->listingType !== 'bundle' && !$x->isDeposit && !$x->isDefective;
            if ($price !== null && $comparable) { $res['verdict'] = $evaluate($product, $price); }
        }

        // what to ask: the questions of the flags, plus the facts the text leaves out
        foreach ($res['flags'] as $f) { $res['questions'][] = $f['ask']; }
        if ($warranty === null && !in_array('no_warranty', array_column($res['flags'], 'code'), true)) { $res['questions'][] = 'ถามว่ายังมีประกันเหลืออีกกี่เดือน และประกันอยู่กับใคร'; }
        if ($x->hasReceipt === null) { $res['questions'][] = 'ถามว่าซื้อมาจากไหนเมื่อไร และมีใบเสร็จหรือหลักฐานไหม'; }
        $res['questions'][] = 'ขอนัดดูของจริงและให้รันทดสอบสั้น ๆ ก่อนจ่าย';
        $res['questions'] = array_values(array_unique($res['questions']));
        return $res;
    }
}
