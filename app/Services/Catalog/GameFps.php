<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/** LS-59: in-game frame rates for CPU and GPU pages. Pure: which games and resolutions are known, which categories get the panel, and how stored rows are grouped for display. Results are reference information, never a price input. */
final class GameFps
{
    /** game_key => label (the owner's starting list; add a key here to support another title) */
    public const GAMES = ['cyberpunk2077' => 'Cyberpunk 2077', 'cs2' => 'Counter-Strike 2', 're6' => 'Resident Evil 6', 'cod' => 'Call of Duty'];
    public const RESOLUTIONS = ['1080p' => 'Full HD 1080p', '1440p' => '2K 1440p', '2160p' => '4K 2160p'];
    private const CATEGORIES = ['cpu', 'gpu'];

    public static function supports(?string $categorySlug): bool
    {
        return in_array(strtolower((string) $categorySlug), self::CATEGORIES, true);
    }

    /**
     * @param list<array<string,mixed>> $rows stored rows of one product
     * @return array<string,list<array<string,mixed>>> resolution => rows in game order, known games and resolutions only
     */
    public static function group(array $rows): array
    {
        $gameOrder = array_flip(array_keys(self::GAMES));
        $out = [];
        foreach ($rows as $r) {
            $g = (string) ($r['game_key'] ?? ''); $res = (string) ($r['resolution'] ?? '');
            if (!isset(self::GAMES[$g], self::RESOLUTIONS[$res])) { continue; }
            $out[$res][] = $r;
        }
        foreach ($out as &$list) { usort($list, static fn(array $a, array $b): int => $gameOrder[$a['game_key']] <=> $gameOrder[$b['game_key']] ?: (float) $b['avg_fps'] <=> (float) $a['avg_fps']); }
        unset($list);
        $res = array_flip(array_keys(self::RESOLUTIONS));
        uksort($out, static fn(string $a, string $b): int => $res[$a] <=> $res[$b]);
        return $out;
    }
}
