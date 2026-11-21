<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\AliasAudit;

/** LS-28 A1: the alias audit is pure, flags the four risky shapes, leaves clean aliases alone and the CLI only reads. */
function ok_aa(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$names = [1 => 'NVIDIA GeForce RTX 3070', 2 => 'NVIDIA GeForce RTX 3070 Ti', 3 => 'AMD Ryzen 5 5600'];
$aliases = [
    ['id' => 1, 'product_id' => 1, 'alias_text' => '3070'],
    ['id' => 2, 'product_id' => 1, 'alias_text' => 'rtx3070'],
    ['id' => 3, 'product_id' => 2, 'alias_text' => 'rtx3070ti'],
    ['id' => 4, 'product_id' => 3, 'alias_text' => 'r5'],
    ['id' => 5, 'product_id' => 3, 'alias_text' => 'NVIDIA GeForce RTX 3070 Ti'],
    ['id' => 6, 'product_id' => 3, 'alias_text' => 'ryzen 5 5600'],
];
$r = AliasAudit::audit($aliases, $names);
$by = static function (string $k) use ($r): array { return array_values(array_map(static fn($f) => $f['alias_id'], array_filter($r['findings'], static fn($f) => $f['kind'] === $k))); };
ok_aa($by('too_short') === [4], 'Only the two-letter alias is too short: ' . json_encode($by('too_short')));
ok_aa($by('digits_only') === [1], 'A bare number is flagged');
ok_aa($by('equals_other_name') === [5], 'An alias equal to another product name is flagged');
ok_aa($by('shared_prefix') === [2], 'rtx3070 is the start of rtx3070ti on another product: ' . json_encode($by('shared_prefix')));
ok_aa(!in_array(6, array_column($r['findings'], 'alias_id'), true), 'A clean alias is not flagged');
ok_aa($r['aliases'] === 6 && array_sum($r['counts']) === count($r['findings']), 'Counts add up');
ok_aa(AliasAudit::audit([], [])['findings'] === [], 'No aliases, no findings');
$src = (string)file_get_contents(ROOT_PATH . '/cli/alias_audit.php');
ok_aa(!preg_match('/\b(INSERT|UPDATE|DELETE)\b/', $src), 'The CLI only reads');
echo "alias_audit_test: PASS\n";
