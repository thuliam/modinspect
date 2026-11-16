<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Heading order on the public pages (LS-3 J-11), over real HTTP on the ISOLATED test database: exactly one h1 and no jump of more than one level
 * going down the page (an `aria-level` counts, it is how the footer headings are placed without changing their look). A heading that comes after
 * a deeper one may go back up by any amount. The test also proves the checker works by feeding it a page that does skip.
 */
function ok_hl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

/** @return array{h1:int,skips:list<string>} */
function hl_check(string $html): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xp = new DOMXPath($dom);
    $levels = []; $h1 = 0;
    foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6|//*[@role="heading"]') as $h) {
        $level = $h->hasAttribute('aria-level') ? (int)$h->getAttribute('aria-level') : (int)substr($h->nodeName, 1);
        $levels[] = [$level, trim(preg_replace('/\s+/u', ' ', $h->textContent) ?? '')];
        if ($level === 1) { $h1++; }
    }
    $skips = [];
    for ($i = 1; $i < count($levels); $i++) {
        if ($levels[$i][0] - $levels[$i - 1][0] > 1) { $skips[] = 'h' . $levels[$i - 1][0] . ' "' . mb_substr($levels[$i - 1][1], 0, 20) . '" -> h' . $levels[$i][0] . ' "' . mb_substr($levels[$i][1], 0, 20) . '"'; }
    }
    return ['h1' => $h1, 'skips' => $skips];
}

// the checker itself
$bad = hl_check('<h1>A</h1><h3>B</h3>');
ok_hl($bad['skips'] !== [] && $bad['h1'] === 1, 'The checker flags h1 -> h3');
ok_hl(hl_check('<h1>A</h1><h2>B</h2><h4 aria-level="3">C</h4>')['skips'] === [], 'aria-level is honoured');
ok_hl(hl_check('<h1>A</h1><h2>B</h2><h3>C</h3><h2>D</h2>')['skips'] === [], 'Going back up is allowed');
ok_hl(hl_check('<h1>A</h1><h2>B</h2><h4>C</h4>')['skips'] !== [], 'An h2 followed by an h4 is flagged');

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
ok_hl($socket !== false, 'No free port');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line; }
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-hl-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
ok_hl(str_contains((string)file_get_contents($envFile), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');
$process = null;
try {
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-hl-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-hl-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    ok_hl(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_hl($ready, 'PHP test server did not start');

    $db = \App\Core\Database::connection($GLOBALS['config']['db']);
    $slug = (string)$db->query("SELECT slug FROM products WHERE is_active=1 ORDER BY is_popular DESC, id LIMIT 1")->fetchColumn();
    $problems = []; $checked = 0;
    foreach (['/', '/price', '/price?q=zzzzqqqq', '/price/' . $slug, '/deal-checker', '/methodology', '/build', '/compare', '/guide/gpu-checklist'] as $path) {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $html = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($code !== 200) { $problems[] = "$path answered $code"; continue; }
        $r = hl_check($html); $checked++;
        if ($r['h1'] !== 1) { $problems[] = "$path has {$r['h1']} h1"; }
        foreach ($r['skips'] as $s) { $problems[] = "$path: $s"; }
    }
    ok_hl($checked >= 8, "At least 8 public pages were checked ($checked)");
    ok_hl($problems === [], "Heading problems:\n  " . implode("\n  ", $problems));
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    @unlink($envFile);
}

echo "heading_levels_test: PASS ($checked public pages: one h1 and no skipped level)\n";
