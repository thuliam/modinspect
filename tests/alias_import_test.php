<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\AliasImport as A;

/** LS-26: bulk alias import. The plan judges every line and writes nothing; only "new" lines may ever be inserted; the admin action is guarded server-side. */
function ok_ai(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$ids = ['ryzen-5-5600' => 1, 'rtx-3060' => 2];
$existing = [A::normalize('R5 5600 ถูก') => 1, A::normalize('3060 หายาก') => 1];
$text = "# comment\n\nryzen-5-5600, ryzen 5 5600 ลดราคา\nryzen-5-5600, R5-5600  ถูก\nrtx-3060, 3060 หายาก\nno-such, x y\nrtx-3060,\n\"rtx-3060\",\"RTX 3060, 12GB\",จากร้าน\nrtx-3060\tRTX3060 12GB\nRYZEN-5-5600, ryzen 5 5600 ลดราคา";
$p = A::plan($text, $ids, $existing);
$st = array_column($p['rows'], 'status');
ok_ai($st === ['new', 'exists', 'conflict', 'unknown_product', 'invalid', 'new', 'duplicate_in_file', 'duplicate_in_file'], 'Every line gets the right verdict: ' . json_encode($st));
ok_ai($p['rows'][5]['alias'] === 'RTX 3060, 12GB' && $p['rows'][5]['source'] === 'จากร้าน', 'Quoted commas stay in the alias and the third column is the note');
ok_ai($p['rows'][0]['line'] === 3 && $p['counts']['new'] === 2 && $p['counts']['conflict'] === 1 && !$p['truncated'], 'Line numbers are the file lines (comments and blanks skipped) and counts add up');
ok_ai(A::normalize('R5-5600  ถูก') === A::normalize('r5 5600 ถูก'), 'Spacing, case and punctuation do not make a different alias');
$big = implode("\n", array_map(static fn($i) => "ryzen-5-5600, alias number $i", range(1, A::MAX_LINES + 20)));
$q = A::plan($big, $ids, []);
ok_ai(count($q['rows']) === A::MAX_LINES && $q['truncated'] === true, 'A very long paste is cut at the limit and says so');
ok_ai(A::plan('ryzen-5-5600, ' . str_repeat('x', 300), $ids, [])['rows'][0]['status'] === 'invalid', 'An alias longer than the column is invalid');

$src = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/AdminController.php');
preg_match('/public function aliasImport\(\): void \{(.*?)\n    \}\n/s', $src, $m);
$body = $m[1] ?? '';
ok_ai(str_contains($body, "requirePermission('products.manage')") && strpos($body, 'requirePermission') < strpos($body, 'Csrf::verify') && strpos($body, 'Csrf::verify') < strpos($body, 'beginTransaction'), 'The action checks permission, then the token, before it writes');
ok_ai(str_contains($body, "'confirm'") && str_contains($body, "!=='new'") && str_contains($body, 'product_aliases_imported'), 'Only a confirm inserts, only new lines, and the batch is audited');
ok_ai(str_contains((string) file_get_contents(dirname(__DIR__) . '/public/index.php'), "post('/admin/product-aliases/import'"), 'The route is registered as POST only');
echo "alias_import_test: PASS\n";
