<?php
declare(strict_types=1);
namespace App\Services\Image;

/**
 * Thai text with PHP's GD. GD has no text shaping (no OpenType layout), so a tone mark that follows a vowel mark ("ที่", "อยู่", "ปู่") or precedes sara am
 * ("ต่ำ") is drawn where it overlaps the vowel or disappears. This helper never relies on GD for tone marks: the text without them goes out in one call, then
 * every tone mark is drawn on its own, centred on the consonant of its syllable, lifted above an upper vowel if there is one, and moved left of the sara am's
 * ring when "ำ" follows. Consonants and vowels GD draws correctly. Only used for the share card's fixed Thai phrases (product names are Latin). Static, no state.
 */
final class ThaiText
{
    /** upper vowels (and mai han-akat) that a tone mark has to clear */
    private const ABOVE = ['ิ', 'ี', 'ึ', 'ื', 'ั'];
    /** tone marks and thanthakhat */
    private const MARKS = ['่', '้', '๊', '๋', '์'];

    /**
     * @return array{plain:string,marks:list<array{mark:string,base:int,lift:?string,beforeAm:bool}>}
     *   base = index, in plain, of the consonant the mark belongs to; lift = the upper vowel above that consonant (or null)
     */
    public static function split(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $plain = []; $marks = [];
        foreach ($chars as $i => $ch) {
            if (!in_array($ch, self::MARKS, true)) { $plain[] = $ch; continue; }
            $base = count($plain) - 1;
            while ($base > 0 && preg_match('/^\p{Mn}$/u', $plain[$base])) { $base--; }   // back over vowel marks to the consonant
            if ($base < 0) { continue; }                                                  // a stray mark with nothing under it
            $lift = null;
            for ($k = $base + 1; $k < count($plain); $k++) { if (in_array($plain[$k], self::ABOVE, true)) { $lift = $plain[$k]; } }
            $marks[] = ['mark' => $ch, 'base' => $base, 'lift' => $lift, 'beforeAm' => ($chars[$i + 1] ?? '') === 'ำ'];
        }
        return ['plain' => implode('', $plain), 'marks' => $marks];
    }

    /** horizontal advance of a string: the right edge of the string followed by a marker glyph, minus the marker's own right edge */
    public static function advance(string $font, float $size, string $text): float
    {
        if ($text === '') { return 0.0; }
        $a = imagettfbbox($size, 0, $font, $text . 'ก'); $b = imagettfbbox($size, 0, $font, 'ก');
        return (float)($a[2] - $b[2]);
    }

    public static function width(string $font, float $size, string $text): float
    {
        return self::advance($font, $size, self::split($text)['plain']);
    }

    public static function draw($im, float $size, float $x, float $y, int $color, string $font, string $text): void
    {
        $parts = self::split($text);
        imagettftext($im, $size, 0, (int)round($x), (int)round($y), $color, $font, $parts['plain']);
        if (!$parts['marks']) { return; }
        $chars = preg_split('//u', $parts['plain'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts['marks'] as $m) {
            $before = implode('', array_slice($chars, 0, $m['base']));
            $a0 = self::advance($font, $size, $before);
            $center = $x + $a0 + (self::advance($font, $size, $before . $chars[$m['base']]) - $a0) / 2;
            if ($m['beforeAm']) { $center -= $size * 0.20; }              // the ring of sara am takes the right half; the mark sits left of it
            $bm = imagettfbbox($size, 0, $font, $m['mark']);
            $markX = $center - ($bm[2] - $bm[0]) / 2 - $bm[0] + ($m['lift'] !== null ? $size * 0.04 : 0.0);
            $markY = $y;
            if ($m['lift'] !== null) { $bv = imagettfbbox($size, 0, $font, $m['lift']); $markY = $y + $bv[7] - $size * 0.05 - $bm[1]; }   // just above the vowel's top edge
            imagettftext($im, $size, 0, (int)round($markX), (int)round($markY), $color, $font, $m['mark']);
        }
    }
}
