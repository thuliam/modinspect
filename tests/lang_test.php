<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\LangController;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\RedirectException;

/**
 * Admin language (TH default, EN): the two text files have the same keys and no empty text, "{param}" slots match, Thai is the default and the fallback,
 * an unknown language is refused, the switch is POST + CSRF only and can only go back to a local path, and every key the views/controllers/menus use exists.
 * Pure file/in-process checks, no database.
 */
function ok_lg(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

$th = Lang::all('th'); $en = Lang::all('en');
ok_lg(count($th) > 100, 'The Thai file loaded (' . count($th) . ' keys)');
$onlyTh = array_diff(array_keys($th), array_keys($en)); $onlyEn = array_diff(array_keys($en), array_keys($th));
ok_lg($onlyTh === [] && $onlyEn === [], 'Both languages have the same keys. Only TH: ' . implode(', ', $onlyTh) . ' Only EN: ' . implode(', ', $onlyEn));
foreach ($th as $k => $v) {
    ok_lg(trim($v) !== '' && trim($en[$k]) !== '', "Key $k has text in both languages");
    preg_match_all('/\{(\w+)\}/', $v, $a); preg_match_all('/\{(\w+)\}/', $en[$k], $b);
    ok_lg(array_unique($a[1]) === array_unique($b[1]), "Key $k uses the same {slots} in both languages");
}
// the Thai file is Thai: most texts contain a Thai letter (technical words and the language buttons may not)
$thai = 0; foreach ($th as $v) { if (preg_match('/\p{Thai}/u', $v)) { $thai++; } }
ok_lg($thai / count($th) > 0.9, 'The Thai file is written in Thai (' . $thai . ' of ' . count($th) . ')');

// default, fallback, set, refuse
$_SESSION = []; $_COOKIE = [];
ok_lg(Lang::current() === 'th', 'Thai is the default');
ok_lg(Lang::t('nav.products') === $th['nav.products'], 't() gives Thai by default');
ok_lg(Lang::set('en') && Lang::current() === 'en' && Lang::t('nav.products') === 'Products', 'Switching to English works');
ok_lg(Lang::set('fr') === false && Lang::current() === 'en', 'An unknown language is refused and the choice stays');
$_SESSION = ['lang' => 'xx']; ok_lg(Lang::current() === 'th', 'A tampered session value falls back to Thai');
$_SESSION = []; $_COOKIE = ['mi_lang' => 'en']; ok_lg(Lang::current() === 'en', 'The cookie is used when there is no session choice');
$_COOKIE = ['mi_lang' => '<script>']; ok_lg(Lang::current() === 'th', 'A bad cookie falls back to Thai');
$_COOKIE = []; $_GET = ['lang' => 'en']; ok_lg(Lang::current() === 'th', 'A URL parameter cannot switch the language');
ok_lg(Lang::t('no.such.key') === 'no.such.key', 'A key missing everywhere shows the key');
ok_lg(Lang::t('header.pending_title', ['n' => 7], 'en') === '7 items waiting for review' && str_contains(Lang::t('header.pending_title', ['n' => 7], 'th'), '7'), '{n} is filled in');
ok_lg(Lang::slug('Trends & Locations') === 'trends_locations' && Lang::slug('New-price refs') === 'new_price_refs', 'Menu labels become keys');
ok_lg(t('nav.users') === Lang::t('nav.users'), 'The t() helper is the same function');

// the switch
$call = static function (array $post): ?string {
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $post;
    try { (new LangController())->set(); } catch (RedirectException $e) { return $e->path; }
    return null;
};
$_SESSION = []; $tok = Csrf::token();
ok_lg($call(['_token' => $tok, 'lang' => 'en', 'back' => '/admin/legal-documents?view=3']) === '/admin/legal-documents?view=3' && Lang::current() === 'en', 'A valid switch changes the language and goes back');
$_SESSION['lang'] = 'th';
ok_lg($call(['lang' => 'en', 'back' => '/admin']) === '/admin' && Lang::current() === 'th', 'A switch without a CSRF token changes nothing');
ok_lg($call(['_token' => $tok, 'lang' => 'zz', 'back' => '/admin']) === '/admin' && Lang::current() === 'th', 'An unknown language changes nothing');
foreach (['https://evil.example/x', '//evil.example', '/\\evil.example', 'javascript:alert(1)', '/ok://x', "/a\r\nSet-Cookie: x=1", str_repeat('/a', 200), ['/a'], ''] as $bad) {
    ok_lg($call(['_token' => $tok, 'lang' => 'en', 'back' => $bad]) === '/admin', 'A back address that is not a plain local path goes to /admin: ' . (is_string($bad) ? substr($bad, 0, 20) : 'array'));
}
$index = (string)file_get_contents($root . '/public/index.php');
ok_lg(preg_match("#router->post\\('/lang'#", $index) === 1 && preg_match("#router->get\\('/lang'#", $index) !== 1, '/lang is a POST route only');

// every key used in the code exists
$used = [];
$scan = function (string $dir) use (&$scan, &$used): void {
    foreach (scandir($dir) ?: [] as $f) {
        if ($f[0] === '.') { continue; }
        $p = $dir . '/' . $f;
        if (is_dir($p)) { if ($f !== 'Lang') { $scan($p); } continue; }
        if (!str_ends_with($f, '.php')) { continue; }
        $src = (string)file_get_contents($p);
        if (preg_match_all("/\\bt\\('([a-z0-9_.]+)'/", $src, $m) || preg_match_all("/Lang::t\\('([a-z0-9_.]+)'/", $src, $m)) { foreach ($m[1] as $k) { $used[$k] = $p; } }
        if (preg_match_all("/\\bt\\('([a-z0-9_.]+)'/", $src, $m2)) { foreach ($m2[1] as $k) { $used[$k] = $p; } }
    }
};
$scan($root . '/app');
ok_lg(count($used) > 80, 'Keys used in the code were found (' . count($used) . ')');
// a key ending in "." is the start of one built at run time (t('nav.' . $slug)); those are checked one by one below
$missing = array_filter(array_keys($used), static fn(string $k): bool => !str_ends_with($k, '.') && !isset($th[$k]));
ok_lg($missing === [], 'Every key used in the code exists: ' . implode(', ', $missing));

// keys built at run time: menu labels, group labels, roles, statuses, types
$header = (string)file_get_contents($root . '/app/Views/layouts/admin_header.php');
preg_match_all("/'label' => '([^']+)'/", $header, $labels); preg_match_all("/^    '([A-Z ]+)' => \\[/m", $header, $groups);
ok_lg(count($labels[1]) >= 24 && count($groups[1]) >= 6, 'The menu was parsed (' . count($labels[1]) . ' items, ' . count($groups[1]) . ' groups)');
foreach ($labels[1] as $l) { ok_lg(isset($th['nav.' . Lang::slug($l)]), "Menu item \"$l\" has a text (nav." . Lang::slug($l) . ')'); }
foreach ($groups[1] as $g) { ok_lg(isset($th['navg.' . Lang::slug($g)]), "Menu group \"$g\" has a text"); }
foreach (['admin', 'reviewer', 'operator', 'user'] as $r) { ok_lg(isset($th["role.$r"]), "Role $r has a text"); }
foreach (['received', 'in_progress', 'completed', 'rejected'] as $s) { ok_lg(isset($th["pr.s.$s"]), "Privacy status $s has a text"); }
foreach (\App\Services\Member\PrivacyRequests::TYPES as $s) { ok_lg(isset($th["pr.t.$s"]), "Privacy type $s has a text"); }
foreach (['draft', 'published', 'retired'] as $s) { ok_lg(isset($th["ld.s.$s"]), "Document status $s has a text"); }
foreach (['active', 'disabled'] as $s) { ok_lg(isset($th["member.status.$s"]), "Member status $s has a text"); }

// the pages themselves carry the language attribute and the switch
$login = (string)file_get_contents($root . '/app/Views/auth/login.php');
ok_lg(str_contains($login, 'action="<?= $base ?>/lang"') && str_contains($login, "t('login.sign_in')"), 'The login page has the language switch and translated text');
ok_lg(str_contains($header, 'action="<?= $base ?>/lang"') && str_contains($header, '<html lang="<?= $lang ?>">'), 'The admin header has the switch and a dynamic lang attribute');
// fixed controller messages: both texts present, no text repeated, lookup works from either language
$pairs = Lang::messagePairs(); $seen = [];
ok_lg(count($pairs) > 50, 'The message pairs loaded (' . count($pairs) . ')');
foreach ($pairs as $p) {
    ok_lg(isset($p['en'], $p['th']) && trim($p['en']) !== '' && trim($p['th']) !== '', 'Every message pair has both texts: ' . ($p['en'] ?? '?'));
    ok_lg(preg_match('/\p{Thai}/u', $p['th']) === 1, 'The Thai side of a message is Thai: ' . $p['en']);
    foreach (['en', 'th'] as $l) { ok_lg(!isset($seen[$p[$l]]) || $seen[$p[$l]] === $p['en'], 'A message text belongs to one pair only: ' . $p[$l]); $seen[$p[$l]] = $p['en']; }
}
$_SESSION = []; $_COOKIE = [];
ok_lg(Lang::message('User created.') === 'สร้างผู้ใช้แล้ว' && Lang::message('สร้างผู้ใช้แล้ว') === 'สร้างผู้ใช้แล้ว', 'A fixed message is shown in Thai by default, whichever language the code wrote it in');
Lang::set('en');
ok_lg(Lang::message('สร้างผู้ใช้แล้ว') === 'User created.' && Lang::message('User created.') === 'User created.', 'and in English after the switch');
ok_lg(Lang::message('A text that is not in the list') === 'A text that is not in the list', 'An unknown message is returned unchanged');
$_SESSION = [];
// the texts the page scripts use
$footer = (string)file_get_contents($root . '/app/Views/layouts/admin_footer.php');
preg_match_all("/t\('((?:js|dt)\.[a-z_]+)'\)/", $footer, $jsKeys);
ok_lg(count($jsKeys[1]) >= 20, 'The footer hands the script texts to the page (' . count($jsKeys[1]) . ')');
// page titles and crumbs written in English in the code have a text in both languages
$ctrl = (string)file_get_contents($root . '/app/Controllers/AdminController.php');
preg_match_all("/render\('[a-z-]+','([^']+)'/", $ctrl, $titles);
foreach (array_unique($titles[1]) as $ti) {
    if (str_contains($ti, '#') || preg_match('/[\x{0E01}-\x{0E4E}]/u', $ti)) { continue; }
    ok_lg(Lang::label($ti) !== null, "The page title \"$ti\" has a text (a nav., navg. or crumb. key)");
}
echo "lang_test: PASS (" . count($th) . " keys, " . count($used) . " used)\n";
