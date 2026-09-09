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

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9ก-๙]+/iu', '', mb_strtolower($value, 'UTF-8')) ?? '';
    }
}
