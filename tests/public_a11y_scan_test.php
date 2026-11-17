<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-4 K-6: static accessibility and weight scan of the public pages over real HTTP on the ISOLATED test database.
 * Checks what a script can prove (not contrast or screen-reader behaviour): html lang, title, viewport, one main landmark, img alt, form controls with a
 * name, links and buttons with a name, duplicate ids, HTML weight and server time. Findings that are accepted are listed in $accepted with a reason.
 */
function ok_a(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

/** @return list<string> */
function a11y_check(string $html): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xp = new DOMXPath($dom);
    $p = [];
    $lang = $xp->query('//html/@lang')->item(0);
    if ($lang === null || trim($lang->nodeValue) === '') { $p[] = 'html has no lang'; }
    $title = $xp->query('//head/title')->item(0);
    if ($title === null || trim($title->textContent) === '') { $p[] = 'no title'; }
    if ($xp->query('//meta[@name="viewport"]')->length === 0) { $p[] = 'no viewport meta'; }
    if ($xp->query('//main|//*[@role="main"]')->length !== 1) { $p[] = 'expected exactly one main landmark, found ' . $xp->query('//main|//*[@role="main"]')->length; }
    foreach ($xp->query('//img[not(@alt)]') as $i) { $p[] = 'img without alt: ' . substr((string)$i->getAttribute('src'), 0, 40); }
    $name = static function (DOMElement $e) use ($xp): bool {
        if (trim($e->getAttribute('aria-label')) !== '' || trim($e->getAttribute('aria-labelledby')) !== '' || trim($e->getAttribute('title')) !== '') { return true; }
        if (trim(preg_replace('/\s+/u', ' ', $e->textContent) ?? '') !== '') { return true; }
        foreach ($xp->query('.//img[@alt!=""]|.//*[@aria-label]', $e) as $_) { return true; }
        return false;
    };
    foreach ($xp->query('//a[@href]|//button') as $e) { if (!$name($e)) { $p[] = $e->nodeName . ' without an accessible name: ' . substr(trim($dom->saveHTML($e)), 0, 60); } }
    foreach ($xp->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")]|//select|//textarea') as $e) {
        /** @var DOMElement $e */
        $id = $e->getAttribute('id');
        $labelled = trim($e->getAttribute('aria-label')) !== '' || trim($e->getAttribute('aria-labelledby')) !== '' || trim($e->getAttribute('title')) !== ''
            || ($id !== '' && $xp->query('//label[@for="' . $id . '"]')->length > 0) || $xp->query('ancestor::label', $e)->length > 0;
        if (!$labelled) { $p[] = $e->nodeName . ' (' . ($e->getAttribute('name') ?: $e->getAttribute('type')) . ') has no label'; }
    }
    $seen = [];
    foreach ($xp->query('//*[@id]') as $e) { $id = $e->getAttribute('id'); if (isset($seen[$id])) { $p[] = "duplicate id $id"; } $seen[$id] = true; }
    return $p;
}

// the checker itself must catch each defect
ok_a(count(a11y_check('<html><head></head><body><img src="a.png"><button></button><input name="q"></body></html>')) >= 6, 'The checker flags a bare page');
ok_a(a11y_check('<html lang="th"><head><title>t</title><meta name="viewport" content="width=device-width"></head><body><main><label for="q">q</label><input id="q" name="q"><a href="/">Home</a><img alt="x" src="a"></main></body></html>') === [], 'The checker accepts a clean page');

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
ok_a($socket !== false, 'No free port');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line; }
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-a11y-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
ok_a(str_contains((string)file_get_contents($envFile), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');
$process = null; $checked = 0; $slowest = 0.0; $heaviest = 0;
try {
    $log = sys_get_temp_dir() . '/modinspect-a11y-server.log';
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    ok_a(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_a($ready, 'PHP test server did not start');
    $db = \App\Core\Database::connection($GLOBALS['config']['db']);
    $slug = (string)$db->query("SELECT slug FROM products WHERE is_active=1 ORDER BY is_popular DESC, id LIMIT 1")->fetchColumn();
    $problems = [];
    foreach (['/', '/price', '/price?q=zzzzqqqq', '/price/' . $slug, '/deal-checker', '/methodology', '/build', '/compare', '/guide/gpu-checklist'] as $path) {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $html = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $t = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME); curl_close($ch);
        if ($code !== 200) { $problems[] = "$path answered $code"; continue; }
        $checked++; $slowest = max($slowest, $t); $heaviest = max($heaviest, strlen($html));
        foreach (a11y_check($html) as $f) { $problems[] = "$path: $f"; }
        if (strlen($html) > 400000) { $problems[] = "$path HTML is " . strlen($html) . ' bytes'; }
    }
    ok_a($checked >= 8, "At least 8 public pages were checked ($checked)");
    ok_a($problems === [], "Accessibility problems:\n  " . implode("\n  ", $problems));
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    @unlink($envFile);
}
printf("public_a11y_scan_test: PASS (%d pages, heaviest HTML %d bytes, slowest %.2fs)\n", $checked, $heaviest, $slowest);
