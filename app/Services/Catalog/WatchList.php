<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-25 C5: a visitor's list of models to keep an eye on. It lives only in the visitor's own cookie ("slug~median" pairs, at most MAX), so there is no account, no
 * server-side record and nothing personal. The median stored is the one shown when the model was added; the list page compares it with today's median and says
 * the direction only. Pure: parse/toggle/serialize take and return strings and arrays.
 */
final class WatchList
{
    public const MAX = 20;
    public const COOKIE = 'mi_watch';

    /** @return list<array{slug:string,median:?float}> in the order added; anything malformed is dropped */
    public static function parse(?string $cookie): array
    {
        $out = [];
        foreach (explode(',', (string)$cookie) as $part) {
            if (!preg_match('/^([a-z0-9][a-z0-9-]{0,120})(?:~(\d{1,9}))?$/', trim($part), $m)) { continue; }
            if (isset($out[$m[1]])) { continue; }
            $out[$m[1]] = ['slug' => $m[1], 'median' => isset($m[2]) && $m[2] !== '' ? (float)$m[2] : null];
        }
        return array_slice(array_values($out), 0, self::MAX);
    }

    public static function has(array $list, string $slug): bool
    {
        foreach ($list as $w) { if ($w['slug'] === $slug) { return true; } }
        return false;
    }

    /** @return array{list:list<array{slug:string,median:?float}>,added:bool,full:bool} remove when present; add (newest last) when absent and there is room */
    public static function toggle(array $list, string $slug, ?float $medianNow): array
    {
        if (self::has($list, $slug)) { return ['list' => array_values(array_filter($list, static fn(array $w): bool => $w['slug'] !== $slug)), 'added' => false, 'full' => false]; }
        if (count($list) >= self::MAX) { return ['list' => $list, 'added' => false, 'full' => true]; }
        $list[] = ['slug' => $slug, 'median' => $medianNow !== null && $medianNow > 0 ? (float)round($medianNow) : null];
        return ['list' => $list, 'added' => true, 'full' => false];
    }

    public static function serialize(array $list): string
    {
        return implode(',', array_map(static fn(array $w): string => $w['slug'] . ($w['median'] !== null ? '~' . (int)$w['median'] : ''), $list));
    }

    /** @return array{direction:string,diff:float,percent:int}|null direction only (up/down/same) between the median when added and now; null when either is unknown */
    public static function change(?float $then, ?float $now): ?array
    {
        if ($then === null || $then <= 0 || $now === null || $now <= 0) { return null; }
        $d = $now - $then;
        return ['direction' => $d > 0 ? 'up' : ($d < 0 ? 'down' : 'same'), 'diff' => abs($d), 'percent' => (int)round(abs($d) / $then * 100)];
    }
}
