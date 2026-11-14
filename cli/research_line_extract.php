<?php
declare(strict_types=1);

/**
 * Owner research extractor: LINE "keep" export of copied Facebook sale posts -> sanitized item-level dataset.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/research_line_extract.php --file=<export.txt> --out=<dir>
 *
 * Safety contract (see docs/DATA_RESEARCH_POLICY.md):
 *  - Reads the raw export locally and NEVER prints message text. Output is aggregate counts only.
 *  - Messages that look like credentials/personal notes are dropped before any parsing.
 *  - Output rows carry no seller names, phone numbers, URLs, e-mails, LINE ids or locations.
 *  - Read-only: it does not write to any database. The Product Master catalog is only read for resolution.
 *  - Rows are labelled OWNER_COLLECTED_TEXT / asking price / no listing URL. They are NOT accepted observations.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Validation\ModelMarker;

$options = getopt('', ['file:', 'out:']);
$file = (string)($options['file'] ?? '');
$outDir = (string)($options['out'] ?? '');
if ($file === '' || !is_file($file) || $outDir === '') {
    fwrite(STDERR, "Usage: php cli/research_line_extract.php --file=<export.txt> --out=<dir>\n");
    exit(2);
}

global $config;
$host = (string)($config['db']['host'] ?? '');
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Refusing to run: set MODINSPECT_ENV_FILE=.env.testing so only the local catalog is read.\n");
    exit(2);
}
$db = Database::connection($config['db']);

// ---------------------------------------------------------------- catalog (all categories, for variant-safe resolution)
$catalog = [];
foreach ($db->query(
    "SELECT p.id,p.full_name,c.slug category,GROUP_CONCAT(pa.alias_text SEPARATOR '||') aliases
     FROM products p JOIN product_categories c ON c.id=p.category_id
     LEFT JOIN product_aliases pa ON pa.product_id=p.id
     WHERE p.is_active=1 GROUP BY p.id ORDER BY p.id"
) as $row) {
    $catalog[] = ['id' => (int)$row['id'], 'full_name' => (string)$row['full_name'], 'aliases' => $row['aliases'] ? explode('||', (string)$row['aliases']) : [], 'category' => (string)$row['category']];
}
$resolver = new ProductResolver(array_values(array_filter($catalog, static fn(array $p): bool => in_array($p['category'], ['cpu', 'gpu'], true))));

// ---------------------------------------------------------------- 1. parse export into messages
$raw = preg_replace('/^\xEF\xBB\xBF/', '', (string)file_get_contents($file)) ?? '';
$messages = [];
$date = null;
$current = null;
foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
    if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2}) [A-Za-z]+$/', $line, $m)) {
        $current && $messages[] = $current;
        $current = null;
        $date = "{$m[1]}-{$m[2]}-{$m[3]}";
        continue;
    }
    if (preg_match('/^(\d{2}):(\d{2}) (#iaM|ThuliaM #Dev) (.*)$/u', $line, $m)) {
        $current && $messages[] = $current;
        $current = ['date' => $date, 'sender' => $m[3], 'text' => $m[4]];
        continue;
    }
    if ($current) {
        $current['text'] .= "\n" . $line;
    }
}
$current && $messages[] = $current;

// ---------------------------------------------------------------- helpers
const HW_WORDS = '/rtx|gtx|\brx ?\d{3,4}|radeon|geforce|ryzen|\br[3579][ -]?\d{4}|core ?i[3579]|\bi[3579][ -]?\d{4,5}|xeon|ddr[345]|\bram\b|แรม|เเรม|\bssd\b|nvme|m\.2|mainboard|motherboard|เมนบอร์ด|การ์ดจอ|\bpsu\b|power supply|corsair rm|seasonic/iu';
const SENSITIVE = '/[\w.+-]+@[\w-]+\.[\w.]+|password|passwd|รหัสผ่าน|api[ _-]?key|secret|token|username|\bpass\b/iu';

function scrub(string $text): string
{
    $text = preg_replace('#https?://\S+|www\.\S+#iu', ' ', $text) ?? $text;
    $text = preg_replace('/(?<!\d)0\d{1,2}[- ]?\d{3}[- ]?\d{4}(?!\d)/u', ' ', $text) ?? $text;
    $text = preg_replace('/(?:ไลน์|line|ไอดี|id|โทร|tel|เบอร์|facebook|fb|inbox|แชท)\s*[:：@]?\s*[\w.@-]{3,}/iu', ' ', $text) ?? $text;
    $text = preg_replace('/@[\w.]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/ร้าน\s*\S+/u', ' ', $text) ?? $text;
    return $text;
}

/** @return list<array{value:int,kind:string}> */
function explicit_prices(string $line): array
{
    $found = [];
    $take = static function (string $s, string $kind) use (&$found): void {
        $v = (int)str_replace(',', '', $s);
        if ($v >= 300 && $v <= 300000) {
            $found[$v . ':' . $kind] = ['value' => $v, 'kind' => $kind];
        }
    };
    $unitBad = '(?!\s*(?:mhz|gb\b|g\b|w\b|watt|tb|hz|mm|v\b|ชิ้น|วัน|เดือน|ปี|%))';
    if (preg_match_all('/(?:ราคา|ขาย|เหมา|price|เหลือ|@)\s*[:：]?\s*(\d{1,3}(?:,\d{3})+|\d{3,6})(?![\d,])' . $unitBad . '/iu', $line, $m)) {
        foreach ($m[1] as $s) { $take($s, 'marker'); }
    }
    if (preg_match_all('/(?<![A-Za-z\d,.\/-])(\d{1,3}(?:,\d{3})+|\d{3,6})\s*(?:บาท|฿|thb|\.-|-\.|บ\.|-(?![\w])|รวมส่ง|ส่งฟรี|\+ส่ง)/iu', $line, $m)) {
        foreach ($m[1] as $s) { $take($s, 'unit'); }
    }
    if (preg_match_all('/(?<![A-Za-z\d,.\/-])(\d{1,3}(?:,\d{3})+)(?![\d,])' . $unitBad . '/u', $line, $m)) {
        foreach ($m[1] as $s) { $take($s, 'comma'); }
    }
    // keep the strongest kind per value
    $best = [];
    $rank = ['marker' => 3, 'unit' => 2, 'comma' => 1];
    foreach ($found as $f) {
        if (!isset($best[$f['value']]) || $rank[$f['kind']] > $rank[$best[$f['value']]['kind']]) {
            $best[$f['value']] = $f;
        }
    }
    return array_values($best);
}

