<?php
declare(strict_types=1);

/**
 * Golden search queries (LS-3 J-5). Each entry: the query a visitor might type, the product slugs that MUST appear in the results, and
 * `top` = true when the first result must be the first listed slug. `none` = true means no product may be returned (a query for something that
 * does not exist must not produce noise). Forms covered: with and without spaces or hyphens, Thai words around a model number, non-contiguous words.
 * The set is checked against whatever catalog the database holds: entries whose slugs are missing from that database are skipped and counted.
 */
return [
    // model number forms
    ['q' => 'rtx 3070',          'must' => ['nvidia-geforce-rtx-3070']],
    ['q' => 'RTX3070',           'must' => ['nvidia-geforce-rtx-3070']],
    ['q' => '3070',              'must' => ['nvidia-geforce-rtx-3070']],
    ['q' => 'geforce 3070',      'must' => ['nvidia-geforce-rtx-3070']],
    ['q' => '3070ti',            'must' => ['nvidia-geforce-rtx-3070-ti']],
    ['q' => '3070 ti',           'must' => ['nvidia-geforce-rtx-3070-ti']],
    ['q' => 'rtx3070ti',         'must' => ['nvidia-geforce-rtx-3070-ti']],
    ['q' => 'rx6600xt',          'must' => ['amd-radeon-rx-6600-xt']],
    ['q' => '6600 xt',           'must' => ['amd-radeon-rx-6600-xt']],
    ['q' => 'radeon rx 6600xt',  'must' => ['amd-radeon-rx-6600-xt']],
    ['q' => 'ryzen 5 5600',      'must' => ['amd-ryzen-5-5600']],
    ['q' => 'ryzen5600',         'must' => ['amd-ryzen-5-5600']],
    ['q' => 'r5 5600',           'must' => ['amd-ryzen-5-5600']],
    ['q' => '5600x',             'must' => ['amd-ryzen-5-5600x']],
    ['q' => 'ryzen 5 5600x',     'must' => ['amd-ryzen-5-5600x'], 'top' => true],
    ['q' => 'i5 12400f',         'must' => ['intel-core-i5-12400f']],
    ['q' => 'i5-12400f',         'must' => ['intel-core-i5-12400f']],
    ['q' => '12400f',            'must' => ['intel-core-i5-12400f']],
    ['q' => 'i512400f',          'must' => ['intel-core-i5-12400f']],
    ['q' => '4060ti',            'must' => ['nvidia-geforce-rtx-4060-ti']],
    ['q' => 'rtx 5070 ti',       'must' => ['nvidia-geforce-rtx-5070-ti']],
    ['q' => '5070ti',            'must' => ['nvidia-geforce-rtx-5070-ti']],
    ['q' => 'amd 7800x3d',       'must' => ['amd-ryzen-7-7800x3d']],
    ['q' => '7800 x3d',          'must' => ['amd-ryzen-7-7800x3d']],
    // Thai words around a model number
    ['q' => 'การ์ดจอ 3070',      'must' => ['nvidia-geforce-rtx-3070']],
    ['q' => 'ซีพียู 5600',        'must' => ['amd-ryzen-5-5600']],
    // memory, storage, power: words in a different order or with other separators
    ['q' => 'ddr4-3200 16gb',    'must' => ['ram-ddr4-3200-16gb']],
    ['q' => 'ddr4 3200 16gb',    'must' => ['ram-ddr4-3200-16gb']],
    ['q' => 'ddr5 5600 32gb',    'must' => ['ram-ddr5-5600-32gb']],
    ['q' => 'ssd 1tb',           'must' => ['storage-m2-nvme-1000gb']],
    ['q' => 'nvme 1tb',          'must' => ['storage-m2-nvme-1000gb']],
    ['q' => '850w gold',         'must' => ['psu-850w-gold']],
    // LS-28 A2: more ways people actually type (spacing, vendor words, Thai words, abbreviations); entries skip themselves when the catalog lacks the product
    ['q' => 'geforce rtx 4060 ti', 'must' => ['nvidia-geforce-rtx-4060-ti']],
    ['q' => 'rtx4060ti',           'must' => ['nvidia-geforce-rtx-4060-ti']],
    ['q' => '4060 ti',             'must' => ['nvidia-geforce-rtx-4060-ti']],
    ['q' => 'rtx 3070ti',          'must' => ['nvidia-geforce-rtx-3070-ti']],
    ['q' => 'rx 6600 xt',          'must' => ['amd-radeon-rx-6600-xt']],
    ['q' => 'amd 5600',            'must' => ['amd-ryzen-5-5600']],
    ['q' => 'ryzen 5600x',         'must' => ['amd-ryzen-5-5600x']],
    ['q' => 'core i5 12400f',      'must' => ['intel-core-i5-12400f']],
    ['q' => 'การ์ดจอ rtx 5070 ti', 'must' => ['nvidia-geforce-rtx-5070-ti']],
    ['q' => 'ซีพียู 7800x3d',      'must' => ['amd-ryzen-7-7800x3d']],
    ['q' => 'แรม ddr4 16gb',       'must' => ['ram-ddr4-3200-16gb']],
    ['q' => 'ram ddr5 32gb',       'must' => ['ram-ddr5-5600-32gb']],
    ['q' => 'psu 850w',            'must' => ['psu-850w-gold']],
    ['q' => 'rtx 0000',            'none' => true],
    ['q' => 'ryzen 99 9999x',      'none' => true],
    // nothing may match
    ['q' => 'zzzz qqqq',         'none' => true],
    ['q' => 'rtx 9999 super',    'none' => true],
];
