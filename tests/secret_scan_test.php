<?php
declare(strict_types=1);

/** The repository must be clean, and the scanner must actually catch what it claims to catch. */

function assert_scan(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$script = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/cli/secret_scan.php');

// 1. The project itself is clean.
exec($script . ' 2>&1', $out, $code);
assert_scan($code === 0, "Repository secret scan failed:\n" . implode("\n", $out));

// 2. Self-test: fake secrets (built at runtime so this file never contains a scannable token) must be caught,
//    and the report must not echo the value.
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mi-secret-' . bin2hex(random_bytes(4));
mkdir($dir);
$fakeGoogle = 'AIza' . str_repeat('a1B2', 9);
$fakePassword = 'Zq' . bin2hex(random_bytes(6));
file_put_contents($dir . '/leak.php', "<?php \$k = '{$fakeGoogle}';\n");
file_put_contents($dir . '/config.env', "DB_PASSWORD={$fakePassword}\n");
file_put_contents($dir . '/ok.env', "DB_PASSWORD=\nGEMINI_API_KEY=<your key>\n");
$report = [];
exec($script . ' --path=' . escapeshellarg($dir) . ' 2>&1', $report, $code);
$text = implode("\n", $report);
assert_scan($code === 1, 'Scanner must fail on a planted key and password');
assert_scan(str_contains($text, 'leak.php') && str_contains($text, 'google-api-key'), 'Planted Google-style key not reported');
assert_scan(str_contains($text, 'config.env') && str_contains($text, 'env-style-secret'), 'Planted DB_PASSWORD not reported');
assert_scan(!str_contains($text, 'ok.env'), 'Empty/placeholder values must not be reported');
assert_scan(!str_contains($text, $fakeGoogle) && !str_contains($text, $fakePassword), 'Scanner must never print the secret value');

foreach (glob($dir . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($dir);

echo "Secret scan test passed\n";