function category_of(string $t): string
{
    if (preg_match('/rtx|gtx|\brx ?\d{3,4}|radeon|geforce|การ์ดจอ|\barc ?[ab]\d{3}/iu', $t)) { return 'gpu'; }
    if (preg_match('/ryzen|\br[3579][ -]?\d{4}|core ?i[3579]|\bi[3579][ -]?\d{4,5}|xeon|ซีพียู|\bcpu\b/iu', $t)) { return 'cpu'; }
    if (preg_match('/nvme|m\.2|\bssd\b|\bhdd\b|sata/iu', $t)) { return 'storage'; }
    if (preg_match('/ddr[345]|\bram\b|แรม|เเรม/iu', $t)) { return 'ram'; }
    if (preg_match('/mainboard|motherboard|เมนบอร์ด|\b[abhxz]\d{3}m?\b/iu', $t)) { return 'motherboard'; }
    if (preg_match('/\bpsu\b|power supply|พาวเวอร์|corsair rm|seasonic|\d{3,4}\s?w\b/iu', $t)) { return 'psu'; }
    return 'other';
}

/** Normalised model key; suffix kept explicit so variants are never merged. */
function model_key(string $t, string $category): array
{
    $flags = [];
    $s = mb_strtolower($t, 'UTF-8');
    if ($category === 'gpu') {
        if (preg_match('/\b(rtx|gtx|arc)\s*-?\s*(\d{3,4})\s*(ti\s*super|super|ti|xtx|xt|gre|s)?\b/u', $s, $m)) {
            $suffix = trim((string)($m[3] ?? ''));
            if ($suffix === 's') { $suffix = 'super'; $flags[] = 'SHORTHAND_SUFFIX_S'; }
            return [trim("{$m[1]} {$m[2]} " . preg_replace('/\s+/', ' ', $suffix)), $flags];
        }
        if (preg_match('/\brx\s*-?\s*(\d{3,4})\s*(xtx|xt|gre)?\b/u', $s, $m)) {
            return [trim("rx {$m[1]} " . ($m[2] ?? '')), $flags];
        }
        if (preg_match('/radeon\s*(?:rx\s*)?(\d{4})\s*(xtx|xt|gre)?/u', $s, $m)) {
            return [trim("rx {$m[1]} " . ($m[2] ?? '')), $flags];
        }
        // Bare model such as "การ์ดจอ 5060 8G" or "galax 3080": NVIDIA-style number, family prefix inferred (flagged).
        if (preg_match('/(?<![\d.,\/])((?:10|16|20|30|40|50)(?:50|60|70|80|90))\s*(ti\s*super|super|ti|s)?(?![\d])/u', $s, $m)) {
            $suffix = trim((string)($m[2] ?? ''));
            if ($suffix === 's') { $suffix = 'super'; $flags[] = 'SHORTHAND_SUFFIX_S'; }
            $flags[] = 'PREFIX_INFERRED';
            return [trim(((int)$m[1] < 2000 ? 'gtx' : 'rtx') . " {$m[1]} " . preg_replace('/\s+/', ' ', $suffix)), $flags];
        }
    }
    if ($category === 'cpu') {
        if (preg_match('/(?:ryzen\s*[3579]\s*|\br[3579]\s*-?\s*)(\d{4,5})\s*(x3d|xt|x|g|f|gt)?\b/u', $s, $m)) {
            return [trim("ryzen {$m[1]}" . ($m[2] ?? '')), $flags];
        }
        if (preg_match('/\b(?:core\s*)?(i[3579])\s*-?\s*(\d{4,5}[a-z]{0,2})\b/u', $s, $m)) {
            return [trim("{$m[1]}-{$m[2]}"), $flags];
        }
    }
    return [null, $flags];
}

