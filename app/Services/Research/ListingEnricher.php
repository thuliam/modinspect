<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Deterministic enrichment of one owner-collected listing text: brand, product line and category specs.
 *
 * Rules (docs/DATA_RESEARCH_POLICY.md):
 *  - Only facts stated in the listing text, or derived from it by a fixed lookup (line -> brand, chipset -> socket),
 *    are filled. Derived values are flagged so a reviewer can tell stated from derived.
 *  - Nothing is guessed. A field the text does not support stays empty and is listed in missing_fields.
 *  - No AI, no network. The same text always gives the same result.
 */
final class ListingEnricher
{
    /** Fields without which a row cannot be grouped or priced as a variant. */
    private const REQUIRED = [
        'gpu' => ['model', 'vram'],
        'cpu' => ['model'],
        'ram' => ['ddr_gen', 'capacity_gb'],
        'storage' => ['storage_type', 'capacity_gb'],
        'psu' => ['watt', 'brand_or_model'],
        'motherboard' => ['chipset'],
    ];

    /** Brand => regex. Order matters only for display; first hit in text order wins. */
    private const BRANDS = [
        'asus' => 'asus|rog|tuf|proart', 'msi' => '\bmsi\b', 'gigabyte' => 'gigabyte|aorus', 'asrock' => 'asrock',
        'galax' => 'galax', 'gainward' => 'gainward', 'palit' => 'palit', 'zotac' => 'zotac', 'inno3d' => 'inno3d',
        'colorful' => 'colorful', 'pny' => '\bpny\b', 'sapphire' => 'sapphire', 'powercolor' => 'powercolor', 'xfx' => '\bxfx\b',
        'leadtek' => 'leadtek', 'manli' => 'manli', 'sparkle' => 'sparkle', 'biostar' => 'biostar', 'maxsun' => 'maxsun',
        'corsair' => 'corsair|corsir|corsiar', 'kingston' => 'kingston|kington', 'g.skill' => 'g\.?skill',
        'teamgroup' => 'team ?group|taem group', 'adata' => 'adata|\bxpg\b', 'crucial' => 'crucial|ballistix',
        'patriot' => 'patriot', 'hyperx' => 'hyperx', 'thermaltake' => 'thermaltake', 'apacer' => 'apacer',
        'lexar' => 'lexar', 'kingmax' => 'kingmax', 'v-color' => 'v-?color', 'transcend' => 'transcend', 'klevv' => 'klevv',
        'samsung' => 'samsung', 'sk hynix' => 'hynix', 'micron' => 'micron', 'wd' => '\bwd\b|western digital', 'seagate' => 'seagate',
        'sandisk' => 'sandisk', 'toshiba' => 'toshiba', 'kioxia' => 'kioxia', 'netac' => 'netac', 'hikvision' => 'hikvision',
        'seasonic' => 'seasonic', 'cooler master' => 'cooler ?master', 'antec' => 'antec', 'silverstone' => 'silver ?stone',
        'evga' => '\bevga\b', 'fsp' => '\bfsp\b', 'deepcool' => 'deepcool', 'nzxt' => 'nzxt', 'be quiet' => 'be ?quiet',
        'super flower' => 'super ?flower', 'lian li' => 'lian ?li', 'cougar' => 'cougar', 'enermax' => 'enermax',
        'gamemax' => 'gamemax', 'montech' => 'montech', 'zalman' => 'zalman', 'fractal' => 'fractal',
    ];

