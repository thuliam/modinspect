<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * F-5 search page: the category filter must reach every category the catalog has (cpu, gpu, motherboard, cooling, psu, case, ram, storage),
 * in the dropdown and in the quick chips, from one list. Before this the dropdown stopped at four and the chips at two, so 23 listed models
 * (motherboard, PSU, case, cooling) could not be reached by category.
 */
function ok_pc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$view = (string)file_get_contents(dirname(__DIR__) . '/app/Views/price/index.php');
foreach (['cpu', 'gpu', 'motherboard', 'cooling', 'psu', 'case', 'ram', 'storage'] as $slug) {
    ok_pc(str_contains($view, "'$slug' =>"), "The category list has $slug");
}
ok_pc(substr_count($view, 'foreach ($categories as') === 2, 'The dropdown and the chips both read the one list');
ok_pc(str_contains($view, 'ทั้งหมด'), 'The "all" chip stays');
echo "price_index_categories_test: PASS\n";
