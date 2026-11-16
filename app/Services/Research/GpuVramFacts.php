<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Public hardware facts, not guesses from listing text: GPU models that were only ever sold with ONE memory size.
 * A listing of such a model that does not state the VRAM is still unambiguous, so it may be priced as that model.
 * Models that exist with several sizes (RTX 3050 6/8 GB, RTX 3060 8/12 GB, RTX 3080 10/12 GB, RTX 4060 Ti 8/16 GB, RTX 5060 Ti 8/16 GB,
 * RX 6500 XT 4/8 GB, RX 9060 XT 8/16 GB, GTX 1050 2/3 GB, RTX 2060 6/12 GB ...) are deliberately NOT listed: without the VRAM they stay out.
 * Keys are the lower-case model keys the research extractor emits.
 */
final class GpuVramFacts
{
    private const SINGLE = [
        'gtx 1050 ti' => 4, 'gtx 1650' => 4, 'gtx 1650 super' => 4, 'gtx 1660 super' => 6, 'gtx 1660 ti' => 6,
        'rtx 2070 super' => 8, 'rtx 2080' => 8,
        'rtx 3060 ti' => 8, 'rtx 3070' => 8, 'rtx 3070 ti' => 8, 'rtx 3080 ti' => 12, 'rtx 3090' => 24, 'rtx 3090 ti' => 24,
        'rtx 4060' => 8, 'rtx 4070' => 12, 'rtx 4070 super' => 12, 'rtx 4070 ti' => 12, 'rtx 4070 ti super' => 16, 'rtx 4080' => 16, 'rtx 4080 super' => 16, 'rtx 4090' => 24,
        'rtx 5050' => 8, 'rtx 5060' => 8, 'rtx 5070' => 12, 'rtx 5070 ti' => 16, 'rtx 5080' => 16, 'rtx 5090' => 32,
        'rx 5700 xt' => 8, 'rx 6600 xt' => 8, 'rx 6700 xt' => 12, 'rx 7800 xt' => 16, 'rx 9070 xt' => 16,
    ];

    public static function singleVram(string $modelKey): ?int
    {
        return self::SINGLE[strtolower(trim($modelKey))] ?? null;
    }

    /**
     * True when an enrichment row marked "missing" lacks only the VRAM (brand may also be absent) and the model has a single memory size.
     * @param array<string,string> $row a research CSV row
     */
    public static function acceptsMissingVram(array $row): bool
    {
        if (($row['category'] ?? '') !== 'gpu' || ($row['enrichment_status'] ?? '') !== 'missing') { return false; }
        $missing = array_values(array_filter(explode('|', (string)($row['missing_fields'] ?? '')), static fn(string $f): bool => $f !== '' && $f !== 'brand'));
        return $missing === ['vram'] && self::singleVram((string)($row['model_key'] ?? '')) !== null;
    }
}
