<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Seo\SeoService as S;

/**
 * Pilot mode (docs/PILOT_SCOPE_TH.md, owner said "pilot" on 2026-11-17): a closed group tries the site, so it must not be indexed and every page says plainly what
 * the data can and cannot do. One switch, APP_PILOT (default off), drives all of it:
 *  - robots.txt disallows everything and names no sitemap, the sitemap is empty;
 *  - every response carries X-Robots-Tag, every page a robots meta with noindex, nofollow, noarchive;
 *  - a notice with the three statements of the pilot document sits above every public page.
 * With the switch off nothing changes. Pure (no database).
 */
function ok_pm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// the switch: off by default, on only for a true value
$root = dirname(__DIR__);
$conf = static function (?string $v) use ($root): bool {
    unset($_ENV['APP_PILOT']); if ($v !== null) { $_ENV['APP_PILOT'] = $v; }
    $c = require $root . '/config/app.php';
    unset($_ENV['APP_PILOT']);
    return (bool)($c['pilot'] ?? null);
};
ok_pm($conf(null) === false && $conf('false') === false && $conf('0') === false && $conf('') === false, 'Off when unset, false, 0 or empty');
ok_pm($conf('1') === true && $conf('true') === true, 'On for 1 and true');

// robots.txt and sitemap
$r = S::robotsTxt('https://example.org/app/', true);
ok_pm($r === "User-agent: *\nDisallow: /\n", 'Pilot robots.txt disallows everything and names no sitemap: ' . json_encode($r));
ok_pm(S::robotsTxt('https://example.org/app/') === S::robotsTxt('https://example.org/app/', false) && str_contains(S::robotsTxt('https://example.org/app/'), 'Allow: /') && str_contains(S::robotsTxt('https://example.org/app/'), 'Sitemap:'), 'With the switch off robots.txt is exactly what it was');
$empty = S::pilotSitemapXml();
ok_pm(simplexml_load_string($empty) !== false && !str_contains($empty, '<url>') && str_contains($empty, 'urlset'), 'The pilot sitemap is valid XML with no urls: ' . $empty);

// the pieces are wired
$ctl = (string)file_get_contents($root . '/app/Controllers/SeoController.php');
ok_pm(str_contains($ctl, "\$config['pilot']") && substr_count($ctl, "\$config['pilot']") >= 2, 'robots and sitemap both read the switch');
$boot = (string)file_get_contents($root . '/app/bootstrap.php');
ok_pm(preg_match("/if\s*\(\s*!empty\(\s*\\\$config\['pilot'\]\s*\)\s*\)\s*\{?\s*header\('X-Robots-Tag: noindex, nofollow, noarchive'\)/", $boot) === 1 || (str_contains($boot, "X-Robots-Tag: noindex, nofollow, noarchive") && str_contains($boot, "'pilot'")), 'Every response carries X-Robots-Tag when the switch is on');
$head = (string)file_get_contents($root . '/app/Views/layouts/header.php');
ok_pm(str_contains($head, 'noindex,nofollow,noarchive') && str_contains($head, "\$config['pilot']"), 'Every page has the robots meta with noarchive when the switch is on');
ok_pm(substr_count($head, '<meta name="robots"') + substr_count($head, 'name=\"robots\"') === 2, 'Exactly two places print a robots meta (pilot, and the existing private paths), and never both');
ok_pm(str_contains($head, 'components/pilot-banner.php'), 'The header includes the pilot notice');
$ban = (string)file_get_contents($root . '/app/Views/components/pilot-banner.php');
foreach (['ความมั่นใจยังต่ำ', 'ประกอบครบชุดยังไม่ได้', 'ไม่ได้วัดความแรง', 'ทดลอง'] as $s) { ok_pm(str_contains($ban, $s), "The pilot notice says: $s"); }
ok_pm(!preg_match('/https?:|@|\b0\d{9}\b/', $ban), 'The notice carries no link, address or phone-like number');
ok_pm(str_contains((string)file_get_contents($root . '/.env.example'), 'APP_PILOT='), '.env.example documents the switch');
echo "pilot_mode_test: PASS\n";