    /** Brands that make sense per category; a brand outside the set belongs to a neighbouring item in the same post. */
    private const CATEGORY_BRANDS = [
        'gpu' => ['asus', 'msi', 'gigabyte', 'asrock', 'galax', 'gainward', 'palit', 'zotac', 'inno3d', 'colorful', 'pny', 'sapphire', 'powercolor', 'xfx', 'leadtek', 'manli', 'sparkle'],
        'ram' => ['corsair', 'kingston', 'g.skill', 'teamgroup', 'adata', 'crucial', 'patriot', 'hyperx', 'thermaltake', 'apacer', 'lexar', 'kingmax', 'v-color', 'transcend', 'klevv', 'samsung', 'sk hynix', 'micron'],
        'storage' => ['samsung', 'wd', 'seagate', 'kingston', 'crucial', 'sandisk', 'adata', 'apacer', 'lexar', 'toshiba', 'kioxia', 'netac', 'hikvision', 'teamgroup', 'patriot', 'gigabyte', 'transcend', 'sk hynix', 'micron'],
        'psu' => ['corsair', 'seasonic', 'thermaltake', 'cooler master', 'antec', 'silverstone', 'evga', 'msi', 'asus', 'gigabyte', 'fsp', 'deepcool', 'nzxt', 'be quiet', 'super flower', 'lian li', 'cougar', 'enermax', 'gamemax', 'montech', 'zalman', 'fractal', 'adata'],
        'motherboard' => ['asus', 'msi', 'gigabyte', 'asrock', 'biostar', 'colorful', 'maxsun'],
    ];

    /** Product line => brand. Used only when the text names the line but not the brand (flag BRAND_FROM_LINE). */
    private const LINE_BRAND = [
        '/\bvengeance\b/' => 'corsair', '/\bcorsair\b|\b(?:rm|rmx|rme|hx|hxi|cx|cv|tx|sf|ax)\s?-?\d{3,4}\s?[ie]?\b/' => 'corsair',
        '/\bfury\b/' => 'kingston', '/\bt-?force\b|\bdark z\b/' => 'teamgroup', '/\bripjaws\b|\btrident\b/' => 'g.skill',
        '/\btoughram\b/' => 'thermaltake', '/\brog\b|\btuf\b|\bproart\b/' => 'asus', '/\baorus\b/' => 'gigabyte',
        '/\btomahawk\b|\bmortar\b|\bmag\b|\bmpg\b/' => 'msi', '/\bphantom gaming\b|\bpantom gaming\b|\bsteel legend\b|\btaichi\b/' => 'asrock',
        '/\bventus\b|\bsuprim\b|\bgaming x\b/' => 'msi', '/\bevo\b.*\b(?:970|980|990|860|870)\b|\b(?:970|980|990|860|870)\s?(?:evo|pro|qvo)\b/' => 'samsung',
    ];

    private const CHIPSET_SOCKET = [
        'am5' => 'x870e|x870|x670e|x670|b850|b840|b650e|b650|a620',
        'am4' => 'x570|x470|b550|b450|a520|a320|b350|x370',
        'lga1851' => 'z890|b860|h810',
        'lga1700' => 'z790|z690|b760|b660|h770|h670|h610',
        'lga1200' => 'z590|b560|h510|h410|z490|b460|h470',
        'lga1151' => 'z390|z370|b365|b360|h310|h370|b250|z270|h110|z170',
    ];

