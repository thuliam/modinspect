<?php
declare(strict_types=1);

/**
 * The main menu carries the PC build page (owner request 2026-11-17): the primary navigation in the header and the "เมนูหลัก" list in the footer both link to
 * /build with the label "ประกอบคอม". LS-34: the header menu is two grouped dropdowns (เช็กราคา, เครื่องมือ) built from one list, so the checks read that list: every group item
 * is rendered with the same markup, the tools are in the เครื่องมือ group (Deal Checker, then the build page) and the methodology page sits in the เช็กราคา group.
 */
function ok_mm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__) . '/app/Views/layouts/';
$header = (string)file_get_contents($root . 'header.php');
$footer = (string)file_get_contents($root . 'footer.php');

preg_match('#<nav[^>]*aria-label="Primary navigation".*?</nav>#s', $header, $m);
ok_mm(isset($m[0]), 'The primary navigation is found in the header');
$nav = $m[0];
ok_mm(str_contains($nav, "['ประกอบคอม', 'จัดสเปกคอมมือสอง', '/build', 'fa-tools']"), 'The header menu lists /build with the label ประกอบคอม');
ok_mm(str_contains($nav, '<a class="mi-mega-item" href="<?= $base . $it[2] ?>">'), 'Every grouped item is one link built from the list');
$pos = static fn(string $needle): int|false => strpos($nav, $needle);
ok_mm($pos("'เครื่องมือ'") < $pos('/deal-checker') && $pos('/deal-checker') < $pos("'/build'"), 'Order inside the tools group: Deal Checker, then the build page');
ok_mm($pos("'เช็กราคา'") < $pos("'/methodology'") && $pos("'/methodology'") < $pos("'เครื่องมือ'"), 'The methodology page sits in the price menu');
ok_mm(substr_count($nav, "'/build'") === 1, 'Exactly one build entry in the header menu');
ok_mm(str_contains($nav, 'data-toggle="dropdown"') && str_contains($nav, 'aria-haspopup="true"'), 'The grouped menus are Bootstrap dropdowns (click and keyboard work without a script of our own)');
preg_match('#<h4[^>]*>เมนูหลัก</h4>\s*<ul class="footer-links">(.*?)</ul>#su', $footer, $f);
ok_mm(isset($f[1]), 'The footer main-menu list is found');
ok_mm(str_contains($f[1], 'href="<?= $base ?>/build">ประกอบคอม</a>'), 'The footer main-menu list links to /build with the same label');
ok_mm(strpos($f[1], '/deal-checker') < strpos($f[1], '/build') && strpos($f[1], '/build') < strpos($f[1], '/methodology'), 'Footer order: Deal Checker, build page, methodology');

// LS-14 Module 1: Compare and the condition guide are main-menu tools (they were linked from nowhere / one place); the menu collapses below 1200 px so Thai labels never break
ok_mm(str_contains($nav, "['เทียบรุ่น', 'เทียบสเปกและราคา', '/compare'") && str_contains($nav, "['เช็กสภาพ', 'ต้องเช็กอะไรก่อนจ่ายเงิน', '/condition-guide'"), 'The header menu lists Compare and the condition guide');
ok_mm(str_contains($f[1], 'href="<?= $base ?>/compare">เทียบรุ่น</a>') && str_contains($f[1], 'href="<?= $base ?>/condition-guide">เช็กสภาพ</a>'), 'The footer main menu links to Compare and the condition guide');
ok_mm(str_contains($nav, 'navbar-expand-xl') && str_contains($nav, 'text-nowrap'), 'The header menu expands only from 1200 px and never wraps a label');
foreach (['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'case', 'cooling'] as $cat) {
    ok_mm(str_contains($footer, '/price?category=' . $cat . '"'), "The footer lists the $cat category");
}
echo "main_menu_test: PASS\n";