function vram_of(string $t): ?int
{
    return preg_match('/(?<![\d.])(\d{1,2})\s*g(?:b)?(?![a-z\d])/iu', $t, $m) && (int)$m[1] >= 2 && (int)$m[1] <= 48 ? (int)$m[1] : null;
}

function warranty_of(string $t): array
{
    $end = null;
    $kind = 'unspecified';
    if (preg_match('/ประกัน[^\d\n]{0,30}?(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}|\d{1,2}[\/.-]\d{4}|\d{4}[\/.-]\d{1,2}(?:[\/.-]\d{1,2})?|\d{1,2}[\/.-]\d{2}(?!\d))/u', $t, $m)) {
        $p = preg_split('/[\/.-]/', $m[1]) ?: [];
        $norm = static function (int $y): ?int {
            if ($y < 100) { $y = $y >= 60 ? 2500 + $y - 543 : 2000 + $y; } // 2-digit: 60-99 = Buddhist era, else Gregorian
            elseif ($y >= 2400) { $y -= 543; }
            return $y >= 2020 && $y <= 2040 ? $y : null;
        };
        if (count($p) === 3) {
            if (strlen($p[0]) === 4) { $y = $norm((int)$p[0]); $mo = (int)$p[1]; $d = (int)$p[2]; } else { $y = $norm((int)$p[2]); $mo = (int)$p[1]; $d = (int)$p[0]; }
            if ($y !== null && $mo >= 1 && $mo <= 12) { $end = sprintf('%04d-%02d-%02d', $y, $mo, max(1, min(31, $d))); }
        } elseif (count($p) === 2) {
            if (strlen($p[0]) === 4) { $y = $norm((int)$p[0]); $mo = (int)$p[1]; } else { $y = $norm((int)$p[1]); $mo = (int)$p[0]; }
            if ($y !== null && $mo >= 1 && $mo <= 12) { $end = sprintf('%04d-%02d', $y, $mo); }
        }
    }
    if ($end !== null) { $kind = 'dated'; }
    if (preg_match('/ประกันใจ|ปกสากล|ประกัน\s*7\s*วัน|7\s*วัน/u', $t)) { $kind = $end ? 'dated' : 'seller_7_day'; }
    // "ประกันหมด 01/2573" means the warranty ENDS on that date; only treat it as none/expired when no date follows.
    if ($end === null && preg_match('/ไม่มีประกัน|หมดประกัน|ประกันหมด|ไร้ประกัน|no warranty/iu', $t)) { $kind = 'none_or_expired'; }
    return [$end, $kind];
}

