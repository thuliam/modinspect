<?php
declare(strict_types=1);

namespace App\Services\ProductResolver;

use App\Data\ProductResolution;
use App\Services\Validation\ModelMarker;

final class ProductResolver
{
    /**
     * @param array<int,array{id:int,full_name:string,aliases:array<int,string>}> $catalog
     */
    public function __construct(private array $catalog)
    {
    }

    public function resolve(string $title): ProductResolution
    {
        $normalizedTitle = $this->normalize($title);
        $titleMarkers = ModelMarker::all($title);
        $best = [null, null, 0.0, 'none', 0];

        foreach ($this->catalog as $product) {
            // LS-82: a title that names only another hardware category (a DDR5 kit, a motherboard) never resolves to this product
            if (!empty($product['category']) && \App\Services\Catalog\SpecConsistency::categoryConflict($title, (string)$product['category']) !== null) { continue; }
            $productMarkers = ModelMarker::all((string)$product['full_name']);
            $names = array_merge([(string)$product['full_name']], $product['aliases'] ?? []);
            foreach ($names as $name) {
                $normalizedName = $this->normalize($name);
                if ($normalizedName === '') {
                    continue;
                }

                $nameMarkers = ModelMarker::all($name);
                $effectiveNameMarkers = $nameMarkers ?: $productMarkers;
                $sharedMarkers = array_values(array_intersect($titleMarkers, $effectiveNameMarkers));
                if ($titleMarkers !== [] && $effectiveNameMarkers !== [] && $sharedMarkers === []) {
                    continue;
                }

                if (str_contains($normalizedTitle, $normalizedName)) {
                    $score = $normalizedName === $normalizedTitle ? 1.0 : min(0.99, 0.82 + strlen($normalizedName) / max(strlen($normalizedTitle), 1));
                    if ($sharedMarkers !== []) {
                        $score = max($score, 0.98);
                    }
                    if ($score > $best[2] || ($score === $best[2] && strlen($normalizedName) > $best[4])) {
                        $best = [(int)$product['id'], (string)$product['full_name'], $score, $sharedMarkers !== [] ? 'model_marker_alias' : 'alias_contains', strlen($normalizedName)];
                    }
                    continue;
                }

                if ($sharedMarkers !== []) {
                    // a number alone is weak evidence: the model suffix (Ti/Super/XT...) and the product family (Ryzen/RTX/...) must also agree
                    if (!$this->sameSuffixAndFamily($title, (string)$product['full_name'])) {
                        continue;
                    }
                    $score = 0.96;
                    if (($titleMarkers[0] ?? null) !== null && in_array($titleMarkers[0], $sharedMarkers, true)) {
                        $score = 0.985;
                    }
                    if ($score > $best[2] || ($score === $best[2] && strlen($normalizedName) > $best[4])) {
                        $best = [(int)$product['id'], (string)$product['full_name'], $score, 'model_marker', strlen($normalizedName)];
                    }
                    continue;
                }

                similar_text($normalizedTitle, $normalizedName, $percent);
                $score = $percent / 100;
                if ($score > $best[2] && $score >= 0.84) {
                    $best = [(int)$product['id'], (string)$product['full_name'], $score, 'similarity', strlen($normalizedName)];
                }
            }
        }

        return new ProductResolution($best[0], $best[1], round($best[2], 4), $best[3]);
    }

    /** Suffix words and family words must match between a title and a product name for a marker-only (number) match. */
    private function sameSuffixAndFamily(string $title, string $productName): bool
    {
        $suffix = static function (string $v): array {
            preg_match_all('/(?<![a-z])(ti|super|xtx|xt|gre)(?![a-z])/', mb_strtolower($v, 'UTF-8'), $m);
            $set = array_values(array_unique($m[1])); sort($set);
            return $set;
        };
        if ($suffix($title) !== $suffix($productName)) {
            return false;
        }
        // a memory kit (DDR3/4/5 in its name) is only matched by a title that talks about DDR memory
        if (preg_match('/ddr\d/i', $productName) === 1 && preg_match('/ddr/i', $title) !== 1) {
            return false;
        }
        $family = static function (string $v): array {
            preg_match_all('/(?<![a-z])(ryzen|rtx|gtx|rx|radeon|geforce|arc|i[3579])(?![a-z])/', mb_strtolower($v, 'UTF-8'), $m);
            return array_values(array_unique($m[1]));
        };
        $t = $family($title); $p = $family($productName);
        // a title that names a family while the product names none of it (a Ryzen title against a RAM kit) is not a match
        if ($t === []) {
            return true;
        }
        return $p !== [] && array_intersect($t, $p) !== [];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9ก-๙]+/iu', '', mb_strtolower($value, 'UTF-8')) ?? '';
    }
}