    /**
     * @param string $text      the listing text for this row (already scrubbed of personal data)
     * @param string $category  gpu|cpu|ram|storage|psu|motherboard|other
     * @param ?string $modelKey model key already derived for GPU/CPU rows
     * @param ?int $vram        VRAM already derived for GPU rows
     * @return array<string,mixed>
     */
    public static function enrich(string $text, string $category, ?string $modelKey = null, ?int $vram = null): array
    {
        $s = mb_strtolower($text, 'UTF-8');
        $flags = [];
        [$brand, $brandAmbiguous] = self::brand($s, $category, $flags);
        $out = [
            'brand' => $brand, 'product_line' => null, 'capacity_gb' => null, 'ddr_gen' => null, 'speed_mhz' => null,
            'kit' => null, 'storage_type' => null, 'chipset' => null, 'socket' => null, 'watt' => null, 'efficiency' => null,
        ];
        switch ($category) {
            case 'ram':
                self::ram($s, $out, $flags);
                break;
            case 'storage':
                self::storage($s, $out, $flags);
                break;
            case 'psu':
                self::psu($s, $out);
                break;
            case 'motherboard':
                self::motherboard($s, $out, $flags);
                break;
        }
        if ($brandAmbiguous || self::multiItem($s, $category, $out)) {
            // The post text mixes several products: fill nothing rather than attach another item's facts to this row.
            $blank = array_fill_keys(array_keys($out), null);
            return $blank + ['spec_key' => null, 'enrichment_status' => 'ambiguous', 'missing_fields' => 'multi_item_text', 'enrichment_flags' => ['MULTI_ITEM_CONTEXT']];
        }
        $facts = $out + ['model' => $modelKey, 'vram' => $vram, 'brand_or_model' => $out['brand'] ?? $out['product_line']];

        $missing = [];
        foreach (self::REQUIRED[$category] ?? [] as $field) {
            if (($facts[$field] ?? null) === null || $facts[$field] === '') { $missing[] = $field; }
        }
        if ($category !== 'other' && $out['brand'] === null && !in_array($category, ['cpu'], true)) { $missing[] = 'brand'; }
        $required = self::REQUIRED[$category] ?? [];
        $missingRequired = array_values(array_intersect($missing, $required));

        $status = $category === 'other' ? 'not_applicable' : ($missingRequired !== [] ? 'missing' : ($missing !== [] ? 'partial' : 'complete'));
        return $out + [
            'spec_key' => self::specKey($category, $out, $modelKey, $vram, $missingRequired === []),
            'enrichment_status' => $status,
            'missing_fields' => implode('|', $missing),
            'enrichment_flags' => $flags,
        ];
    }

    /** @return array{0:?string,1:bool} [brand, ambiguous] */
    private static function brand(string $s, string $category, array &$flags): array
    {
        $allowed = self::CATEGORY_BRANDS[$category] ?? [];
        $hits = [];
        foreach (self::BRANDS as $name => $re) {
            if (in_array($name, $allowed, true) && preg_match('/' . $re . '/u', $s, $m, PREG_OFFSET_CAPTURE)) { $hits[$name] = $m[0][1]; }
        }
        if (count($hits) > 1) { return [null, true]; }
        if (count($hits) === 1) { return [array_key_first($hits), false]; }
        $viaLine = [];
        foreach (self::LINE_BRAND as $re => $name) {
            if (in_array($name, $allowed, true) && preg_match($re . 'u', $s)) { $viaLine[$name] = true; }
        }
        if (count($viaLine) > 1) { return [null, true]; }
        if (count($viaLine) === 1) { $flags[] = 'BRAND_FROM_LINE'; return [array_key_first($viaLine), false]; }
        return [null, false];
    }

