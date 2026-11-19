<?php
declare(strict_types=1);

/**
 * LS-20 B1-B3: every view (public, Admin and sign-in) is free of inline scripts and inline event handlers, so the Content-Security-Policy in
 * public/.htaccess can be switched from report-only to enforcing (docs/CSP_ENFORCE.md) without breaking a page. A browser sweep of 35 pages with a
 * report-uri logger (22 Admin, 13 public) found no violation; this test keeps it that way for new pages.
 */
function ok_csp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

$bad = []; $files = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!str_ends_with($f->getFilename(), '.php')) { continue; }
    $files++; $src = (string)file_get_contents($f->getPathname()); $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($root) + 1);
    preg_match_all('/<script(\s[^>]*)?>/i', $src, $tags);
    foreach ($tags[0] as $t) { if (!preg_match('/\ssrc=|type="application\/(ld\+)?json"/i', $t)) { $bad[] = "$rel: inline <script>"; } }
    if (preg_match('/\son[a-z]+="/i', $src)) { $bad[] = "$rel: inline event handler"; }
    if (preg_match('/href="\s*javascript:/i', $src)) { $bad[] = "$rel: javascript: URL"; }
}
ok_csp($files >= 60, "The scan covered the views ($files files)");
ok_csp($bad === [], "A view carries inline script that an enforcing CSP would block:\n  " . implode("\n  ", $bad));

// no script or style is loaded from another host by any layout
foreach (['header', 'footer', 'admin_header', 'admin_footer', 'auth_header', 'auth_footer'] as $l) {
    ok_csp(!preg_match('#<(script|link)[^>]+(src|href)="https?://#i', (string)file_get_contents($root . "/app/Views/layouts/$l.php")), "$l.php loads no script or style from another host");
}

// the policy: report-only now, with the directives the sweep was run against; the enforce note exists
$ht = (string)file_get_contents($root . '/public/.htaccess');
ok_csp(preg_match('/Content-Security-Policy-Report-Only "(default-src \'self\'; script-src \'self\';[^"]*)"/', $ht, $m) === 1, 'The report-only policy is in .htaccess');
foreach (["object-src 'none'", "base-uri 'self'", "frame-ancestors 'self'", "connect-src 'self'"] as $d) { ok_csp(str_contains($m[1], $d), "The policy has $d"); }
ok_csp(!str_contains($m[1], 'unsafe-eval') && !preg_match("/script-src[^;]*unsafe-inline/", $m[1]), 'Scripts allow neither eval nor inline code');
$note = (string)file_get_contents($root . '/docs/CSP_ENFORCE.md');
ok_csp(str_contains($note, 'Content-Security-Policy-Report-Only') && str_contains($note, 'Rollback') && str_contains($m[1], "script-src 'self'"), 'The enforce note names the header to change and how to roll back');
echo "ls20_csp_ready_test: PASS ($files views scanned)\n";