function warranty_provider(string $t): ?string
{
    return preg_match('/\b(jib|advice|ascenti|synnex|synex|svoa|achieva|doracool|banana|alpha|ingram|lt)\b/iu', $t, $m) ? strtolower($m[1]) : null;
}

function box_of(string $t): ?string
{
    if (preg_match('/ไม่มีกล่อง|ตัวเปล่า|มีแต่ตัว|มีเเต่ตัว|แต่ตัว|เเต่ตัว|no box/iu', $t)) { return 'no'; }
    if (preg_match('/ครบกล่อง|มีกล่อง|กล่องครบ|พร้อมกล่อง|box/iu', $t)) { return 'yes'; }
    return null;
}

/** Distinct hardware kinds mentioned (cpu, gpu, ram, storage, motherboard); 2+ means a bundle, not a single part. */
function kinds_present(string $t): array
{
    $k = [];
    if (preg_match('/rtx|gtx|\brx ?\d{3,4}|radeon|geforce|การ์ดจอ/iu', $t)) { $k['gpu'] = 1; }
    if (preg_match('/ryzen|\br[3579][ -]?\d{4}|core ?i[3579]|\bi[3579][ -]?\d{4,5}|\bcpu\b|ซีพียู/iu', $t)) { $k['cpu'] = 1; }
    if (preg_match('/ddr[345]|\bram\b|แรม|เเรม/iu', $t)) { $k['ram'] = 1; }
    if (preg_match('/nvme|m\.2|\bssd\b|\bhdd\b/iu', $t)) { $k['storage'] = 1; }
    if (preg_match('/mainboard|motherboard|เมนบอร์ด|\bmb\b|\b[abhxz]\d{3}m?\b/iu', $t)) { $k['motherboard'] = 1; }
    return array_keys($k);
}

function condition_of(string $t): string
{
    if (preg_match('/มือ\s*1\b|มือหนึ่ง|ยังไม่แกะ|ซีล|sealed|brand new|\bnew\b|ใหม่(?!\s*ๆ)/iu', $t) && !preg_match('/มือ\s*2|มือสอง|used|สภาพ/iu', $t)) { return 'new_sealed'; }
    if (preg_match('/มือ\s*2|มือสอง|used|สภาพ|ใช้งาน|เทส/iu', $t)) { return 'used'; }
    return 'unknown';
}

function clean_title(string $t): string
{
    $t = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', ' ', $t) ?? $t;
    $t = preg_replace('/(?:ราคา|ขาย)\s*[:：]?\s*[\d,]+\s*(?:บาท|฿|-)?/u', ' ', $t) ?? $t;
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    $t = preg_replace('/^\s*\d{1,2}\s*[.)\/]\s*/u', '', trim($t)) ?? $t; // list numbering is positional, not part of the listing
    $t = trim($t, " \t-–—•*/.:|");
    return mb_substr($t, 0, 140, 'UTF-8');
}

