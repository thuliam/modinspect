<?php
declare(strict_types=1);

/**
 * The main menu carries the PC build page (owner request 2026-11-17): the primary navigation in the header and the "เมนูหลัก" list in the footer both link to
 * /build with the label "ประกอบคอม", the header link uses the same active-link helper as its neighbours, and the tools sit together (Deal Checker, then the
 * build page, then the methodology page).
 */
function ok_mm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__) . '/app/Views/layouts/';
$header = (string)file_get_contents($root . 'header.php');
$footer = (string)file_get_contents($root . 'footer.php');

preg_match('#<nav[^>]*aria-label="Primary navigation".*?</nav>#s', $header, $m);
ok_mm(isset($m[0]), 'The primary navigation is found in the header');
$nav = $m[0];
ok_mm(preg_match('#<a class="nav-link<\?= \$navCurrent\(\'/build\'\) \?>" href="<\?= \$base \?>/build">ประกอบคอม</a>#u', $nav) === 1, 'The header menu links to /build with the label ประกอบคอม and the active-link helper');
$pos = static fn(string $needle): int|false => strpos($nav, $needle);
ok_mm($pos('/deal-checker') < $pos('/build') && $pos('/build') < $pos('/methodology'), 'Order: Deal Checker, then the build page, then the methodology page');
ok_mm(substr_count($nav, 'href="<?= $base ?>/build"') === 1, 'Exactly one build link in the header menu');

preg_match('#<h4[^>]*>เมนูหลัก</h4>\s*<ul class="footer-links">(.*?)</ul>#su', $footer, $f);
ok_mm(isset($f[1]), 'The footer main-menu list is found');
ok_mm(str_contains($f[1], 'href="<?= $base ?>/build">ประกอบคอม</a>'), 'The footer main-menu list links to /build with the same label');
ok_mm(strpos($f[1], '/deal-checker') < strpos($f[1], '/build') && strpos($f[1], '/build') < strpos($f[1], '/methodology'), 'Footer order matches the header');
echo "main_menu_test: PASS\n";
