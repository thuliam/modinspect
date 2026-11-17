<?php
declare(strict_types=1);

/**
 * LS-7 N-4: check a production .env before going live (beta checklist C-4). Read-only. Prints check names, pass/warn/fail and a plain reason; never a value.
 * Run it on the host, where the real .env lives:
 *   php cli/env_preflight.php [--env-file=.env]
 * Exit 0 = PASS or PASS_WITH_WARNINGS, 1 = FAIL, 2 = file not found.
 */
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Services/Ops/EnvPreflight.php';

use App\Services\Ops\EnvPreflight;

$file = '.env';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--env-file=')) { $file = substr($arg, 11); }
}
$path = preg_match('#^([A-Za-z]:)?[\\\\/]#', $file) === 1 ? $file : ROOT_PATH . '/' . $file;
if (!is_file($path) || !is_readable($path)) { fwrite(STDERR, "Env file not found or not readable: " . basename($file) . "\n"); exit(2); }

$env = [];
foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $env[$k] = trim($v, "\"'");   // same parsing as app/bootstrap.php
}
$mode = null;
if (DIRECTORY_SEPARATOR === '/') { $perms = fileperms($path); $mode = $perms === false ? null : ($perms & 0777); }
$result = EnvPreflight::evaluate($env, ['file_mode' => $mode, 'public_env_exists' => is_file(ROOT_PATH . '/public/.env')]);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
exit($result['verdict'] === 'FAIL' ? 1 : 0);