// ---------------------------------------------------------------- 2. segment messages into item groups and rows
$stats = ['messages_total' => count($messages), 'dropped_sensitive' => 0, 'not_hardware' => 0, 'hardware_messages' => 0, 'wanted_or_buying' => 0, 'rows' => 0, 'rows_without_price' => 0];
$rows = [];
foreach ($messages as $mi => $message) {
    if ($message['sender'] !== '#iaM') { continue; }
    $text = (string)$message['text'];
    if (preg_match(SENSITIVE, $text)) { $stats['dropped_sensitive']++; continue; }
    if (!preg_match(HW_WORDS, $text) || !preg_match('/ราคา|ขาย|บาท|฿|\d,\d{3}/u', $text)) { $stats['not_hardware']++; continue; }
    $stats['hardware_messages']++;
    $text = scrub($text);

    // sequential numbered items: 1, 2, 3 ... (works for "1." lines and inline "1/ ... 2/ ...")
    $positions = [];
    $expected = 1;
    $offset = 0;
    while ($expected <= 60 && preg_match('/(?<![\d.,\/:-])' . $expected . '\s*[.)\/]\s*(?=[\p{L}])/u', $text, $mm, PREG_OFFSET_CAPTURE, $offset)) {
        $positions[] = $mm[0][1];
        $offset = $mm[0][1] + strlen($mm[0][0]);
        $expected++;
    }
    $items = [];
    if (count($positions) >= 2) {
        foreach ($positions as $k => $pos) {
            $end = $positions[$k + 1] ?? strlen($text);
            $items[] = substr($text, $pos, $end - $pos);
        }
    } else {
        $items = [$text];
    }

    foreach ($items as $ii => $item) {
        $item = (string)preg_replace('/\n\s*(?:-{3,}|\*{3,}|={3,}|_{3,})\s*(?:\n|$).*/su', '', $item); // drop footer after a separator
        $buffer = [];
        $emitted = [];
        $lines = preg_split('/\n/', $item) ?: [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || preg_match('/^[-*=_ ]{3,}$/u', $trim)) { continue; }
            $prices = explicit_prices($trim);
            $isContext = (bool)preg_match('/^[-•*\s]*(ประกัน|warranty|มีกล่อง|ตัวเปล่า|สภาพ|เทส|ผลเทส|ปกสากล|ส่ง|นัด|รับ|ตจว)/iu', $trim) && !preg_match(HW_WORDS, $trim);
            if ($prices === []) {
                if ($isContext && $emitted) { $emitted[count($emitted) - 1]['context'] .= ' ' . $trim; continue; }
                $buffer[] = $trim;
                continue;
            }
            $block = implode(' ', array_merge($buffer, [$trim]));
            $buffer = [];
            foreach ($prices as $price) {
                $emitted[] = ['context' => $block, 'price' => $price, 'bundle_line' => (bool)preg_match('/เหมา|ยกชุด|ทั้งชุด/u', $trim)];
            }
        }
        foreach ($emitted as $e) {
            $block = $e['context'];
            if (!preg_match(HW_WORDS, $block)) { continue; }
            $category = category_of($block);
            [$key, $flags] = model_key($block, $category);
            [$wEnd, $wKind] = warranty_of($block);
            $kinds = kinds_present($block);
            $listingType = ($e['bundle_line'] || count($kinds) >= 2 || (preg_match('/(?:\+|ชุด|เหมา|bundle|combo)/iu', $block) && preg_match_all(HW_WORDS, $block) > 1)) ? 'bundle' : 'single_item';
            $wanted = (bool)preg_match('/รับซื้อ|รับเทิร์น|รับเทรินการ์ด|หา\s+.+\s*มาเทิร์น|ต้องการซื้อ|ประกาศหา|wtb|แลก/iu', $block);
            $defective = (bool)preg_match('/ซ่อม|เสีย|อะไหล่|ไม่ติด|มีปัญหา|ลายกราฟิก|พัง|ตาย\b|ไม่แสดงภาพ|งานช่าง|ภาพลาย|ลายภาพ|รอยร้าว/iu', $block);
            $resolution = $category === 'gpu' || $category === 'cpu' ? $resolver->resolve($block) : null;
            // Same guard the validator applies: a nearby model (3060 vs 3060 Ti, 4060 vs 4060 Ti) is NOT a match.
            if ($resolution !== null && $resolution->productId !== null && ModelMarker::primary($block) !== ModelMarker::primary((string)$resolution->matchedName)) {
                $flags[] = 'NEARBY_MASTER_MODEL_REJECTED';
                $resolution = null;
            }
            $rows[] = [
                'note_date' => $message['date'],
                'category' => $category,
                'title' => clean_title($block),
                'model_key' => $key,
                'vram_gb' => $category === 'gpu' ? vram_of($block) : null,
                'variant_key' => $key === null ? null : ($category === 'gpu' ? $key . ' ' . ((vram_of($block) ?? 0) > 0 ? vram_of($block) . 'gb' : '?gb') : $key),
                'asking_price_thb' => $e['price']['value'],
                'price_evidence' => $e['price']['kind'],
                'shipping_included' => preg_match('/รวมส่ง|ส่งฟรี/u', $block) ? 'yes' : null,
                'listing_type' => $listingType,
                'bundle_total_line' => $e['bundle_line'] ? 'yes' : 'no',
                'box' => box_of($block),
                'condition_class' => condition_of($block),
                'warranty_kind' => $wKind,
                'warranty_end' => $wEnd,
                'warranty_provider' => warranty_provider($block),
                'wanted_or_trade' => $wanted ? 'yes' : 'no',
                'defective_hint' => $defective ? 'yes' : 'no',
                'master_product_id' => $resolution?->productId,
                'master_match' => $resolution?->productId ? $resolution->matchedName : null,
                'master_confidence' => $resolution?->productId ? $resolution->confidence : null,
                'flags' => implode('|', array_filter(array_merge($flags, ($wEnd !== null && substr($wEnd, 0, 7) < substr((string)$message['date'], 0, 7)) ? ['WARRANTY_ENDED_BEFORE_NOTE'] : []))),
                'msg' => $mi,
                'item' => $ii + 1,
            ];
        }
    }
}

