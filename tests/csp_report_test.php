<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Security\CspReport;

/**
 * LS-21: the Content-Security-Policy is enforcing, so a blocked thing must be findable afterwards. /csp-report turns a browser report into ONE log line.
 * Hostile input must not forge log lines, fill the log or carry a query string (search terms, share links) into it.
 */
function ok_cr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

$good = json_encode(['csp-report' => ['document-uri' => 'https://x.test/price/nvidia-geforce-rtx-3060?q=secret+term#top', 'blocked-uri' => 'https://evil.test/a.js?token=abc', 'effective-directive' => 'script-src-elem', 'violated-directive' => 'script-src', 'disposition' => 'enforce']]);
$l = CspReport::line($good);
ok_cr($l === '[modinspect-csp] directive=script-src-elem blocked=https://evil.test/a.js page=https://x.test/price/nvidia-geforce-rtx-3060 disposition=enforce', 'A report becomes one line with directive, blocked source and page path: ' . var_export($l, true));
ok_cr(!str_contains((string)$l, 'secret') && !str_contains((string)$l, 'token') && !str_contains((string)$l, '?'), 'No query string reaches the log');
ok_cr(str_contains((string)CspReport::line(json_encode(['csp-report' => ['document-uri' => 'https://x.test/', 'blocked-uri' => 'inline', 'violated-directive' => 'script-src']])), 'blocked=inline') , 'Keywords such as inline are kept as they are; the older violated-directive field is used when the effective one is missing');
ok_cr(CspReport::line('') === null && CspReport::line('not json') === null && CspReport::line('{"a":1}') === null && CspReport::line('[]') === null && CspReport::line('{"csp-report":"x"}') === null && CspReport::line('{"csp-report":{"blocked-uri":"inline"}}') === null, 'Anything that is not a CSP report with a directive is ignored');
ok_cr(CspReport::line(str_repeat('a', CspReport::MAX_BODY + 1)) === null, 'A body over the cap is ignored');
$forged = CspReport::line(json_encode(['csp-report' => ['document-uri' => "https://x.test/a\n[modinspect-csp] directive=fake blocked=fake page=fake", 'blocked-uri' => "inline\r\nFORGED", 'effective-directive' => "script-src\n", 'disposition' => "enforce\x00"]]));
ok_cr($forged !== null && substr_count($forged, "\n") === 0 && substr_count($forged, "\r") === 0 && substr_count($forged, "\0") === 0 && substr_count($forged, '[modinspect-csp]') === 1, 'Line breaks and control characters cannot forge a second log line: ' . var_export($forged, true));
$long = CspReport::line(json_encode(['csp-report' => ['document-uri' => 'https://x.test/' . str_repeat('p', 1300), 'blocked-uri' => 'https://e.test/' . str_repeat('q', 1300), 'effective-directive' => str_repeat('d', 400)]]));
ok_cr($long !== null && strlen($long) < 600, 'Every field is cut short (' . strlen((string)$long) . ' characters)');
ok_cr(str_contains((string)CspReport::line(json_encode(['csp-report' => ['document-uri' => 'https://x.test/ค้นหา', 'blocked-uri' => 'data', 'effective-directive' => 'img-src']])), 'ค้นหา'), 'Thai in a path survives');

// the endpoint
$ctl = (string)file_get_contents($root . '/app/Controllers/SecurityController.php');
ok_cr(str_contains($ctl, 'http_response_code(204)') && str_contains($ctl, "attempt('csp-report'") && str_contains($ctl, ', 60, 600)') && str_contains($ctl, 'CspReport::MAX_BODY + 1') && str_contains($ctl, 'error_log($line)'), 'The endpoint answers 204, is rate limited, reads a capped body and only writes one log line');
ok_cr(!preg_match('/\\$_(GET|POST|SESSION|COOKIE)|Database|file_put_contents|INSERT|setcookie/i', $ctl), 'It stores nothing and touches no session, cookie or database');
ok_cr(str_contains((string)file_get_contents($root . '/public/index.php'), "\$router->post('/csp-report',[SecurityController::class,'cspReport']);"), 'The route exists');
ok_cr(str_contains((string)file_get_contents($root . '/public/.htaccess'), '; report-uri /csp-report"'), 'The policy points browsers at it');
echo "csp_report_test: PASS\n";
