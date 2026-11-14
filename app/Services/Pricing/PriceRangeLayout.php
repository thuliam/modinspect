<?php
declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * Presentation geometry for the public range-bar answer (docs/UX_UI_SYSTEM.md, DL-015).
 *
 * Pure display math: it positions already-computed snapshot values on a track and never changes them.
 * Track = observed low..high, band = Q1..Q3, tick = median. All outputs are percentages 0..100 rounded to 0.1.
 */
final class PriceRangeLayout
{
    /**
     * @return array{valid:bool,band_left:float,band_width:float,median:float}
     */
    public static function compute(float $low, float $q1, float $median, float $q3, float $high): array
    {
        $ordered = $low <= $q1 && $q1 <= $median && $median <= $q3 && $q3 <= $high && $low >= 0 && $median > 0;
        if (!$ordered) {
            return ['valid' => false, 'band_left' => 0.0, 'band_width' => 0.0, 'median' => 0.0];
        }
        $span = $high - $low;
        if ($span <= 0.0) {
            // Every observation is the same price: show a full-width band rather than dividing by zero.
            return ['valid' => true, 'band_left' => 0.0, 'band_width' => 100.0, 'median' => 50.0];
        }
        $pct = static fn(float $v): float => round(max(0.0, min(100.0, ($v - $low) / $span * 100.0)), 1);
        $left = $pct($q1);
        $width = max(round($pct($q3) - $left, 1), 2.0); // a hairline band would be invisible
        if ($left + $width > 100.0) { $left = round(100.0 - $width, 1); }
        return ['valid' => true, 'band_left' => $left, 'band_width' => $width, 'median' => $pct($median)];
    }
}