// ---------------------------------------------------------------- 3. de-duplicate (same model + price + warranty + title stem across posts)
$seen = [];
foreach ($rows as $i => &$r) {
    $stem = mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($r['title'], 'UTF-8')) ?? '', 0, 40, 'UTF-8');
    $hash = sha1(($r['model_key'] ?? $r['category']) . '|' . $r['asking_price_thb'] . '|' . ($r['warranty_end'] ?? '') . '|' . $stem);
    $r['dedupe_key'] = substr($hash, 0, 12);
    $r['duplicate_of_row'] = $seen[$hash] ?? null;
    $seen[$hash] ??= $i + 1;
    $r['row_id'] = 'LINEFB-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
}
unset($r);

// ---------------------------------------------------------------- 3b. price anomaly flags (never delete rows; flag them)
$groups = [];
foreach ($rows as $r) {
    if ($r['duplicate_of_row'] === null && $r['listing_type'] === 'single_item' && $r['bundle_total_line'] === 'no' && $r['variant_key'] !== null && $r['condition_class'] !== 'new_sealed') {
        $groups[$r['variant_key']][] = $r['asking_price_thb'];
    }
}
$medians = [];
foreach ($groups as $k => $prices) {
    sort($prices);
    if (count($prices) >= 5) { $medians[$k] = $prices[intdiv(count($prices), 2)]; }
}
foreach ($rows as &$r) {
    $reasons = [];
    $digits = preg_replace('/\D/', '', (string)$r['model_key']) ?? '';
    if ($digits !== '' && (string)$r['asking_price_thb'] === $digits) { $reasons[] = 'PRICE_EQUALS_MODEL_NUMBER'; }
    if (isset($medians[$r['variant_key']])) {
        $ratio = $r['asking_price_thb'] / $medians[$r['variant_key']];
        if ($ratio < 0.5) { $reasons[] = 'PRICE_BELOW_HALF_MODEL_MEDIAN'; }
        if ($ratio > 2.0) { $reasons[] = 'PRICE_ABOVE_DOUBLE_MODEL_MEDIAN'; }
    }
    $r['price_anomaly'] = implode('|', $reasons);
}
unset($r);

// ---------------------------------------------------------------- 4. write sanitized outputs + aggregate report
if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) {
    fwrite(STDERR, "Cannot create output directory\n");
    exit(1);
}
$columns = ['row_id', 'note_date', 'category', 'model_key', 'variant_key', 'vram_gb', 'title', 'asking_price_thb', 'price_evidence', 'shipping_included', 'listing_type', 'bundle_total_line', 'box', 'condition_class', 'price_anomaly', 'warranty_kind', 'warranty_end', 'warranty_provider', 'wanted_or_trade', 'defective_hint', 'master_product_id', 'master_match', 'master_confidence', 'flags', 'dedupe_key', 'duplicate_of_row'];
$fh = fopen($outDir . '/research_listings_sanitized.csv', 'wb');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array_merge($columns, ['source_label', 'provenance_grade', 'price_type']), ',', '"', '');
foreach ($rows as $r) {
    $line = [];
    foreach ($columns as $c) { $line[] = $r[$c] ?? ''; }
    fputcsv($fh, array_merge($line, ['owner_collected_facebook_text', 'OWNER_TEXT_NO_URL', 'asking']), ',', '"', '');
}
fclose($fh);

