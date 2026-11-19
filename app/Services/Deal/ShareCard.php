<?php
declare(strict_types=1);
namespace App\Services\Deal;

use App\Services\Image\ThaiText;

/**
 * The picture of a Deal Checker result that a visitor can save or that a chat app shows when the result link is shared (1200 x 630, the Open Graph size).
 * Drawn with PHP's GD in the brand colours (CI canvas, lime) and the self-hosted Prompt font. It carries only what the shared result page already shows:
 * the model name, the price the visitor found, the verdict wording, and the reference range and median. Never the condition, warranty, receipt, a name,
 * an address or anything about the visitor. The wording comes from the caller (PriceEngine / DealExplanation), so the card can never disagree with the page.
 */
final class ShareCard
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /** tone => [text colour, tint] in RGB */
    private const TONES = ['ok' => [[34, 197, 94], [18, 52, 36]], 'warn' => [[250, 204, 21], [58, 52, 18]], 'bad' => [[239, 68, 68], [60, 24, 28]], 'neutral' => [[148, 163, 184], [30, 36, 50]]];

    /** the card needs GD with FreeType and the two Prompt files; without them the pages simply do not offer it (no error, no button) */
    public static function available(string $fontDir): bool
    {
        return function_exists('imagettftext') && function_exists('imagepng') && is_file($fontDir . '/Prompt-Regular.ttf') && is_file($fontDir . '/Prompt-SemiBold.ttf');
    }

    /**
     * @param array{name:string,price:float,label:string,tone:string,range:?array{0:float,1:float},median:?float,caption?:string} $c (caption: the small line above the price, default "the price you found"; LS-24 C1 uses it for the product card)
     * @return string PNG bytes
     */
    public static function render(array $c, string $fontDir, string $logoFile): string
    {
        $regular = $fontDir . '/Prompt-Regular.ttf'; $semi = $fontDir . '/Prompt-SemiBold.ttf';
        if (!is_file($regular) || !is_file($semi)) { throw new \RuntimeException('SHARE_CARD_FONT_MISSING'); }
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($im, true); imageantialias($im, true);
        $col = static fn(array $rgb): int => (int)imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        $canvas = $col([10, 13, 20]); $panel = $col([17, 22, 34]); $line = $col([39, 48, 68]); $text = $col([241, 245, 249]); $muted = $col([148, 163, 184]); $lime = $col([187, 242, 70]);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $canvas);
        self::roundRect($im, 36, 36, self::WIDTH - 37, self::HEIGHT - 37, 28, $line);
        self::roundRect($im, 38, 38, self::WIDTH - 39, self::HEIGHT - 39, 26, $panel);

        // brand
        if (is_file($logoFile) && ($logo = @imagecreatefrompng($logoFile))) {
            $lw = 280; $lh = (int)round(imagesy($logo) * $lw / imagesx($logo));
            imagecopyresampled($im, $logo, 80, 70, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        }
        $tag = 'เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง';
        ThaiText::draw($im, 22, self::WIDTH - 80 - ThaiText::width($regular, 22, $tag), 112, $muted, $regular, $tag);

        // model name: up to two lines, shrunk if still too wide
        $maxW = self::WIDTH - 160; $size = 46; $lines = self::wrap((string)$c['name'], $semi, $size, $maxW);
        while (count($lines) > 2 && $size > 30) { $size -= 2; $lines = self::wrap((string)$c['name'], $semi, $size, $maxW); }
        if (count($lines) > 2) { $lines = array_slice($lines, 0, 2); $lines[1] = rtrim($lines[1], ' ,-') . '…'; }
        $y = 210; foreach ($lines as $l) { ThaiText::draw($im, $size, 80, $y, $text, $semi, $l); $y += (int)round($size * 1.25); }

        // the price found
        ThaiText::draw($im, 26, 80, 292, $muted, $regular, (string)($c['caption'] ?? 'ราคาที่คุณพบ'));
        $price = '฿' . number_format((float)$c['price']);
        ThaiText::draw($im, 104, 80, 436, $lime, $semi, $price);

        // verdict pill, to the right of the price
        [$fg, $tint] = self::TONES[$c['tone']] ?? self::TONES['neutral'];
        $label = (string)$c['label']; $ps = 34; $pw = (int)ThaiText::width($semi, $ps, $label) + 72; $px = 80 + (int)ThaiText::width($semi, 104, $price) + 56;
        if ($px + $pw > self::WIDTH - 80) { $px = 80; $py = 456; } else { $py = 346; }   // below the price when it does not fit beside it
        self::roundRect($im, $px, $py, $px + $pw, $py + 70, 35, $col($fg));
        self::roundRect($im, $px + 3, $py + 3, $px + $pw - 3, $py + 67, 32, $col($tint));
        ThaiText::draw($im, $ps, $px + 36, $py + 49, $col($fg), $semi, $label);

        // the reference
        if ($c['range'] !== null) {
            $range = 'ช่วงราคาที่พบ ฿' . number_format((float)$c['range'][0]) . ' – ฿' . number_format((float)$c['range'][1]) . (($c['median'] ?? null) ? '   ·   ราคากลาง ฿' . number_format((float)$c['median']) : '');
        } else {
            $range = 'ยังไม่มีช่วงราคาตลาดของรุ่นนี้';
        }
        ThaiText::draw($im, 30, 80, 524, $text, $regular, $range);
        ThaiText::draw($im, 21, 80, 570, $muted, $regular, 'ราคาประกาศที่ผ่านการตรวจ ไม่ใช่ราคาปิดดีล และไม่ได้ประเมินสภาพสินค้า');

        ob_start(); imagepng($im, null, 6); $png = (string)ob_get_clean(); imagedestroy($im);
        return $png;
    }

    /** @return list<string> greedy word wrap to a pixel width (words are split on spaces; Latin model names have them) */
    private static function wrap(string $s, string $font, int $size, int $maxW): array
    {
        $lines = []; $cur = '';
        foreach (preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            if ($cur !== '' && ThaiText::width($font, $size, $try) > $maxW) { $lines[] = $cur; $cur = $w; } else { $cur = $try; }
        }
        if ($cur !== '') { $lines[] = $cur; }
        return $lines ?: [''];
    }

    /** filled rounded rectangle (GD has none): the middle bands plus four corner discs */
    private static function roundRect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) { imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color); }
    }
}
