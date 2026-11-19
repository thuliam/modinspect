<?php
declare(strict_types=1);

/**
 * LS-14 3.4: post-deploy smoke check. GET requests only, nothing is written, no cookies or credentials are sent.
 *   php cli/smoke_check.php https://modinspect.thuliamdev.com [--links]
 * --links (LS-17 A1) also follows every internal link found on the key pages and the sitemap (product pages included) and fails on any that does not answer 200 or a redirect.
 * Exit 0 when every check passes, 1 otherwise. The same list the deploy workflow runs (.github/workflows/ftps-deploy.yml, job "smoke").
 * Checks: key public pages answer 200, /admin sends a visitor to the login (302), secrets and private files are not served, no PHP warning or
 * stack trace shows on a page, the health endpoint says ok, robots.txt keeps the private areas out, the optional features are off unless enabled.
 */
$args = array_slice($argv, 1); $links = in_array('--links', $args, true); $args = array_values(array_diff($args, ['--links']));
$base = rtrim((string)($args[0] ?? ''), '/');
if (!preg_match('#^https?://[a-z0-9.\-:]+(/[a-z0-9._\-/]*)?$#i', $base)) { fwrite(STDERR, "Usage: php cli/smoke_check.php https://host[/base]\n"); exit(2); }

/** @return array{code:int,body:string,location:string} */
function smoke_get(string $url, bool $head = false): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => $head, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'modinspect-smoke/1']);
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    preg_match('/^Location:\s*(.+)$/mi', substr($raw, 0, $hs), $m);
    return ['code' => $code, 'body' => substr($raw, $hs), 'location' => trim($m[1] ?? '')];
}

$fail = 0; $pass = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$fail, &$pass): void {
    if ($ok) { $pass++; echo "  ok    $label\n"; } else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? " ($detail)" : '') . "\n"; }
};

foreach (['/', '/price', '/compare', '/condition-guide', '/deal-checker', '/build', '/methodology', '/login', '/robots.txt', '/sitemap.xml', '/manifest.webmanifest', '/favicon.ico'] as $p) {
    $r = smoke_get($base . $p);
    $check("GET $p is 200", $r['code'] === 200, 'got ' . $r['code']);
    if (str_contains((string)$r['body'], '<html') || str_contains((string)$r['body'], '<!doctype')) {
        $check("$p shows no PHP warning or trace", !preg_match('/(Warning|Fatal error|Notice|Deprecated|Stack trace):|on line \d+|\.php:\d+/', $r['body']));
    }
}
// LS-17 A1: uptime monitors and link checkers use HEAD; it was a 404 on every page before LS-16
foreach (['/', '/price'] as $p) { $r = smoke_get($base . $p, true); $check("HEAD $p is 200", $r['code'] === 200, 'got ' . $r['code']); }
$a = smoke_get($base . '/admin');
$check('/admin sends a visitor to the login', $a['code'] === 302 && str_contains($a['location'], '/login'), 'got ' . $a['code'] . ' ' . $a['location']);
$hl = smoke_get($base . '/health');
$check('/health says ok', $hl['code'] === 200 && str_contains($hl['body'], 'ok'), 'got ' . $hl['code']);
foreach (['/.env', '/.git/config', '/app/Core/Database.php', '/config/app.php', '/storage/logs/', '/composer.json', '/database/schema.sql', '/modinspect.sql'] as $p) {
    $r = smoke_get($base . $p);
    $check("$p is not served", in_array($r['code'], [301, 302, 403, 404], true) && !str_contains((string)$r['body'], 'DB_PASSWORD') && !str_contains((string)$r['body'], '<?php'), 'got ' . $r['code']);
}
$robots = smoke_get($base . '/robots.txt')['body'];
$check('robots.txt keeps /admin and /account out', str_contains($robots, 'Disallow: /admin') && str_contains($robots, 'Disallow: /account'));
$sitemap = smoke_get($base . '/sitemap.xml')['body'];
$check('the sitemap is XML and lists no private path', str_starts_with(trim($sitemap), '<?xml') && !preg_match('#/(admin|account|login|seller)#', $sitemap));
if ($links) {
    // same-origin links only, from the key pages and the sitemap; private areas, sign-in and POST-only paths are skipped, at most 400 URLs
    $basePath = (string)parse_url($base, PHP_URL_PATH); $origin = substr($base, 0, strlen($base) - strlen($basePath));
    // non-page files (manifest, sitemap, robots) are checked by name in the page list above; the crawler follows pages only
    $todo = []; $skip = '#/(admin|account|login|logout|seller)(/|$|\?)|\.(png|jpe?g|webp|svg|ico|css|js|woff2?|pdf|webmanifest|xml|txt)(\?|$)#i';
    $add = static function (string $href) use (&$todo, $base, $basePath, $origin, $skip): void {
        $href = html_entity_decode(trim($href)); if ($href === '' || $href[0] === '#' || preg_match('#^(mailto|tel|javascript):#i', $href)) { return; }
        if (str_starts_with($href, '/')) { $href = $origin . $href; }
        if (!str_starts_with($href, $base . '/') && $href !== $base) { return; }
        $href = preg_replace('/#.*$/', '', $href); if (preg_match($skip, substr($href, strlen($base)))) { return; }
        $todo[$href] = true;
    };
    foreach (['/', '/price', '/compare', '/condition-guide', '/deal-checker', '/build', '/methodology'] as $p) {
        preg_match_all('/href="([^"]+)"/i', smoke_get($base . $p)['body'], $m); foreach ($m[1] as $h) { $add($h); }
    }
    preg_match_all('#<loc>([^<]+)</loc>#', $sitemap, $m);
    foreach ($m[1] as $loc) { $add(str_replace((string)parse_url($loc, PHP_URL_SCHEME) . '://' . (string)parse_url($loc, PHP_URL_HOST) . (parse_url($loc, PHP_URL_PORT) ? ':' . parse_url($loc, PHP_URL_PORT) : ''), $origin, $loc)); }
    $bad = [];
    foreach (array_slice(array_keys($todo), 0, 400) as $u) { $c = smoke_get($u)['code']; if (!in_array($c, [200, 301, 302], true)) { $bad[] = "$c " . substr($u, strlen($base)); } }
    $check('every internal link answers (' . min(400, count($todo)) . ' checked)', $bad === [], implode(', ', array_slice($bad, 0, 8)));
}
// optional features: closed unless the owner opened them (a 200 here is not a failure, it only says what is on)
foreach (['/seller-check' => 'seller check (HG-14)', '/account/login' => 'member sign-in (HG-13)'] as $p => $label) {
    $r = smoke_get($base . $p); echo "  info  $label is " . ($r['code'] === 404 ? 'OFF' : 'reachable') . " ($p: {$r['code']})\n";
}
echo "\n" . ($fail === 0 ? "SMOKE PASS ($pass checks)\n" : "SMOKE FAIL ($fail of " . ($pass + $fail) . " checks failed)\n");
exit($fail === 0 ? 0 : 1);
