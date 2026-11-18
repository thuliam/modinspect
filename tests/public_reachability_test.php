<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-14 Module 1: no orphan public page. Over real HTTP on the ISOLATED test database, a visitor who starts on the home page and only follows
 * links must be able to reach every public tool: price search, a product page, Deal Checker, Compare (index and per product), the condition guide
 * (index and per category), the build page and the methodology. Before LS-14 /compare was linked from nowhere and the condition guide only from a
 * product page. The crawl is breadth-first, GET only, same host, bounded.
 */
function ok_pr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
ok_pr($socket !== false, 'No free port');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line; }
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-pr-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
ok_pr(str_contains((string)file_get_contents($envFile), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');

/** Path of a same-host link, query kept, fragment dropped; null for anything else. */
function pr_path(string $href, string $base): ?string
{
    if ($href === '' || $href[0] === '#' || preg_match('#^(mailto|tel|javascript):#i', $href)) { return null; }
    if (str_starts_with($href, $base)) { $href = substr($href, strlen($base)); }
    if (!str_starts_with($href, '/') || str_starts_with($href, '//')) { return null; }
    $href = explode('#', $href)[0];
    return $href === '' ? '/' : $href;
}

$process = null;
try {
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-pr-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-pr-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    ok_pr(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_pr($ready, 'PHP test server did not start');

    // a few pages of each shape are enough (/price/{slug}, /price?q=..), so the bounded crawl reaches every kind of page instead of 80 product pages
    $shapeCount = []; $shape = static fn(string $p): string => preg_replace('#^(/[a-z-]+)/[^/?]+$#', '$1/{x}', explode('?', $p)[0]) ?? $p;
    $queue = ['/']; $seen = ['/' => true]; $found = []; $fetched = 0;
    while ($queue && $fetched < 80) {
        $path = array_shift($queue);
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $html = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        $fetched++;
        if ($code !== 200 || $html === '') { continue; }
        $found[] = $path;
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        foreach ($dom->getElementsByTagName('a') as $a) {
            $p = pr_path(html_entity_decode($a->getAttribute('href')), $base);
            // stay on public pages; one product page and one guide category are enough to go deeper
            if ($p === null || isset($seen[$p]) || preg_match('#^/(admin|login|logout|assets|health|robots|sitemap)#', $p)) { continue; }
            $seen[$p] = true;
            $k = $shape($p);
            if (($shapeCount[$k] = ($shapeCount[$k] ?? 0) + 1) > 3) { continue; }
            $queue[] = $p;
        }
    }

    $need = [
        'price search' => '#^/price($|\?)#',
        'a product page' => '#^/price/[a-z0-9-]+$#',
        'Deal Checker' => '#^/deal-checker($|\?)#',
        'Compare' => '#^/compare($|\?)#',
        'Compare for one product' => '#^/compare/[a-z0-9-]+$#',
        'condition guide' => '#^/condition-guide$#',
        'condition guide for one category' => '#^/condition-guide/[a-z]+$#',
        'build page' => '#^/build$#',
        'methodology' => '#^/methodology$#',
    ];
    $missing = [];
    foreach ($need as $label => $re) {
        if (!array_filter($found, static fn(string $p): bool => preg_match($re, $p) === 1)) { $missing[] = $label; }
    }
    ok_pr($missing === [], 'Not reachable by links from the home page: ' . implode(', ', $missing) . ' (crawled ' . count($found) . ' pages)');
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    @unlink($envFile);
}

echo 'public_reachability_test: PASS (' . count($found) . " pages crawled from the home page, every public tool reachable)\n";