    /** True when one post block clearly describes more than one product (two capacities, two DDR generations). */
    private static function multiItem(string $s, string $category, array $out): bool
    {
        if ($category === 'ram') {
            preg_match_all('/ddr\s*([2345])/u', $s, $g);
            if (count(array_unique($g[1])) > 1) { return true; }
            preg_match_all('/(?<![\d.])(\d{1,3})\s*g(?:b)?(?![a-z\d])/u', $s, $c);
            $allowed = [$out['capacity_gb']];
            if ($out['kit'] !== null) { $allowed[] = (int)explode('x', (string)$out['kit'])[1]; }
            foreach (array_unique($c[1]) as $v) { if (!in_array((int)$v, $allowed, true) && (int)$v >= 4) { return true; } }
        }
        if ($category === 'storage') {
            preg_match_all('/(?<![\d.])(\d{3,4})\s*g(?:b)?(?![a-z\d])|(?<![\d.])(\d)\s*t(?:b)?(?![a-z\d])/u', $s, $c);
            $vals = array_unique(array_filter(array_merge($c[1], $c[2]), static fn($v) => $v !== ''));
            if (count($vals) > 1) { return true; }
        }
        return false;
    }
    private static function ram(string $s, array &$out, array &$flags): void
    {
        if (preg_match('/ddr\s*([2345])/u', $s, $m)) { $out['ddr_gen'] = 'ddr' . $m[1]; }
        elseif (preg_match('/(?<![a-z\d])d([45])(?![a-z\d])/u', $s, $m) && preg_match('/ram|แรม|เเรม/u', $s)) { $out['ddr_gen'] = 'ddr' . $m[1]; }
        if (preg_match('/(?<![\d.])(2|4|8|16|24|32|48)\s*(?:g(?:b)?)?\s*[x×*]\s*([1-8])(?![\d])/u', $s, $m)) {
            $out['kit'] = $m[2] . 'x' . $m[1];
            $out['capacity_gb'] = (int)$m[1] * (int)$m[2];
        } elseif (preg_match('/(?<![\d.])([1-8])\s*[x×*]\s*(\d{1,3})\s*g(?:b)?(?![a-z\d])/u', $s, $m)) {
            $out['kit'] = $m[1] . 'x' . $m[2];
            $out['capacity_gb'] = (int)$m[1] * (int)$m[2];
        } elseif (preg_match('/(?<![\d.])(\d{1,3})\s*g(?:b)?(?![a-z\d])/u', $s, $m) && in_array((int)$m[1], [2, 4, 8, 16, 24, 32, 48, 64, 96, 128], true)) {
            $out['capacity_gb'] = (int)$m[1];
        }
        if (preg_match('/(\d{4})\s*m?hz|ddr[2345]\s*[-\/ ]\s*(\d{4})\b|(\d{4})\s*mt\/s|\bbus+\s*(\d{4})|\bbas+\s*(\d{4})/u', $s, $m)) {
            $out['speed_mhz'] = (int)($m[1] !== '' ? $m[1] : ($m[2] ?? '' ?: ($m[3] ?? '' ?: ($m[4] ?? '' ?: ($m[5] ?? '')))));
        }
        // DDR5 starts at 4800 and DDR3 tops out below 2133, so a stated 2133-3733 speed is DDR4 and 4800+ is DDR5 (flagged as derived).
        if ($out['ddr_gen'] === null && $out['speed_mhz'] !== null) {
            if ($out['speed_mhz'] >= 2133 && $out['speed_mhz'] <= 3733) { $out['ddr_gen'] = 'ddr4'; $flags[] = 'DDR_GEN_FROM_SPEED'; }
            elseif ($out['speed_mhz'] >= 4800) { $out['ddr_gen'] = 'ddr5'; $flags[] = 'DDR_GEN_FROM_SPEED'; }
        }
        if (preg_match('/fury beast|fury renegade|vengeance pro|vengeance rgb|vengeance lpx|vengeance|t-?force delta|t-?force vulcan|dark z|toughram|trident z\d?|ripjaws|lancer|xpg spectrix|ballistix/u', $s, $m)) {
            $out['product_line'] = $m[0];
        }
    }

    private static function storage(string $s, array &$out, array &$flags): void
    {
        if (preg_match('/nvme|pcie|gen ?[345]\b/u', $s)) { $out['storage_type'] = 'm2_nvme'; }
        elseif (preg_match('/m\.?2[^\n]{0,15}sata|sata[^\n]{0,15}m\.?2/u', $s)) { $out['storage_type'] = 'm2_sata'; }
        elseif (preg_match('/m\.?2/u', $s)) { $out['storage_type'] = 'm2_unspecified'; }
        elseif (preg_match('/\bssd\b[^\n]{0,12}sata|sata[^\n]{0,12}ssd|\bssd\b/u', $s)) { $out['storage_type'] = preg_match('/sata/u', $s) ? 'sata_ssd' : 'ssd_unspecified'; }
        elseif (preg_match('/hdd|hard ?disk|ฮาร์ดดิส/u', $s)) { $out['storage_type'] = 'hdd'; }
        if (preg_match('/(?<![\d.])(\d(?:\.\d)?)\s*t(?:b)?(?![a-z\d])/u', $s, $m) && in_array((float)$m[1], [0.5, 1.0, 2.0, 4.0, 8.0], true)) { $out['capacity_gb'] = (int)round((float)$m[1] * 1000); }
        elseif (preg_match('/(?<![\d.])(120|128|240|250|256|480|500|512|960|1000)\s*g(?:b)?(?![a-z\d])/u', $s, $m)) { $out['capacity_gb'] = (int)$m[1]; }
        if ($out['capacity_gb'] === null && $out['storage_type'] !== null && preg_match('/(?<![\d.,])(128|240|250|256|480|500|512|960)(?![\d,.a-z])/u', $s, $m)) {
            $out['capacity_gb'] = (int)$m[1];
            $flags[] = 'CAPACITY_UNIT_ASSUMED_GB';
        }
        if (preg_match('/(?:970|980|990)\s?(?:evo plus|evo|pro)|(?:860|870)\s?(?:evo|qvo)|\bmx500\b|\bbx500\b|\bsn\d{3,4}\b|\bkc\d{3,4}\b|wd (?:blue|black|green|red)|\ba400\b|\bnv[123]\b|\bp[35] ?plus\b/u', $s, $m)) {
            $out['product_line'] = $m[0];
        }
    }

