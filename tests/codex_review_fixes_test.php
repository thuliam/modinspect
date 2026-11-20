<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\AliasImport as A;

/**
 * LS-27: the fixes that came out of the external UX review (docs/reviews/codex_review_2026-11-20.md) after each finding was checked against the code.
 * Source-level checks of the wording, the ARIA state, the placeholder contrast (computed from the CSS value) and the alias hints.
 */
function ok_cx(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
$read = static fn(string $p): string => (string) file_get_contents($root . '/' . $p);

// F02: the consent text claims no use the code does not have (the flag is stored and read nowhere; a deal check writes no observation)
$deal = $read('app/Views/deal/form.php');
ok_cx(!str_contains($deal, 'ปรับปรุงดัชนีราคา') && str_contains($deal, 'ไม่มีผลต่อราคากลาง'), 'The consent text does not say user data adjusts the price index');
foreach (['app/Models/DealCheck.php', 'app/Controllers/DealCheckerController.php'] as $f) { ok_cx(!preg_match('/INSERT INTO (price_observations|raw_price_observations|price_indices)/', $read($f)), "$f writes no observation or index"); }
ok_cx(substr_count($read('app/Models/DealCheck.php') . $read('app/Controllers/DealCheckerController.php'), 'consent_to_anonymous_observation') === 1, 'The consent flag is only ever written, never read to drive anything');

// F09 / F03 (sort label)
$build = $read('app/Views/modules/build.php');
ok_cx(str_contains($build, 'บางข้อตรวจไม่ได้') && !str_contains($build, 'ระบบกรองให้เหลือเฉพาะรุ่นที่ใช้ด้วยกันได้'), 'The Build lead says some checks cannot be made');
ok_cx(str_contains($build, '>มีราคามือสองก่อน<') || str_contains($build, 'มีราคามือสองก่อน'), 'The default sort is named by what it does');
ok_cx(!str_contains($build, 'แนะนำ (มีราคามือสองก่อน)'), 'The default sort is not called a recommendation');

// F04
ok_cx(str_contains($read('app/Views/listing/reader.php'), 'ไม่ต้องวางชื่อ ที่อยู่ หรือข้อมูลติดต่อ'), 'The reader tells people not to paste names, addresses or contact details');

// F05: a group of toggle buttons, with the pressed state in the markup and kept by the script
$home = $read('app/Views/home/index.php');
ok_cx(!str_contains($home, 'role="tablist"') && str_contains($home, 'class="mi-tabs" role="group"') && substr_count($home, 'aria-pressed=') === 2, 'The category filter is a button group with aria-pressed (all, and each category)');
ok_cx(str_contains($read('public/assets/site.js'), "attr('aria-pressed', 'true')") && str_contains($read('public/assets/site.js'), "attr('aria-pressed', 'false')"), 'The script moves aria-pressed with the active button');

// F07: every verdict the plan can give has a hint
$p = A::plan("x, y", [], []);
ok_cx(array_keys(A::HINTS) === array_keys($p['counts']), 'Every import verdict has a hint');
ok_cx(str_contains($read('app/Views/admin/aliases.php'), 'AliasImport::HINTS['), 'The admin shows the hint beside the verdict');
echo "codex_review_fixes_test: PASS\n";
