<?php
/**
 * Range-bar answer block (direction C look + A range display, DL-015).
 * Input: $rangeProduct (price_indices columns), $rangeSize 'lg' | 'sm'.
 * Renders nothing unless the quartile values are ordered; the caller decides eligibility.
 */
use App\Services\Pricing\PriceRangeLayout;

$rangeSize = $rangeSize ?? 'lg';
$low = (float) ($rangeProduct['price_low'] ?? $rangeProduct['q1'] ?? 0);
$q1 = (float) ($rangeProduct['q1'] ?? 0);
$median = (float) ($rangeProduct['median'] ?? 0);
$q3 = (float) ($rangeProduct['q3'] ?? 0);
$high = (float) ($rangeProduct['price_high'] ?? $rangeProduct['q3'] ?? 0);
$layout = PriceRangeLayout::compute($low, $q1, $median, $q3, $high);
$baht = static fn(float $v): string => '฿' . number_format($v);
if ($layout['valid']):
?>
<div class="mb-range mb-range-<?= $rangeSize === 'sm' ? 'sm' : 'lg' ?>">
    <div class="mb-range-figures">
        <span class="mb-range-label">ช่วงราคาที่พบ (Q1–Q3)</span>
        <strong class="mb-range-value"><?= $baht($q1) ?> – <?= $baht($q3) ?></strong>
    </div>
    <div class="mb-range-track" role="img" aria-label="ช่วงราคา <?= $baht($q1) ?> ถึง <?= $baht($q3) ?> ราคากลาง <?= $baht($median) ?> จากราคาต่ำสุด <?= $baht($low) ?> ถึงสูงสุด <?= $baht($high) ?>">
        <span class="mb-range-band" style="left:<?= $layout['band_left'] ?>%;width:<?= $layout['band_width'] ?>%"></span>
        <span class="mb-range-tick" style="left:<?= $layout['median'] ?>%"></span>
    </div>
    <div class="mb-range-scale" aria-hidden="true">
        <span><?= $baht($low) ?></span>
        <span class="mb-range-median">ราคากลาง <?= $baht($median) ?></span>
        <span><?= $baht($high) ?></span>
    </div>
</div>
<?php endif; ?>