$usable = array_values(array_filter($rows, static fn(array $r): bool => $r['duplicate_of_row'] === null && $r['listing_type'] === 'single_item' && $r['bundle_total_line'] === 'no' && $r['wanted_or_trade'] === 'no' && $r['defective_hint'] === 'no' && $r['price_anomaly'] === '' && $r['condition_class'] !== 'new_sealed'));
$by = static function (array $set, string $field): array {
    $out = [];
    foreach ($set as $r) { $k = (string)($r[$field] ?? '(none)'); $out[$k] = ($out[$k] ?? 0) + 1; }
    arsort($out);
    return $out;
};
$models = [];
foreach ($usable as $r) {
    if ($r['variant_key'] === null) { continue; }
    $models[$r['variant_key']][] = $r['asking_price_thb'];
}
$modelStats = [];
foreach ($models as $k => $prices) {
    sort($prices);
    $n = count($prices);
    $modelStats[$k] = ['n' => $n, 'min' => $prices[0], 'median' => $prices[intdiv($n, 2)], 'max' => $prices[$n - 1]];
}
uasort($modelStats, static fn(array $a, array $b): int => $b['n'] <=> $a['n']);

$stats['rows'] = count($rows);
$report = [
    'generated_for' => 'owner research; asking prices from copied Facebook posts; no listing URLs; NOT accepted observations',
    'counts' => $stats,
    'rows_by_category' => $by($rows, 'category'),
    'rows_by_month' => $by(array_map(static fn(array $r): array => ['m' => substr((string)$r['note_date'], 0, 7)], $rows), 'm'),
    'duplicates' => count(array_filter($rows, static fn(array $r): bool => $r['duplicate_of_row'] !== null)),
    'bundle_or_bundle_total' => count(array_filter($rows, static fn(array $r): bool => $r['listing_type'] === 'bundle' || $r['bundle_total_line'] === 'yes')),
    'wanted_or_trade' => count(array_filter($rows, static fn(array $r): bool => $r['wanted_or_trade'] === 'yes')),
    'defective_hint' => count(array_filter($rows, static fn(array $r): bool => $r['defective_hint'] === 'yes')),
    'new_sealed_rows' => count(array_filter($rows, static fn(array $r): bool => $r['condition_class'] === 'new_sealed')),
    'price_anomaly_rows' => count(array_filter($rows, static fn(array $r): bool => $r['price_anomaly'] !== '')),
    'usable_single_deduped' => count($usable),
    'usable_by_category' => $by($usable, 'category'),
    'warranty_kind_usable' => $by($usable, 'warranty_kind'),
    'box_usable' => $by($usable, 'box'),
    'price_evidence_usable' => $by($usable, 'price_evidence'),
    'gpu_cpu_resolved_to_master' => count(array_filter($usable, static fn(array $r): bool => $r['master_product_id'] !== null)),
    'gpu_cpu_usable' => count(array_filter($usable, static fn(array $r): bool => in_array($r['category'], ['gpu', 'cpu'], true))),
    'gpu_cpu_without_model_key' => count(array_filter($usable, static fn(array $r): bool => in_array($r['category'], ['gpu', 'cpu'], true) && $r['model_key'] === null)),
    'model_price_stats_usable_top40' => array_slice($modelStats, 0, 40, true),
    'distinct_models_usable' => count($modelStats),
    'models_with_5plus_samples' => count(array_filter($modelStats, static fn(array $s): bool => $s['n'] >= 5)),
    'master_products_total' => count($catalog),
];
file_put_contents($outDir . '/research_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode(['rows' => $stats['rows'], 'usable_single_deduped' => count($usable), 'out' => 'research_listings_sanitized.csv + research_report.json'], JSON_UNESCAPED_SLASHES) . "\n";
