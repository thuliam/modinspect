<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Research\GpuVramFacts;

function assert_vf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// single-size models
assert_vf(GpuVramFacts::singleVram('RTX 3070') === 8 && GpuVramFacts::singleVram('rtx 4070 ti') === 12 && GpuVramFacts::singleVram('rtx 5090') === 32, 'Single-size models must return their size');
// multi-size models must never be guessed
foreach (['rtx 3050', 'rtx 3060', 'rtx 3080', 'rtx 4060 ti', 'rtx 5060 ti', 'rx 6500 xt', 'rx 9060 xt', 'gtx 1050', 'rtx 2060', 'unknown model'] as $key) {
    assert_vf(GpuVramFacts::singleVram($key) === null, "$key exists with several memory sizes (or is unknown) and must not be accepted without a stated VRAM");
}
// row acceptance: only "missing" rows lacking VRAM (and optionally brand) of a single-size model
$row = ['category' => 'gpu', 'enrichment_status' => 'missing', 'missing_fields' => 'vram', 'model_key' => 'rtx 3070'];
assert_vf(GpuVramFacts::acceptsMissingVram($row), 'VRAM-only gap on a single-size model is acceptable');
assert_vf(GpuVramFacts::acceptsMissingVram(['missing_fields' => 'vram|brand'] + $row), 'VRAM + brand gap is acceptable (brand never decides the product)');
assert_vf(!GpuVramFacts::acceptsMissingVram(['model_key' => 'rtx 4060 ti'] + $row), 'Multi-size model without VRAM must stay out');
assert_vf(!GpuVramFacts::acceptsMissingVram(['missing_fields' => 'model'] + $row), 'Any other missing field keeps the row out');
assert_vf(!GpuVramFacts::acceptsMissingVram(['category' => 'cpu'] + $row), 'Only GPU rows');
assert_vf(!GpuVramFacts::acceptsMissingVram(['enrichment_status' => 'ambiguous'] + $row), 'Ambiguous rows stay out');

echo "gpu_vram_facts_test: PASS\n";
