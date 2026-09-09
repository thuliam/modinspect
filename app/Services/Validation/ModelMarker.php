<?php
declare(strict_types=1);

namespace App\Services\Validation;

final class ModelMarker
{
    /**
     * @return array<int,string>
     */
    public static function all(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $markers = [];

        if (preg_match_all('/\b(?:nvidia\s*|geforce\s*)?(rtx|gtx)\s*([0-9]{3,4})\s*(ti\s*super|ti|super|s)?\b/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $suffix = str_replace(' ', '', strtolower((string)($match[3] ?? '')));
                if ($suffix === 's') {
                    $suffix = 'super';
                }
                $markers[] = strtolower((string)$match[1]) . (string)$match[2] . $suffix;
            }
        }

        if (preg_match_all('/\b(?:amd\s*|radeon\s*)?rx\s*([0-9]{3,4})\s*(xtx|xt)?\b/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $markers[] = 'rx' . (string)$match[1] . strtolower((string)($match[2] ?? ''));
            }
        }

        if (preg_match_all('/\b(?:ryzen\s*)?(?:r\s*[3579]\s*)?([0-9]{4})\s*(x3d|3d|x|g|f|k|kf|ks)?\b/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $number = (string)$match[1][0];
                $offset = (int)$match[0][1];
                $context = substr($text, max(0, $offset - 12), strlen($match[0][0]) + 24);
                if (preg_match('/\b(rtx|gtx|rx)\s*' . preg_quote($number, '/') . '/u', $context)) {
                    continue;
                }
                $suffix = self::cpuSuffix((string)($match[2][0] ?? ''));
                $markers[] = $number . $suffix;
            }
        }

        if (preg_match_all('/\b(?:core\s*)?i([3579])\s*-?\s*([0-9]{4,5})\s*(kf|ks|k|f)?\b/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $markers[] = 'i' . (string)$match[1] . (string)$match[2] . strtolower((string)($match[3] ?? ''));
            }
        }

        return array_values(array_unique(array_filter($markers)));
    }

    public static function primary(string $text): ?string
    {
        $markers = self::all($text);
        return $markers[0] ?? null;
    }

    public static function category(?string $marker): ?string
    {
        if ($marker === null) {
            return null;
        }
        if (preg_match('/^(rtx|gtx|rx)/', $marker)) {
            return 'gpu';
        }
        if (preg_match('/^([0-9]{4}|i[3579][0-9])/', $marker)) {
            return 'cpu';
        }
        return null;
    }

    private static function cpuSuffix(string $suffix): string
    {
        $suffix = strtolower(str_replace(' ', '', $suffix));
        return $suffix === '3d' ? 'x3d' : $suffix;
    }
}