    private static function psu(string $s, array &$out): void
    {
        if (preg_match('/\b(rm|rmx|rme|hx|hxi|cx|cv|tx|sf|ax)\s?-?(\d{3,4})\s?([ie]?|shift)\b/u', $s, $m)) {
            $out['product_line'] = strtoupper($m[1]) . $m[2] . ($m[3] !== '' ? strtolower($m[3]) : '');
            $out['watt'] = (int)$m[2];
        }
        if (preg_match('/(?<![\d.])(\d{3,4})\s*w(?:att)?(?![a-z])/u', $s, $m) && (int)$m[1] >= 200 && (int)$m[1] <= 2000) { $out['watt'] = (int)$m[1]; }
        if (preg_match('/80\s?\+?\s*(?:plus)?\s*(white|bronze|silver|gold|platinum|titanium)/u', $s, $m)) { $out['efficiency'] = $m[1]; }
    }

    private static function motherboard(string $s, array &$out, array &$flags): void
    {
        $all = implode('|', array_values(self::CHIPSET_SOCKET));
        if (preg_match('/(?<![a-z\d])(' . $all . ')(?:[a-z]{0,2})(?![a-z\d])/u', $s, $m)) {
            $out['chipset'] = $m[1];
            foreach (self::CHIPSET_SOCKET as $socket => $re) {
                if (preg_match('/^(?:' . $re . ')$/u', $m[1])) { $out['socket'] = $socket; $flags[] = 'SOCKET_FROM_CHIPSET'; break; }
            }
        }
        if (preg_match('/ddr\s*([345])/u', $s, $m)) { $out['ddr_gen'] = 'ddr' . $m[1]; }
        if (preg_match('/rog strix|rog crosshair|tuf gaming|prime|proart|aorus (?:elite|master|pro|ultra)|gaming x|gaming plus|ud\b|pro [abhxz]\d{3}|mag [abhxz]\d{3}|mpg|tomahawk|mortar|phantom gaming|pantom gaming|steel legend|taichi|pg riptide/u', $s, $m)) {
            $out['product_line'] = $m[0];
        }
    }

    private static function specKey(string $category, array $o, ?string $modelKey, ?int $vram, bool $complete): ?string
    {
        if (!$complete) { return null; }
        return match ($category) {
            'ram' => implode(' ', array_filter([$o['ddr_gen'], $o['capacity_gb'] . 'gb', $o['kit'], $o['speed_mhz'] ? (string)$o['speed_mhz'] : null])),
            'storage' => $o['storage_type'] . ' ' . $o['capacity_gb'] . 'gb',
            'psu' => trim(implode(' ', array_filter([$o['brand'], $o['product_line'], $o['watt'] . 'w']))),
            'motherboard' => trim(implode(' ', array_filter([$o['chipset'], $o['brand']]))),
            default => null,
        };
    }
}
