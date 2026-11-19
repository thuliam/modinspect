<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/** LS-22 B1: the spec coverage CLI is read-only, lists every filtered category, and counts only what the filters can really match. */
function ok_sc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$src = (string)file_get_contents(ROOT_PATH . '/cli/spec_coverage.php');
ok_sc(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', $src), 'The coverage CLI must not write');

$out = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/spec_coverage.php') . ' --json 2>&1', $out, $code);
$data = json_decode(implode("\n", $out), true);
ok_sc($code === 0 && is_array($data), 'The coverage CLI exits 0 and prints JSON: ' . implode(' ', array_slice($out, 0, 3)));
$cats = array_column($data, 'category');
foreach (array_keys(\App\Services\Catalog\SpecFilter::BY_CATEGORY) as $c) { ok_sc(in_array($c, $cats, true), "Category $c is reported"); }
foreach ($data as $row) {
    foreach ($row['filters'] as $key => $f) {
        ok_sc($f['covered'] + count($f['missing']) === $row['models'], "{$row['category']}.$key covered + missing equals the model count");
    }
}
echo "spec_coverage_test: PASS\n";
