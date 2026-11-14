<?php
declare(strict_types=1);

/**
 * Dependency-free secret scan.
 *
 *   php cli/secret_scan.php                 # tracked files (git ls-files); falls back to the project tree without git
 *   php cli/secret_scan.php --path=<dir>    # scan a directory tree (used by the self-test)
 *
 * Exit 0 = clean, 1 = findings, 2 = usage error. Findings print file:line and the rule name, never the matched value.
 * It is a safety net, not a replacement for rotating anything that has ever been exposed.
 */

$root = dirname(__DIR__);
$options = getopt('', ['path::']);

$rules = [
    'google-api-key' => '/AIza[0-9A-Za-z_\-]{30,}/',
    'openai-style-key' => '/\bsk-[A-Za-z0-9]{20,}/',
    'github-token' => '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{30,}/',
    'github-fine-grained-token' => '/\bgithub_pat_[A-Za-z0-9_]{40,}/',
    'slack-token' => '/\bxox[baprs]-[A-Za-z0-9-]{10,}/',
    'aws-access-key-id' => '/\bAKIA[0-9A-Z]{16}\b/',
    'private-key-block' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY/',
    // A real-looking value after DB_PASSWORD/API_KEY in a tracked file (empty, placeholders and <...> are fine).
    'env-style-secret' => '/^[ \t]*(?:DB_PASSWORD|[A-Z_]*API_KEY|[A-Z_]*SECRET|[A-Z_]*TOKEN)[ \t]*=[ \t]*(?![ \t]*$|<|your|change|example|placeholder|\*+|xxx|null|false|true)[^\s#]{8,}/im',
];

$skipDirs = ['.git', 'node_modules', 'storage', 'vendor', 'dist'];
$skipPathParts = ['public/assets/admin/vendor/'];
$skipFiles = ['.env', '.env.testing']; // local, git-ignored runtime files; "no tracked .env" is a separate CI guard

$files = [];
if (isset($options['path']) && is_string($options['path']) && is_dir($options['path'])) {
    $base = rtrim(str_replace('\\', '/', $options['path']), '/');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    $relativeTo = $base . '/';
} else {
    $relativeTo = str_replace('\\', '/', $root) . '/';
    $out = [];
    $code = 1;
    @exec('git -C ' . escapeshellarg($root) .' ls-files -z ' . (PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null'), $out, $code);
    $listed = $code === 0 ? array_filter(explode("\0", implode("\0", $out))) : [];
    if ($listed) {
        foreach ($listed as $rel) {
            $files[] = $relativeTo . $rel;
        }
    } else {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }
}

$findings = [];
$scanned = 0;
foreach ($files as $path) {
    $relative = str_starts_with($path, $relativeTo) ? substr($path, strlen($relativeTo)) : $path;
    $segments = explode('/', $relative);
    if (array_intersect($segments, $skipDirs) || in_array(basename($relative), $skipFiles, true)) {
        continue;
    }
    foreach ($skipPathParts as $part) {
        if (str_contains($relative, $part)) {
            continue 2;
        }
    }
    if (!is_file($path) || filesize($path) > 2_000_000) {
        continue;
    }
    $content = (string)file_get_contents($path);
    if ($content === '' || str_contains(substr($content, 0, 4096), "\0")) {
        continue; // binary
    }
    $scanned++;
    foreach ($rules as $name => $regex) {
        if (preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [, $offset]) {
                $findings[] = $relative . ':' . (substr_count(substr($content, 0, (int)$offset), "\n") + 1) . "  {$name}";
            }
        }
    }
}

if ($findings) {
    fwrite(STDERR, "Secret scan FAILED (" . count($findings) . " finding(s); values are not printed):\n - " . implode("\n - ", array_unique($findings)) . "\n");
    exit(1);
}
echo "Secret scan passed: {$scanned} files, " . count($rules) . " rules\n";
